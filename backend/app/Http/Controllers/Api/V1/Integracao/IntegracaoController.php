<?php

namespace App\Http\Controllers\Api\V1\Integracao;

use App\Http\Controllers\Controller;
use App\Models\Integracao;
use Illuminate\Http\JsonResponse;

class IntegracaoController extends Controller
{
    // ── GET /integracoes ───────────────────────────────────────
    // Lista as conexões de marketplace/plataformas da empresa (sem expor credenciais).

    public function index(): JsonResponse
    {
        $this->authorize('integracoes.view');

        $integracoes = Integracao::withCount(['marketplaceProdutos', 'marketplacePedidos'])
            ->orderBy('tipo')
            ->get();

        return response()->json($integracoes);
    }

    // ── DELETE /integracoes/{id} ───────────────────────────────
    // Desconecta (não apaga o histórico de pedidos/produtos já sincronizados).

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('integracoes.delete');

        $integracao = Integracao::findOrFail($id);
        $integracao->update(['status' => 'desconectado', 'ativo' => false, 'credenciais' => []]);

        return response()->json(['message' => 'Integração desconectada com sucesso.']);
    }
}
