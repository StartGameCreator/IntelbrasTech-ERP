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
        'mercadolivre' => [
            'nome' => 'Mercado Livre',
            'categoria' => 'marketplace',
            'descricao' => 'Marketplace líder na América Latina',
            'cor' => '#FFE600',
            'icone' => 'https://cdn.simpleicons.org/mercadolivre/FFE600',
            'campos' => ['seller_id', 'client_id', 'client_secret', 'access_token', 'refresh_token', 'marketplace_id'],
        ],
        'shopee' => [
            'nome' => 'Shopee',
            'categoria' => 'marketplace',
            'descricao' => 'Marketplace de alto volume e social commerce',
            'cor' => '#EE4D2D',
            'icone' => 'https://cdn.simpleicons.org/shopee/EE4D2D',
            'campos' => ['partner_id', 'partner_key', 'shop_id', 'access_token', 'refresh_token'],
        ],
        'amazon' => [
            'nome' => 'Amazon',
            'categoria' => 'marketplace',
            'descricao' => 'Amazon Seller Central e SP-API',
            'cor' => '#FF9900',
            'icone' => 'https://cdn.simpleicons.org/amazon/FF9900',
            'campos' => ['seller_id', 'client_id', 'client_secret', 'refresh_token', 'marketplace_id'],
        ],
        'magalu' => [
            'nome' => 'Magalu',
            'categoria' => 'marketplace',
            'descricao' => 'Marketplace Magalu e IntegraCommerce',
            'cor' => '#0086FF',
            'icone' => 'https://cdn.simpleicons.org/magazineluiza/0086FF',
            'campos' => ['client_id', 'client_secret', 'access_token', 'refresh_token', 'seller_id'],
        ],
        'americanas' => [
            'nome' => 'Americanas',
            'categoria' => 'marketplace',
            'descricao' => 'Americanas Marketplace',
            'cor' => '#E60014',
            'icone' => 'https://cdn.simpleicons.org/americanas/E60014',
            'campos' => ['client_id', 'client_secret', 'access_token', 'refresh_token', 'seller_id'],
        ],
        'casas_bahia' => [
            'nome' => 'Casas Bahia / Ponto',
            'categoria' => 'marketplace',
            'descricao' => 'Grupo Casas Bahia Marketplace',
            'cor' => '#0033A0',
            'icone' => 'https://cdn.simpleicons.org/casasbahia/0033A0',
            'campos' => ['client_id', 'client_secret', 'access_token', 'refresh_token', 'seller_id'],
        ],
        'olist' => [
            'nome' => 'Olist',
            'categoria' => 'marketplace',
            'descricao' => 'Hub e marketplace Olist',
            'cor' => '#00A868',
            'icone' => 'https://cdn.simpleicons.org/olist/00A868',
            'campos' => ['client_id', 'client_secret', 'access_token', 'refresh_token', 'seller_id'],
        ],
        'aliexpress' => [
            'nome' => 'AliExpress',
            'categoria' => 'marketplace',
            'descricao' => 'Marketplace internacional AliExpress',
            'cor' => '#FF4747',
            'icone' => 'https://cdn.simpleicons.org/aliexpress/FF4747',
            'campos' => ['app_key', 'app_secret', 'access_token', 'refresh_token', 'seller_id'],
        ],
        'carrefour' => [
            'nome' => 'Carrefour',
            'categoria' => 'marketplace',
            'descricao' => 'Carrefour Marketplace',
            'cor' => '#004E9F',
            'icone' => 'https://cdn.simpleicons.org/carrefour/004E9F',
            'campos' => ['client_id', 'client_secret', 'access_token', 'refresh_token', 'seller_id'],
        ],
        'madeiramadeira' => [
            'nome' => 'MadeiraMadeira',
            'categoria' => 'marketplace',
            'descricao' => 'Casa, móveis e decoração',
            'cor' => '#FF6B00',
            'icone' => 'https://cdn.simpleicons.org/homeassistant/FF6B00',
            'campos' => ['client_id', 'client_secret', 'access_token', 'seller_id'],
        ],
        'leroy_merlin' => [
            'nome' => 'Leroy Merlin',
            'categoria' => 'marketplace',
            'descricao' => 'Casa, construção e jardinagem',
            'cor' => '#78BE20',
            'icone' => 'https://cdn.simpleicons.org/leroymerlin/78BE20',
            'campos' => ['client_id', 'client_secret', 'access_token', 'seller_id'],
        ],
        'netshoes' => [
            'nome' => 'Netshoes',
            'categoria' => 'marketplace',
            'descricao' => 'Esportes, moda e lifestyle',
            'cor' => '#5B2C91',
            'icone' => 'https://cdn.simpleicons.org/netshoes/5B2C91',
            'campos' => ['client_id', 'client_secret', 'access_token', 'seller_id'],
        ],
        'dafiti' => [
            'nome' => 'Dafiti',
            'categoria' => 'marketplace',
            'descricao' => 'Moda e lifestyle',
            'cor' => '#111111',
            'icone' => 'https://cdn.simpleicons.org/dafiti/111111',
            'campos' => ['client_id', 'client_secret', 'access_token', 'seller_id'],
        ],
        'shein' => [
            'nome' => 'SHEIN',
            'categoria' => 'marketplace',
            'descricao' => 'Moda e marketplace global',
            'cor' => '#000000',
            'icone' => 'https://cdn.simpleicons.org/shein/000000',
            'campos' => ['app_id', 'app_secret', 'access_token', 'refresh_token', 'shop_id'],
        ],
        'temu' => [
            'nome' => 'Temu',
            'categoria' => 'marketplace',
            'descricao' => 'Marketplace global Temu',
            'cor' => '#FF6A00',
            'icone' => 'https://cdn.simpleicons.org/temu/FF6A00',
            'campos' => ['app_id', 'app_secret', 'access_token', 'shop_id'],
        ],
        'tiktok_shop' => [
            'nome' => 'TikTok Shop',
            'categoria' => 'marketplace',
            'descricao' => 'Social commerce TikTok Shop',
            'cor' => '#111111',
            'icone' => 'https://cdn.simpleicons.org/tiktok/111111',
            'campos' => ['app_key', 'app_secret', 'access_token', 'refresh_token', 'shop_id'],
        ],
        'enjoei' => [
            'nome' => 'Enjoei',
            'categoria' => 'marketplace',
            'descricao' => 'Marketplace de moda e usados',
            'cor' => '#EC2D83',
            'icone' => 'https://cdn.simpleicons.org/enjoei/EC2D83',
            'campos' => ['client_id', 'client_secret', 'access_token', 'seller_id'],
        ],
        'petlove' => [
            'nome' => 'Petlove',
            'categoria' => 'marketplace',
            'descricao' => 'Marketplace especializado em pets',
            'cor' => '#7B2CBF',
            'icone' => 'https://cdn.simpleicons.org/petlove/7B2CBF',
            'campos' => ['client_id', 'client_secret', 'access_token', 'seller_id'],
        ],
        'kabum' => [
            'nome' => 'KaBuM!',
            'categoria' => 'marketplace',
            'descricao' => 'Tecnologia e games',
            'cor' => '#FF6500',
            'icone' => 'https://cdn.simpleicons.org/amd/FF6500',
            'campos' => ['client_id', 'client_secret', 'access_token', 'seller_id'],
        ],
        'centauro' => [
            'nome' => 'Centauro',
            'categoria' => 'marketplace',
            'descricao' => 'Esportes e fitness',
            'cor' => '#D50000',
            'icone' => 'https://cdn.simpleicons.org/adidas/D50000',
            'campos' => ['client_id', 'client_secret', 'access_token', 'seller_id'],
        ],
        'decathlon' => [
            'nome' => 'Decathlon',
            'categoria' => 'marketplace',
            'descricao' => 'Esportes e atividades ao ar livre',
            'cor' => '#0082C3',
            'icone' => 'https://cdn.simpleicons.org/decathlon/0082C3',
            'campos' => ['client_id', 'client_secret', 'access_token', 'seller_id'],
        ],
        'rihappy' => [
            'nome' => 'Ri Happy',
            'categoria' => 'marketplace',
            'descricao' => 'Brinquedos e produtos infantis',
            'cor' => '#F58220',
            'icone' => 'https://cdn.simpleicons.org/lego/F58220',
            'campos' => ['client_id', 'client_secret', 'access_token', 'seller_id'],
        ],
        'cobasi' => [
            'nome' => 'Cobasi',
            'categoria' => 'marketplace',
            'descricao' => 'Produtos para pets, casa e jardim',
            'cor' => '#F58220',
            'icone' => 'https://cdn.simpleicons.org/datadog/F58220',
            'campos' => ['client_id', 'client_secret', 'access_token', 'seller_id'],
        ],
        'mercado_shops' => [
            'nome' => 'Mercado Shops',
            'categoria' => 'marketplace',
            'descricao' => 'Loja virtual integrada ao Mercado Livre',
            'cor' => '#FFE600',
            'icone' => 'https://cdn.simpleicons.org/mercadolivre/FFE600',
            'campos' => ['seller_id', 'client_id', 'client_secret', 'access_token', 'refresh_token'],
        ],
        'google_shopping' => [
            'nome' => 'Google Shopping',
            'categoria' => 'marketplace',
            'descricao' => 'Catálogo e campanhas no Google Merchant Center',
            'cor' => '#4285F4',
            'icone' => 'https://cdn.simpleicons.org/google/4285F4',
            'campos' => ['merchant_id', 'client_id', 'client_secret', 'refresh_token'],
        ],
        'facebook_shop' => [
            'nome' => 'Facebook Shop',
            'categoria' => 'marketplace',
            'descricao' => 'Catálogo Meta Commerce',
            'cor' => '#0866FF',
            'icone' => 'https://cdn.simpleicons.org/facebook/0866FF',
            'campos' => ['business_id', 'catalog_id', 'access_token'],
        ],
        'instagram_shop' => [
            'nome' => 'Instagram Shop',
            'categoria' => 'marketplace',
            'descricao' => 'Catálogo e compras no Instagram',
            'cor' => '#E4405F',
            'icone' => 'https://cdn.simpleicons.org/instagram/E4405F',
            'campos' => ['business_id', 'catalog_id', 'access_token'],
        ],
        'nuvemshop' => [
            'nome' => 'Nuvemshop',
            'categoria' => 'ecommerce',
            'descricao' => 'Plataforma de loja virtual',
            'cor' => '#2F7DE1',
            'icone' => 'https://cdn.simpleicons.org/nuvemshop/2F7DE1',
            'campos' => ['store_id', 'client_id', 'client_secret', 'access_token'],
        ],
        'shopify' => [
            'nome' => 'Shopify',
            'categoria' => 'ecommerce',
            'descricao' => 'Plataforma global de e-commerce',
            'cor' => '#7AB55C',
            'icone' => 'https://cdn.simpleicons.org/shopify/7AB55C',
            'campos' => ['store_url', 'api_key', 'api_secret', 'access_token'],
        ],
        'woocommerce' => [
            'nome' => 'WooCommerce',
            'categoria' => 'ecommerce',
            'descricao' => 'Loja virtual para WordPress',
            'cor' => '#96588A',
            'icone' => 'https://cdn.simpleicons.org/woocommerce/96588A',
            'campos' => ['store_url', 'consumer_key', 'consumer_secret'],
        ],
        'tray' => [
            'nome' => 'Tray',
            'categoria' => 'ecommerce',
            'descricao' => 'Plataforma brasileira de e-commerce',
            'cor' => '#3B82F6',
            'icone' => 'https://cdn.simpleicons.org/tray/3B82F6',
            'campos' => ['store_id', 'api_key', 'access_token'],
        ],
        'loja_integrada' => [
            'nome' => 'Loja Integrada',
            'categoria' => 'ecommerce',
            'descricao' => 'Plataforma de loja virtual brasileira',
            'cor' => '#22C55E',
            'icone' => 'https://cdn.simpleicons.org/shopware/22C55E',
            'campos' => ['store_url', 'api_key'],
        ],
        'vtex' => [
            'nome' => 'VTEX',
            'categoria' => 'ecommerce',
            'descricao' => 'Digital commerce enterprise',
            'cor' => '#F71963',
            'icone' => 'https://cdn.simpleicons.org/vtex/F71963',
            'campos' => ['account_name', 'environment', 'app_key', 'app_token'],
        ],
        'wake' => [
            'nome' => 'Wake Commerce',
            'categoria' => 'ecommerce',
            'descricao' => 'Plataforma omnichannel Wake',
            'cor' => '#6C63FF',
            'icone' => 'https://cdn.simpleicons.org/wakelet/6C63FF',
            'campos' => ['store_url', 'api_key', 'api_secret'],
        ],
        'bagy' => [
            'nome' => 'Bagy',
            'categoria' => 'ecommerce',
            'descricao' => 'Loja virtual e social commerce',
            'cor' => '#6D28D9',
            'icone' => 'https://cdn.simpleicons.org/baguette/6D28D9',
            'campos' => ['store_id', 'api_key', 'access_token'],
        ],
        'yampi' => [
            'nome' => 'Yampi',
            'categoria' => 'ecommerce',
            'descricao' => 'Checkout e e-commerce',
            'cor' => '#6C5CE7',
            'icone' => 'https://cdn.simpleicons.org/yammer/6C5CE7',
            'campos' => ['alias', 'token', 'secret_key'],
        ],
        'wix' => [
            'nome' => 'Wix Stores',
            'categoria' => 'ecommerce',
            'descricao' => 'Loja virtual Wix',
            'cor' => '#0C0C0C',
            'icone' => 'https://cdn.simpleicons.org/wixstudio/0C0C0C',
            'campos' => ['site_id', 'api_key', 'account_id'],
        ],
        'opencart' => [
            'nome' => 'OpenCart',
            'categoria' => 'ecommerce',
            'descricao' => 'Plataforma open source de e-commerce',
            'cor' => '#2AB7EC',
            'icone' => 'https://cdn.simpleicons.org/opencart/2AB7EC',
            'campos' => ['store_url', 'api_key'],
        ],
        'prestashop' => [
            'nome' => 'PrestaShop',
            'categoria' => 'ecommerce',
            'descricao' => 'Plataforma open source de e-commerce',
            'cor' => '#DF0067',
            'icone' => 'https://cdn.simpleicons.org/prestashop/DF0067',
            'campos' => ['store_url', 'api_key'],
        ],
        'magento' => [
            'nome' => 'Adobe Commerce / Magento',
            'categoria' => 'ecommerce',
            'descricao' => 'Plataforma enterprise Adobe Commerce',
            'cor' => '#EE672F',
            'icone' => 'https://cdn.simpleicons.org/magento/EE672F',
            'campos' => ['store_url', 'access_token'],
        ],
        'bling' => [
            'nome' => 'Bling',
            'categoria' => 'erp',
            'descricao' => 'ERP e hub de integrações',
            'cor' => '#39B54A',
            'icone' => 'https://cdn.simpleicons.org/bling/39B54A',
            'campos' => ['client_id', 'client_secret', 'access_token', 'refresh_token'],
        ],
        'tiny' => [
            'nome' => 'Tiny ERP',
            'categoria' => 'erp',
            'descricao' => 'ERP e hub Olist Tiny',
            'cor' => '#00A868',
            'icone' => 'https://cdn.simpleicons.org/tinyletter/00A868',
            'campos' => ['token'],
        ],
        'omie' => [
            'nome' => 'Omie',
            'categoria' => 'erp',
            'descricao' => 'ERP em nuvem',
            'cor' => '#1769FF',
            'icone' => 'https://cdn.simpleicons.org/onlyoffice/1769FF',
            'campos' => ['app_key', 'app_secret'],
        ],
        'conta_azul' => [
            'nome' => 'Conta Azul',
            'categoria' => 'erp',
            'descricao' => 'ERP financeiro e empresarial',
            'cor' => '#2684FF',
            'icone' => 'https://cdn.simpleicons.org/azuredataexplorer/2684FF',
            'campos' => ['client_id', 'client_secret', 'access_token', 'refresh_token'],
        ],
        'totvs' => [
            'nome' => 'TOTVS',
            'categoria' => 'erp',
            'descricao' => 'Ecossistema ERP TOTVS',
            'cor' => '#0067B1',
            'icone' => 'https://cdn.simpleicons.org/totvs/0067B1',
            'campos' => ['base_url', 'client_id', 'client_secret', 'access_token'],
        ],
        'sap' => [
            'nome' => 'SAP',
            'categoria' => 'erp',
            'descricao' => 'Integração SAP Business One / S4',
            'cor' => '#0FAAFF',
            'icone' => 'https://cdn.simpleicons.org/sap/0FAAFF',
            'campos' => ['base_url', 'company_db', 'username', 'password'],
        ],
        'senior' => [
            'nome' => 'Senior Sistemas',
            'categoria' => 'erp',
            'descricao' => 'ERP e gestão empresarial Senior',
            'cor' => '#E31B23',
            'icone' => 'https://cdn.simpleicons.org/redhat/E31B23',
            'campos' => ['base_url', 'username', 'password', 'tenant'],
        ],
        'mercado_pago' => [
            'nome' => 'Mercado Pago',
            'categoria' => 'pagamentos',
            'descricao' => 'Pagamentos e conciliação Mercado Pago',
            'cor' => '#009EE3',
            'icone' => 'https://cdn.simpleicons.org/mercadopago/009EE3',
            'campos' => ['public_key', 'access_token', 'client_id', 'client_secret'],
        ],
        'pagseguro' => [
            'nome' => 'PagBank / PagSeguro',
            'categoria' => 'pagamentos',
            'descricao' => 'Pagamentos online e adquirência',
            'cor' => '#00B98E',
            'icone' => 'https://cdn.simpleicons.org/pagseguro/00B98E',
            'campos' => ['email', 'token', 'app_id', 'app_key'],
        ],
        'pagarme' => [
            'nome' => 'Pagar.me',
            'categoria' => 'pagamentos',
            'descricao' => 'Gateway e infraestrutura de pagamentos',
            'cor' => '#1B1B1B',
            'icone' => 'https://cdn.simpleicons.org/pagarme/1B1B1B',
            'campos' => ['api_key', 'encryption_key'],
        ],
        'stripe' => [
            'nome' => 'Stripe',
            'categoria' => 'pagamentos',
            'descricao' => 'Pagamentos globais Stripe',
            'cor' => '#635BFF',
            'icone' => 'https://cdn.simpleicons.org/stripe/635BFF',
            'campos' => ['publishable_key', 'secret_key', 'webhook_secret'],
        ],
        'paypal' => [
            'nome' => 'PayPal',
            'categoria' => 'pagamentos',
            'descricao' => 'Pagamentos globais PayPal',
            'cor' => '#003087',
            'icone' => 'https://cdn.simpleicons.org/paypal/003087',
            'campos' => ['client_id', 'client_secret'],
        ],
        'stone' => [
            'nome' => 'Stone',
            'categoria' => 'pagamentos',
            'descricao' => 'Adquirência e serviços financeiros',
            'cor' => '#00A868',
            'icone' => 'https://cdn.simpleicons.org/stone/00A868',
            'campos' => ['client_id', 'client_secret', 'access_token'],
        ],
        'cielo' => [
            'nome' => 'Cielo',
            'categoria' => 'pagamentos',
            'descricao' => 'Pagamentos e adquirência Cielo',
            'cor' => '#0095D9',
            'icone' => 'https://cdn.simpleicons.org/cielo/0095D9',
            'campos' => ['merchant_id', 'merchant_key'],
        ],
        'rede' => [
            'nome' => 'Rede',
            'categoria' => 'pagamentos',
            'descricao' => 'Adquirência Itaú Rede',
            'cor' => '#EC7000',
            'icone' => 'https://cdn.simpleicons.org/itau/EC7000',
            'campos' => ['pv', 'token'],
        ],
        'asaas' => [
            'nome' => 'Asaas',
            'categoria' => 'pagamentos',
            'descricao' => 'Cobranças, Pix e boletos',
            'cor' => '#0030B9',
            'icone' => 'https://cdn.simpleicons.org/cashapp/0030B9',
            'campos' => ['api_key'],
        ],
        'correios' => [
            'nome' => 'Correios',
            'categoria' => 'logistica',
            'descricao' => 'Frete, etiquetas e rastreamento',
            'cor' => '#F6C500',
            'icone' => 'https://cdn.simpleicons.org/maildotru/F6C500',
            'campos' => ['usuario', 'codigo_acesso', 'cartao_postagem', 'contrato'],
        ],
        'melhor_envio' => [
            'nome' => 'Melhor Envio',
            'categoria' => 'logistica',
            'descricao' => 'Hub de fretes e etiquetas',
            'cor' => '#2F80ED',
            'icone' => 'https://cdn.simpleicons.org/deliveroo/2F80ED',
            'campos' => ['client_id', 'client_secret', 'access_token', 'refresh_token'],
        ],
        'intelipost' => [
            'nome' => 'Intelipost',
            'categoria' => 'logistica',
            'descricao' => 'Gestão de fretes e logística',
            'cor' => '#00B8A9',
            'icone' => 'https://cdn.simpleicons.org/instacart/00B8A9',
            'campos' => ['api_key'],
        ],
        'frenet' => [
            'nome' => 'Frenet',
            'categoria' => 'logistica',
            'descricao' => 'Gateway de fretes',
            'cor' => '#0054A6',
            'icone' => 'https://cdn.simpleicons.org/fastly/0054A6',
            'campos' => ['token'],
        ],
        'jadlog' => [
            'nome' => 'Jadlog',
            'categoria' => 'logistica',
            'descricao' => 'Transportadora nacional',
            'cor' => '#E30613',
            'icone' => 'https://cdn.simpleicons.org/dhl/E30613',
            'campos' => ['cnpj', 'token'],
        ],
        'loggi' => [
            'nome' => 'Loggi',
            'categoria' => 'logistica',
            'descricao' => 'Entregas e logística urbana',
            'cor' => '#000000',
            'icone' => 'https://cdn.simpleicons.org/logitech/000000',
            'campos' => ['client_id', 'client_secret', 'access_token'],
        ],
        'azul_cargo' => [
            'nome' => 'Azul Cargo',
            'categoria' => 'logistica',
            'descricao' => 'Transporte aéreo de cargas',
            'cor' => '#005DAA',
            'icone' => 'https://cdn.simpleicons.org/americanairlines/005DAA',
            'campos' => ['codigo_cliente', 'token'],
        ],
        'google_ads' => [
            'nome' => 'Google Ads',
            'categoria' => 'marketing',
            'descricao' => 'Campanhas e conversões Google Ads',
            'cor' => '#4285F4',
            'icone' => 'https://cdn.simpleicons.org/googleads/4285F4',
            'campos' => ['developer_token', 'client_id', 'client_secret', 'refresh_token', 'customer_id'],
        ],
        'meta_ads' => [
            'nome' => 'Meta Ads',
            'categoria' => 'marketing',
            'descricao' => 'Facebook e Instagram Ads',
            'cor' => '#0866FF',
            'icone' => 'https://cdn.simpleicons.org/meta/0866FF',
            'campos' => ['business_id', 'ad_account_id', 'access_token'],
        ],
        'tiktok_ads' => [
            'nome' => 'TikTok Ads',
            'categoria' => 'marketing',
            'descricao' => 'Campanhas no TikTok Ads',
            'cor' => '#111111',
            'icone' => 'https://cdn.simpleicons.org/tiktok/111111',
            'campos' => ['app_id', 'secret', 'access_token', 'advertiser_id'],
        ],
        'mailchimp' => [
            'nome' => 'Mailchimp',
            'categoria' => 'marketing',
            'descricao' => 'E-mail marketing e automação',
            'cor' => '#FFE01B',
            'icone' => 'https://cdn.simpleicons.org/mailchimp/FFE01B',
            'campos' => ['api_key', 'server_prefix'],
        ],
        'rd_station' => [
            'nome' => 'RD Station',
            'categoria' => 'marketing',
            'descricao' => 'Marketing e CRM RD Station',
            'cor' => '#5B2EFF',
            'icone' => 'https://cdn.simpleicons.org/hubspot/5B2EFF',
            'campos' => ['client_id', 'client_secret', 'access_token', 'refresh_token'],
        ],
        'hubspot' => [
            'nome' => 'HubSpot',
            'categoria' => 'marketing',
            'descricao' => 'CRM, marketing e atendimento',
            'cor' => '#FF7A59',
            'icone' => 'https://cdn.simpleicons.org/hubspot/FF7A59',
            'campos' => ['client_id', 'client_secret', 'access_token', 'refresh_token'],
        ],
        'google_business' => [
            'nome' => 'Google Business Profile',
            'categoria' => 'marketing',
            'descricao' => 'Perfil da empresa e avaliações',
            'cor' => '#4285F4',
            'icone' => 'https://cdn.simpleicons.org/googlemybusiness/4285F4',
            'campos' => ['client_id', 'client_secret', 'refresh_token', 'account_id'],
        ],
    ];

    public function catalogo(): JsonResponse
    {
        $this->authorize('integracoes.view');

        $integracoes = Integracao::query()
            ->whereIn('tipo', array_keys($this->marketplaces))
            ->get()
            ->keyBy('tipo');

        return response()->json([
            'data' => collect($this->marketplaces)->map(function (array $item, string $tipo) use ($integracoes) {
                $integracao = $integracoes->get($tipo);

                return [
                    'tipo' => $tipo,
                    'nome' => $item['nome'],
                    'categoria' => $item['categoria'],
                    'descricao' => $item['descricao'],
                    'cor' => $item['cor'],
                    'icone' => $item['icone'],
                    'campos' => $item['campos'],
                    'conectado' => $integracao?->status === 'ativo',
                    'status' => $integracao?->status ?? 'nao_configurado',
                    'integracao_id' => $integracao?->id,
                    'ultimo_sync' => $integracao?->ultimo_sync,
                    'nome_conta' => $integracao?->nome,
                ];
            })->values(),
            'categorias' => collect($this->marketplaces)
                ->groupBy('categoria')
                ->map(fn ($items, $categoria) => [
                    'categoria' => $categoria,
                    'total' => $items->count(),
                ])->values(),
        ]);
    }

    public function salvar(Request $request, string $tipo): JsonResponse
    {
        $this->authorize('integracoes.edit');
        abort_unless(isset($this->marketplaces[$tipo]), 404, 'Conector não cadastrado no catálogo.');

        $data = $request->validate([
            'nome' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['ativo', 'pendente', 'inativo', 'desconectado'])],
            'credenciais' => ['nullable', 'array'],
            'config' => ['nullable', 'array'],
        ]);

        $integracao = Integracao::updateOrCreate(
            ['empresa_id' => auth()->user()->empresa_id, 'tipo' => $tipo],
            [
                'nome' => $data['nome'] ?? $this->marketplaces[$tipo]['nome'],
                'credenciais' => $data['credenciais'] ?? [],
                'config' => $data['config'] ?? [],
                'status' => $data['status'] ?? 'pendente',
                'ativo' => ($data['status'] ?? 'pendente') !== 'desconectado',
            ]
        );

        return response()->json([
            'message' => 'Configuração salva com sucesso.',
            'data' => $integracao->makeVisible('credenciais'),
        ]);
    }

    public function testar(string $tipo): JsonResponse
    {
        $this->authorize('integracoes.view');
        abort_unless(isset($this->marketplaces[$tipo]), 404, 'Conector não cadastrado no catálogo.');

        $integracao = Integracao::where('tipo', $tipo)->first();

        return response()->json([
            'ok' => (bool) $integracao,
            'tipo' => $tipo,
            'nome' => $this->marketplaces[$tipo]['nome'],
            'status' => $integracao?->status ?? 'nao_configurado',
            'message' => $integracao
                ? 'Rota encontrada e configuração localizada.'
                : 'Rota encontrada, mas ainda sem configuração salva.',
        ]);
    }
}
