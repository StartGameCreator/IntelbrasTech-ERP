<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\ClienteGeolocalizacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardMapaVendasController extends Controller
{
    public function __construct(
        private readonly ClienteGeolocalizacaoService $geolocalizacao
    ) {}

    public function index(Request $request): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $inicio = $request->date('inicio')?->startOfDay() ?? now()->subMonths(12)->startOfDay();
        $fim = $request->date('fim')?->endOfDay() ?? now()->endOfDay();
        $perPage = min(max((int) $request->get('per_page', 50), 1), 200);
        $page = max((int) $request->get('page', 1), 1);

        if (!Schema::hasTable('clientes')) {
            return response()->json([
                'pontos' => [],
                'resumo' => ['clientes' => 0, 'pedidos' => 0, 'valor' => 0, 'estados' => 0],
            ]);
        }

        $nomeColumn = $this->primeiraColunaExistente('clientes', ['razao_social', 'nome', 'nome_fantasia']);
        $cidadeColumn = $this->primeiraColunaExistente('clientes', ['cidade', 'municipio']);
        $estadoColumn = $this->primeiraColunaExistente('clientes', ['estado', 'uf']);
        $cepColumn = $this->primeiraColunaExistente('clientes', ['cep', 'cep_endereco', 'endereco_cep']);

        $clientes = DB::table('clientes as c')
            ->where('c.empresa_id', $empresaId)
            ->whereNotNull('c.latitude')
            ->whereNotNull('c.longitude')
            ->select([
                'c.id',
                DB::raw($nomeColumn ? "c.{$nomeColumn} as nome" : "'Cliente' as nome"),
                DB::raw($cidadeColumn ? "c.{$cidadeColumn} as cidade" : "'' as cidade"),
                DB::raw($estadoColumn ? "c.{$estadoColumn} as estado" : "'' as estado"),
                DB::raw($cepColumn ? "c.{$cepColumn} as cep" : "'' as cep"),
                'c.latitude',
                'c.longitude',
            ])
            ->get()
            ->keyBy('id');

        $pedidosAgrupados = collect();

        if (Schema::hasTable('pedidos')) {
            $dateColumn = $this->primeiraColunaExistente('pedidos', ['data_pedido', 'created_at', 'emitido_em']);
            $totalColumn = $this->primeiraColunaExistente('pedidos', ['total', 'valor_total', 'total_pedido']);
            $statusColumn = $this->primeiraColunaExistente('pedidos', ['status']);

            $query = DB::table('pedidos')
                ->where('empresa_id', $empresaId)
                ->whereNotNull('cliente_id');

            if ($dateColumn) {
                $query->whereBetween($dateColumn, [$inicio, $fim]);
            }

            if ($statusColumn) {
                $query->whereNotIn($statusColumn, ['cancelado', 'cancelada']);
            }

            $pedidosAgrupados = $query
                ->select(
                    'cliente_id',
                    DB::raw('COUNT(*) as pedidos'),
                    DB::raw($totalColumn ? "COALESCE(SUM({$totalColumn}),0) as valor" : '0 as valor')
                )
                ->groupBy('cliente_id')
                ->get()
                ->keyBy('cliente_id');
        }

        $pontos = $clientes->map(function ($cliente) use ($pedidosAgrupados) {
            $vendas = $pedidosAgrupados->get($cliente->id);

            return [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'cidade' => $cliente->cidade,
                'estado' => $cliente->estado,
                'cep' => $cliente->cep,
                'latitude' => (float) $cliente->latitude,
                'longitude' => (float) $cliente->longitude,
                'pedidos' => (int) ($vendas->pedidos ?? 0),
                'valor' => (float) ($vendas->valor ?? 0),
            ];
        })
            ->filter(fn ($item) => $item['pedidos'] > 0)
            ->sortByDesc('valor')
            ->values();

        $total = $pontos->count();
        $offset = ($page - 1) * $perPage;
        $pontosPagina = $pontos->slice($offset, $perPage)->values();
        $hasMore = $offset + $pontosPagina->count() < $total;

        return response()->json([
            'pontos' => $pontosPagina,
            'resumo' => [
                'clientes' => $total,
                'pedidos' => $pontos->sum('pedidos'),
                'valor' => round((float) $pontos->sum('valor'), 2),
                'estados' => $pontos->pluck('estado')->filter()->unique()->count(),
            ],
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'has_more' => $hasMore,
            'next_page' => $hasMore ? $page + 1 : null,
            'periodo' => [
                'inicio' => $inicio->toDateString(),
                'fim' => $fim->toDateString(),
            ],
        ]);
    }

    public function sincronizar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limite' => 'nullable|integer|min:1|max:300',
        ]);

        $resultado = $this->geolocalizacao->sincronizar(
            auth()->user()->empresa_id,
            (int) ($data['limite'] ?? 100)
        );

        return response()->json([
            'message' => 'Geolocalização por CEP concluída.',
            'data' => $resultado,
        ]);
    }

    private function primeiraColunaExistente(string $table, array $columns): ?string
    {
        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }
}
