<?php

namespace App\Http\Controllers\Api\V1\Comercial;

use App\Http\Controllers\Controller;
use App\Models\{Pedido, PedidoItem, Produto, Deposito};
use App\Services\Estoque\EstoqueService;
use App\Services\Fiscal\NFeService;
use App\Events\PedidoCriadoEvent;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;

class PedidoController extends Controller
{
    public function __construct(
        private readonly EstoqueService $estoque,
        private readonly NFeService $nfe,
    ) {}

    // ── GET /pedidos ──────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('pedidos.view');

        $q = Pedido::with(['cliente:id,razao_social,nome_fantasia', 'vendedor:id,nome'])
            ->when($request->status,   fn($q, $v) => $q->status($v))
            ->when($request->canal,    fn($q, $v) => $q->canal($v))
            ->when($request->marketplace, fn($q, $v) => $q->marketplace($v))
            ->when($request->cliente_id,  fn($q, $v) => $q->where('cliente_id', $v))
            ->when($request->vendedor_id, fn($q, $v) => $q->where('vendedor_id', $v))
            ->when($request->data_inicio && $request->data_fim,
                fn($q) => $q->periodo($request->data_inicio, $request->data_fim))
            ->when($request->numero, fn($q, $v) => $q->where('numero', 'like', "%{$v}%"))
            ->when($request->status_pagamento, fn($q, $v) => $q->where('status_pagamento', $v))
            ->orderBy($request->get('order_by', 'data_pedido'), $request->get('order', 'desc'));

        $perPage = min($request->get('per_page', 15), 100);

