<?php

namespace App\Services\Integracao;

use App\Models\{Integracao, MarketplacePedido, MarketplaceProduto, Pedido, PedidoItem, Cliente, Produto};
use Illuminate\Support\Facades\{Http, Log, DB};

/**
 * Integração com a Shopee Open Platform v2.
 *
 * Diferente do Mercado Livre, toda chamada à API da Shopee precisa ser
 * assinada (HMAC-SHA256) usando partner_id + path + timestamp (+ access_token
 * e shop_id quando autenticado), conforme a documentação oficial:
 * https://open.shopee.com/documents/v2/v2.public.authenticate
 */
class ShopeeService
{
    public function __construct(private readonly Integracao $integracao) {}

    // ── Auth OAuth2 ───────────────────────────────────────────

    public function urlAutorizacao(string $redirectUri): string
    {
        $path = '/api/v2/shop/auth_partner';
        $timestamp = now()->timestamp;
        $assinatura = $this->assinar($path, $timestamp);

        $params = http_build_query([
            'partner_id' => config('integracoes.shopee.partner_id'),
            'timestamp'  => $timestamp,
            'sign'       => $assinatura,
            'redirect'   => $redirectUri,
        ]);

        return config('integracoes.shopee.base_url') . "{$path}?{$params}";
    }

    public function trocarCodigo(string $code, string $shopId): array
    {
        $path = '/api/v2/auth/token/get';
        $timestamp = now()->timestamp;

        $response = Http::post(config('integracoes.shopee.base_url') . $path, [
            'code'       => $code,
            'shop_id'    => (int) $shopId,
            'partner_id' => (int) config('integracoes.shopee.partner_id'),
        ] + $this->paramsAssinados($path, $timestamp));

        if (! $response->ok() || isset($response->json()['error']) && $response->json()['error'] !== '') {
            throw new \Exception('Falha na autenticação Shopee: ' . $response->body());
        }

        $dados = $response->json();
        $this->integracao->update([
            'credenciais' => [
                'access_token'  => $dados['access_token'],
                'refresh_token' => $dados['refresh_token'],
                'shop_id'       => $shopId,
                'expire_in'     => $dados['expire_in'],
                'token_expira'  => now()->addSeconds($dados['expire_in'])->toDateTimeString(),
            ],
            'status' => 'ativo',
        ]);

        return $dados;
    }

    public function refreshToken(): void
    {
        $creds = $this->integracao->credenciais;
        $path  = '/api/v2/auth/access_token/get';
        $timestamp = now()->timestamp;

        $response = Http::post(config('integracoes.shopee.base_url') . $path, [
            'refresh_token' => $creds['refresh_token'],
            'shop_id'       => (int) $creds['shop_id'],
            'partner_id'    => (int) config('integracoes.shopee.partner_id'),
        ] + $this->paramsAssinados($path, $timestamp));

        if (! $response->ok()) {
            Log::error('Shopee: falha ao renovar token', ['body' => $response->body()]);
            return;
        }

        $dados = $response->json();
        $creds['access_token']  = $dados['access_token'];
        $creds['refresh_token'] = $dados['refresh_token'] ?? $creds['refresh_token'];
        $creds['token_expira']  = now()->addSeconds($dados['expire_in'])->toDateTimeString();

        $this->integracao->update(['credenciais' => $creds]);
    }

    // ── Pedidos ───────────────────────────────────────────────

    public function sincronizarPedidos(int $limite = 50): array
    {
        $this->verificarToken();

        $desde = now()->subDays(7)->timestamp;
        $ate   = now()->timestamp;

        $resposta = $this->get('/api/v2/order/get_order_list', [
            'time_range_field' => 'create_time',
            'time_from'        => $desde,
            'time_to'          => $ate,
            'page_size'        => $limite,
            'order_status'     => 'READY_TO_SHIP',
        ]);

        $processados = 0;
        $erros = 0;

        foreach ($resposta['response']['order_list'] ?? [] as $ordem) {
            try {
                $detalhes = $this->get('/api/v2/order/get_order_detail', [
                    'order_sn_list' => $ordem['order_sn'],
                ]);
                $pedidoShopee = $detalhes['response']['order_list'][0] ?? null;
                if ($pedidoShopee) {
                    $this->processarPedido($pedidoShopee);
                    $processados++;
                }
            } catch (\Throwable $e) {
                Log::error("Shopee: erro ao processar pedido {$ordem['order_sn']}", ['error' => $e->getMessage()]);
                $erros++;
            }
        }

        $this->integracao->update(['ultimo_sync' => now()]);

        return ['processados' => $processados, 'erros' => $erros];
    }

