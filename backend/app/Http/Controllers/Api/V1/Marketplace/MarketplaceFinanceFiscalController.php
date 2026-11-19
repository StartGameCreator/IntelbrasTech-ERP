<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\MarketplaceFinanceFiscalService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Throwable;

class MarketplaceFinanceFiscalController extends Controller
{
    public function __construct(
        private readonly MarketplaceFinanceFiscalService $service
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $finance = DB::table('marketplace_financial_reconciliations')
            ->where('empresa_id', $empresaId)
            ->when($request->filled('status_financeiro'), fn ($q) =>
                $q->where('status', $request->status_financeiro)
            )
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $fiscal = DB::table('marketplace_fiscal_queue_items')
            ->where('empresa_id', $empresaId)
            ->when($request->filled('status_fiscal'), fn ($q) =>
                $q->where('status', $request->status_fiscal)
            )
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'resumo' => [
                'financeiro_pendente' => DB::table('marketplace_financial_reconciliations')
                    ->where('empresa_id', $empresaId)
                    ->whereIn('status', ['aguardando_conciliacao', 'aguardando_pagamento'])
                    ->count(),
                'financeiro_conciliado' => DB::table('marketplace_financial_reconciliations')
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'conciliado')
                    ->count(),
                'fiscal_pronto' => DB::table('marketplace_fiscal_queue_items')
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'pronto')
                    ->count(),
                'fiscal_pendente' => DB::table('marketplace_fiscal_queue_items')
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'pendente_validacao')
                    ->count(),
            ],
            'financeiro' => $finance,
            'fiscal' => $fiscal,
        ]);
    }

    public function preparar(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'documento_tipo' => 'required|in:nfe,nfce',
            'ambiente' => 'required|in:homologacao,producao',
            'serie' => 'required|integer|min:1|max:999',
        ]);

        try {
            $result = $this->service->prepare(
                auth()->user()->empresa_id,
                $orderId,
                $data
            );

            return response()->json([
                'message' => 'Financeiro e fila fiscal preparados.',
                'resultado' => $result,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function conciliar(int $id): JsonResponse
    {
        try {
            $this->service->reconcile(auth()->user()->empresa_id, $id);

            return response()->json([
                'message' => 'Lançamento conciliado.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