        return response()->json($q->paginate($perPage));
    }

    // ── POST /pedidos ─────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->authorize('pedidos.create');

        $dados = $request->validate([
            'cliente_id'           => 'nullable|exists:clientes,id',
            'canal'                => 'sometimes|string|max:30',
            'tabela_preco_id'      => 'nullable|exists:tabelas_preco,id',
            'entrega_cep'          => 'nullable|string|max:9',
            'entrega_logradouro'   => 'nullable|string|max:200',
            'entrega_numero'       => 'nullable|string|max:10',
            'entrega_complemento'  => 'nullable|string|max:100',
            'entrega_bairro'       => 'nullable|string|max:100',
            'entrega_cidade'       => 'nullable|string|max:100',
            'entrega_estado'       => 'nullable|string|size:2',
            'frete'                => 'nullable|numeric|min:0',
            'desconto_percentual'  => 'nullable|numeric|min:0|max:100',
            'desconto_valor'       => 'nullable|numeric|min:0',
            'observacoes'          => 'nullable|string',
            'observacoes_nfe'      => 'nullable|string',
            'condicao_pagamento_id'=> 'nullable|exists:condicoes_pagamento,id',
            'transportadora_id'    => 'nullable|exists:transportadoras,id',
            'tipo_frete'           => 'nullable|in:C,F,T,S',
            'itens'                => 'required|array|min:1',
            'itens.*.produto_id'   => 'required|exists:produtos,id',
            'itens.*.variacao_id'  => 'nullable|exists:produto_variacoes,id',
            'itens.*.deposito_id'  => 'nullable|exists:depositos,id',
            'itens.*.quantidade'   => 'required|numeric|min:0.001',
            'itens.*.preco_unitario' => 'required|numeric|min:0',
            'itens.*.desconto_percentual' => 'nullable|numeric|min:0|max:100',
            'itens.*.desconto_valor'      => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $pedido = Pedido::create([
                ...$dados,
                'numero'     => '',
                'vendedor_id'=> auth()->id(),
                'status'     => Pedido::STATUS_ORCAMENTO,
                'data_pedido'=> now(),
            ]);

            $pedido->update(['numero' => $pedido->gerarNumero()]);

            $subtotal = 0;
            foreach ($dados['itens'] as $itemDados) {
                $produto = Produto::findOrFail($itemDados['produto_id']);
                $qtde    = $itemDados['quantidade'];
                $preco   = $itemDados['preco_unitario'];

                $descPct = $itemDados['desconto_percentual'] ?? 0;
                $descVal = $itemDados['desconto_valor'] ?? 0;
                if ($descPct > 0) $descVal = round($preco * $qtde * ($descPct / 100), 2);

                $total = round(($preco * $qtde) - $descVal, 2);

                PedidoItem::create([
                    'pedido_id'          => $pedido->id,
                    'produto_id'         => $produto->id,
                    'variacao_id'        => $itemDados['variacao_id'] ?? null,
                    'deposito_id'        => $itemDados['deposito_id'] ?? null,
                    'codigo'             => $produto->codigo ?? $produto->sku,
                    'descricao'          => $produto->nome,
                    'ncm'                => $produto->ncm,
                    'cfop'               => $produto->cfop ?? '5102',
                    'unidade'            => $produto->unidade?->sigla ?? 'UN',
                    'quantidade'         => $qtde,
                    'preco_unitario'     => $preco,
                    'desconto_percentual'=> $descPct,
                    'desconto_valor'     => $descVal,
                    'total'              => $total,
                    // Tributos do produto
                    'icms_cst'    => $produto->icms_cst,
                    'icms_aliquota' => $produto->icms_aliquota,
                    'pis_cst'     => $produto->pis_cst,
                    'pis_aliquota'=> $produto->pis_aliquota,
                    'cofins_cst'  => $produto->cofins_cst,
                    'cofins_aliquota' => $produto->cofins_aliquota,
                ]);

                $subtotal += $total;
            }

            $pedido->recalcularTotais();

            DB::commit();

            event(new PedidoCriadoEvent($pedido->fresh(['itens', 'cliente'])));

            return response()->json([
                'message' => 'Pedido criado com sucesso.',
                'pedido'  => $pedido->fresh(['itens.produto', 'cliente', 'vendedor']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── GET /pedidos/{id} ─────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $this->authorize('pedidos.view');

        $pedido = Pedido::with([
            'itens.produto.imagens',
            'itens.variacao',
            'cliente',
            'vendedor:id,nome',
            'notasFiscais',
            'contasReceber',
            'envio',
            'transportadora',
        ])->findOrFail($id);

        $pedido->append(['pode_emitir_nfe', 'pode_cancelar', 'status_cor']);

        return response()->json($pedido);
    }

    // ── POST /pedidos/{id}/confirmar ──────────────────────────

    public function confirmar(int $id): JsonResponse
    {
        $this->authorize('pedidos.edit');

        $pedido = Pedido::with('itens.produto')->findOrFail($id);

        if ($pedido->status !== Pedido::STATUS_ORCAMENTO) {
            return response()->json(['message' => 'Apenas orçamentos podem ser confirmados.'], 422);
        }

        DB::beginTransaction();
        try {
            // Reservar estoque para cada item
            foreach ($pedido->itens as $item) {
                if ($item->produto->controla_estoque && $item->deposito_id) {
                    $deposito = Deposito::find($item->deposito_id);
                    $this->estoque->reservar(
                        $item->produto,
                        $deposito,
                        $item->quantidade,
                        $item->variacao_id
                    );
                }
            }

            $pedido->update([
                'status'           => Pedido::STATUS_CONFIRMADO,
                'data_confirmacao' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Pedido confirmado com sucesso.',
                'pedido'  => $pedido->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── POST /pedidos/{id}/cancelar ───────────────────────────

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->authorize('pedidos.delete');

        $request->validate(['motivo' => 'required|string|min:10']);

        $pedido = Pedido::with('itens.produto')->findOrFail($id);

        if (! $pedido->pode_cancelar) {
            return response()->json(['message' => 'Este pedido não pode ser cancelado.'], 422);
        }

        DB::beginTransaction();
        try {
            // Liberar reservas de estoque
            if ($pedido->status === Pedido::STATUS_CONFIRMADO) {
                foreach ($pedido->itens as $item) {
                    if ($item->produto->controla_estoque && $item->deposito_id) {
                        $deposito = Deposito::find($item->deposito_id);
                        $this->estoque->liberarReserva(
                            $item->produto,
                            $deposito,
                            $item->quantidade,
                            $item->variacao_id
                        );
                    }
                }
            }

            $pedido->update([
                'status'              => Pedido::STATUS_CANCELADO,
                'motivo_cancelamento' => $request->motivo,
                'data_cancelamento'   => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Pedido cancelado.',
                'pedido'  => $pedido->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── POST /pedidos/{id}/emitir-nfe ─────────────────────────

    public function emitirNfe(int $id): JsonResponse
    {
        $this->authorize('fiscal.emitir');

        $pedido = Pedido::with(['itens.produto.unidade', 'cliente', 'empresa'])->findOrFail($id);

        if (! $pedido->pode_emitir_nfe) {
            return response()->json(['message' => 'NF-e não pode ser emitida para este pedido.'], 422);
        }

        $nfeMontada = $this->nfe->montarDePedido($pedido);
        $nfeEmitida = $this->nfe->emitir($nfeMontada);

        return response()->json([
            'message'    => 'NF-e emitida e autorizada com sucesso.',
            'nota_fiscal'=> $nfeEmitida,
        ]);
    }

    // ── GET /pedidos/kanban ───────────────────────────────────

    public function kanban(): JsonResponse
    {
        $this->authorize('pedidos.view');

        $statusList = [
            Pedido::STATUS_ORCAMENTO,
            Pedido::STATUS_CONFIRMADO,
            Pedido::STATUS_FATURADO,
            Pedido::STATUS_ENTREGUE,
        ];

        $kanban = [];
        foreach ($statusList as $status) {
            $kanban[$status] = Pedido::status($status)
                ->with('cliente:id,razao_social,nome_fantasia')
                ->select('id', 'numero', 'cliente_id', 'total', 'data_pedido', 'status', 'canal')
                ->latest('data_pedido')
                ->limit(50)
                ->get();
        }

        return response()->json($kanban);
    }

    // ── GET /pedidos — resumo do dia ──────────────────────────

    public function resumo(): JsonResponse
    {
        $this->authorize('pedidos.view');

        $hoje  = Pedido::hoje();
        $mes   = Pedido::mesAtual();

        return response()->json([
            'hoje' => [
                'total_pedidos' => (clone $hoje)->count(),
                'valor_total'   => (clone $hoje)->whereNotIn('status', ['cancelado'])->sum('total'),
                'cancelados'    => (clone $hoje)->status('cancelado')->count(),
            ],
            'mes' => [
                'total_pedidos' => (clone $mes)->count(),
                'valor_total'   => (clone $mes)->whereNotIn('status', ['cancelado'])->sum('total'),
                'ticket_medio'  => (clone $mes)->whereNotIn('status', ['cancelado'])->avg('total'),
            ],
            'por_status' => Pedido::select('status', DB::raw('count(*) as total'), DB::raw('sum(total) as valor'))
                ->mesAtual()
                ->groupBy('status')
                ->get(),
        ]);
    }
}
