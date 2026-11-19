<?php

namespace App\Http\Controllers\Api\V1\Compras;

use App\Http\Controllers\Controller;
use App\Models\{PedidoCompra, PedidoCompraItem, Produto, Deposito};
use App\Services\Estoque\EstoqueService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Exception;

class PedidoCompraController extends Controller
{
    public function __construct(private readonly EstoqueService $estoqueService) {}

    // ── GET /pedidos-compra ────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('compras.view');

        $q = PedidoCompra::with(['fornecedor:id,razao_social,nome_fantasia', 'deposito:id,nome'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->fornecedor_id, fn($q, $v) => $q->where('fornecedor_id', $v))
            ->when($request->busca, fn($q, $v) => $q->whereLike('numero', "%{$v}%"))
            ->orderByDesc('data_emissao');

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($q->paginate($perPage));
    }

    // ── POST /pedidos-compra ───────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->authorize('compras.create');

        $dados = $request->validate([
            'fornecedor_id'    => 'nullable|exists:fornecedores,id',
            'deposito_id'      => 'nullable|exists:depositos,id',
            'data_previsao'    => 'nullable|date',
            'frete'            => 'nullable|numeric|min:0',
            'outras_despesas'  => 'nullable|numeric|min:0',
            'desconto_valor'   => 'nullable|numeric|min:0',
            'observacoes'      => 'nullable|string',
            'itens'                    => 'required|array|min:1',
            'itens.*.produto_id'       => 'required|exists:produtos,id',
            'itens.*.variacao_id'      => 'nullable|exists:produto_variacoes,id',
            'itens.*.descricao'        => 'nullable|string|max:300',
            'itens.*.quantidade'       => 'required|numeric|min:0.001',
            'itens.*.preco_unitario'   => 'required|numeric|min:0',
            'itens.*.desconto_percentual' => 'nullable|numeric|min:0|max:100',
        ]);

        $pedido = DB::transaction(function () use ($dados) {
            $subtotal = 0;
            foreach ($dados['itens'] as $item) {
                $bruto = $item['quantidade'] * $item['preco_unitario'];
                $subtotal += $bruto - ($bruto * (($item['desconto_percentual'] ?? 0) / 100));
            }

            $total = $subtotal + ($dados['frete'] ?? 0) + ($dados['outras_despesas'] ?? 0) - ($dados['desconto_valor'] ?? 0);

            $pedido = PedidoCompra::create([
                'empresa_id'       => auth()->user()->empresa_id,
                'fornecedor_id'    => $dados['fornecedor_id'] ?? null,
                'deposito_id'      => $dados['deposito_id'] ?? null,
                'status'           => PedidoCompra::STATUS_RASCUNHO,
                'subtotal'         => $subtotal,
                'desconto_valor'   => $dados['desconto_valor'] ?? 0,
                'frete'            => $dados['frete'] ?? 0,
                'outras_despesas'  => $dados['outras_despesas'] ?? 0,
                'total'            => $total,
                'data_previsao'    => $dados['data_previsao'] ?? null,
                'observacoes'      => $dados['observacoes'] ?? null,
            ]);
            $pedido->update(['numero' => $pedido->gerarNumero()]);

            foreach ($dados['itens'] as $item) {
                $bruto = $item['quantidade'] * $item['preco_unitario'];
                $totalItem = $bruto - ($bruto * (($item['desconto_percentual'] ?? 0) / 100));

                $produto = Produto::find($item['produto_id']);

                PedidoCompraItem::create([
                    'pedido_compra_id'     => $pedido->id,
                    'produto_id'           => $item['produto_id'],
                    'variacao_id'          => $item['variacao_id'] ?? null,
                    'descricao'            => $item['descricao'] ?? $produto?->nome,
                    'quantidade'           => $item['quantidade'],
                    'preco_unitario'       => $item['preco_unitario'],
                    'desconto_percentual'  => $item['desconto_percentual'] ?? 0,
                    'total'                => round($totalItem, 2),
                ]);
            }

            return $pedido;
        });

