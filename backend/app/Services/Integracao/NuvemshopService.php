<?php

namespace App\Services\Integracao;

use App\Models\MarketplaceAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NuvemshopService
{
    public function authorizationUrl(string $state): string
    {
        $clientId = (string) config('services.nuvemshop.client_id');
        if ($clientId === '') throw new RuntimeException('NUVEMSHOP_CLIENT_ID não configurado.');
        return 'https://www.nuvemshop.com.br/apps/'.rawurlencode($clientId).'/authorize?state='.rawurlencode($state);
    }

    public function exchangeCode(string $code): array
    {
        $response = Http::asJson()->post('https://www.nuvemshop.com.br/apps/authorize/token', [
            'client_id' => config('services.nuvemshop.client_id'),
            'client_secret' => config('services.nuvemshop.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);
        if (!$response->successful()) throw new RuntimeException('Falha no OAuth Nuvemshop: '.$response->body());
        return $response->json();
    }

    public function store(MarketplaceAccount $account): array { return $this->request($account)->get('/store')->throw()->json(); }
    public function products(MarketplaceAccount $account, int $page = 1): array { return $this->request($account)->get('/products', ['page'=>$page,'per_page'=>50])->throw()->json(); }
    public function orders(MarketplaceAccount $account, int $page = 1): array { return $this->request($account)->get('/orders', ['page'=>$page,'per_page'=>50])->throw()->json(); }
    public function order(MarketplaceAccount $account, string|int $id): array { return $this->request($account)->get('/orders/'.$id)->throw()->json(); }
    public function createWebhook(MarketplaceAccount $account, string $event, string $url): array
    {
        return $this->request($account)->post('/webhooks', ['event'=>$event,'url'=>$url])->throw()->json();
    }

    private function request(MarketplaceAccount $account): PendingRequest
    {
        $storeId = $account->external_store_id ?: data_get($account->configuracoes, 'store_id');
        if (!$storeId || !$account->access_token) throw new RuntimeException('Conta Nuvemshop não conectada.');
        return Http::baseUrl('https://api.nuvemshop.com.br/v1/'.$storeId)
            ->acceptJson()->asJson()
            ->withToken($account->access_token)
            ->withHeaders(['User-Agent' => config('services.nuvemshop.user_agent', 'IntelbrasTech ERP (suporte@intelbrastech.com.br)')])
            ->timeout(30)->retry(2, 500, throw: false);
    }
}
