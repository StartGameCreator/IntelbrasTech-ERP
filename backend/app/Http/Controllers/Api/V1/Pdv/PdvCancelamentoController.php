<?php

namespace App\Http\Controllers\Api\V1\Pdv;

use App\Http\Controllers\Controller;
use App\Models\{
    CaixaMovimentacao,
    NotaFiscal,
    PdvVenda,
    Produto
};
use App\Services\Estoque\EstoqueService;
use App\Services\Fiscal\NFeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PdvCancelamentoController extends Controller
{
    public function __construct(
        private readonly EstoqueService $estoque,
        private readonly NFeService $nfe,
    ) {}

    public function cancelarNota(Request $request, int $id): JsonResponse
    {
        $this->authorize('fiscal.cancelar');

        $dados = $request->validate([
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ]);

        $venda = $this->venda($id);
        $nota = $venda->notaFiscal;

        if (! $nota) {
            return response()->json([
                'message' => 'Esta venda não possui documento fiscal vinculado.',
            ], 422);
        }

        if ($nota->status === NotaFiscal::STATUS_CANCELADA) {
            return response()->json([
                'message' => 'A nota fiscal já está cancelada.',
                'venda' => $venda->fresh(),
                'nota_fiscal' => $nota,
            ]);
        }

        try {
            $notaCancelada = $this->nfe->cancelar($nota, $dados['justificativa']);

            $venda->update([
                'nfce_status' => 'cancelada',
            ]);

            return response()->json([
                'message' => 'Documento fiscal cancelado na SEFAZ com sucesso.',
                'venda' => $venda->fresh(),
                'nota_fiscal' => $notaCancelada,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cancelarVenda(Request $request, int $id): JsonResponse
    {
        $this->authorize('pedidos.edit');

        $dados = $request->validate([
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $venda = $this->venda($id)->load([
            'pedido.itens.produto',
            'pedido.itens.deposito',
            'pagamentos',
            'notaFiscal',
        ]);

        if ($venda->status === 'cancelada') {
            return response()->json([
                'message' => 'A venda já está cancelada.',
                'venda' => $venda,
            ]);
        }

        if ($venda->notaFiscal?->status === NotaFiscal::STATUS_AUTORIZADA) {
            return response()->json([
                'message' => 'Cancele primeiro a nota fiscal autorizada na SEFAZ. Depois cancele a venda.',
                'requires_fiscal_cancellation' => true,
            ], 422);
        }

        try {
            DB::transaction(function () use ($venda, $dados) {
                foreach ($venda->pedido?->itens ?? [] as $item) {
                    $produto = $item->produto;

                    if (! $produto || ! $produto->controla_estoque || ! $item->deposito) {
                        continue;
                    }

                    $this->estoque->entrada(
                        $produto,
                        $item->deposito,
                        (float) $item->quantidade,
                        0,
                        'cancelamento_venda_pdv',
                        $venda->pedido_id,
                        'pedido',
                        (string) ($venda->pedido?->numero ?? $venda->numero),
                    );
                }

                foreach ($venda->pagamentos as $pagamento) {
                    CaixaMovimentacao::create([
                        'caixa_id' => $venda->caixa_id,
                        'usuario_id' => auth()->id(),
                        'tipo' => 'estorno_venda',
                        'valor' => (float) $pagamento->valor,
                        'forma_pagamento' => $pagamento->tipo,
                        'descricao' => "Estorno da venda PDV {$venda->numero}",
                        'pedido_id' => $venda->pedido_id,
                        'metadados' => [
                            'pdv_venda_id' => $venda->id,
                            'motivo' => $dados['motivo'],
                        ],
                        'created_at' => now(),
                    ]);
                }

                $venda->pedido?->update([
                    'status' => 'cancelado',
                    'status_pagamento' => 'cancelado',
                    'status_entrega' => 'cancelado',
                    'motivo_cancelamento' => $dados['motivo'],
                    'data_cancelamento' => now(),
                ]);

                $venda->update([
                    'status' => 'cancelada',
                    'motivo_cancelamento' => $dados['motivo'],
                    'cancelada_em' => now(),
                    'cancelada_por' => auth()->id(),
                ]);
            });

            return response()->json([
                'message' => 'Venda cancelada. Estoque e caixa foram estornados.',
                'venda' => $venda->fresh(['notaFiscal']),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Não foi possível cancelar a venda: '.$e->getMessage(),
            ], 422);
        }
    }

    private function venda(int $id): PdvVenda
    {
        return PdvVenda::with(['notaFiscal', 'pedido'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);
    }
}
