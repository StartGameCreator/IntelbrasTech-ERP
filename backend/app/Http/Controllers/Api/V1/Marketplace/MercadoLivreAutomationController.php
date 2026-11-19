<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\MarketplaceOrderAutomationService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Throwable;

class MercadoLivreAutomationController extends Controller
{
    public function __construct(
        private readonly MarketplaceOrderAutomationService $automation
    ) {}

    public function overview(): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        return response()->json([
            'resumo' => [
                'pendentes' => DB::table('marketplace_orders')
                    ->where('empresa_id', $empresaId)
                    ->whereNull('pedido_id')
                    ->count(),
                'processados' => DB::table('marketplace_orders')
                    ->where('empresa_id', $empresaId)
                    ->whereNotNull('pedido_id')
                    ->count(),
                'com_erro' => DB::table('marketplace_order_processings')
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'erro')
                    ->count(),
            ],
            'depositos' => DB::table('depositos')
                ->where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get(['id','nome']),
        ]);
    }

    public function validar(int $orderId): JsonResponse
    {
        $order = $this->order($orderId);
        $validation = $this->automation->validate(
            $order,
            auth()->user()->empresa_id
        );

        return response()->json($validation);
    }

    public function processar(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'deposito_id' => 'required|exists:depositos,id',
        ]);

        $order = $this->order($orderId);

        try {
            $pedido = $this->automation->process(
                $order,
                auth()->user()->empresa_id,
                auth()->id(),
                (int) $data['deposito_id']
            );

            return response()->json([
                'message' => 'Pedido interno criado e estoque baixado.',
                'pedido' => [
                    'id' => $pedido->id,
                    'numero' => $pedido->numero,
                    'status' => $pedido->status,
                    'total' => $pedido->total,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            DB::table('marketplace_order_processings')->updateOrInsert(
                [
                    'empresa_id' => auth()->user()->empresa_id,
                    'marketplace_order_id' => $orderId,
                ],
                [
                    'deposito_id' => $data['deposito_id'],
                    'status' => 'erro',
                    'ultimo_erro' => $e->getMessage(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function processarLote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'deposito_id' => 'required|exists:depositos,id',
            'order_ids' => 'required|array|min:1|max:100',
            'order_ids.*' => 'required|integer',
        ]);

        $success = 0;
        $errors = [];

        foreach ($data['order_ids'] as $orderId) {
            try {
                $order = $this->order((int) $orderId);

                $this->automation->process(
                    $order,
                    auth()->user()->empresa_id,
                    auth()->id(),
                    (int) $data['deposito_id']
                );

                $success++;
            } catch (Throwable $e) {
                $errors[] = [
                    'order_id' => $orderId,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Processamento em lote concluído.',
            'processados' => $success,
            'erros' => $errors,
        ]);
    }

    private function order(int $id): object
    {
        $order = DB::table('marketplace_orders')
            ->where('id', $id)
            ->where('empresa_id', auth()->user()->empresa_id)
            ->first();

        abort_unless($order, 404, 'Pedido do marketplace não encontrado.');

        return $order;
    }
}
