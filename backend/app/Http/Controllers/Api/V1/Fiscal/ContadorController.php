<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Http\Controllers\Controller;
use App\Services\Fiscal\ContadorExportacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ContadorController extends Controller
{
    public function __construct(
        private readonly ContadorExportacaoService $service
    ) {}

    public function resumo(): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        return response()->json([
            'configuracao' => DB::table('contador_configuracoes')
                ->where('empresa_id', $empresaId)
                ->first(),
            'tipos' => collect($this->service->tiposDisponiveis())
                ->map(fn ($label, $id) => ['id' => $id, 'label' => $label])
                ->values(),
            'historico' => DB::table('contador_exportacoes')
                ->where('empresa_id', $empresaId)
                ->orderByDesc('id')
                ->limit(30)
                ->get(),
        ]);
    }

    public function salvarDados(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nome_contador' => 'nullable|string|max:150',
            'cpf_cnpj' => 'nullable|string|max:20',
            'crc' => 'nullable|string|max:40',
            'escritorio' => 'nullable|string|max:180',
            'email' => 'nullable|email|max:180',
            'telefone' => 'nullable|string|max:30',
            'responsavel' => 'nullable|string|max:150',
            'possui_escritorio' => 'boolean',
        ]);

        $this->salvarConfiguracao($data);

        return response()->json(['message' => 'Dados do contador salvos.']);
    }

    public function salvarAgendamento(Request $request): JsonResponse
    {
        $data = $request->validate([
            'frequencia' => ['required', Rule::in(['semanal', 'quinzenal', 'mensal'])],
            'dia_envio' => 'nullable|integer|min:1|max:31',
            'hora_envio' => 'required|date_format:H:i',
            'envio_automatico' => 'boolean',
            'tipos_arquivo' => 'nullable|array',
            'tipos_arquivo.*' => 'string|max:80',
            'destinos' => 'nullable|array',
        ]);

        $this->salvarConfiguracao($data);

        return response()->json(['message' => 'Agendamento salvo.']);
    }

    public function gerar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo' => ['required', Rule::in(array_keys($this->service->tiposDisponiveis()))],
            'inicio' => 'required|date',
            'fim' => 'required|date|after_or_equal:inicio',
        ]);

        try {
            $result = $this->service->gerar(
                auth()->user()->empresa_id,
                auth()->id(),
                $data['tipo'],
                $data['inicio'],
                $data['fim']
            );

            return response()->json([
                'message' => 'Arquivo gerado com sucesso.',
                'data' => $result,
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function download(int $id): BinaryFileResponse
    {
        $arquivo = DB::table('contador_exportacoes')
            ->where('id', $id)
            ->where('empresa_id', auth()->user()->empresa_id)
            ->first();

        abort_unless($arquivo, 404, 'Arquivo não encontrado.');
        abort_unless(
            $arquivo->caminho_arquivo && Storage::disk('local')->exists($arquivo->caminho_arquivo),
            404,
            'Arquivo físico não encontrado.'
        );

        return response()->download(
            Storage::disk('local')->path($arquivo->caminho_arquivo),
            $arquivo->nome_arquivo
        );
    }

    public function excluir(int $id): JsonResponse
    {
        $arquivo = DB::table('contador_exportacoes')
            ->where('id', $id)
            ->where('empresa_id', auth()->user()->empresa_id)
            ->first();

        abort_unless($arquivo, 404, 'Arquivo não encontrado.');

        if ($arquivo->caminho_arquivo) {
            Storage::disk('local')->delete($arquivo->caminho_arquivo);
        }

        DB::table('contador_exportacoes')->where('id', $id)->delete();

        return response()->json(['message' => 'Arquivo removido.']);
    }

    private function salvarConfiguracao(array $data): void
    {
        $empresaId = auth()->user()->empresa_id;

        foreach (['tipos_arquivo', 'destinos'] as $jsonField) {
            if (array_key_exists($jsonField, $data)) {
                $data[$jsonField] = json_encode($data[$jsonField], JSON_UNESCAPED_UNICODE);
            }
        }

        DB::table('contador_configuracoes')->updateOrInsert(
            ['empresa_id' => $empresaId],
            array_merge(
                $data,
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            )
        );
    }
}