    private function processarPedido(array $shopeePedido): Pedido
    {
        $mpExistente = MarketplacePedido::where('marketplace_pedido_id', $shopeePedido['order_sn'])
            ->where('marketplace', 'shopee')
            ->first();

        if ($mpExistente?->pedido_id) {
            return $mpExistente->pedido;
        }

        return DB::transaction(function () use ($shopeePedido) {
            $cliente = $this->obterOuCriarCliente($shopeePedido);

            $pedido = Pedido::create([
                'empresa_id'            => $this->integracao->empresa_id,
                'numero'                => '',
                'cliente_id'            => $cliente->id,
                'canal'                 => 'marketplace',
                'marketplace'           => 'shopee',
                'marketplace_pedido_id' => $shopeePedido['order_sn'],
                'status'                => Pedido::STATUS_CONFIRMADO,
                'status_pagamento'      => 'pago',
                'frete'                 => (float) ($shopeePedido['estimated_shipping_fee'] ?? 0),
                'subtotal'              => 0,
                'total'                 => (float) ($shopeePedido['total_amount'] ?? 0),
                'data_pedido'           => now()->createFromTimestamp($shopeePedido['create_time']),
                'entrega_logradouro'    => $shopeePedido['recipient_address']['full_address'] ?? null,
                'entrega_cidade'        => $shopeePedido['recipient_address']['city'] ?? null,
                'entrega_estado'        => $shopeePedido['recipient_address']['state'] ?? null,
                'entrega_cep'           => preg_replace('/\D/', '', $shopeePedido['recipient_address']['zipcode'] ?? ''),
            ]);

            $pedido->update(['numero' => $pedido->gerarNumero()]);

            $subtotal = 0;
            foreach ($shopeePedido['item_list'] ?? [] as $item) {
                $produto = $this->encontrarProduto((string) $item['item_id']);
                $precoUnitario = (float) $item['model_discounted_price'];
                $quantidade = (int) $item['model_quantity_purchased'];
                $total = $precoUnitario * $quantidade;
                $subtotal += $total;

                PedidoItem::create([
                    'pedido_id'      => $pedido->id,
                    'produto_id'     => $produto?->id,
                    'descricao'      => $item['item_name'],
                    'quantidade'     => $quantidade,
                    'preco_unitario' => $precoUnitario,
                    'total'          => $total,
                ]);
            }

            $pedido->update(['subtotal' => $subtotal]);

            MarketplacePedido::create([
                'integracao_id'         => $this->integracao->id,
                'pedido_id'             => $pedido->id,
                'marketplace_pedido_id' => $shopeePedido['order_sn'],
                'marketplace'           => 'shopee',
                'status_marketplace'    => $shopeePedido['order_status'],
                'dados_raw'             => json_encode($shopeePedido),
                'sincronizado'          => true,
            ]);

            return $pedido;
        });
    }

    // ── Produtos / Anúncios ───────────────────────────────────

    public function sincronizarProdutos(): array
    {
        $this->verificarToken();

        $resposta = $this->get('/api/v2/product/get_item_list', [
            'offset'     => 0,
            'page_size'  => 100,
            'item_status' => 'NORMAL',
        ]);

        $itens = $resposta['response']['item'] ?? [];
        $ids = array_column($itens, 'item_id');
        $processados = 0;

        foreach (array_chunk($ids, 50) as $chunk) {
            $detalhes = $this->get('/api/v2/product/get_item_base_info', [
                'item_id_list' => implode(',', $chunk),
            ]);

            foreach ($detalhes['response']['item_list'] ?? [] as $item) {
                MarketplaceProduto::updateOrCreate(
                    ['integracao_id' => $this->integracao->id, 'marketplace_id' => (string) $item['item_id']],
                    [
                        'titulo'               => $item['item_name'],
                        'marketplace_sku'      => $item['item_sku'] ?? null,
                        'preco_anunciado'      => (float) ($item['price_info'][0]['current_price'] ?? 0),
                        'estoque_sincronizado' => (int) ($item['stock_info'][0]['current_stock'] ?? 0),
                        'status_anuncio'       => $item['item_status'],
                        'ultimo_sync'          => now(),
                    ]
                );
                $processados++;
            }
        }

        return ['processados' => $processados];
    }

