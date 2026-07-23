<?php

namespace App\Services\Estoque;

use App\Models\{Estoque, MovimentacaoEstoque, Produto, Deposito};
use Illuminate\Support\Facades\DB;
use Exception;

class EstoqueService
{
    // ──────────────────────────────────────────────────────────
    // ENTRADA (compra, devolução, ajuste+)
    // ──────────────────────────────────────────────────────────

    public function entrada(
        Produto $produto,
        Deposito $deposito,
        float $quantidade,
        float $custoUnitario = 0,
        string $motivo = 'compra',
        ?int $documentoId = null,
        string $documentoTipo = '',
        string $documentoNumero = '',
        ?int $variacaoId = null,
    ): Estoque {
        if ($quantidade <= 0) throw new Exception('Quantidade deve ser maior que zero.');

        DB::beginTransaction();
        try {
            $estoque = $this->obterOuCriar($produto->id, $deposito->id, $variacaoId);

            // Custo médio ponderado
            $novoTotal    = ($estoque->quantidade * $estoque->custo_medio) + ($quantidade * $custoUnitario);
            $novaQtde     = $estoque->quantidade + $quantidade;
            $novoCustaMed = $novaQtde > 0 ? round($novoTotal / $novaQtde, 4) : $custoUnitario;

            $saldoAnterior = $estoque->quantidade;
            $estoque->update([
                'quantidade'   => $novaQtde,
                'custo_medio'  => $novoCustaMed,
                'updated_at'   => now(),
            ]);

            $this->registrarMovimentacao([
                'empresa_id'     => $produto->empresa_id,
                'produto_id'     => $produto->id,
                'variacao_id'    => $variacaoId,
                'deposito_id'    => $deposito->id,
                'tipo'           => 'entrada',
                'motivo'         => $motivo,
                'documento_tipo' => $documentoTipo,
                'documento_id'   => $documentoId,
                'documento_numero' => $documentoNumero,
                'quantidade'     => $quantidade,
                'custo_unitario' => $custoUnitario,
                'custo_total'    => round($quantidade * $custoUnitario, 2),
                'saldo_anterior' => $saldoAnterior,
                'saldo_posterior'=> $novaQtde,
                'usuario_id'     => auth()->id(),
            ]);

            DB::commit();
            return $estoque->fresh();

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ──────────────────────────────────────────────────────────
    // SAÍDA (venda, perda, ajuste-)
    // ──────────────────────────────────────────────────────────

    public function saida(
        Produto $produto,
        Deposito $deposito,
        float $quantidade,
        string $motivo = 'venda',
        ?int $documentoId = null,
        string $documentoTipo = '',
        string $documentoNumero = '',
        ?int $variacaoId = null,
        bool $forcar = false,
    ): Estoque {
        if ($quantidade <= 0) throw new Exception('Quantidade deve ser maior que zero.');

        DB::beginTransaction();
        try {
            $estoque = $this->obterOuCriar($produto->id, $deposito->id, $variacaoId);

            if (! $forcar && $produto->controla_estoque && $estoque->quantidade_disponivel < $quantidade) {
                throw new Exception(
                    "Estoque insuficiente para o produto [{$produto->nome}]. " .
                    "Disponível: {$estoque->quantidade_disponivel} | Solicitado: {$quantidade}"
                );
            }

            $saldoAnterior = $estoque->quantidade;
            $novaQtde      = max(0, $estoque->quantidade - $quantidade);

            $estoque->update([
                'quantidade' => $novaQtde,
                'updated_at' => now(),
            ]);

            $this->registrarMovimentacao([
                'empresa_id'      => $produto->empresa_id,
                'produto_id'      => $produto->id,
                'variacao_id'     => $variacaoId,
                'deposito_id'     => $deposito->id,
                'tipo'            => 'saida',
                'motivo'          => $motivo,
                'documento_tipo'  => $documentoTipo,
                'documento_id'    => $documentoId,
                'documento_numero'=> $documentoNumero,
                'quantidade'      => $quantidade,
                'custo_unitario'  => $estoque->custo_medio,
                'custo_total'     => round($quantidade * $estoque->custo_medio, 2),
                'saldo_anterior'  => $saldoAnterior,
                'saldo_posterior' => $novaQtde,
                'usuario_id'      => auth()->id(),
            ]);

            DB::commit();
            return $estoque->fresh();

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ──────────────────────────────────────────────────────────
    // TRANSFERÊNCIA entre depósitos
    // ──────────────────────────────────────────────────────────

    public function transferir(
        Produto $produto,
        Deposito $origem,
        Deposito $destino,
        float $quantidade,
        ?int $variacaoId = null,
    ): array {
        if ($origem->id === $destino->id) {
            throw new Exception('Depósito de origem e destino não podem ser iguais.');
        }

        DB::beginTransaction();
        try {
            $estoqueOrigem = $this->obterOuCriar($produto->id, $origem->id, $variacaoId);

            if ($produto->controla_estoque && $estoqueOrigem->quantidade_disponivel < $quantidade) {
                throw new Exception("Estoque insuficiente no depósito {$origem->nome}.");
            }

            // Saída do depósito de origem
            $saida = $this->saida($produto, $origem, $quantidade, 'transferencia',
                null, 'transferencia', '', $variacaoId, false);

            // Entrada no depósito de destino
            $entrada = $this->entrada($produto, $destino, $quantidade,
                $estoqueOrigem->custo_medio, 'transferencia',
                null, 'transferencia', '', $variacaoId);

            // Registrar relação de transferência
            MovimentacaoEstoque::where('produto_id', $produto->id)
                ->where('deposito_id', $origem->id)
                ->where('tipo', 'saida')
                ->where('motivo', 'transferencia')
                ->latest()
                ->first()
                ?->update(['deposito_destino_id' => $destino->id]);

            DB::commit();
            return ['saida' => $saida, 'entrada' => $entrada];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ──────────────────────────────────────────────────────────
    // RESERVAR (ao confirmar pedido)
    // ──────────────────────────────────────────────────────────

    public function reservar(Produto $produto, Deposito $deposito, float $quantidade, ?int $variacaoId = null): void
    {
        $estoque = $this->obterOuCriar($produto->id, $deposito->id, $variacaoId);

        if ($estoque->quantidade_disponivel < $quantidade) {
            throw new Exception("Saldo insuficiente para reserva do produto [{$produto->nome}].");
        }

        DB::table('estoque')
            ->where('id', $estoque->id)
            ->update([
                'quantidade_reservada'  => DB::raw("quantidade_reservada + {$quantidade}"),
                'quantidade_disponivel' => DB::raw("quantidade_disponivel - {$quantidade}"),
            ]);
    }

    public function liberarReserva(Produto $produto, Deposito $deposito, float $quantidade, ?int $variacaoId = null): void
    {
        $estoque = $this->obterOuCriar($produto->id, $deposito->id, $variacaoId);
        $liberar = min($quantidade, $estoque->quantidade_reservada);

        DB::table('estoque')
            ->where('id', $estoque->id)
            ->update([
                'quantidade_reservada'  => DB::raw("quantidade_reservada - {$liberar}"),
                'quantidade_disponivel' => DB::raw("quantidade_disponivel + {$liberar}"),
            ]);
    }

    // ──────────────────────────────────────────────────────────
    // AJUSTE manual (inventário)
    // ──────────────────────────────────────────────────────────

    public function ajustar(
        Produto $produto,
        Deposito $deposito,
        float $novaQuantidade,
        float $novoCusto = 0,
        string $observacao = '',
        ?int $variacaoId = null,
    ): Estoque {
        DB::beginTransaction();
        try {
            $estoque       = $this->obterOuCriar($produto->id, $deposito->id, $variacaoId);
            $saldoAnterior = $estoque->quantidade;
            $diferenca     = $novaQuantidade - $saldoAnterior;

            if ($novaQuantidade < (float) $estoque->quantidade_reservada) {
                throw new Exception(
                    'A nova quantidade não pode ser menor que o saldo reservado de '.
                    number_format((float) $estoque->quantidade_reservada, 3, ',', '.').'.'
                );
            }

            if (abs($diferenca) < 0.0005) {
                throw new Exception('A nova quantidade é igual ao saldo atual.');
            }

            $estoque->update([
                'quantidade'  => $novaQuantidade,
                'custo_medio' => $novoCusto > 0 ? $novoCusto : $estoque->custo_medio,
                'updated_at'  => now(),
            ]);

            $this->registrarMovimentacao([
                'empresa_id'      => $produto->empresa_id,
                'produto_id'      => $produto->id,
                'variacao_id'     => $variacaoId,
                'deposito_id'     => $deposito->id,
                'tipo'            => 'ajuste',
                'motivo'          => 'inventario',
                'quantidade'      => abs($diferenca),
                'custo_unitario'  => $estoque->custo_medio,
                'saldo_anterior'  => $saldoAnterior,
                'saldo_posterior' => $novaQuantidade,
                'observacoes'     => $observacao,
                'usuario_id'      => auth()->id(),
            ]);

            DB::commit();
            return $estoque->fresh();

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ──────────────────────────────────────────────────────────
    // POSIÇÃO ATUAL
    // ──────────────────────────────────────────────────────────

    public function posicaoAtual(int $empresaId, ?int $depositoId = null): array
    {
        $q = DB::table('estoque as e')
            ->join('produtos as p', 'p.id', '=', 'e.produto_id')
            ->join('depositos as d', 'd.id', '=', 'e.deposito_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->where('p.empresa_id', $empresaId)
            ->where('p.deleted_at', null)
            ->select([
                'p.id', 'p.nome', 'p.codigo', 'p.sku', 'p.ncm',
                'p.estoque_minimo', 'p.estoque_maximo',
                'c.nome as categoria',
                'd.nome as deposito',
                'e.quantidade', 'e.quantidade_reservada', 'e.quantidade_disponivel',
                'e.custo_medio',
                DB::raw('e.quantidade * e.custo_medio as valor_total'),
                DB::raw('e.quantidade <= p.estoque_minimo as abaixo_minimo'),
            ]);

        if ($depositoId) {
            $q->where('e.deposito_id', $depositoId);
        }

        return $q->orderBy('p.nome')->get()->toArray();
    }

    public function alertasEstoqueBaixo(int $empresaId): array
    {
        return DB::table('estoque as e')
            ->join('produtos as p', 'p.id', '=', 'e.produto_id')
            ->join('depositos as d', 'd.id', '=', 'e.deposito_id')
            ->where('p.empresa_id', $empresaId)
            ->where('p.controla_estoque', true)
            ->where('p.deleted_at', null)
            ->whereRaw('e.quantidade_disponivel <= p.estoque_minimo')
            ->select('p.id', 'p.nome', 'p.sku', 'p.estoque_minimo',
                     'e.quantidade_disponivel', 'd.nome as deposito')
            ->orderBy('e.quantidade_disponivel')
            ->get()
            ->toArray();
    }

    // ──────────────────────────────────────────────────────────
    // Helpers privados
    // ──────────────────────────────────────────────────────────

    private function obterOuCriar(int $produtoId, int $depositoId, ?int $variacaoId): Estoque
    {
        return Estoque::firstOrCreate(
            [
                'produto_id'  => $produtoId,
                'deposito_id' => $depositoId,
                'variacao_id' => $variacaoId,
            ],
            [
                'empresa_id'           => Produto::find($produtoId)->empresa_id,
                'quantidade'           => 0,
                'quantidade_reservada' => 0,
                'custo_medio'          => 0,
            ]
        );
    }

    private function registrarMovimentacao(array $dados): MovimentacaoEstoque
    {
        return MovimentacaoEstoque::create($dados);
    }
}
