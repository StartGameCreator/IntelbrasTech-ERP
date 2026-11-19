<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class MarketplaceFinanceFiscalService
{
    public function prepare(int $empresaId, int $marketplaceOrderId, array $options): array
    {
        $order = DB::table('marketplace_orders')
            ->where('id', $marketplaceOrderId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$order) {
            throw new RuntimeException('Pedido do marketplace não encontrado.');
        }

        if (!$order->pedido_id) {
            throw new RuntimeException(
                'O pedido ainda não foi convertido em pedido interno. Execute primeiro a automação omnichannel.'
            );
        }

        $processing = DB::table('marketplace_order_processings')
            ->where('empresa_id', $empresaId)
            ->where('marketplace_order_id', $marketplaceOrderId)
            ->first();

        if (!$processing || $processing->status !== 'processado') {
            throw new RuntimeException('O processamento omnichannel ainda não foi concluído.');
        }

        return DB::transaction(function () use ($empresaId, $order, $options) {
            $raw = $this->decode($order->dados);
            $payments = $raw['payments'] ?? [];

            $gross = (float) $order->total;
            $fees = collect($payments)->sum(function ($payment) {
                return (float) ($payment['marketplace_fee'] ?? 0)
                    + (float) ($payment['transaction_amount_refunded'] ?? 0);
            });

            $shipping = (float) data_get($raw, 'shipping.cost', 0);
            $net = max(0, round($gross - $fees - $shipping, 2));

            $paymentId = (string) data_get($payments, '0.id', '');

            DB::table('marketplace_financial_reconciliations')->updateOrInsert(
                [
                    'empresa_id' => $empresaId,
                    'marketplace_order_id' => $order->id,
                ],
                [
                    'pedido_id' => $order->pedido_id,
                    'marketplace' => 'mercado_livre',
                    'external_payment_id' => $paymentId ?: null,
                    'status' => in_array($order->status_pagamento, ['approved', 'paid'], true)
                        ? 'aguardando_conciliacao'
                        : 'aguardando_pagamento',
                    'moeda' => $order->moeda ?: 'BRL',
                    'valor_bruto' => $gross,
                    'taxas' => $fees,
                    'frete' => $shipping,
                    'valor_liquido' => $net,
                    'data_venda' => $order->data_venda,
                    'dados' => json_encode($payments, JSON_UNESCAPED_UNICODE),
                    'ultimo_erro' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $validations = $this->fiscalValidations($empresaId, $order);

            DB::table('marketplace_fiscal_queue_items')->updateOrInsert(
                [
                    'empresa_id' => $empresaId,
                    'marketplace_order_id' => $order->id,
                ],
                [
                    'pedido_id' => $order->pedido_id,
                    'documento_tipo' => $options['documento_tipo'] ?? 'nfe',
                    'status' => $validations['valido'] ? 'pronto' : 'pendente_validacao',
                    'ambiente' => $options['ambiente'] ?? 'homologacao',
                    'serie' => (int) ($options['serie'] ?? 1),
                    'validacoes' => json_encode($validations, JSON_UNESCAPED_UNICODE),
                    'ultimo_erro' => $validations['valido']
                        ? null
                        : implode(' | ', $validations['erros']),
                    'preparado_em' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            DB::table('marketplace_order_processings')
                ->where('empresa_id', $empresaId)
                ->where('marketplace_order_id', $order->id)
                ->update([
                    'financeiro_criado' => true,
                    'fiscal_preparado' => $validations['valido'],
                    'updated_at' => now(),
                ]);

            return [
                'financeiro' => [
                    'valor_bruto' => $gross,
                    'taxas' => $fees,
                    'frete' => $shipping,
                    'valor_liquido' => $net,
                ],
                'fiscal' => $validations,
            ];
        });
    }

    public function reconcile(int $empresaId, int $id): void
    {
        $row = DB::table('marketplace_financial_reconciliations')
            ->where('id', $id)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$row) {
            throw new RuntimeException('Lançamento financeiro não encontrado.');
        }

        DB::table('marketplace_financial_reconciliations')
            ->where('id', $id)
            ->update([
                'status' => 'conciliado',
                'conciliado_em' => now(),
                'ultimo_erro' => null,
                'updated_at' => now(),
            ]);
    }

    private function fiscalValidations(int $empresaId, object $order): array
    {
        $errors = [];
        $warnings = [];

        $empresa = DB::table('empresas')->where('id', $empresaId)->first();

        if (!$empresa?->cnpj) {
            $errors[] = 'CNPJ da empresa não informado.';
        }

        if (!$empresa?->inscricao_estadual) {
            $errors[] = 'Inscrição estadual não informada.';
        }

        if (!$empresa?->uf) {
            $errors[] = 'UF da empresa não informada.';
        }

        $pedido = DB::table('pedidos')->where('id', $order->pedido_id)->first();

        if (!$pedido) {
            $errors[] = 'Pedido interno não encontrado.';
        }

        $items = DB::table('pedido_itens')
            ->where('pedido_id', $order->pedido_id)
            ->get();

        if ($items->isEmpty()) {
            $errors[] = 'Pedido interno sem itens.';
        }

        foreach ($items as $index => $item) {
            if (!$item->ncm) {
                $errors[] = 'Item ' . ($index + 1) . ': NCM não informado.';
            }

            if (!$item->cfop) {
                $errors[] = 'Item ' . ($index + 1) . ': CFOP não informado.';
            }
        }

        if (!$order->comprador_documento) {
            $warnings[] = 'Comprador sem CPF/CNPJ importado.';
        }

        return [
            'valido' => !$errors,
            'erros' => $errors,
            'avisos' => $warnings,
        ];
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
