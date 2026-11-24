<?php

namespace App\Http\Controllers\Api\V1\Sistema;

use App\Http\Controllers\Controller;
use App\Services\Sistema\InfrastructureMonitor;
use App\Services\Sistema\SystemLogReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

class SystemLogController extends Controller
{
    public function __construct(
        private readonly SystemLogReader $reader,
        private readonly InfrastructureMonitor $infrastructureMonitor,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $filters = $request->validate([
            'level' => 'nullable|in:all,emergency,alert,critical,error,warning,notice,info,debug',
            'search' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:20|max:200',
            'hours' => 'nullable|integer|min:1|max:168',
        ]);

        return response()->json($this->reader->read($filters));
    }

    public function diagnostics(Request $request): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $checks = [
            $this->check('database', 'Banco de dados', function (): array {
                $startedAt = microtime(true);
                DB::select('select 1');

                return [
                    'message' => 'Conexão ativa ('.DB::connection()->getDriverName().')',
                    'latency_ms' => round((microtime(true) - $startedAt) * 1000, 1),
                ];
            }),
            $this->check('redis', 'Redis', function (): array {
                $startedAt = microtime(true);
                Redis::connection()->ping();

                return [
                    'message' => 'Cache e sessões acessíveis',
                    'latency_ms' => round((microtime(true) - $startedAt) * 1000, 1),
                ];
            }),
            $this->check('storage', 'Armazenamento', function (): array {
                $path = storage_path();
                $freeBytes = disk_free_space($path);
                $totalBytes = disk_total_space($path);
                $freePercent = ($freeBytes !== false && $totalBytes) ? round(($freeBytes / $totalBytes) * 100, 1) : null;

                return [
                    'status' => $freePercent !== null && $freePercent < 10 ? 'warning' : 'ok',
                    'message' => is_writable($path) ? 'Volume persistente gravável' : 'Volume sem permissão de escrita',
                    'free_bytes' => $freeBytes === false ? null : $freeBytes,
                    'total_bytes' => $totalBytes === false ? null : $totalBytes,
                    'free_percent' => $freePercent,
                ];
            }),
            $this->check('queue', 'Filas', function (): array {
                $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
                $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

                return [
                    'status' => $failed > 0 ? 'warning' : 'ok',
                    'message' => $failed > 0 ? "{$failed} tarefa(s) com falha" : 'Sem falhas registradas',
                    'pending' => $pending,
                    'failed' => $failed,
                ];
            }),
            $this->check('log', 'Log Laravel', function (): array {
                $path = storage_path('logs/laravel.log');
                if (! is_file($path) || ! is_readable($path)) {
                    return ['status' => 'warning', 'message' => 'Arquivo ainda não existe ou não pode ser lido'];
                }

                return [
                    'message' => 'Leitura em tempo real disponível',
                    'size_bytes' => filesize($path) ?: 0,
                    'modified_at' => ($modified = filemtime($path)) ? date(DATE_ATOM, $modified) : null,
                ];
            }),
        ];

        $recent = $this->reader->recentSeverityCounts();
        $overall = collect($checks)->contains(fn (array $check) => $check['status'] === 'error')
            ? 'error'
            : (collect($checks)->contains(fn (array $check) => $check['status'] === 'warning') ? 'warning' : 'ok');

        return response()->json([
            'status' => $overall,
            'server_time' => now()->toIso8601String(),
            'application' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'timezone' => config('app.timezone'),
            ],
            'recent' => $recent,
            'checks' => $checks,
        ]);
    }

    public function infrastructure(Request $request): JsonResponse
    {
        $this->authorizeAdministrator($request);

        return response()->json($this->infrastructureMonitor->snapshot());
    }

    private function authorizeAdministrator(Request $request): void
    {
        $user = $request->user();
        $user?->loadMissing('perfil');

        abort_unless((bool) $user?->perfil?->admin, 403, 'Acesso restrito aos administradores do sistema.');
    }

    private function check(string $key, string $label, callable $callback): array
    {
        try {
            $result = $callback();

            return [
                'key' => $key,
                'label' => $label,
                'status' => $result['status'] ?? 'ok',
                ...$result,
            ];
        } catch (\Throwable $exception) {
            return [
                'key' => $key,
                'label' => $label,
                'status' => 'error',
                'message' => $this->reader->sanitize($exception->getMessage()),
            ];
        }
    }
}
