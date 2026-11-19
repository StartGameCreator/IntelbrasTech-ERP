<?php

namespace App\Services\Marketplace;

use App\Models\{
    Deposito,
    Pedido,
    PedidoItem,
    Produto
};
use App\Services\Estoque\EstoqueService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarketplaceOrderAutomationService
{
    public function __construct(
        private readonly EstoqueService $estoque
    ) {}

    public function validate(object $marketplaceOrder, int $empresaId): array
    {
        $items = $this->decode($marketplaceOrder->itens);
        $errors = [];
        $warnings = [];
        $mapped = [];

        if (!$items) {
            $errors[] = 'O pedido não possui itens importados.';
        }

        foreach ($items as $index => $item) {
            $sku = trim((string) ($item['sku'] ?? ''));
            $externalId = trim((string) ($item['external_item_id'] ?? ''));

            $product = Produto::where('empresa_id', $empresaId)
                ->where(function ($query) use ($sku, $externalId) {
                    if ($sku !== '') {
                        $query->where('sku', $sku)->orWhere('codigo', $sku);
                    }

                    if ($externalId !== '') {
                        $query->orWhere('codigo', $externalId);
                    }
                })
                ->first();

            if (!$product) {
                $errors[] = 'Item ' . ($index + 1) . ': produto não localizado por SKU/código.';
                $mapped[] = [
                    'index' => $index,
                    'sku' => $sku,
                    'external_item_id' => $externalId,
                    'produto_id' => null,
                    'status' => 'nao_localizado',
                ];
                continue;
            }

            if (!$product->ativo) {
                $warnings[] = "Produto {$product->nome} está inativo.";
            }

            $mapped[] = [
                'index' => $index,
                'sku' => $sku,
                'external_item_id' => $externalId,
                'produto_id' => $product->id,
                'produto_nome' => $product->nome,
                'status' => 'localizado',
            ];
        }

        return [
            'valido' => !$errors,
            'erros' => $errors,
            'avisos' => $warnings,
            'mapeamentos' => $mapped,
        ];
    }

    public function process(
        object $marketplaceOrder,
        int $empresaId,
        int $usuarioId,
        int $depositoId
    ): Pedido {
        $validation = $this->validate($marketplaceOrder, $empresaId);

        if (!$validation['valido']) {
            throw new RuntimeException(implode(' | ', $validation['erros']));
        }

        $deposit = Deposito::where('empresa_id', $empresaId)->findOrFail($depositoId);
        $items = $this->decode($marketplaceOrder->itens);

        return DB::transaction(function () use (
            $marketplaceOrder,
            $empresaId,
            $usuarioId,
            $deposit,
            $items,
            $validation
        ) {
            $existingProcessing = DB::table('marketplace_order_processings')
                ->where('empresa_id', $empresaId)
                ->where('marketplace_order_id', $marketplaceOrder->id)
                ->first();

            if ($existingProcessing?->pedido_id) {
                return Pedido::findOrFail($existingProcessing->pedido_id);
            }

            $subtotal = collect($items)->sum(
                fn ($item) => (float) ($item['quantidade'] ?? 0)
                    * (float) ($item['preco_unitario'] ?? 0)
            );

            $pedido = Pedido::create([
                'empresa_id' => $empresaId,
                'numero' => '',
                'cliente_id' => null,
                'vendedor_id' => $usuarioId,
                'canal' => 'mercado_livre',
                'subtotal' => $subtotal,
                'desconto_valor' => 0,
                'acrescimo_valor' => 0,
                'frete' => 0,
                'total' => (float) $marketplaceOrder->total,
                'status' => Pedido::STATUS_CONFIRMADO,
                'status_pagamento' => in_array(
                    $marketplaceOrder->status_pagamento,
                    ['approved', 'paid'],
                    true
                ) ? Pedido::STATUS_PAG_PAGO : Pedido::STATUS_PAG_PENDENTE,
                'status_entrega' => $marketplaceOrder->status_envio ?: 'aguardando_envio',
                'data_pedido' => $marketplaceOrder->data_venda ?: now(),
                'data_confirmacao' => now(),
                'observacoes' => 'Pedido importado do Mercado Livre. ID externo: '
                    . $marketplaceOrder->external_id,
            ]);

            $pedido->update(['numero' => $pedido->gerarNumero()]);

            foreach ($items as $item) {
                $sku = trim((string) ($item['sku'] ?? ''));
                $externalId = trim((string) ($item['external_item_id'] ?? ''));

                $product = Produto::where('empresa_id', $empresaId)
                    ->where(function ($query) use ($sku, $externalId) {
                        if ($sku !== '') {
                            $query->where('sku', $sku)->orWhere('codigo', $sku);
                        }

                        if ($externalId !== '') {
                            $query->orWhere('codigo', $externalId);
                        }
                    })
                    ->firstOrFail();

                $quantity = (float) ($item['quantidade'] ?? 0);
                $unitPrice = (float) ($item['preco_unitario'] ?? 0);
                $total = round($quantity * $unitPrice, 2);

                PedidoItem::create([
                    'pedido_id' => $pedido->id,
                    'produto_id' => $product->id,
                    'deposito_id' => $deposit->id,
                    'codigo' => $product->codigo ?: $product->sku,
                    'descricao' => mb_substr($product->nome, 0, 300),
                    'ncm' => $product->ncm,
                    'cfop' => $product->cfop ?: '5102',
                    'unidade' => $product->unidade?->sigla ?: 'UN',
                    'quantidade' => $quantity,
                    'preco_unitario' => $unitPrice,
                    'desconto_valor' => 0,
                    'total' => $total,
                    'icms_cst' => $product->icms_cst,
                    'icms_aliquota' => $product->icms_aliquota,
                    'pis_cst' => $product->pis_cst,
                    'pis_aliquota' => $product->pis_aliquota,
                    'cofins_cst' => $product->cofins_cst,
                    'cofins_aliquota' => $product->cofins_aliquota,
                ]);

                if ($product->controla_estoque && $quantity > 0) {
                    $this->estoque->saida(
                        $product,
                        $deposit,
                        $quantity,
                        'venda_marketplace',
                        $pedido->id,
                        'pedido',
                        $pedido->numero
                    );
                }
            }

            DB::table('marketplace_orders')
                ->where('id', $marketplaceOrder->id)
                ->update([
                    'pedido_id' => $pedido->id,
                    'updated_at' => now(),
                ]);

            DB::table('marketplace_order_processings')->updateOrInsert(
                [
                    'empresa_id' => $empresaId,
                    'marketplace_order_id' => $marketplaceOrder->id,
                ],
                [
                    'pedido_id' => $pedido->id,
                    'deposito_id' => $deposit->id,
                    'status' => 'processado',
                    'estoque_baixado' => true,
                    'financeiro_criado' => false,
                    'fiscal_preparado' => false,
                    'mapeamentos' => json_encode(
                        $validation['mapeamentos'],
                        JSON_UNESCAPED_UNICODE
                    ),
                    'validacoes' => json_encode(
                        $validation,
                        JSON_UNESCAPED_UNICODE
                    ),
                    'ultimo_erro' => null,
                    'processado_em' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return $pedido;
        });
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!$value) {
            return [];
        }

        return json_decode((string) $value, true) ?: [];
    }
}
