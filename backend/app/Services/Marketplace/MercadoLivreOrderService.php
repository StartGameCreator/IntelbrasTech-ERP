<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MercadoLivreOrderService
{
    public function search(
        MarketplaceAccount $account,
        ?string $from = null,
        ?string $to = null,
        int $offset = 0,
        int $limit = 50
    ): array {
        if (!$account->external_user_id) {
            throw new RuntimeException('A conta conectada não possui ID externo.');
        }

        $params = [
            'seller' => $account->external_user_id,
            'sort' => 'date_desc',
            'offset' => max(0, $offset),
            'limit' => min(max(1, $limit), 50),
        ];

        if ($from) {
            $params['order.date_created.from'] = $from;
        }

        if ($to) {
            $params['order.date_created.to'] = $to;
        }

        $response = $this->client($account)->get('/orders/search', $params);

        $this->assertSuccess($response);

        return $response->json();
    }

    public function order(MarketplaceAccount $account, string $orderId): array
    {
        $response = $this->client($account)->get("/orders/{$orderId}");
        $this->assertSuccess($response);

        return $response->json();
    }

    public function shipment(MarketplaceAccount $account, string $shippingId): ?array
    {
        if (!$shippingId) {
            return null;
        }

        $response = $this->client($account)->get("/shipments/{$shippingId}");

        if ($response->status() === 404) {
            return null;
        }

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
