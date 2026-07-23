<?php

namespace App\Http\Controllers\Api\V1\Integracao;

use App\Http\Controllers\Controller;
use App\Models\{Integracao, MarketplacePedido, MarketplaceProduto};
use App\Services\Integracao\MercadoLivreService;
use Illuminate\Http\{Request, JsonResponse};
use Exception;

class MercadoLivreController extends Controller
{
    // ── GET /integracoes/mercadolivre/conectar ────────────────
    // Devolve a URL de autorização do Mercado Livre para o front redirecionar o usuário.

    public function conectar(Request $request): JsonResponse
    {
        $this->authorize('integracoes.edit');

        $integracao = Integracao::firstOrCreate(
            ['empresa_id' => auth()->user()->empresa_id, 'tipo' => 'mercadolivre'],
            ['nome' => 'Mercado Livre', 'credenciais' => [], 'config' => [], 'status' => 'pendente', 'ativo' => true]
        );

        $redirectUri = $request->get('redirect_uri', config('app.url').'/api/v1/integracoes/mercadolivre/callback');
        $service = new MercadoLivreService($integracao);

        return response()->json([
            'url'           => $service->urlAutorizacao($redirectUri),
            'integracao_id' => $integracao->id,
        ]);
    }

    // ── GET /integracoes/mercadolivre/callback ────────────────
    // Endpoint de retorno do OAuth do Mercado Livre (troca o "code" pelo access_token).

    public function callback(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $integracao = Integracao::where('empresa_id', auth()->user()->empresa_id)
            ->where('tipo', 'mercadolivre')
            ->firstOrFail();

        $redirectUri = $request->get('redirect_uri', config('app.url').'/api/v1/integracoes/mercadolivre/callback');

        try {
            (new MercadoLivreService($integracao))->trocarCodigo($request->get('code'), $redirectUri);

            return response()->json(['message' => 'Mercado Livre conectado com sucesso.', 'integracao' => $integracao->fresh()]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Falha ao conectar: '.$e->getMessage()], 422);
        }
    }

    // ── GET /integracoes/mercadolivre ──────────────────────────

    public function status(): JsonResponse
    {
        $this->authorize('integracoes.view');

        $integracao = Integracao::where('empresa_id', auth()->user()->empresa_id)
            ->where('tipo', 'mercadolivre')
            ->first();

        return response()->json($integracao ?: ['status' => 'nao_conectado']);
    }

    // ── DELETE /integracoes/mercadolivre ───────────────────────

    public function desconectar(): JsonResponse
    {
        $this->authorize('integracoes.edit');

        Integracao::where('empresa_id', auth()->user()->empresa_id)
            ->where('tipo', 'mercadolivre')
            ->delete();

        return response()->json(['message' => 'Mercado Livre desconectado.']);
    }

    // ── POST /integracoes/mercadolivre/sincronizar-pedidos ─────

    public function sincronizarPedidos(Request $request): JsonResponse
    {
        $this->authorize('integracoes.edit');

        $integracao = $this->integracaoAtiva();

        try {
            $resultado = (new MercadoLivreService($integracao))->sincronizarPedidos((int) $request->get('limite', 50));
            return response()->json(['message' => 'Sincronização de pedidos concluída.', 'data' => $resultado]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── POST /integracoes/mercadolivre/sincronizar-produtos ────

    public function sincronizarProdutos(): JsonResponse
    {
        $this->authorize('integracoes.edit');

        $integracao = $this->integracaoAtiva();

        try {
            $resultado = (new MercadoLivreService($integracao))->sincronizarProdutos();
            return response()->json(['message' => 'Sincronização de produtos concluída.', 'data' => $resultado]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── PUT /integracoes/mercadolivre/produtos/{id}/estoque ────

    public function atualizarEstoque(Request $request, int $id): JsonResponse
    {
        $this->authorize('integracoes.edit');

        $mp = MarketplaceProduto::whereHas('integracao', function ($q) {
            $q->where('empresa_id', auth()->user()->empresa_id)->where('tipo', 'mercadolivre');
        })->findOrFail($id);

        $dados = $request->validate(['quantidade' => 'required|integer|min:0']);

        try {
            $ok = (new MercadoLivreService($mp->integracao))->atualizarEstoque($mp->marketplace_id, $dados['quantidade']);
            return response()->json(['message' => $ok ? 'Estoque atualizado no Mercado Livre.' : 'Não foi possível atualizar o estoque.']);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── GET /integracoes/mercadolivre/produtos ─────────────────

    public function produtos(): JsonResponse
    {
        $this->authorize('integracoes.view');

        $integracao = $this->integracaoAtiva();

        return response()->json(
            MarketplaceProduto::where('integracao_id', $integracao->id)
                ->with('produto:id,nome,sku')
                ->paginate(20)
        );
    }

    // ── GET /integracoes/mercadolivre/pedidos ──────────────────

    public function pedidos(): JsonResponse
    {
        $this->authorize('integracoes.view');

        $integracao = $this->integracaoAtiva();

        return response()->json(
            MarketplacePedido::where('integracao_id', $integracao->id)
                ->with('pedido:id,numero,status,total')
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }

    // ── POST /webhooks/mercadolivre ─────────────────────────────
    // Rota pública (sem auth), chamada pelo próprio Mercado Livre.

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        // O webhook do ML manda "user_id" (dono da conta vendedora); usamos isso
        // para achar a integração correta sem depender de sessão/empresa autenticada.
        $userId = $payload['user_id'] ?? null;

        $integracao = Integracao::where('tipo', 'mercadolivre')
            ->when($userId, fn ($q) => $q->whereJsonContains('credenciais->user_id', $userId))
            ->first();

        if ($integracao) {
            try {
                (new MercadoLivreService($integracao))->processarWebhook($payload);
            } catch (Exception $e) {
                // Nunca retorna erro para o ML (ele desativa o webhook após falhas repetidas);
                // apenas registra e responde 200 mesmo assim.
                report($e);
            }
        }

        return response()->json(['received' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────

    private function integracaoAtiva(): Integracao
    {
        return Integracao::where('empresa_id', auth()->user()->empresa_id)
            ->where('tipo', 'mercadolivre')
            ->where('ativo', true)
            ->firstOrFail();
    }
}
