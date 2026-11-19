<?php

namespace App\Http\Controllers\Api\V1\Comercial;

use App\Http\Controllers\Controller;
use App\Models\{Recibo, Pedido};
use App\Services\Vendas\ReciboPdfService;
use Illuminate\Http\{Request, JsonResponse};
use Exception;

class ReciboController extends Controller
{
    public function __construct(private readonly ReciboPdfService $pdfService) {}

    // ── GET /recibos ────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('recibos.view');

        $q = Recibo::with('pedido:id,numero')
            ->when($request->busca, function ($q, $v) {
                $q->where(function ($qq) use ($v) {
                    $qq->whereLike('numero', "%{$v}%")
                       ->orWhereLike('cliente_nome', "%{$v}%")
                       ->orWhereLike('entregador_nome', "%{$v}%");
                });
            })
            ->when($request->com_garantia_vigente, fn ($q) => $q->where('data_garantia_vence', '>=', today()))
            ->orderByDesc('data_emissao');

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($q->paginate($perPage));
    }

    // ── POST /recibos ───────────────────────────────────────────
    // Gera um recibo (comprovante não fiscal) a partir de um pedido existente,
    // ou de forma avulsa informando os itens diretamente.

    public function store(Request $request): JsonResponse
    {
        $this->authorize('recibos.create');

        $dados = $request->validate([
            'pedido_id'            => 'nullable|exists:pedidos,id',
            'cliente_nome'         => 'nullable|string|max:200',
            'cliente_documento'    => 'nullable|string|max:18',
            'cliente_telefone'     => 'nullable|string|max:20',
            'itens'                => 'required_without:pedido_id|nullable|array|min:1',
            'itens.*.descricao'    => 'required_with:itens|string|max:300',
            'itens.*.quantidade'   => 'required_with:itens|numeric|min:0.001',
            'itens.*.preco_unitario' => 'required_with:itens|numeric|min:0',
            'valor_total'          => 'required_without:pedido_id|nullable|numeric|min:0',
            'forma_pagamento'      => 'nullable|string|max:100',
            'observacoes'          => 'nullable|string',
            'prazo_garantia_dias'  => 'nullable|integer|min:0',

            'entregador_tipo'         => 'nullable|in:proprio,uber,app_parceiro,outro',
            'entregador_app_nome'     => 'nullable|string|max:100',
            'entregador_nome'         => 'nullable|string|max:150',
            'entregador_documento'    => 'nullable|string|max:18',
            'entregador_telefone'     => 'nullable|string|max:20',
            'veiculo_placa'           => 'nullable|string|max:10',
            'veiculo_modelo'          => 'nullable|string|max:100',
        ]);

        $empresaId = auth()->user()->empresa_id;
        $prazoGarantia = $dados['prazo_garantia_dias'] ?? 90;

        if (! empty($dados['pedido_id'])) {
            $pedido = Pedido::with(['itens', 'cliente'])->findOrFail($dados['pedido_id']);

            $itensSnapshot = $pedido->itens->map(fn ($item) => [
                'descricao'      => $item->descricao ?? $item->produto?->nome,
                'quantidade'     => (float) $item->quantidade,
                'preco_unitario' => (float) $item->preco_unitario,
                'total'          => (float) $item->total,
            ])->toArray();

            $dados['cliente_nome']      = $dados['cliente_nome'] ?? $pedido->cliente?->razao_social ?? $pedido->cliente?->nome_fantasia;
            $dados['cliente_documento'] = $dados['cliente_documento'] ?? $pedido->cliente?->cpf_cnpj;
            $dados['cliente_telefone']  = $dados['cliente_telefone'] ?? $pedido->cliente?->celular ?? $pedido->cliente?->telefone;
            $valorTotal = (float) $pedido->total;
        } else {
            $itensSnapshot = collect($dados['itens'])->map(fn ($item) => [
                'descricao'      => $item['descricao'],
                'quantidade'     => (float) $item['quantidade'],
                'preco_unitario' => (float) $item['preco_unitario'],
                'total'          => round($item['quantidade'] * $item['preco_unitario'], 2),
            ])->toArray();
            $valorTotal = $dados['valor_total'] ?? collect($itensSnapshot)->sum('total');
        }

        $recibo = Recibo::create([
            'empresa_id'          => $empresaId,
            'pedido_id'           => $dados['pedido_id'] ?? null,
            'usuario_id'          => auth()->id(),
            'numero'              => 'REC-' . now()->format('Ymd') . '-' . str_pad((string) (Recibo::where('empresa_id', $empresaId)->count() + 1), 4, '0', STR_PAD_LEFT),
            'cliente_nome'        => $dados['cliente_nome'] ?? null,
            'cliente_documento'   => $dados['cliente_documento'] ?? null,
            'cliente_telefone'    => $dados['cliente_telefone'] ?? null,
            'itens_snapshot'      => $itensSnapshot,
            'valor_total'         => $valorTotal,
            'forma_pagamento'     => $dados['forma_pagamento'] ?? null,
            'observacoes'         => $dados['observacoes'] ?? null,
            'prazo_garantia_dias' => $prazoGarantia,
            'data_garantia_vence' => $prazoGarantia > 0 ? now()->addDays($prazoGarantia) : null,
            'entregador_tipo'         => $dados['entregador_tipo'] ?? null,
            'entregador_app_nome'     => $dados['entregador_app_nome'] ?? null,
            'entregador_nome'         => $dados['entregador_nome'] ?? null,
            'entregador_documento'    => $dados['entregador_documento'] ?? null,
            'entregador_telefone'     => $dados['entregador_telefone'] ?? null,
            'veiculo_placa'           => $dados['veiculo_placa'] ?? null,
            'veiculo_modelo'          => $dados['veiculo_modelo'] ?? null,
            'data_emissao'        => now(),
        ]);

        return response()->json([
            'message' => 'Recibo emitido com sucesso. Este documento não tem validade fiscal.',
            'recibo'  => $recibo,
        ], 201);
    }

    // ── GET /recibos/{id} ────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $this->authorize('recibos.view');

        $recibo = Recibo::with(['pedido:id,numero', 'usuario:id,nome'])->findOrFail($id);

        return response()->json($recibo);
    }

    // ── GET /recibos/{id}/pdf ────────────────────────────────────

    public function pdf(int $id): JsonResponse
    {
        $this->authorize('recibos.view');

        $recibo = Recibo::findOrFail($id);

        try {
            $url = $this->pdfService->gerar($recibo);
            return response()->json(['url' => $url]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Não foi possível gerar o PDF: '.$e->getMessage()], 422);
        }
    }
}
