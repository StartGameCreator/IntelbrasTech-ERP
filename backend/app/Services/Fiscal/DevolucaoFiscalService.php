<?php

namespace App\Services\Fiscal;

use App\Models\{
    CaixaMovimentacao,
    DevolucaoFiscal,
    DevolucaoFiscalItem,
    NotaFiscal,
    NotaFiscalItem,
    PdvVenda,
    SerieFiscal
};
use App\Services\Estoque\EstoqueService;
use Illuminate\Support\Facades\DB;
use DomainException;

class DevolucaoFiscalService
{
    public function __construct(
        private readonly EstoqueService $estoque,
        private readonly NFeService $nfe,
    ) {}

    public function criar(PdvVenda $venda, array $dados): DevolucaoFiscal
    {
        $venda->loadMissing(['pedido.itens.produto', 'pedido.itens.deposito', 'notaFiscal', 'pagamentos']);

        if (! $venda->notaFiscal || $venda->notaFiscal->status !== NotaFiscal::STATUS_AUTORIZADA) {
            throw new DomainException('A devolução fiscal exige uma nota autorizada vinculada à venda.');
        }

        return DB::transaction(function () use ($venda, $dados) {
            $numero = 'DEV-'.now()->format('YmdHis').'-'.$venda->id;
            $itensEntrada = collect($dados['itens']);
            $valor = 0.0;

            $devolucao = DevolucaoFiscal::create([
                'empresa_id' => $venda->empresa_id,
                'pdv_venda_id' => $venda->id,
                'nota_fiscal_origem_id' => $venda->nota_fiscal_id,
                'cliente_id' => $venda->cliente_id,
                'usuario_id' => auth()->id(),
                'numero' => $numero,
                'tipo' => count($dados['itens']) === $venda->pedido->itens->count() ? 'total' : 'parcial',
                'motivo' => $dados['motivo'],
                'descricao_motivo' => $dados['descricao_motivo'] ?? null,
                'status' => $dados['mercadoria_retornou'] ? 'recebida' : 'aguardando_retorno',
                'situacao_mercadoria' => $dados['mercadoria_retornou'] ? 'retornada' : 'nao_retornada',
                'forma_reembolso' => $dados['forma_reembolso'] ?? null,
                'status_reembolso' => 'pendente',
                'valor_produtos' => 0,
                'valor_reembolso' => 0,
                'solicitada_em' => now(),
                'recebida_em' => $dados['mercadoria_retornou'] ? now() : null,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            foreach ($itensEntrada as $entrada) {
                $pedidoItem = $venda->pedido->itens->firstWhere('id', (int) $entrada['pedido_item_id']);
                if (! $pedidoItem) {
                    throw new DomainException('Item da venda não encontrado.');
                }

                $jaDevolvido = DevolucaoFiscalItem::query()
                    ->where('pedido_item_id', $pedidoItem->id)
                    ->whereHas('devolucao', fn ($q) => $q->whereNotIn('status', ['cancelada', 'rejeitada']))
                    ->sum('quantidade_devolvida');

                $quantidade = (float) $entrada['quantidade'];
                $disponivel = (float) $pedidoItem->quantidade - (float) $jaDevolvido;

                if ($quantidade <= 0 || $quantidade > $disponivel + 0.0001) {
                    throw new DomainException("Quantidade inválida para {$pedidoItem->descricao}. Disponível para devolução: {$disponivel}.");
                }

                $total = round($quantidade * (float) $pedidoItem->preco_unitario, 2);
                $valor += $total;

                DevolucaoFiscalItem::create([
                    'devolucao_fiscal_id' => $devolucao->id,
                    'pedido_item_id' => $pedidoItem->id,
                    'produto_id' => $pedidoItem->produto_id,
                    'variacao_id' => $pedidoItem->variacao_id,
                    'deposito_id' => $pedidoItem->deposito_id,
                    'codigo' => $pedidoItem->codigo,
                    'descricao' => $pedidoItem->descricao,
                    'ncm' => $pedidoItem->ncm,
                    'unidade' => $pedidoItem->unidade ?: 'UN',
                    'quantidade_vendida' => $pedidoItem->quantidade,
                    'quantidade_devolvida' => $quantidade,
                    'valor_unitario' => $pedidoItem->preco_unitario,
                    'valor_total' => $total,
                    'condicao' => $entrada['condicao'] ?? 'revendavel',
                    'retornou_estoque' => false,
                ]);
            }

            $devolucao->update([
                'valor_produtos' => $valor,
                'valor_reembolso' => $valor,
            ]);

            if ($dados['mercadoria_retornou']) {
                $this->receber($devolucao->fresh('itens'));
            }

            return $devolucao->fresh(['itens', 'notaOrigem']);
        });
    }

    public function receber(DevolucaoFiscal $devolucao): DevolucaoFiscal
    {
        return DB::transaction(function () use ($devolucao) {
            $devolucao->loadMissing(['itens.produto', 'itens.deposito', 'venda.pagamentos']);

            foreach ($devolucao->itens as $item) {
                if ($item->retornou_estoque || $item->condicao !== 'revendavel') {
                    continue;
                }
                if (! $item->produto || ! $item->deposito) {
                    throw new DomainException("Produto ou depósito ausente no item {$item->descricao}.");
                }

                $this->estoque->entrada(
                    $item->produto,
                    $item->deposito,
                    (float) $item->quantidade_devolvida,
                    0,
                    'devolucao_cliente',
                    $devolucao->id,
                    'devolucao_fiscal',
                    $devolucao->numero,
                    $item->variacao_id,
                );

                $item->update(['retornou_estoque' => true]);
            }

            $devolucao->update([
                'status' => 'recebida',
                'situacao_mercadoria' => 'retornada',
                'recebida_em' => $devolucao->recebida_em ?: now(),
            ]);

            return $devolucao->fresh('itens');
        });
    }

    public function registrarReembolso(DevolucaoFiscal $devolucao): DevolucaoFiscal
    {
        if ($devolucao->status_reembolso === 'concluido') {
            return $devolucao;
        }

        return DB::transaction(function () use ($devolucao) {
            $devolucao->loadMissing('venda');

            CaixaMovimentacao::create([
                'caixa_id' => $devolucao->venda->caixa_id,
                'usuario_id' => auth()->id(),
                'tipo' => 'reembolso_devolucao',
                'valor' => (float) $devolucao->valor_reembolso,
                'forma_pagamento' => $devolucao->forma_reembolso ?: 'estorno',
                'descricao' => "Reembolso da devolução {$devolucao->numero}",
                'pedido_id' => $devolucao->venda->pedido_id,
                'metadados' => [
                    'devolucao_fiscal_id' => $devolucao->id,
                    'pdv_venda_id' => $devolucao->pdv_venda_id,
                ],
                'created_at' => now(),
            ]);

            $devolucao->update(['status_reembolso' => 'concluido']);
            return $devolucao->fresh();
        });
    }

    public function prepararNfe(DevolucaoFiscal $devolucao): NotaFiscal
    {
        if ($devolucao->situacao_mercadoria !== 'retornada') {
            throw new DomainException('A NF-e de devolução só pode ser preparada após o retorno da mercadoria.');
        }

        if ($devolucao->nota_fiscal_devolucao_id) {
            return $devolucao->notaDevolucao;
        }

        return DB::transaction(function () use ($devolucao) {
            $devolucao->loadMissing(['notaOrigem', 'itens', 'venda.empresa', 'venda.cliente']);
            $origem = $devolucao->notaOrigem;
            $empresa = $devolucao->venda->empresa;

            $serie = SerieFiscal::query()
                ->where('empresa_id', $empresa->id)
                ->where('tipo', 'NFe')
                ->lockForUpdate()
                ->firstOrFail();

            $numero = $serie->proximo_numero;
            $serie->update(['ultimo_numero' => $numero]);

            $mesmaUf = strtoupper((string) $empresa->estado) === strtoupper((string) $origem->destinatario_uf);
            $cfop = $mesmaUf ? '1202' : '2202';

            $nota = NotaFiscal::create([
                'empresa_id' => $empresa->id,
                'pedido_id' => $devolucao->venda->pedido_id,
                'nota_fiscal_referenciada_id' => $origem->id,
                'tipo' => 'NFe',
                'modelo' => '55',
                'serie' => $serie->serie,
                'numero' => $numero,
                'chave_referenciada' => $origem->chave_acesso,
                'natureza_operacao' => 'DEVOLUCAO DE VENDA',
                'ambiente' => $serie->ambiente,
                'finalidade' => 4,
                'emitente_cnpj' => $empresa->cnpj,
                'emitente_ie' => $empresa->ie,
                'emitente_razao' => $empresa->razao_social,
                'destinatario_cpf_cnpj' => $origem->destinatario_cpf_cnpj,
                'destinatario_ie' => $origem->destinatario_ie,
                'destinatario_nome' => $origem->destinatario_nome,
                'destinatario_email' => $origem->destinatario_email,
                'destinatario_cep' => $origem->destinatario_cep,
                'destinatario_logradouro' => $origem->destinatario_logradouro,
                'destinatario_numero' => $origem->destinatario_numero,
                'destinatario_complemento' => $origem->destinatario_complemento,
                'destinatario_bairro' => $origem->destinatario_bairro,
                'destinatario_cidade' => $origem->destinatario_cidade,
                'destinatario_uf' => $origem->destinatario_uf,
                'destinatario_ibge' => $origem->destinatario_ibge,
                'valor_produtos' => $devolucao->valor_produtos,
                'valor_total' => $devolucao->valor_produtos,
                'tipo_frete' => '9',
                'informacoes_adicionais' => "Devolução vinculada à NFC-e {$origem->chave_acesso}. Processo {$devolucao->numero}. Motivo: {$devolucao->motivo}.",
                'status' => NotaFiscal::STATUS_RASCUNHO,
                'data_emissao' => now(),
            ]);

            foreach ($devolucao->itens as $index => $item) {
                $origemItem = $origem->itens()->where('produto_id', $item->produto_id)->first();

                NotaFiscalItem::create([
                    'nota_fiscal_id' => $nota->id,
                    'produto_id' => $item->produto_id,
                    'numero_item' => $index + 1,
                    'codigo' => $item->codigo,
                    'descricao' => $item->descricao,
                    'ncm' => $item->ncm,
                    'cfop' => $cfop,
                    'unidade' => $item->unidade,
                    'quantidade' => $item->quantidade_devolvida,
                    'valor_unitario' => $item->valor_unitario,
                    'valor_total' => $item->valor_total,
                    'valor_desconto' => 0,
                    'icms_origem' => $origemItem?->icms_origem ?? 0,
                    'icms_cst' => $origemItem?->icms_cst ?? '102',
                    'icms_base' => 0,
                    'icms_aliquota' => 0,
                    'icms_valor' => 0,
                    'ipi_cst' => $origemItem?->ipi_cst,
                    'ipi_base' => 0,
                    'ipi_aliquota' => 0,
                    'ipi_valor' => 0,
                    'pis_cst' => $origemItem?->pis_cst ?? '49',
                    'pis_base' => 0,
                    'pis_aliquota' => 0,
                    'pis_valor' => 0,
                    'cofins_cst' => $origemItem?->cofins_cst ?? '49',
                    'cofins_base' => 0,
                    'cofins_aliquota' => 0,
                    'cofins_valor' => 0,
                ]);
            }

            $devolucao->update([
                'nota_fiscal_devolucao_id' => $nota->id,
                'status' => 'aguardando_emissao_fiscal',
            ]);

            return $nota->fresh('itens');
        });
    }

    public function emitirNfe(DevolucaoFiscal $devolucao): NotaFiscal
    {
        $nota = $this->prepararNfe($devolucao);
        $emitida = $this->nfe->emitir($nota);

        if ($emitida->status === NotaFiscal::STATUS_AUTORIZADA) {
            $devolucao->update([
                'status' => $devolucao->status_reembolso === 'concluido' ? 'concluida' : 'nota_autorizada',
                'concluida_em' => $devolucao->status_reembolso === 'concluido' ? now() : null,
            ]);
        }

        return $emitida;
    }
}
