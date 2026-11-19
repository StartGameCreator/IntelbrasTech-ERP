<?php

namespace App\Http\Controllers\Api\V1\Estoque;

use App\Http\Controllers\Controller;
use App\Models\{Produto, Deposito};
use App\Services\Estoque\EstoqueService;
use Illuminate\Http\{Request, JsonResponse};
use Exception;

class EstoqueController extends Controller
{
    public function __construct(private readonly EstoqueService $estoqueService) {}

    // ── GET /estoque ──────────────────────────────────────────
    // Posição atual de estoque (visão consolidada por produto/depósito)

    public function index(Request $request): JsonResponse
    {
        $this->authorize('estoque.view');

        $posicao = $this->estoqueService->posicaoAtual(
            auth()->user()->empresa_id,
            $request->integer('deposito_id') ?: null,
        );

        if ($request->filled('busca')) {
            $busca = mb_strtolower($request->busca);
            $posicao = array_values(array_filter($posicao, function ($item) use ($busca) {
                return str_contains(mb_strtolower($item->nome ?? ''), $busca)
                    || str_contains(mb_strtolower($item->sku ?? ''), $busca)
                    || str_contains(mb_strtolower($item->codigo ?? ''), $busca);
            }));
        }

        return response()->json([
            'total_itens'  => count($posicao),
            'valor_total'  => round(array_sum(array_column($posicao, 'valor_total')), 2),
            'dados'        => $posicao,
        ]);
    }

    // ── GET /estoque/alertas ──────────────────────────────────

    public function alertas(): JsonResponse
    {
        $this->authorize('estoque.view');

        $alertas = $this->estoqueService->alertasEstoqueBaixo(auth()->user()->empresa_id);

        return response()->json(['total' => count($alertas), 'dados' => $alertas]);
    }

    // ── PUT /estoque/ajustar ──────────────────────────────────

    public function ajustar(Request $request): JsonResponse
    {
        $this->authorize('estoque.ajustar');

        $dados = $request->validate([
            'produto_id'      => 'required|exists:produtos,id',
            'deposito_id'     => 'required|exists:depositos,id',
            'nova_quantidade' => 'required|numeric|min:0',
            'novo_custo'      => 'nullable|numeric|min:0',
            'observacao'      => 'nullable|string|max:500',
            'variacao_id'     => 'nullable|exists:produto_variacoes,id',
        ]);

        $produto  = Produto::findOrFail($dados['produto_id']);
        $deposito = Deposito::findOrFail($dados['deposito_id']);

        try {
            $estoque = $this->estoqueService->ajustar(
                $produto,
                $deposito,
                (float) $dados['nova_quantidade'],
                (float) ($dados['novo_custo'] ?? 0),
                $dados['observacao'] ?? '',
                $dados['variacao_id'] ?? null,
            );

            return response()->json(['message' => 'Estoque ajustado com sucesso.', 'estoque' => $estoque]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── POST /estoque/transferencia ───────────────────────────

    public function transferir(Request $request): JsonResponse
    {
        $this->authorize('estoque.transferir');

        $dados = $request->validate([
            'produto_id'         => 'required|exists:produtos,id',
            'deposito_origem_id' => 'required|exists:depositos,id',
            'deposito_destino_id'=> 'required|exists:depositos,id|different:deposito_origem_id',
            'quantidade'         => 'required|numeric|min:0.001',
            'variacao_id'        => 'nullable|exists:produto_variacoes,id',
        ]);

        $produto = Produto::findOrFail($dados['produto_id']);
        $origem  = Deposito::findOrFail($dados['deposito_origem_id']);
        $destino = Deposito::findOrFail($dados['deposito_destino_id']);

        try {
            $resultado = $this->estoqueService->transferir(
                $produto,
                $origem,
                $destino,
                (float) $dados['quantidade'],
                $dados['variacao_id'] ?? null,
            );

            return response()->json(['message' => 'Transferência realizada com sucesso.', 'data' => $resultado]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
