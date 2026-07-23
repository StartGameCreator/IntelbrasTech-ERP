<?php
namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MercadoLivreOAuthService
{
    public function authorizationUrl(string $state): string {
        $c=config('marketplaces.mercado_livre'); $this->check($c);
        return $c['authorize_url'].'?'.http_build_query([
            'response_type'=>'code','client_id'=>$c['client_id'],
            'redirect_uri'=>$c['redirect_uri'],'state'=>$state,
        ]);
    }

    public function exchange(string $code): array {
        $c=config('marketplaces.mercado_livre'); $this->check($c);
        $r=Http::asForm()->acceptJson()->timeout(30)->post($c['token_url'],[
            'grant_type'=>'authorization_code','client_id'=>$c['client_id'],
            'client_secret'=>$c['client_secret'],'code'=>$code,'redirect_uri'=>$c['redirect_uri'],
        ]);
        if(!$r->successful()) throw new RuntimeException($r->json('message') ?: $r->json('error_description') ?: 'Falha no OAuth.');
        return $r->json();
    }

    public function refresh(string $token): array {
        $c=config('marketplaces.mercado_livre'); $this->check($c);
        $r=Http::asForm()->acceptJson()->timeout(30)->post($c['token_url'],[
            'grant_type'=>'refresh_token','client_id'=>$c['client_id'],
            'client_secret'=>$c['client_secret'],'refresh_token'=>$token,
        ]);
        if(!$r->successful()) throw new RuntimeException($r->json('message') ?: 'Falha ao renovar token.');
        return $r->json();
    }

    public function me(string $token): array {
        $url=rtrim(config('marketplaces.mercado_livre.api_url'),'/').'/users/me';
        $r=Http::withToken($token)->acceptJson()->timeout(30)->get($url);
        if(!$r->successful()) throw new RuntimeException($r->json('message') ?: 'Falha ao consultar a conta.');
        return $r->json();
    }

    private function check(array $c): void {
        if(!$c['client_id'] || !$c['client_secret']) throw new RuntimeException('Configure o Client ID e Client Secret no .env.');
    }
}
