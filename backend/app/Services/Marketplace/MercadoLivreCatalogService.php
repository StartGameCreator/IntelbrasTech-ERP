<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MercadoLivreCatalogService
{
    public function sellerItems(MarketplaceAccount $account, int $offset = 0, int $limit = 50): array
    {
        $userId = $account->external_user_id;

        if (!$userId) {
            throw new RuntimeException('A conta conectada não possui ID externo.');
        }

        $response = $this->client($account)->get("/users/{$userId}/items/search", [
            'offset' => $offset,
            'limit' => min($limit, 50),
        ]);

        $this->assertSuccess($response);

        return $response->json();
    }

    public function items(MarketplaceAccount $account, array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $response = $this->client($account)->get('/items', [
            'ids' => implode(',', array_slice($ids, 0, 20)),
            'attributes' => 'id,title,price,available_quantity,status,permalink,thumbnail,category_id,seller_custom_field',
        ]);

        $this->assertSuccess($response);

        return collect($response->json())
            ->map(fn ($row) => $row['body'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    public function updatePriceStock(
        MarketplaceAccount $account,
        string $externalId,
        ?float $price,
        ?float $stock
    ): array {
        $payload = [];

        if ($price !== null) {
            $payload['price'] = round($price, 2);
        }

        if ($stock !== null) {
            $payload['available_quantity'] = max(0, (int) floor($stock));
        }

        if (!$payload) {
            throw new RuntimeException('Nenhum dado foi informado para sincronização.');
        }

        $response = $this->client($account)->put("/items/{$externalId}", $payload);

        $this->assertSuccess($response);

        return $response->json();
    }

    private function client(MarketplaceAccount $account)
    {
        if (!$account->access_token) {
            throw new RuntimeException('Conta do Mercado Livre sem token de acesso.');
        }

        return Http::withToken(decrypt($account->access_token))
            ->acceptJson()
            ->baseUrl(rtrim(config('marketplaces.mercado_livre.api_url'), '/'))
            ->timeout(45);
    }

    private function assertSuccess($response): void
    {
        if (!$response->successful()) {
            throw new RuntimeException(
                $response->json('message')
                ?: $response->json('error')
                ?: "Mercado Livre retornou HTTP {$response->status()}."
            );
        }
    }
}
