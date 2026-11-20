<?php

namespace App\Http\Controllers\Api\V1\Pdv;

use App\Http\Controllers\Controller;
use App\Models\{
    Caixa, CaixaMovimentacao, Cliente, Deposito, FormaPagamento,
    Pedido, PedidoItem, PdvPagamento, PdvVenda, Produto
};
use App\Services\Estoque\EstoqueService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PdvController extends Controller
{
    public function __construct(private readonly EstoqueService $estoque) {}

    public function bootstrap(Request $request): JsonResponse
    {
        $this->authorize('pedidos.view');

        $empresaId = auth()->user()->empresa_id;
        $caixa = $this->caixaAberto();

        return response()->json([
            'empresa' => [
                'uf' => strtoupper((string) auth()->user()->empresa?->estado),
            ],
            'caixa' => $caixa?->load('deposito:id,nome'),
            'depositos' => Deposito::where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->orderByDesc('principal')
                ->orderBy('nome')
                ->get(['id','nome','principal']),
            'formas_pagamento' => FormaPagamento::where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->orderBy('nome')
                ->get(['id','nome','tipo']),
            'resumo' => $caixa ? $this->resumoCaixa($caixa) : null,
        ]);
    }

    public function buscarProdutos(Request $request): JsonResponse
    {
        $this->authorize('produtos.view');

        $data = $request->validate([
            'q' => 'required|string|min:1|max:200',
            'deposito_id' => 'nullable|exists:depositos,id',
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ]);

        $empresaId = auth()->user()->empresa_id;
        $termo = trim($data['q']);
        $perPage = $data['per_page'] ?? 30;

        $produtos = Produto::query()
            ->with([
                'marca:id,nome',
                'unidade:id,sigla',
                'imagens' => fn ($q) => $q->where('principal', true)->limit(1),
                'estoque' => fn ($q) => $q
                    ->when($data['deposito_id'] ?? null, fn ($q, $id) => $q->where('deposito_id', $id))
                    ->select('id','produto_id','deposito_id','quantidade_disponivel'),
            ])
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where(function ($q) use ($termo) {
                $q->where('nome', 'like', "%{$termo}%")
                    ->orWhere('codigo', 'like', "%{$termo}%")
                    ->orWhere('sku', 'like', "%{$termo}%")
                    ->orWhere('codigo_barras', 'like', "%{$termo}%")
                    ->orWhere('ean', 'like', "%{$termo}%");
            })
            ->orderBy('nome')
            ->paginate($perPage);

        $produtos->setCollection($produtos->getCollection()->map(fn (Produto $produto) => [
                'id' => $produto->id,
                'codigo' => $produto->codigo,
                'sku' => $produto->sku,
                'ean' => $produto->ean,
                'nome' => $produto->nome,
                'marca' => $produto->marca?->nome,
                'unidade' => $produto->unidade?->sigla ?: 'UN',
                'preco' => $produto->modo_preco_venda === 'markup'
                    ? round((float) $produto->preco_custo * (1 + ((float) $produto->markup_venda / 100)), 2)
                    : (float) $produto->preco_venda,
                'custo_compra' => (float) $produto->preco_custo,
                'modo_preco' => $produto->modo_preco_venda ?: 'manual',
                'markup' => (float) $produto->markup_venda,
                'preco_minimo' => (float) $produto->preco_venda_minimo,
                'controla_estoque' => (bool) $produto->controla_estoque,
                'estoque_disponivel' => (float) $produto->estoque->sum('quantidade_disponivel'),
                'imagem' => $produto->imagens->first()?->url,
            ]));

        return response()->json($produtos);
    }

    public function buscarClientes(Request $request): JsonResponse
    {
        $this->authorize('clientes.view');

        $data = $request->validate(['q' => 'required|string|min:1|max:200']);

        $clientes = Cliente::where('empresa_id', auth()->user()->empresa_id)
            ->where(function ($q) use ($data) {
                $termo = $data['q'];
                $q->where('razao_social', 'like', "%{$termo}%")
                    ->orWhere('nome_fantasia', 'like', "%{$termo}%")
                    ->orWhere('cpf_cnpj', 'like', "%{$termo}%")
                    ->orWhere('telefone', 'like', "%{$termo}%");
            })
            ->limit(15)
            ->get(['id','razao_social','nome_fantasia','cpf_cnpj','telefone','cidade','estado']);

        return response()->json(['data' => $clientes]);
    }

    public function abrirCaixa(Request $request): JsonResponse
    {
        $this->authorize('pedidos.create');

        if ($this->caixaAberto()) {
            return response()->json(['message' => 'Já existe um caixa aberto para este usuário.'], 422);
        }

        $data = $request->validate([
            'deposito_id' => 'nullable|exists:depositos,id',
            'valor_abertura' => 'required|numeric|min:0',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        $empresaId = auth()->user()->empresa_id;
        $ultimo = Caixa::where('empresa_id', $empresaId)->max('id') ?? 0;

        $caixa = Caixa::create([
            'empresa_id' => $empresaId,
            'usuario_id' => auth()->id(),
            'deposito_id' => $data['deposito_id'] ?? null,
            'numero' => str_pad((string) ($ultimo + 1), 6, '0', STR_PAD_LEFT),
            'status' => 'aberto',
            'valor_abertura' => $data['valor_abertura'],
            'aberto_em' => now(),
            'observacoes_abertura' => $data['observacoes'] ?? null,
        ]);

        CaixaMovimentacao::create([
            'caixa_id' => $caixa->id,
            'usuario_id' => auth()->id(),
            'tipo' => 'abertura',
            'valor' => $data['valor_abertura'],
            'forma_pagamento' => 'dinheiro',
            'descricao' => 'Abertura de caixa',
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Caixa aberto com sucesso.',
            'caixa' => $caixa->load('deposito:id,nome'),
        ], 201);
    }

    public function movimentar(Request $request): JsonResponse
    {
        $this->authorize('pedidos.edit');
        $caixa = $this->caixaAbertoOrFail();

        $data = $request->validate([
            'tipo' => 'required|in:sangria,suprimento',
            'valor' => 'required|numeric|min:0.01',
            'descricao' => 'required|string|max:250',
        ]);

        $movimento = CaixaMovimentacao::create([
            'caixa_id' => $caixa->id,
            'usuario_id' => auth()->id(),
            'tipo' => $data['tipo'],
            'valor' => $data['valor'],
            'forma_pagamento' => 'dinheiro',
            'descricao' => $data['descricao'],
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => $data['tipo'] === 'sangria' ? 'Sangria registrada.' : 'Suprimento registrado.',
            'movimentacao' => $movimento,
            'resumo' => $this->resumoCaixa($caixa),
        ]);
    }

    public function concluirVenda(Request $request): JsonResponse
    {
        $this->authorize('pedidos.create');
        $caixa = $this->caixaAbertoOrFail();

        $data = $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'deposito_id' => 'required|exists:depositos,id',
            'desconto' => 'nullable|numeric|min:0',
            'acrescimo' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string|max:2000',
            'itens' => 'required|array|min:1|max:300',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.quantidade' => 'required|numeric|min:0.001',
            'itens.*.preco_unitario' => 'required|numeric|min:0',
            'itens.*.desconto' => 'nullable|numeric|min:0',
            'pagamentos' => 'required|array|min:1|max:20',
            'pagamentos.*.forma_pagamento_id' => 'nullable|exists:formas_pagamento,id',
            'pagamentos.*.tipo' => 'required|in:dinheiro,pix,cartao_debito,cartao_credito,transferencia,crediario,vale,outro',
            'pagamentos.*.valor' => 'required|numeric|min:0.01',
            'pagamentos.*.parcelas' => 'nullable|integer|min:1|max:24',
        ]);

        $empresaId = auth()->user()->empresa_id;
        $subtotal = 0.0;

        foreach ($data['itens'] as $item) {
            $subtotal += max(
                0,
                ((float) $item['preco_unitario'] * (float) $item['quantidade'])
                - (float) ($item['desconto'] ?? 0)
            );
        }

        $desconto = (float) ($data['desconto'] ?? 0);
        $acrescimo = (float) ($data['acrescimo'] ?? 0);
        $total = round(max(0, $subtotal - $desconto + $acrescimo), 2);
        $pago = round(collect($data['pagamentos'])->sum(fn ($p) => (float) $p['valor']), 2);

        if ($pago < $total) {
            return response()->json([
                'message' => 'O valor recebido é menor que o total da venda.',
                'total' => $total,
                'recebido' => $pago,
            ], 422);
        }

        $troco = round($pago - $total, 2);

        $resultado = DB::transaction(function () use (
            $data, $empresaId, $caixa, $subtotal, $desconto, $acrescimo, $total, $troco
        ) {
            $pedido = Pedido::create([
                'empresa_id' => $empresaId,
                'numero' => '',
                'cliente_id' => $data['cliente_id'] ?? null,
                'vendedor_id' => auth()->id(),
                'canal' => 'pdv',
                'subtotal' => $subtotal,
                'desconto_valor' => $desconto,
                'acrescimo_valor' => $acrescimo,
                'frete' => 0,
                'total' => $total,
                'status' => Pedido::STATUS_ENTREGUE,
                'status_pagamento' => Pedido::STATUS_PAG_PAGO,
                'status_entrega' => 'entregue',
                'data_pedido' => now(),
                'data_confirmacao' => now(),
                'data_faturamento' => now(),
                'data_entrega' => now(),
                'observacoes' => $data['observacoes'] ?? null,
            ]);

            $pedido->update(['numero' => $pedido->gerarNumero()]);
            $deposito = Deposito::findOrFail($data['deposito_id']);

            foreach ($data['itens'] as $item) {
                $produto = Produto::findOrFail($item['produto_id']);
                $quantidade = (float) $item['quantidade'];
                $preco = (float) $item['preco_unitario'];
                $descontoItem = (float) ($item['desconto'] ?? 0);
                $totalItem = round(max(0, ($preco * $quantidade) - $descontoItem), 2);

                PedidoItem::create([
                    'pedido_id' => $pedido->id,
                    'produto_id' => $produto->id,
                    'deposito_id' => $deposito->id,
                    'codigo' => $produto->codigo ?: $produto->sku,
                    'descricao' => mb_substr($produto->nome, 0, 300),
                    'ncm' => $produto->ncm,
                    'cfop' => $produto->cfop ?: '5102',
                    'unidade' => $produto->unidade?->sigla ?: 'UN',
                    'quantidade' => $quantidade,
                    'preco_unitario' => $preco,
                    'desconto_valor' => $descontoItem,
                    'total' => $totalItem,
                    'icms_cst' => $produto->icms_cst,
                    'icms_aliquota' => $produto->icms_aliquota,
                    'pis_cst' => $produto->pis_cst,
                    'pis_aliquota' => $produto->pis_aliquota,
                    'cofins_cst' => $produto->cofins_cst,
                    'cofins_aliquota' => $produto->cofins_aliquota,
                ]);

                if ($produto->controla_estoque) {
                    $this->estoque->saida(
                        $produto,
                        $deposito,
                        $quantidade,
                        'venda_pdv',
                        $pedido->id,
                        'pedido',
                        $pedido->numero
                    );
                }
            }

            $ultimo = PdvVenda::where('empresa_id', $empresaId)->max('id') ?? 0;

            $venda = PdvVenda::create([
                'empresa_id' => $empresaId,
                'caixa_id' => $caixa->id,
                'pedido_id' => $pedido->id,
                'cliente_id' => $data['cliente_id'] ?? null,
                'usuario_id' => auth()->id(),
                'numero' => str_pad((string) ($ultimo + 1), 6, '0', STR_PAD_LEFT),
                'subtotal' => $subtotal,
                'desconto' => $desconto,
                'acrescimo' => $acrescimo,
                'total' => $total,
                'troco' => $troco,
                'status' => 'concluida',
                'comprovante_codigo' => strtoupper(Str::random(12)),
                'nfce_status' => 'nao_emitida',
                'observacoes' => $data['observacoes'] ?? null,
                'concluida_em' => now(),
            ]);

            foreach ($data['pagamentos'] as $pagamento) {
                PdvPagamento::create([
                    'pdv_venda_id' => $venda->id,
                    'forma_pagamento_id' => $pagamento['forma_pagamento_id'] ?? null,
                    'tipo' => $pagamento['tipo'],
                    'valor' => $pagamento['valor'],
                    'parcelas' => $pagamento['parcelas'] ?? 1,
                ]);

                CaixaMovimentacao::create([
                    'caixa_id' => $caixa->id,
                    'usuario_id' => auth()->id(),
                    'tipo' => 'venda',
                    'valor' => $pagamento['valor'],
                    'forma_pagamento' => $pagamento['tipo'],
                    'descricao' => "Venda PDV {$venda->numero}",
                    'pedido_id' => $pedido->id,
                    'metadados' => ['pdv_venda_id' => $venda->id],
                    'created_at' => now(),
                ]);
            }

            return [
                'venda' => $venda->load('pagamentos'),
                'pedido' => $pedido->load('itens'),
            ];
        });

        return response()->json([
            'message' => 'Venda concluída com sucesso.',
            'troco' => $troco,
            ...$resultado,
            'resumo_caixa' => $this->resumoCaixa($caixa),
        ], 201);
    }

    public function vendas(Request $request): JsonResponse
    {
        $this->authorize('pedidos.view');

        return response()->json(
            PdvVenda::with([
                'cliente:id,razao_social,nome_fantasia,cpf_cnpj',
                'caixa:id,numero',
                'pagamentos',
                'pedido:id,numero',
                'notaFiscal:id,tipo,modelo,numero,serie,status,chave_acesso,danfe_url',
            ])
                ->where('empresa_id', auth()->user()->empresa_id)
                ->when($request->filled('busca'), function ($query) use ($request) {
                    $termo = trim((string) $request->busca);
                    $query->where(function ($query) use ($termo) {
                        $query->where('numero', 'like', "%{$termo}%")
                            ->orWhere('comprovante_codigo', 'like', "%{$termo}%")
                            ->orWhereHas('cliente', function ($query) use ($termo) {
                                $query->where('razao_social', 'like', "%{$termo}%")
                                    ->orWhere('nome_fantasia', 'like', "%{$termo}%")
                                    ->orWhere('cpf_cnpj', 'like', "%{$termo}%");
                            });
                    });
                })
                ->when($request->filled('data_inicio'), fn ($q) => $q->whereDate('concluida_em', '>=', $request->data_inicio))
                ->when($request->filled('data_fim'), fn ($q) => $q->whereDate('concluida_em', '<=', $request->data_fim))
                ->latest('concluida_em')
                ->paginate(min((int) $request->get('per_page', 20), 100))
        );
    }

    public function comprovante(int $id): JsonResponse
    {
        $this->authorize('pedidos.view');

        $venda = PdvVenda::with([
            'cliente',
            'caixa.deposito',
            'pagamentos',
            'pedido.itens',
        ])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);

        $empresa = auth()->user()->empresa;

        return response()->json([
            'data' => [
                'codigo' => $venda->comprovante_codigo,
                'numero' => $venda->numero,
                'data' => optional($venda->concluida_em)->format('d/m/Y H:i:s'),
                'empresa' => [
                    'razao_social' => $empresa?->razao_social,
                    'nome_fantasia' => $empresa?->nome_fantasia,
                    'cnpj' => $empresa?->cnpj,
                    'telefone' => $empresa?->telefone,
                ],
                'cliente' => [
                    'nome' => $venda->cliente?->nome_fantasia
                        ?: $venda->cliente?->razao_social
                        ?: 'Consumidor final',
                    'documento' => $venda->cliente?->cpf_cnpj,
                ],
                'itens' => $venda->pedido?->itens?->map(fn ($item) => [
                    'codigo' => $item->codigo,
                    'descricao' => $item->descricao,
                    'quantidade' => (float) $item->quantidade,
                    'unidade' => $item->unidade,
                    'preco_unitario' => (float) $item->preco_unitario,
                    'total' => (float) $item->total,
                ])->values() ?? [],
                'subtotal' => (float) $venda->subtotal,
                'desconto' => (float) $venda->desconto,
                'acrescimo' => (float) $venda->acrescimo,
                'total' => (float) $venda->total,
                'troco' => (float) $venda->troco,
                'pagamentos' => $venda->pagamentos->map(fn ($p) => [
                    'tipo' => $p->tipo,
                    'valor' => (float) $p->valor,
                    'parcelas' => (int) $p->parcelas,
                ])->values(),
                'nfce_status' => $venda->nfce_status,
            ],
        ]);
    }

    public function prepararNfce(int $id): JsonResponse
    {
        $this->authorize('notas_fiscais.create');

        $venda = PdvVenda::where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);

        $venda->update(['nfce_status' => 'preparada']);

        return response()->json([
            'message' => 'Venda preparada para emissão de NFC-e.',
            'venda' => $venda->fresh(),
        ]);
    }

    public function fecharCaixa(Request $request): JsonResponse
    {
        $this->authorize('pedidos.edit');
        $caixa = $this->caixaAbertoOrFail();

        $data = $request->validate([
            'valor_informado' => 'required|numeric|min:0',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        $resumo = $this->resumoCaixa($caixa);
        $calculado = (float) $resumo['saldo_dinheiro_calculado'];
        $informado = (float) $data['valor_informado'];

        $caixa->update([
            'status' => 'fechado',
            'valor_fechamento_informado' => $informado,
            'valor_fechamento_calculado' => $calculado,
            'diferenca' => round($informado - $calculado, 2),
            'fechado_em' => now(),
            'observacoes_fechamento' => $data['observacoes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Caixa fechado com sucesso.',
            'caixa' => $caixa->fresh(),
            'resumo' => $resumo,
        ]);
    }

    private function caixaAberto(): ?Caixa
    {
        return Caixa::where('empresa_id', auth()->user()->empresa_id)
            ->where('usuario_id', auth()->id())
            ->where('status', 'aberto')
            ->latest('id')
            ->first();
    }

    private function caixaAbertoOrFail(): Caixa
    {
        $caixa = $this->caixaAberto();

        abort_unless($caixa, 422, 'Abra o caixa antes de realizar esta operação.');

        return $caixa;
    }

    private function resumoCaixa(Caixa $caixa): array
    {
        $movimentos = CaixaMovimentacao::where('caixa_id', $caixa->id)->get();
        $vendas = $movimentos->where('tipo', 'venda');

        $porForma = $vendas
            ->groupBy(fn ($m) => $m->forma_pagamento ?: 'outro')
            ->map(fn ($grupo) => round((float) $grupo->sum('valor'), 2));

        $abertura = (float) $caixa->valor_abertura;
        $suprimentos = (float) $movimentos->where('tipo', 'suprimento')->sum('valor');
        $sangrias = (float) $movimentos->where('tipo', 'sangria')->sum('valor');
        $dinheiro = (float) ($porForma['dinheiro'] ?? 0);

        return [
            'valor_abertura' => $abertura,
            'vendas_total' => round((float) $vendas->sum('valor'), 2),
            'suprimentos' => round($suprimentos, 2),
            'sangrias' => round($sangrias, 2),
            'por_forma' => $porForma,
            'saldo_dinheiro_calculado' => round($abertura + $suprimentos + $dinheiro - $sangrias, 2),
            'quantidade_vendas' => $movimentos->where('tipo', 'venda')->pluck('pedido_id')->filter()->unique()->count(),
        ];
    }
}
