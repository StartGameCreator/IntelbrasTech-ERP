<?php

namespace App\Http\Controllers\Api\V1\Integracao;

use App\Http\Controllers\Controller;
use App\Models\Integracao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceConfigController extends Controller
{
    private array $marketplaces = [
        'mercadolivre' => 'Mercado Livre',
        'shopee' => 'Shopee',
        'magalu' => 'Magalu',
        'amazon' => 'Amazon',
        'olist' => 'Olist',
        'aliexpress' => 'AliExpress',
        'americanas' => 'Americanas',
        'casas_bahia' => 'Casas Bahia',
        'carrefour' => 'Carrefour',
        'enjoei' => 'Enjoei',
        'petlove' => 'PetLove',
        'tiktok_shop' => 'TikTok Shop',
    ];

    public function catalogo(): JsonResponse
    {
        $this->authorize('integracoes.view');

        return response()->json([
            'data' => collect($this->marketplaces)->map(fn ($nome, $tipo) => [
                'tipo' => $tipo,
                'nome' => $nome,
                'campos' => $this->camposPadraoMarketplace($tipo),
            ])->values(),
        ]);
    }

    public function salvar(Request $request, string $tipo): JsonResponse
    {
        $this->authorize('integracoes.edit');
        abort_unless(isset($this->marketplaces[$tipo]), 404, 'Marketplace não cadastrado no catálogo.');

        $data = $request->validate([
            'nome' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['ativo', 'pendente', 'inativo', 'desconectado'])],
            'credenciais' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
        ]);

        $integracao = Integracao::updateOrCreate(
            ['empresa_id' => auth()->user()->empresa_id, 'tipo' => $tipo],
            [
                'nome' => $data['nome'] ?? $this->marketplaces[$tipo],
                'credenciais' => $data['credenciais'] ?? [],
                'config' => $data['config'] ?? [],
                'status' => $data['status'] ?? 'pendente',
                'ativo' => ($data['status'] ?? 'pendente') !== 'desconectado',
            ]
        );

        return response()->json([
            'message' => 'Configuração do marketplace salva com sucesso.',
            'data' => $integracao->makeVisible('credenciais'),
        ]);
    }

    public function testar(string $tipo): JsonResponse
    {
        $this->authorize('integracoes.view');
        abort_unless(isset($this->marketplaces[$tipo]), 404, 'Marketplace não cadastrado no catálogo.');

        $integracao = Integracao::where('tipo', $tipo)->first();

        return response()->json([
            'ok' => (bool) $integracao,
            'tipo' => $tipo,
            'nome' => $this->marketplaces[$tipo],
            'status' => $integracao?->status ?? 'nao_configurado',
            'message' => $integracao
                ? 'Rota encontrada e configuração localizada.'
                : 'Rota encontrada, mas ainda sem configuração salva.',
        ]);
    }

    private function camposPadraoMarketplace(string $tipo): array
    {
        return match ($tipo) {
            'amazon' => ['seller_id', 'client_id', 'client_secret', 'refresh_token', 'marketplace_id'],
            'tiktok_shop' => ['app_key', 'app_secret', 'access_token', 'refresh_token', 'shop_id'],
            'magalu', 'olist', 'americanas', 'casas_bahia', 'carrefour', 'enjoei', 'petlove', 'aliexpress' => ['client_id', 'client_secret', 'access_token', 'refresh_token', 'seller_id'],
            default => ['client_id', 'client_secret', 'access_token', 'refresh_token'],
        };
    }
}
