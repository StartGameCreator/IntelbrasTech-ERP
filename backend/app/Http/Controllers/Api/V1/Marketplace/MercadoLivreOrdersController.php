<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\{MarketplaceAccount, MarketplaceConnector};
use App\Services\Marketplace\MercadoLivreOrderService;
use Carbon\Carbon;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Throwable;

class MercadoLivreOrdersController extends Controller
{
    public function __construct(
        private readonly MercadoLivreOrderService $orders
    ) {}

    public function index(Request $request): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $account = $this->account();

        $query = DB::table('marketplace_orders')
            ->where('empresa_id', $empresaId)
            ->where('account_id', $account->id)
            ->when($request->filled('busca'), function ($query) use ($request) {
                $termo = trim((string) $request->busca);

                $query->where(function ($query) use ($termo) {
                    $query->where('external_id', 'like', "%{$termo}%")
                        ->orWhere('numero_externo', 'like', "%{$termo}%")
                        ->orWhere('comprador_nome', 'like', "%{$termo}%")
                        ->orWhere('comprador_documento', 'like', "%{$termo}%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('data_inicio'), fn ($q) => $q->whereDate('data_venda', '>=', $request->data_inicio))
            ->when($request->filled('data_fim'), fn ($q) => $q->whereDate('data_venda', '<=', $request->data_fim))
            ->orderByDesc('data_venda')
            ->orderByDesc('id');

        return response()->json(
            $query->paginate(min((int) $request->get('per_page', 30), 100))
        );
    }

    public function importar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'offset' => 'nullable|integer|min:0',
        ]);

        $account = $this->account();
        $empresaId = auth()->user()->empresa_id;

        $from = isset($data['data_inicio'])
            ? Carbon::parse($data['data_inicio'])->startOfDay()->toIso8601String()
            : now()->subDays(30)->startOfDay()->toIso8601String();

        $to = isset($data['data_fim'])
            ? Carbon::parse($data['data_fim'])->endOfDay()->toIso8601String()
            : now()->endOfDay()->toIso8601String();

        try {
            $result = $this->orders->search(
                $account,
                $from,
                $to,
                (int) ($data['offset'] ?? 0),
                50
            );

            $importados = 0;
            $atualizados = 0;
            $erros = 0;

            foreach ($result['results'] ?? [] as $order) {
                try {
                    $externalId = (string) ($order['id'] ?? '');

                    if (!$externalId) {
                        continue;
                    }

                    $exists = DB::table('marketplace_orders')
                        ->where('account_id', $account->id)
                        ->where('external_id', $externalId)
                        ->exists();

                    $shippingId = (string) data_get($order, 'shipping.id', '');
                    $shipment = $shippingId
                        ? $this->orders->shipment($account, $shippingId)
                        : null;

                    $buyer = $order['buyer'] ?? [];
                    $billing = $order['billing_info'] ?? [];
                    $items = collect($order['order_items'] ?? [])->map(function ($row) {
                        return [
                            'external_item_id' => data_get($row, 'item.id'),
                            'titulo' => data_get($row, 'item.title'),
                            'sku' => data_get($row, 'item.seller_sku')
                                ?: data_get($row, 'item.seller_custom_field'),
                            'quantidade' => (float) ($row['quantity'] ?? 0),
                            'preco_unitario' => (float) ($row['unit_price'] ?? 0),
                            'moeda' => $row['currency_id'] ?? 'BRL',
                        ];
                    })->values()->all();

                    $payload = [
                        'empresa_id' => $empresaId,
                        'account_id' => $account->id,
                        'external_id' => $externalId,
                        'numero_externo' => (string) ($order['pack_id'] ?? $externalId),
                        'status' => $order['status'] ?? 'desconhecido',
                        'status_pagamento' => data_get($order, 'payments.0.status'),
                        'status_envio' => $shipment['status'] ?? data_get($order, 'shipping.status'),
                        'shipping_id' => $shippingId ?: null,
                        'comprador_nome' => trim(
                            ($buyer['first_name'] ?? '') . ' ' . ($buyer['last_name'] ?? '')
                        ) ?: ($buyer['nickname'] ?? null),
                        'comprador_documento' => data_get($billing, 'doc_number'),
                        'comprador_email' => $buyer['email'] ?? null,
                        'total' => (float) ($order['total_amount'] ?? 0),
                        'moeda' => $order['currency_id'] ?? 'BRL',
                        'itens' => json_encode($items, JSON_UNESCAPED_UNICODE),
                        'endereco_entrega' => $shipment
                            ? json_encode($shipment['receiver_address'] ?? null, JSON_UNESCAPED_UNICODE)
                            : null,
                        'dados' => json_encode($order, JSON_UNESCAPED_UNICODE),
                        'data_venda' => isset($order['date_created'])
                            ? Carbon::parse($order['date_created'])
                            : null,
                        'data_atualizacao_externa' => isset($order['last_updated'])
                            ? Carbon::parse($order['last_updated'])
                            : null,
                        'importado_em' => now(),
                        'sincronizado_em' => now(),
                        'ultimo_erro' => null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ];

                    DB::table('marketplace_orders')->updateOrInsert(
                        [
                            'account_id' => $account->id,
                            'external_id' => $externalId,
                        ],
                        $payload
                    );

                    $exists ? $atualizados++ : $importados++;
                } catch (Throwable $e) {
                    report($e);
                    $erros++;
                }
            }

            return response()->json([
                'message' => 'Importação de pedidos concluída.',
                'importados' => $importados,
                'atualizados' => $atualizados,
                'erros' => $erros,
                'paging' => $result['paging'] ?? null,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(int $id): JsonResponse
    {
        $account = $this->account();

        $order = DB::table('marketplace_orders')
            ->where('id', $id)
            ->where('empresa_id', auth()->user()->empresa_id)
            ->where('account_id', $account->id)
            ->first();

        abort_unless($order, 404, 'Pedido não encontrado.');

        foreach (['itens','endereco_entrega','dados'] as $field) {
            $order->{$field} = $order->{$field}
                ? json_decode($order->{$field}, true)
                : null;
        }

        return response()->json(['data' => $order]);
    }

    public function atualizar(int $id): JsonResponse
    {
        $account = $this->account();

        $local = DB::table('marketplace_orders')
            ->where('id', $id)
            ->where('empresa_id', auth()->user()->empresa_id)
            ->where('account_id', $account->id)
            ->first();

        abort_unless($local, 404, 'Pedido não encontrado.');

        try {
            $order = $this->orders->order($account, $local->external_id);
            $shippingId = (string) data_get($order, 'shipping.id', $local->shipping_id);
            $shipment = $shippingId
                ? $this->orders->shipment($account, $shippingId)
                : null;

            DB::table('marketplace_orders')->where('id', $id)->update([
                'status' => $order['status'] ?? $local->status,
                'status_pagamento' => data_get($order, 'payments.0.status'),
                'status_envio' => $shipment['status'] ?? data_get($order, 'shipping.status'),
                'shipping_id' => $shippingId ?: null,
                'total' => (float) ($order['total_amount'] ?? $local->total),
                'dados' => json_encode($order, JSON_UNESCAPED_UNICODE),
                'endereco_entrega' => $shipment
                    ? json_encode($shipment['receiver_address'] ?? null, JSON_UNESCAPED_UNICODE)
                    : $local->endereco_entrega,
                'data_atualizacao_externa' => isset($order['last_updated'])
                    ? Carbon::parse($order['last_updated'])
                    : now(),
                'sincronizado_em' => now(),
                'ultimo_erro' => null,
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Pedido atualizado.',
            ]);
        } catch (Throwable $e) {
            DB::table('marketplace_orders')->where('id', $id)->update([
                'ultimo_erro' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function account(): MarketplaceAccount
    {
        $connector = MarketplaceConnector::where('slug', 'mercado_livre')->firstOrFail();

        return MarketplaceAccount::where('empresa_id', auth()->user()->empresa_id)
            ->where('connector_id', $connector->id)
            ->where('status', 'conectado')
            ->whereNotNull('access_token')
            ->firstOrFail();
    }
}
