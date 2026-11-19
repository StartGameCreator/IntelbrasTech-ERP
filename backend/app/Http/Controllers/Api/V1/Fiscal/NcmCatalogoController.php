<?php

namespace App\Http\Controllers\Api\V1\Fiscal;

use App\Http\Controllers\Controller;
use App\Services\Fiscal\NcmCatalogoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class NcmCatalogoController extends Controller
{
    public function __construct(
        private readonly NcmCatalogoService $service
    ) {}

    public function buscar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:500',
            'gtin' => 'nullable|string|max:20',
            'limite' => 'nullable|integer|min:1|max:50',
        ]);

        $resultados = $this->service->buscar(
            (string) ($data['q'] ?? ''),
            $data['gtin'] ?? null,
            (int) ($data['limite'] ?? 20)
        );

        return response()->json([
            'data' => $resultados,
            'catalogo_total' => DB::table('ncm_catalogo')->where('ativo', true)->count(),
            'fonte' => 'Sistema Classif — Receita Federal / Portal Único Siscomex',
            'aviso' => 'A sugestão auxilia o cadastro, mas a classificação fiscal deve ser validada pelo responsável tributário.',
        ]);
    }

    public function sincronizar(): JsonResponse
    {
        try {
            $resultado = $this->service->sincronizar();

            return response()->json([
                'message' => 'Tabela oficial NCM atualizada.',
                'data' => $resultado,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function registrarEscolha(Request $request): JsonResponse
    {
        $data = $request->validate([
            'produto_id' => 'nullable|integer|exists:produtos,id',
            'consulta' => 'required|string|max:500',
            'gtin' => 'nullable|string|max:20',
            'ncm' => 'required|string|max:10',
            'confianca' => 'nullable|numeric|min:0|max:100',
            'resultados' => 'nullable|array',
        ]);

        DB::table('ncm_sugestoes_produto')->insert([
            'empresa_id' => auth()->user()->empresa_id,
            'produto_id' => $data['produto_id'] ?? null,
            'consulta' => $data['consulta'],
            'gtin' => $data['gtin'] ?? null,
            'ncm_escolhido' => preg_replace('/\D+/', '', $data['ncm']),
            'confianca' => $data['confianca'] ?? null,
            'origem' => 'busca_classif',
            'resultados' => isset($data['resultados'])
                ? json_encode($data['resultados'], JSON_UNESCAPED_UNICODE)
                : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Escolha de NCM registrada.']);
    }
}