        return response()->json([
            'message' => 'Pedido de compra criado com sucesso.',
            'data'    => $pedido->fresh(['fornecedor', 'deposito', 'itens.produto']),
        ], 201);
    }

    // ── GET /pedidos-compra/{id} ───────────────────────────────

    public function show(int $id): JsonResponse
    {
        $this->authorize('compras.view');

        $pedido = PedidoCompra::with(['fornecedor', 'deposito', 'itens.produto:id,nome,sku,codigo'])
            ->findOrFail($id);

        return response()->json($pedido);
    }

    // ── PUT /pedidos-compra/{id} ───────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('compras.edit');

        $pedido = PedidoCompra::findOrFail($id);

        if (! $pedido->pode_editar) {
            return response()->json(['message' => 'Somente pedidos em rascunho podem ser editados.'], 422);
        }

        $dados = $request->validate([
            'fornecedor_id'   => 'nullable|exists:fornecedores,id',
            'deposito_id'     => 'nullable|exists:depositos,id',
            'data_previsao'   => 'nullable|date',
            'frete'           => 'nullable|numeric|min:0',
            'outras_despesas' => 'nullable|numeric|min:0',
            'desconto_valor'  => 'nullable|numeric|min:0',
            'observacoes'     => 'nullable|string',
        ]);

        $pedido->update($dados);

        return response()->json(['message' => 'Pedido de compra atualizado.', 'data' => $pedido]);
    }

    // ── POST /pedidos-compra/{id}/enviar ───────────────────────
    // Marca o pedido como enviado ao fornecedor (não permite mais edição dos itens).

    public function enviar(int $id): JsonResponse
    {
        $this->authorize('compras.edit');

        $pedido = PedidoCompra::findOrFail($id);

        if ($pedido->status !== PedidoCompra::STATUS_RASCUNHO) {
            return response()->json(['message' => 'Somente pedidos em rascunho podem ser enviados.'], 422);
        }

        $pedido->update(['status' => PedidoCompra::STATUS_ENVIADO]);

        return response()->json(['message' => 'Pedido de compra enviado ao fornecedor.', 'data' => $pedido]);
    }

    // ── POST /pedidos-compra/{id}/receber ──────────────────────
    // Dá entrada no estoque das quantidades recebidas (total ou parcial).

    public function receber(Request $request, int $id): JsonResponse
    {
        $this->authorize('compras.receber');

        $pedido = PedidoCompra::with('itens.produto')->findOrFail($id);

        if (! $pedido->pode_receber) {
            return response()->json(['message' => 'Este pedido não está disponível para recebimento.'], 422);
        }
        if (! $pedido->deposito_id) {
            return response()->json(['message' => 'Defina o depósito de destino do pedido antes de receber.'], 422);
        }

        $dados = $request->validate([
            'itens'                  => 'required|array|min:1',
            'itens.*.item_id'        => 'required|exists:pedido_compra_itens,id',
            'itens.*.quantidade_recebida' => 'required|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($pedido, $dados) {
                $deposito = Deposito::findOrFail($pedido->deposito_id);

                foreach ($dados['itens'] as $entrada) {
                    $item = PedidoCompraItem::where('pedido_compra_id', $pedido->id)->findOrFail($entrada['item_id']);
                    $quantidadeRecebendo = (float) $entrada['quantidade_recebida'];

                    if ($quantidadeRecebendo <= 0) {
                        continue;
                    }
                    if ($quantidadeRecebendo > $item->saldo_a_receber) {
                        throw new Exception("Quantidade recebida do item {$item->descricao} é maior que o saldo pendente.");
                    }

                    $this->estoqueService->entrada(
                        $item->produto,
                        $deposito,
                        $quantidadeRecebendo,
                        (float) $item->preco_unitario,
                        'compra',
                        $pedido->id,
                        'pedido_compra',
                        $pedido->numero,
                        $item->variacao_id,
                    );

                    $item->update(['quantidade_recebida' => $item->quantidade_recebida + $quantidadeRecebendo]);
                }

                $totalItens = $pedido->itens->sum('quantidade');
                $totalRecebido = PedidoCompraItem::where('pedido_compra_id', $pedido->id)->sum('quantidade_recebida');

                $pedido->update([
                    'status' => $totalRecebido >= $totalItens ? PedidoCompra::STATUS_RECEBIDO : PedidoCompra::STATUS_PARCIAL,
                    'data_recebimento' => $totalRecebido >= $totalItens ? today() : $pedido->data_recebimento,
                ]);
            });

            return response()->json([
                'message' => 'Recebimento registrado e estoque atualizado com sucesso.',
                'data'    => $pedido->fresh(['fornecedor', 'deposito', 'itens.produto']),
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── DELETE /pedidos-compra/{id} ────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('compras.delete');

        $pedido = PedidoCompra::findOrFail($id);

        if (! $pedido->pode_editar) {
            return response()->json(['message' => 'Somente pedidos em rascunho podem ser excluídos. Cancele-o em vez disso.'], 422);
        }

        $pedido->delete();

        return response()->json(['message' => 'Pedido de compra excluído.']);
    }

    // ── POST /pedidos-compra/{id}/cancelar ─────────────────────

    public function cancelar(int $id): JsonResponse
    {
        $this->authorize('compras.edit');

        $pedido = PedidoCompra::findOrFail($id);

        if ($pedido->status === PedidoCompra::STATUS_RECEBIDO) {
            return response()->json(['message' => 'Pedido já recebido não pode ser cancelado.'], 422);
        }

        $pedido->update(['status' => PedidoCompra::STATUS_CANCELADO]);

        return response()->json(['message' => 'Pedido de compra cancelado.', 'data' => $pedido]);
    }
}
