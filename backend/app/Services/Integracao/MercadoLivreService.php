<?php

namespace App\Services\Integracao;

use App\Models\{Integracao, MarketplacePedido, MarketplaceProduto, Pedido, Cliente, Produto};
use Illuminate\Support\Facades\{Http, Log, DB};

class MercadoLivreService
{
    private const BASE_URL   = 'https://api.mercadolibre.com';
    private const AUTH_URL   = 'https://auth.mercadolivre.com.br';
    private const APP_ID     = '';   // Configurar via .env
    private const SECRET_KEY = '';

    public function __construct(private readonly Integracao $integracao) {}

    // ── Auth OAuth2 ───────────────────────────────────────────

    public function urlAutorizacao(string $redirectUri): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => config('integracoes.mercadolivre.app_id'),
            'redirect_uri'  => $redirectUri,
        ]);
        return self::AUTH_URL . "/authorization?{$params}";
    }

    public function trocarCodigo(string $code, string $redirectUri): array
    {
        $response = Http::post(self::BASE_URL . '/oauth/token', [
            'grant_type'    => 'authorization_code',
            'client_id'     => config('integracoes.mercadolivre.app_id'),
            'client_secret' => config('integracoes.mercadolivre.secret'),
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
        ]);

        if (! $response->ok()) {
            throw new \Exception('Falha na autenticação ML: ' . $response->body());
        }

        $dados = $response->json();
        $this->integracao->update([
            'credenciais' => [
                'access_token'  => $dados['access_token'],
                'refresh_token' => $dados['refresh_token'],
                'user_id'       => $dados['user_id'],
                'expires_in'    => $dados['expires_in'],
                'token_type'    => $dados['token_type'],
                'token_expira'  => now()->addSeconds($dados['expires_in'])->toDateTimeString(),
            ],
            'status' => 'ativo',
        ]);

        return $dados;
    }

    public function refreshToken(): void
    {
        $creds = $this->integracao->credenciais;

        $response = Http::post(self::BASE_URL . '/oauth/token', [
            'grant_type'    => 'refresh_token',
            'client_id'     => config('integracoes.mercadolivre.app_id'),
            'client_secret' => config('integracoes.mercadolivre.secret'),
            'refresh_token' => $creds['refresh_token'],
        ]);

        if (! $response->ok()) {
            Log::error('ML: falha ao renovar token', ['body' => $response->body()]);
            return;
        }

        $dados = $response->json();
        $creds['access_token']  = $dados['access_token'];
        $creds['refresh_token'] = $dados['refresh_token'] ?? $creds['refresh_token'];
        $creds['token_expira']  = now()->addSeconds($dados['expires_in'])->toDateTimeString();

        $this->integracao->update(['credenciais' => $creds]);
    }

    // ── Pedidos ───────────────────────────────────────────────

    public function sincronizarPedidos(int $limite = 50): array
    {
        $this->verificarToken();

        $userId  = $this->integracao->credenciais['user_id'];
        $baseUrl = self::BASE_URL;

        $response = $this->get("/orders/search?seller={$userId}&order.status=paid&limit={$limite}");

        $processados = 0;
        $erros = 0;

        foreach ($response['results'] ?? [] as $mlPedido) {
            try {
                $this->processarPedido($mlPedido);
                $processados++;
            } catch (\Throwable $e) {
                Log::error("ML: erro ao processar pedido #{$mlPedido['id']}", ['error' => $e->getMessage()]);
                $erros++;
            }
        }

        $this->integracao->update(['ultimo_sync' => now()]);

        return ['processados' => $processados, 'erros' => $erros];
    }

    private function processarPedido(array $mlPedido): Pedido
    {
        // Verificar se já existe
        $mpExistente = MarketplacePedido::where('marketplace_pedido_id', (string) $mlPedido['id'])
            ->where('marketplace', 'mercadolivre')
            ->first();

        if ($mpExistente?->pedido_id) {
            return $mpExistente->pedido;
        }

        DB::beginTransaction();
        try {
            // Criar/encontrar cliente
            $mlComprador = $mlPedido['buyer'];
            $cliente = $this->obterOuCriarCliente($mlComprador, $mlPedido['shipping'] ?? null);

            // Criar pedido interno
            $pedido = Pedido::create([
                'empresa_id'         => $this->integracao->empresa_id,
                'numero'             => '',
                'cliente_id'         => $cliente->id,
                'canal'              => 'marketplace',
                'marketplace'        => 'mercadolivre',
                'marketplace_pedido_id' => (string) $mlPedido['id'],
                'status'             => Pedido::STATUS_CONFIRMADO,
                'status_pagamento'   => $this->mapearStatusPagamento($mlPedido['status']),
                'frete'              => ($mlPedido['shipping']['cost'] ?? 0) / 100,
                'subtotal'           => 0,
                'total'              => ($mlPedido['total_amount'] ?? 0),
                'data_pedido'        => $mlPedido['date_created'],
                // Endereço de entrega do ML
                'entrega_logradouro' => $mlPedido['shipping']['receiver_address']['street_name'] ?? null,
                'entrega_numero'     => $mlPedido['shipping']['receiver_address']['street_number'] ?? null,
                'entrega_cidade'     => $mlPedido['shipping']['receiver_address']['city']['name'] ?? null,
                'entrega_estado'     => $mlPedido['shipping']['receiver_address']['state']['id'] ?? null,
                'entrega_cep'        => preg_replace('/\D/', '',
                    $mlPedido['shipping']['receiver_address']['zip_code'] ?? ''),
            ]);

            $pedido->update(['numero' => $pedido->gerarNumero()]);

            // Inserir itens
            $subtotal = 0;
            foreach ($mlPedido['order_items'] as $item) {
                $produto = $this->encontrarProduto($item['item']['id']);

                $precoUnitario = $item['unit_price'];
                $quantidade    = $item['quantity'];
                $total         = $precoUnitario * $quantidade;
                $subtotal     += $total;

                \App\Models\PedidoItem::create([
                    'pedido_id'      => $pedido->id,
                    'produto_id'     => $produto?->id,
                    'descricao'      => $item['item']['title'],
                    'quantidade'     => $quantidade,
                    'preco_unitario' => $precoUnitario,
                    'total'          => $total,
                ]);
            }

            $pedido->update(['subtotal' => $subtotal]);

            // Registrar no marketplace_pedidos
            MarketplacePedido::create([
                'integracao_id'      => $this->integracao->id,
                'pedido_id'          => $pedido->id,
                'marketplace_pedido_id' => (string) $mlPedido['id'],
                'marketplace'        => 'mercadolivre',
                'status_marketplace' => $mlPedido['status'],
                'dados_raw'          => json_encode($mlPedido),
                'sincronizado'       => true,
            ]);

            DB::commit();
            return $pedido;

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ── Produtos / Anúncios ───────────────────────────────────

    public function sincronizarProdutos(): array
    {
        $this->verificarToken();

        $userId   = $this->integracao->credenciais['user_id'];
        $response = $this->get("/users/{$userId}/items/search?limit=100&status=active");

        $ids = $response['results'] ?? [];
        $processados = 0;

        foreach (array_chunk($ids, 20) as $chunk) {
            $idsStr   = implode(',', $chunk);
            $detalhes = $this->get("/items?ids={$idsStr}");

            foreach ($detalhes as $itemResult) {
                if ($itemResult['code'] !== 200) continue;
                $this->sincronizarAnuncio($itemResult['body']);
                $processados++;
            }
        }

        return ['processados' => $processados];
    }

    private function sincronizarAnuncio(array $item): void
    {
        MarketplaceProduto::updateOrCreate(
            [
                'integracao_id'  => $this->integracao->id,
                'marketplace_id' => $item['id'],
            ],
            [
                'titulo'          => $item['title'],
                'marketplace_sku' => $item['seller_sku'] ?? null,
                'marketplace_url' => $item['permalink'] ?? null,
                'preco_anunciado' => $item['price'],
                'estoque_sincronizado' => $item['available_quantity'] ?? 0,
                'status_anuncio'  => $item['status'],
                'ultimo_sync'     => now(),
            ]
        );
    }

    // ── Sincronizar estoque para o ML ─────────────────────────

    public function atualizarEstoque(string $itemId, int $quantidade): bool
    {
        $this->verificarToken();

        $response = $this->put("/items/{$itemId}", [
            'available_quantity' => $quantidade,
        ]);

        return isset($response['id']);
    }

    // ── Webhook ───────────────────────────────────────────────

    public function processarWebhook(array $payload): void
    {
        $topic    = $payload['topic'] ?? '';
        $resource = $payload['resource'] ?? '';

        match ($topic) {
            'orders_v2' => dispatch(new \App\Jobs\SyncPedidoMarketplaceJob(
                $this->integracao,
                $this->extrairId($resource),
                'mercadolivre'
            )),
            'items'     => dispatch(new \App\Jobs\SyncProdutoMarketplaceJob(
                $this->integracao,
                $this->extrairId($resource)
            )),
            default => null,
        };
    }

    // ── Helpers privados ──────────────────────────────────────

    private function get(string $endpoint): array
    {
        $response = Http::withToken($this->integracao->credenciais['access_token'])
            ->timeout(30)
            ->get(self::BASE_URL . $endpoint);

        if ($response->status() === 401) {
            $this->refreshToken();
            return $this->get($endpoint);
        }

        $response->throw();
        return $response->json();
    }

    private function put(string $endpoint, array $data): array
    {
        $response = Http::withToken($this->integracao->credenciais['access_token'])
            ->timeout(30)
            ->put(self::BASE_URL . $endpoint, $data);

        if ($response->status() === 401) {
            $this->refreshToken();
            return $this->put($endpoint, $data);
        }

        $response->throw();
        return $response->json();
    }

    private function verificarToken(): void
    {
        $expira = $this->integracao->credenciais['token_expira'] ?? null;
        if ($expira && now()->addMinutes(5)->isAfter($expira)) {
            $this->refreshToken();
        }
    }

    private function obterOuCriarCliente(array $comprador, ?array $shipping): Cliente
    {
        $email = $comprador['email'] ?? null;
        $nome  = trim(($comprador['first_name'] ?? '') . ' ' . ($comprador['last_name'] ?? ''));

        $cliente = Cliente::where('empresa_id', $this->integracao->empresa_id)
            ->when($email, fn($q) => $q->where('email', $email))
            ->first();

        if ($cliente) return $cliente;

        $enderecoEntrega = $shipping['receiver_address'] ?? null;

        return Cliente::create([
            'empresa_id'   => $this->integracao->empresa_id,
            'tipo'         => 'F',
            'razao_social' => $nome ?: 'Cliente ML',
            'email'        => $email,
            'celular'      => $comprador['phone']['number'] ?? null,
            'cep'          => preg_replace('/\D/', '', $enderecoEntrega['zip_code'] ?? ''),
            'logradouro'   => $enderecoEntrega['street_name'] ?? null,
            'numero'       => $enderecoEntrega['street_number'] ?? null,
            'cidade'       => $enderecoEntrega['city']['name'] ?? null,
            'estado'       => $enderecoEntrega['state']['id'] ?? null,
            'origem'       => 'mercadolivre',
            'canal_aquisicao' => 'marketplace',
            'ativo'        => true,
        ]);
    }

    private function encontrarProduto(string $mlItemId): ?Produto
    {
        $mp = MarketplaceProduto::where('integracao_id', $this->integracao->id)
            ->where('marketplace_id', $mlItemId)
            ->with('produto')
            ->first();

        return $mp?->produto;
    }

    private function mapearStatusPagamento(string $status): string
    {
        return match ($status) {
            'paid'           => 'pago',
            'payment_required' => 'pendente',
            'payment_in_process' => 'pendente',
            default          => 'pendente',
        };
    }

    private function extrairId(string $resource): string
    {
        preg_match('/\/(\d+)$/', $resource, $matches);
        return $matches[1] ?? $resource;
    }
}