    public function atualizarEstoque(string $itemId, int $quantidade): bool
    {
        $this->verificarToken();

        $resposta = $this->post('/api/v2/product/update_stock', [
            'item_id'          => (int) $itemId,
            'stock_list'       => [['model_id' => 0, 'seller_stock' => [['stock' => $quantidade]]]],
        ]);

        return empty($resposta['error']);
    }

    // ── Webhook ───────────────────────────────────────────────

    public function processarWebhook(array $payload): void
    {
        $code = $payload['code'] ?? null; // Shopee identifica o tipo de evento por código numérico

        match ($code) {
            3 => dispatch(new \App\Jobs\SyncPedidoMarketplaceJob(
                $this->integracao,
                $payload['data']['ordersn'] ?? '',
                'shopee'
            )),
            default => null,
        };
    }

    // ── Helpers privados ──────────────────────────────────────

    private function assinar(string $path, int $timestamp, ?string $accessToken = null, ?string $shopId = null): string
    {
        $partnerId = config('integracoes.shopee.partner_id');
        $base = "{$partnerId}{$path}{$timestamp}";
        if ($accessToken) $base .= $accessToken;
        if ($shopId) $base .= $shopId;

        return hash_hmac('sha256', $base, (string) config('integracoes.shopee.partner_key'));
    }

    private function paramsAssinados(string $path, int $timestamp): array
    {
        return [
            'partner_id' => (int) config('integracoes.shopee.partner_id'),
            'timestamp'  => $timestamp,
            'sign'       => $this->assinar($path, $timestamp),
        ];
    }

    private function get(string $path, array $query = []): array
    {
        $creds = $this->integracao->credenciais;
        $timestamp = now()->timestamp;

        $response = Http::timeout(30)->get(config('integracoes.shopee.base_url') . $path, array_merge($query, [
            'partner_id'  => (int) config('integracoes.shopee.partner_id'),
            'shop_id'     => (int) $creds['shop_id'],
            'access_token'=> $creds['access_token'],
            'timestamp'   => $timestamp,
            'sign'        => $this->assinar($path, $timestamp, $creds['access_token'], $creds['shop_id']),
        ]));

        $response->throw();
        return $response->json();
    }

    private function post(string $path, array $body = []): array
    {
        $creds = $this->integracao->credenciais;
        $timestamp = now()->timestamp;

        $response = Http::timeout(30)->post(
            config('integracoes.shopee.base_url') . $path . '?' . http_build_query([
                'partner_id'  => (int) config('integracoes.shopee.partner_id'),
                'shop_id'     => (int) $creds['shop_id'],
                'access_token'=> $creds['access_token'],
                'timestamp'   => $timestamp,
                'sign'        => $this->assinar($path, $timestamp, $creds['access_token'], $creds['shop_id']),
            ]),
            $body
        );

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

    private function obterOuCriarCliente(array $shopeePedido): Cliente
    {
        $nome = $shopeePedido['recipient_address']['name'] ?? 'Cliente Shopee';
        $telefone = $shopeePedido['recipient_address']['phone'] ?? null;

        $cliente = Cliente::where('empresa_id', $this->integracao->empresa_id)
            ->when($telefone, fn($q) => $q->where('celular', $telefone))
            ->first();

        if ($cliente) return $cliente;

        return Cliente::create([
            'empresa_id'      => $this->integracao->empresa_id,
            'tipo'            => 'F',
            'razao_social'    => $nome,
            'celular'         => $telefone,
            'cep'             => preg_replace('/\D/', '', $shopeePedido['recipient_address']['zipcode'] ?? ''),
            'logradouro'      => $shopeePedido['recipient_address']['full_address'] ?? null,
            'cidade'          => $shopeePedido['recipient_address']['city'] ?? null,
            'estado'          => $shopeePedido['recipient_address']['state'] ?? null,
            'origem'          => 'shopee',
            'canal_aquisicao' => 'marketplace',
            'ativo'           => true,
        ]);
    }

    private function encontrarProduto(string $itemId): ?Produto
    {
        $mp = MarketplaceProduto::where('integracao_id', $this->integracao->id)
            ->where('marketplace_id', $itemId)
            ->with('produto')
            ->first();

        return $mp?->produto;
    }
}
