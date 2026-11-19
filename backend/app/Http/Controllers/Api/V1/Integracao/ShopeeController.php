<?php

namespace App\Http\Controllers\Api\V1\Integracao;

use App\Http\Controllers\Controller;
use App\Models\{Integracao, MarketplacePedido, MarketplaceProduto};
use App\Services\Integracao\ShopeeService;
use Illuminate\Http\{Request, JsonResponse};
use Exception;

class ShopeeController extends Controller
{
    // ── GET /integracoes/shopee/conectar ───────────────────────

    public function conectar(Request $request): JsonResponse
    {
        $this->authorize('integracoes.edit');

        $integracao = Integracao::firstOrCreate(
            ['empresa_id' => auth()->user()->empresa_id, 'tipo' => 'shopee'],
            ['nome' => 'Shopee', 'credenciais' => [], 'config' => [], 'status' => 'pendente', 'ativo' => true]
        );

        $redirectUri = $request->get('redirect_uri', config('app.url').'/api/v1/integracoes/shopee/callback');
        $service = new ShopeeService($integracao);

        return response()->json([
            'url'           => $service->urlAutorizacao($redirectUri),
            'integracao_id' => $integracao->id,
        ]);
    }

    // ── GET /integracoes/shopee/callback ───────────────────────

    public function callback(Request $request): JsonResponse
    {
        $request->validate([
            'code'    => 'required|string',
            'shop_id' => 'required|integer',
        ]);

        $integracao = Integracao::where('empresa_id', auth()->user()->empresa_id)
            ->where('tipo', 'shopee')
            ->firstOrFail();

        try {
            (new ShopeeService($integracao))->trocarCodigo($request->get('code'), (int) $request->get('shop_id'));

            return response()->json(['message' => 'Shopee conectada com sucesso.', 'integracao' => $integracao->fresh()]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Falha ao conectar: '.$e->getMessage()], 422);
        }
    }

    // ── GET /integracoes/shopee ─────────────────────────────────

    public function status(): JsonResponse
    {
        $this->authorize('integracoes.view');

        $integracao = Integracao::where('empresa_id', auth()->user()->empresa_id)
            ->where('tipo', 'shopee')
            ->first();

        return response()->json($integracao ?: ['status' => 'nao_conectado']);
    }

    // ── DELETE /integracoes/shopee ──────────────────────────────

    public function desconectar(): JsonResponse
    {
        $this->authorize('integracoes.edit');

        Integracao::where('empresa_id', auth()->user()->empresa_id)
            ->where('tipo', 'shopee')
            ->delete();

        return response()->json(['message' => 'Shopee desconectada.']);
    }

    // ── POST /integracoes/shopee/sincronizar-pedidos ────────────

    public function sincronizarPedidos(Request $request): JsonResponse
    {
        $this->authorize('integracoes.edit');

        try {
            $resultado = (new ShopeeService($this->integracaoAtiva()))->sincronizarPedidos((int) $request->get('limite', 50));
            return response()->json(['message' => 'Sincronização de pedidos concluída.', 'data' => $resultado]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── POST /integracoes/shopee/sincronizar-produtos ───────────

    public function sincronizarProdutos(): JsonResponse
    {
        $this->authorize('integracoes.edit');

        try {
            $resultado = (new ShopeeService($this->integracaoAtiva()))->sincronizarProdutos();
            return response()->json(['message' => 'Sincronização de produtos concluída.', 'data' => $resultado]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── PUT /integracoes/shopee/produtos/{id}/estoque ───────────

    public function atualizarEstoque(Request $request, int $id): JsonResponse
    {
        $this->authorize('integracoes.edit');

        $mp = MarketplaceProduto::whereHas('integracao', function ($q) {
            $q->where('empresa_id', auth()->user()->empresa_id)->where('tipo', 'shopee');
        })->findOrFail($id);

        $dados = $request->validate(['quantidade' => 'required|integer|min:0']);

        try {
            $ok = (new ShopeeService($mp->integracao))->atualizarEstoque($mp->marketplace_id, $dados['quantidade']);
            return response()->json(['message' => $ok ? 'Estoque atualizado na Shopee.' : 'Não foi possível atualizar o estoque.']);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── GET /integracoes/shopee/produtos ────────────────────────

    public function produtos(): JsonResponse
    {
        $this->authorize('integracoes.view');

        return response()->json(
            MarketplaceProduto::where('integracao_id', $this->integracaoAtiva()->id)
                ->with('produto:id,nome,sku')
                ->paginate(20)
        );
    }

    // ── GET /integracoes/shopee/pedidos ─────────────────────────

    public function pedidos(): JsonResponse
    {
        $this->authorize('integracoes.view');

        return response()->json(
            MarketplacePedido::where('integracao_id', $this->integracaoAtiva()->id)
                ->with('pedido:id,numero,status,total')
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }

    // ── POST /webhooks/shopee ────────────────────────────────────
    // Rota pública (sem auth), chamada pela própria Shopee (Push Mechanism).

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $shopId  = $payload['shop_id'] ?? null;

        $integracao = Integracao::where('tipo', 'shopee')
            ->when($shopId, fn ($q) => $q->whereJsonContains('credenciais->shop_id', $shopId))
            ->first();

        if ($integracao) {
            try {
                (new ShopeeService($integracao))->processarWebhook($payload);
            } catch (Exception $e) {
                report($e);
            }
        }

        return response()->json(['received' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────

    private function integracaoAtiva(): Integracao
    {
        return Integracao::where('empresa_id', auth()->user()->empresa_id)
            ->where('tipo', 'shopee')
            ->where('ativo', true)
            ->firstOrFail();
    }
}
