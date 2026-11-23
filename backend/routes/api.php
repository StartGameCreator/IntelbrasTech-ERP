<?php











use App\Http\Controllers\Api\V1\Fiscal\NumeracaoFiscalController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardAnalyticsController;
use App\Http\Controllers\Api\V1\Cadastros\CadastrosAuxiliaresController;
use App\Http\Controllers\Api\V1\Dashboard\DashboardMapaVendasController;
use App\Http\Controllers\Api\V1\Fiscal\ContadorController;
use App\Http\Controllers\Api\V1\Fiscal\NcmCatalogoController;
use App\Http\Controllers\Api\V1\Fiscal\NfseController;
use App\Http\Controllers\Api\V1\Logistica\LogisticaController;
use App\Http\Controllers\Api\V1\Logistica\TransportadoraController;
use App\Http\Controllers\Api\V1\Expedicao\ExpedicaoController;
use App\Http\Controllers\Api\V1\Marketplace\MarketplaceFinanceFiscalController;

use App\Http\Controllers\Api\V1\Marketplace\MercadoLivreCatalogController;











use App\Http\Controllers\Api\V1\Marketplace\MarketplaceFiscalEmissionController;
use App\Http\Controllers\Api\V1\Marketplace\MercadoLivreAutomationController;
use App\Http\Controllers\Api\V1\Marketplace\MercadoLivreOrdersController;
use App\Http\Controllers\Api\V1\Marketplace\MercadoLivreOAuthController;
use App\Http\Controllers\Api\V1\Marketplace\MarketplaceHubController;
use App\Http\Controllers\Api\V1\Marketplace\NuvemshopController;
use App\Http\Controllers\Api\V1\Pdv\NfcePdvController;
use App\Http\Controllers\Api\V1\Pdv\PdvController;
use App\Http\Controllers\Api\V1\Cadastros\ProdutoIntelbrasImportController;
use App\Http\Controllers\Api\V1\Configuracoes\EmpresaLookupController;
use App\Http\Controllers\Api\V1\Crm\LeadCnpjEnrichmentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\SocialAuthController;
use App\Http\Controllers\Api\V1\Cadastros\{ClienteController, FornecedorController, ProdutoController, CategoriaController, MarcaController, UnidadeMedidaController};
use App\Http\Controllers\Api\V1\Comercial\{PedidoController, ReciboController};
use App\Http\Controllers\Api\V1\Compras\PedidoCompraController;
use App\Http\Controllers\Api\V1\Estoque\{EstoqueController, MovimentacaoController, InventarioController};
use App\Http\Controllers\Api\V1\Estoque\XmlEntradaEstoqueController;
use App\Http\Controllers\Api\V1\Financeiro\{ContasReceberController, ContasPagarController, FluxoCaixaController};
use App\Http\Controllers\Api\V1\Fiscal\NFe\NFeController;
use App\Http\Controllers\Api\V1\Fiscal\CertificadoDigitalController;
use App\Http\Controllers\Api\V1\Integracao\{MercadoLivreController, ShopeeController, IntegracaoController, MarketplaceConfigController, TransportadoraConfigController, CsvImportController};
use App\Http\Controllers\Api\V1\OS\{OrdemServicoController, OsStatusController};
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Crm\{LeadController, PipelineController, CrmManagementController};
use App\Http\Controllers\Api\V1\Configuracao\ApiIntegrationController;
use App\Http\Controllers\Api\V1\Portal\PortalController;
use App\Http\Controllers\Api\V1\Portal\LocalidadeController;
use App\Http\Controllers\Api\V1\Portal\PortalCarouselController;
use App\Http\Controllers\Api\V1\Portal\PortalSiteSettingsController;
use App\Http\Controllers\Api\V1\Portal\PortalChamadoController;
use App\Http\Controllers\Api\V1\EmailMarketing\EmailMarketingController;
use App\Http\Controllers\Api\V1\Sistema\SystemLogController;

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// PÃƒÅ¡BLICO
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
Route::prefix('v1')->group(function () {

    Route::prefix('portal')->group(function () {
        Route::get('configuracao', [PortalController::class, 'configuracao']);
        Route::get('pwa/manifest.webmanifest', [PortalController::class, 'pwaManifest']);
        Route::get('pwa/icon/{size}.png', [PortalController::class, 'pwaIcon'])->whereNumber('size');
        Route::get('compartilhamento/imagem', [PortalController::class, 'imagemCompartilhamento']);
        Route::get('catalogo', [PortalController::class, 'catalogo']);
        Route::get('carrossel', [PortalCarouselController::class, 'publico']);
        Route::get('localidades/ufs', [LocalidadeController::class, 'ufs']);
        Route::get('localidades/ufs/{uf}/municipios', [LocalidadeController::class, 'municipios']);
        Route::get('cep/{cep}', [LocalidadeController::class, 'cep'])->where('cep', '[0-9\-]+');
        Route::post('solicitacoes', [PortalController::class, 'solicitar'])->middleware('throttle:10,1');
        Route::post('chamados', [PortalChamadoController::class, 'abrir'])->middleware('throttle:8,1');
        Route::get('chamados/{token}', [PortalChamadoController::class, 'acompanhar'])->middleware('throttle:30,1');
        Route::post('chamados/{token}/autorizar', [PortalChamadoController::class, 'autorizar'])->middleware('throttle:6,1');
        Route::get('chamados/{token}/contrato', [PortalChamadoController::class, 'contrato'])->middleware('throttle:12,1');
    });

    // Cadastro pÃºblico do Marketplace de TÃ©cnicos

    // Auth
    Route::post('auth/login',  [AuthController::class, 'login']);
    Route::post('auth/esqueci-senha', [AuthController::class, 'esqueciSenha']);
    Route::post('auth/redefinir-senha', [AuthController::class, 'redefinirSenha']);
    // Login social OAuth
    Route::get('auth/social/{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::match(['get','post'], 'auth/social/{provider}/callback', [SocialAuthController::class, 'callback']);
    Route::post('auth/social/exchange', [SocialAuthController::class, 'exchange']);


    // Webhooks marketplaces
    Route::post('webhooks/mercadolivre', [MercadoLivreController::class, 'webhook']);
    Route::post('webhooks/shopee',       [ShopeeController::class, 'webhook']);
    Route::post('webhooks/nuvemshop',    [NuvemshopController::class, 'webhook']);

    // UtilitÃƒÂ¡rios
    Route::get('cep/{cep}',   fn($cep) => response()->json(
        \Illuminate\Support\Facades\Http::get("https://viacep.com.br/ws/{$cep}/json/")->json()
    ));
    Route::match(['get','post'], 'email-marketing/descadastrar/{token}', [EmailMarketingController::class, 'unsubscribe']);

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// AUTENTICADO
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
    Route::middleware(['auth:sanctum', 'empresa'])->group(function () {

        Route::prefix('sistema')->group(function () {
            Route::get('diagnostico', [SystemLogController::class, 'diagnostics']);
            Route::get('logs', [SystemLogController::class, 'index']);
        });

        Route::prefix('email-marketing')->group(function () {
            Route::get('/', [EmailMarketingController::class, 'dashboard']);
            Route::put('configuracao', [EmailMarketingController::class, 'saveSettings']);
            Route::post('contatos', [EmailMarketingController::class, 'saveContact']);
            Route::put('contatos/{id}', [EmailMarketingController::class, 'saveContact']);
            Route::post('modelos', [EmailMarketingController::class, 'saveTemplate']);
            Route::put('modelos/{id}', [EmailMarketingController::class, 'saveTemplate']);
            Route::post('campanhas', [EmailMarketingController::class, 'saveCampaign']);
            Route::post('campanhas/{id}/enfileirar', [EmailMarketingController::class, 'queue']);
            Route::post('processar', [EmailMarketingController::class, 'process']);
        });

        Route::prefix('portal/conteudo/carrossel')->group(function () {
            Route::get('/', [PortalCarouselController::class, 'index']);
            Route::post('/', [PortalCarouselController::class, 'store']);
            Route::put('{id}', [PortalCarouselController::class, 'update']);
            Route::delete('{id}', [PortalCarouselController::class, 'destroy']);
            Route::post('ordenar', [PortalCarouselController::class, 'ordenar']);
        });
        Route::get('portal/configuracoes/site', [PortalSiteSettingsController::class, 'show']);
        Route::put('portal/configuracoes/site', [PortalSiteSettingsController::class, 'update']);

        Route::prefix('chamados-clientes')->group(function () {
            Route::get('/', [PortalChamadoController::class, 'index']);
            Route::get('{id}', [PortalChamadoController::class, 'show']);
            Route::put('{id}/orcamento', [PortalChamadoController::class, 'orcamento']);
        });

        // Ã¢â€â‚¬Ã¢â€â‚¬ Auth

        // Busca CNPJ - CRM
        Route::get('cnpj/fontes', [\App\Http\Controllers\Api\V1\Utilitarios\CnpjController::class, 'fontes']);
        Route::get('cnpj/consultar', [\App\Http\Controllers\Api\V1\Utilitarios\CnpjController::class, 'consultar']);
        Route::post('cnpj/lote', [\App\Http\Controllers\Api\V1\Utilitarios\CnpjController::class, 'lote']);
        Route::post('auth/logout',           [AuthController::class, 'logout']);
        Route::get('auth/me',                [AuthController::class, 'me']);
        Route::put('auth/perfil',            [AuthController::class, 'atualizarPerfil']);
        Route::put('auth/senha',             [AuthController::class, 'alterarSenha']);
        Route::get('auth/social/connections', [SocialAuthController::class, 'connections']);
        Route::post('auth/social/google/sync', [SocialAuthController::class, 'syncGoogle']);
        Route::post('auth/social/google/select-location', [SocialAuthController::class, 'selectGoogleLocation']);
        Route::delete('auth/social/{provider}', [SocialAuthController::class, 'disconnect']);



        // Compatibilidade do painel de enriquecimento CNPJ
        Route::post('crm/leads/{lead}/enriquecer-cnpj', LeadCnpjEnrichmentController::class);

        // Ã¢â€â‚¬Ã¢â€â‚¬ Central de APIs
        Route::prefix('configuracoes/apis')->group(function () {
            Route::get('/', [ApiIntegrationController::class, 'index']);
            Route::put('{provider}', [ApiIntegrationController::class, 'save']);
            Route::post('{provider}/testar', [ApiIntegrationController::class, 'test']);
            Route::post('social_google/importar-json', [ApiIntegrationController::class, 'importGoogleOAuthJson']);
        });


        // Nuvemshop + Dropshipping
        Route::prefix('nuvemshop')->group(function () {
            Route::get('status', [NuvemshopController::class, 'status']);
            Route::post('oauth/redirect', [NuvemshopController::class, 'redirect']);
            Route::post('oauth/callback', [NuvemshopController::class, 'callback']);
            Route::post('testar', [NuvemshopController::class, 'test']);
            Route::post('sincronizar-produtos', [NuvemshopController::class, 'syncProducts']);
            Route::post('sincronizar-pedidos', [NuvemshopController::class, 'syncOrders']);
            Route::get('dropshipping', [NuvemshopController::class, 'dropshipDashboard']);
        });

        // Ã¢â€â‚¬Ã¢â€â‚¬ Dashboard

        // Dados completos da empresa

        // Enriquecimento do cadastro da prÃ³pria empresa
        Route::get('empresa/consultar-cnpj/{cnpj}', [EmpresaLookupController::class, 'cnpj']);
        Route::get('empresa/consultar-cep/{cep}', [EmpresaLookupController::class, 'cep']);
        Route::get('empresa/perfil', [\App\Http\Controllers\Api\V1\Configuracoes\EmpresaController::class, 'show']);
        Route::put('empresa/perfil', [\App\Http\Controllers\Api\V1\Configuracoes\EmpresaController::class, 'update']);
        Route::post('empresa/logo', [\App\Http\Controllers\Api\V1\Configuracoes\EmpresaController::class, 'uploadLogo']);
        Route::delete('empresa/logo', [\App\Http\Controllers\Api\V1\Configuracoes\EmpresaController::class, 'removerLogo']);

        Route::get('dashboard/analytics/{key}', [DashboardAnalyticsController::class, 'show']);
        Route::prefix('dashboard')->group(function () {

            // Origem geogrÃ¡fica das vendas por CEP â€” Fase 11L.2
            Route::get('mapa-vendas', [DashboardMapaVendasController::class, 'index']);
            Route::post('mapa-vendas/sincronizar', [DashboardMapaVendasController::class, 'sincronizar']);
            Route::get('/',        [DashboardController::class, 'index']);
            Route::get('mapa',     [DashboardController::class, 'mapa']);
            Route::get('vendas',   [DashboardController::class, 'vendas']);
            Route::get('resumo',   [DashboardController::class, 'resumo']);
        });


        // Cadastros auxiliares conectados ao banco â€” Fase 11L.3
        Route::get('usuarios', [CadastrosAuxiliaresController::class, 'usuarios']);
        Route::get('tabelas-preco', [CadastrosAuxiliaresController::class, 'tabelasPreco']);

        // Ã¢â€â‚¬Ã¢â€â‚¬ Clientes
        Route::prefix('clientes')->group(function () {
            Route::get('/',                  [ClienteController::class, 'index']);
            Route::post('/',                 [ClienteController::class, 'store']);
            Route::get('mapa',               [ClienteController::class, 'mapa']);
            Route::get('aniversariantes',    [ClienteController::class, 'aniversariantes']);
            Route::get('{id}',               [ClienteController::class, 'show']);
            Route::put('{id}',               [ClienteController::class, 'update']);
            Route::delete('{id}',            [ClienteController::class, 'destroy']);
            Route::post('{id}/bloquear',     [ClienteController::class, 'bloquear']);
            Route::post('{id}/desbloquear',  [ClienteController::class, 'desbloquear']);
        });

        // Ã¢â€â‚¬Ã¢â€â‚¬ Compras
        Route::prefix('pedidos-compra')->group(function () {
            Route::get('/',                [PedidoCompraController::class, 'index']);
            Route::post('/',               [PedidoCompraController::class, 'store']);
            Route::get('{id}',             [PedidoCompraController::class, 'show']);
            Route::put('{id}',             [PedidoCompraController::class, 'update']);
            Route::delete('{id}',          [PedidoCompraController::class, 'destroy']);
            Route::post('{id}/enviar',     [PedidoCompraController::class, 'enviar']);
            Route::post('{id}/receber',    [PedidoCompraController::class, 'receber']);
            Route::post('{id}/cancelar',   [PedidoCompraController::class, 'cancelar']);
        });

        // Ã¢â€â‚¬Ã¢â€â‚¬ IntegraÃƒÂ§ÃƒÂµes / Marketplace
        Route::get('integracoes',        [IntegracaoController::class, 'index']);
        Route::delete('integracoes/{id}',[IntegracaoController::class, 'destroy']);

        // Ã¢â€â‚¬Ã¢â€â‚¬ ConfiguraÃƒÂ§ÃƒÂ£o manual de APIs dos marketplaces
        Route::get('marketplaces/catalogo', [MarketplaceConfigController::class, 'catalogo']);
        Route::post('marketplaces/{tipo}/configurar', [MarketplaceConfigController::class, 'salvar']);
        Route::get('marketplaces/{tipo}/testar', [MarketplaceConfigController::class, 'testar']);

        // Ã¢â€â‚¬Ã¢â€â‚¬ APIs de logÃƒÂ­stica / transportadoras
        Route::get('transportadoras/catalogo', [TransportadoraConfigController::class, 'catalogo']);
        Route::get('transportadoras', [TransportadoraConfigController::class, 'index']);
        Route::post('transportadoras/{apiTipo}/configurar', [TransportadoraConfigController::class, 'salvar']);
        Route::get('transportadoras/{apiTipo}/testar', [TransportadoraConfigController::class, 'testar']);

        // Ã¢â€â‚¬Ã¢â€â‚¬ Cadastro e upload de CSV
        Route::get('csv-importacoes', [CsvImportController::class, 'index']);
        Route::post('csv-importacoes', [CsvImportController::class, 'store']);

        Route::prefix('marketplace/mercadolivre')->group(function () {
            Route::get('conectar',                    [MercadoLivreController::class, 'conectar']);
            Route::post('callback',                    [MercadoLivreController::class, 'callback']);
            Route::post('{id}/sincronizar-pedidos',    [MercadoLivreController::class, 'sincronizarPedidos']);
            Route::post('{id}/sincronizar-produtos',   [MercadoLivreController::class, 'sincronizarProdutos']);
            Route::post('{id}/atualizar-estoque',      [MercadoLivreController::class, 'atualizarEstoque']);
        });

        Route::prefix('marketplace/shopee')->group(function () {
            Route::get('conectar',                    [ShopeeController::class, 'conectar']);
            Route::post('callback',                    [ShopeeController::class, 'callback']);
            Route::post('{id}/sincronizar-pedidos',    [ShopeeController::class, 'sincronizarPedidos']);
            Route::post('{id}/sincronizar-produtos',   [ShopeeController::class, 'sincronizarProdutos']);
            Route::post('{id}/atualizar-estoque',      [ShopeeController::class, 'atualizarEstoque']);
        });

        // Ã¢â€â‚¬Ã¢â€â‚¬ CRM

        // Mapa ONS - usinas fotovoltaicas e prospeccao CRM
        Route::get('mapa-ons/usinas', [\App\Http\Controllers\Api\V1\Energia\MapaOnsController::class, 'index']);
        Route::get('mapa-ons/resumo', [\App\Http\Controllers\Api\V1\Energia\MapaOnsController::class, 'resumo']);
        Route::post('mapa-ons/sincronizar', [\App\Http\Controllers\Api\V1\Energia\MapaOnsController::class, 'sincronizar']);
        Route::post('mapa-ons/usinas/{id}/importar-lead', [\App\Http\Controllers\Api\V1\Energia\MapaOnsController::class, 'importarLead']);
        Route::get('mapa-aneel/microgeracao', [\App\Http\Controllers\Api\V1\Energia\MapaAneelController::class, 'index']);
        Route::get('mapa-aneel/microgeracao-residencial-comercial', [\App\Http\Controllers\Api\V1\Energia\MapaAneelController::class, 'microgeracaoResidencialComercial']);
        Route::get('mapa-aneel/resumo', [\App\Http\Controllers\Api\V1\Energia\MapaAneelController::class, 'resumo']);
        Route::get('mapa-aneel/filtros', [\App\Http\Controllers\Api\V1\Energia\MapaAneelController::class, 'filtros']);
        Route::get('mapa-aneel/filtros-residencial-comercial', [\App\Http\Controllers\Api\V1\Energia\MapaAneelController::class, 'filtrosResidencialComercial']);
        Route::post('mapa-aneel/localizar', [\App\Http\Controllers\Api\V1\Energia\MapaAneelController::class, 'localizar']);
        Route::post('mapa-aneel/importar-lead', [\App\Http\Controllers\Api\V1\Energia\MapaAneelController::class, 'importarLead']);
        Route::get('mapa-ccee/datasets', [\App\Http\Controllers\Api\V1\Energia\MapaCceeController::class, 'datasets']);
        Route::get('mapa-ccee/catalogo', [\App\Http\Controllers\Api\V1\Energia\MapaCceeController::class, 'catalogo']);
        Route::get('mapa-ccee/consulta', [\App\Http\Controllers\Api\V1\Energia\MapaCceeController::class, 'consulta']);
        Route::get('pipelines',        [PipelineController::class, 'index']);
        Route::post('pipelines',       [PipelineController::class, 'store']);
        Route::put('pipelines/{id}',   [PipelineController::class, 'update']);
        Route::delete('pipelines/{id}',[PipelineController::class, 'destroy']);


        Route::get('cnpj/fontes', [\App\Http\Controllers\Api\V1\Utilitarios\CnpjController::class, 'fontes']);
        Route::get('cnpj/consultar', [\App\Http\Controllers\Api\V1\Utilitarios\CnpjController::class, 'consultar']);
        Route::post('cnpj/lote', [\App\Http\Controllers\Api\V1\Utilitarios\CnpjController::class, 'lote']);
        Route::post('cnpj/importar-lead', [\App\Http\Controllers\Api\V1\Utilitarios\CnpjController::class, 'importarLead']);
        Route::get('crm/google-business/status', [\App\Http\Controllers\Api\V1\Crm\GoogleBusinessController::class, 'status']);
        Route::post('crm/google-business/pesquisar', [\App\Http\Controllers\Api\V1\Crm\GoogleBusinessController::class, 'pesquisar']);
        Route::post('crm/google-business/importar-lead', [\App\Http\Controllers\Api\V1\Crm\GoogleBusinessController::class, 'importarLead']);
        Route::get('leads/kanban',        [LeadController::class, 'kanban']);
        Route::post('leads/{id}/mover-etapa', [LeadController::class, 'moverEtapa']);
        Route::post('leads/{id}/ganho',    [LeadController::class, 'marcarGanho']);
        Route::post('leads/{id}/perdido',  [LeadController::class, 'marcarPerdido']);
        Route::get('leads/{id}/timeline', [LeadController::class, 'timeline']);
        Route::post('leads/{id}/atividades', [LeadController::class, 'registrarAtividade']);
        Route::put('leads/{id}/atividades/{atividade}', [LeadController::class, 'atualizarAtividade']);
        Route::get('crm/resumo-operacional', [LeadController::class, 'resumoOperacional']);
        Route::get('crm/gestao', [CrmManagementController::class, 'index']);
        Route::post('crm/motivos-perda', [CrmManagementController::class, 'saveReason']);
        Route::put('crm/motivos-perda/{id}', [CrmManagementController::class, 'saveReason']);
        Route::post('crm/automacoes', [CrmManagementController::class, 'saveAutomation']);
        Route::put('crm/automacoes/{id}', [CrmManagementController::class, 'saveAutomation']);
        Route::post('crm/metas', [CrmManagementController::class, 'saveGoal']);
        Route::get('crm/catalogo-produtos', [CrmManagementController::class, 'catalog']);
        Route::get('leads/{id}/produtos', [CrmManagementController::class, 'products']);
        Route::post('leads/{id}/produtos', [CrmManagementController::class, 'addProduct']);
        Route::apiResource('leads', LeadController::class);

        // Ã¢â€â‚¬Ã¢â€â‚¬ Fornecedores
        Route::get('fornecedores/ranking', [FornecedorController::class, 'ranking']);
        Route::apiResource('fornecedores', FornecedorController::class);

        // Ã¢â€â‚¬Ã¢â€â‚¬ Produtos
        Route::prefix('produtos')->group(function () {
            Route::get('/',                  [ProdutoController::class, 'index']);
            Route::post('/',                 [ProdutoController::class, 'store']);
            Route::post('importar-intelbras/iniciar', [ProdutoIntelbrasImportController::class, 'iniciar']);
            Route::post('importar-intelbras/{uuid}/produtos', [ProdutoIntelbrasImportController::class, 'produtos']);
            Route::post('importar-intelbras/{uuid}/precos', [ProdutoIntelbrasImportController::class, 'precos']);
            Route::post('importar-intelbras/{uuid}/finalizar', [ProdutoIntelbrasImportController::class, 'finalizar']);
            Route::get('curva-abc',          [ProdutoController::class, 'curvaABC']);
            Route::get('{id}',               [ProdutoController::class, 'show']);
            Route::put('{id}',               [ProdutoController::class, 'update']);
            Route::delete('{id}',            [ProdutoController::class, 'destroy']);
            Route::get('{id}/estoque',       [ProdutoController::class, 'estoque']);
                        Route::post('{id}/imagens',      [ProdutoController::class, 'uploadImagem']);
            Route::post('{id}/imagens-url',  [ProdutoController::class, 'adicionarImagemUrl']);
            Route::post('{id}/imagens/{imgId}/principal', [ProdutoController::class, 'definirImagemPrincipal']);
            Route::put('{id}/imagens-ordem', [ProdutoController::class, 'ordenarImagens']);
                                    Route::put('{id}/codigos', [ProdutoController::class, 'salvarCodigos']);
            Route::put('{id}/listas-preco', [ProdutoController::class, 'salvarListasPreco']);
            Route::put('{id}/variacoes-completas', [ProdutoController::class, 'salvarVariacoes']);
            Route::put('{id}/composicao', [ProdutoController::class, 'salvarComposicao']);
            Route::post('{id}/documentos', [ProdutoController::class, 'adicionarDocumento']);
            Route::delete('{id}/documentos/{documentoId}', [ProdutoController::class, 'removerDocumento']);
Route::post('{id}/assistente-conteudo', [ProdutoController::class, 'assistenteConteudo']);
            Route::get('{id}/assistente-conteudo/historico', [ProdutoController::class, 'historicoConteudoIa']);
            Route::post('{id}/assistente-conteudo/{conteudoId}/aplicar', [ProdutoController::class, 'aplicarConteudoIa']);
            Route::delete('{id}/imagens/{imgId}', [ProdutoController::class, 'deletarImagem']);
            Route::post('{id}/variacoes',    [ProdutoController::class, 'criarVariacao']);
        });

        // Ã¢â€â‚¬Ã¢â€â‚¬ CatÃƒÂ¡logos auxiliares de Produto
        Route::prefix('categorias')->group(function () {
            Route::get('/',       [CategoriaController::class, 'index']);
            Route::post('/',      [CategoriaController::class, 'store']);
            Route::put('{id}',    [CategoriaController::class, 'update']);
            Route::delete('{id}', [CategoriaController::class, 'destroy']);
        });
        Route::prefix('marcas')->group(function () {
            Route::get('/',       [MarcaController::class, 'index']);
            Route::post('/',      [MarcaController::class, 'store']);
            Route::put('{id}',    [MarcaController::class, 'update']);
            Route::delete('{id}', [MarcaController::class, 'destroy']);
        });
        Route::prefix('unidades-medida')->group(function () {
            Route::get('/',       [UnidadeMedidaController::class, 'index']);
            Route::post('/',      [UnidadeMedidaController::class, 'store']);
            Route::delete('{id}', [UnidadeMedidaController::class, 'destroy']);
        });


        // â”€â”€ PDV / Frente de Caixa
        Route::prefix('pdv')->group(function () {
            Route::get('bootstrap', [PdvController::class, 'bootstrap']);
            Route::get('produtos', [PdvController::class, 'buscarProdutos']);
            Route::get('clientes', [PdvController::class, 'buscarClientes']);
            Route::post('caixas/abrir', [PdvController::class, 'abrirCaixa']);
            Route::post('caixas/movimentar', [PdvController::class, 'movimentar']);
            Route::post('caixas/fechar', [PdvController::class, 'fecharCaixa']);
            Route::post('vendas', [PdvController::class, 'concluirVenda']);
            Route::get('vendas', [PdvController::class, 'vendas']);
            Route::get('vendas/{id}/comprovante', [PdvController::class, 'comprovante']);
            Route::prefix('devolucoes')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\V1\Pdv\DevolucaoFiscalController::class, 'index']);
                Route::get('venda/{id}', [\App\Http\Controllers\Api\V1\Pdv\DevolucaoFiscalController::class, 'venda']);
                Route::post('/', [\App\Http\Controllers\Api\V1\Pdv\DevolucaoFiscalController::class, 'store']);
                Route::post('{id}/receber', [\App\Http\Controllers\Api\V1\Pdv\DevolucaoFiscalController::class, 'receber']);
                Route::post('{id}/reembolsar', [\App\Http\Controllers\Api\V1\Pdv\DevolucaoFiscalController::class, 'reembolsar']);
                Route::post('{id}/preparar-nfe', [\App\Http\Controllers\Api\V1\Pdv\DevolucaoFiscalController::class, 'prepararNfe']);
                Route::post('{id}/emitir-nfe', [\App\Http\Controllers\Api\V1\Pdv\DevolucaoFiscalController::class, 'emitirNfe']);
            });

            Route::post('vendas/{id}/cancelar', [\App\Http\Controllers\Api\V1\Pdv\PdvCancelamentoController::class, 'cancelarVenda']);
            Route::post('vendas/{id}/preparar-nfce', [PdvController::class, 'prepararNfce']);
            Route::prefix('nfce')->group(function () {
                Route::get('{id}/validar', [NfcePdvController::class, 'validar']);
                Route::post('{id}/gerar', [NfcePdvController::class, 'gerar']);
                Route::post('{id}/emitir', [NfcePdvController::class, 'emitir']);
                Route::get('{id}/documento', [NfcePdvController::class, 'documento']);
                Route::get('{id}/pdf', [NfcePdvController::class, 'pdf']);
                Route::post('{id}/cancelar', [\App\Http\Controllers\Api\V1\Pdv\PdvCancelamentoController::class, 'cancelarNota']);
            });
        });

        // Ã¢â€â‚¬Ã¢â€â‚¬ Pedidos
        Route::prefix('pedidos')->group(function () {
            Route::get('/',                  [PedidoController::class, 'index']);
            Route::post('/',                 [PedidoController::class, 'store']);
            Route::get('kanban',             [PedidoController::class, 'kanban']);
            Route::get('resumo',             [PedidoController::class, 'resumo']);
            Route::get('{id}',               [PedidoController::class, 'show']);
            Route::put('{id}',               [PedidoController::class, 'update']);
            Route::post('{id}/confirmar',    [PedidoController::class, 'confirmar']);
            Route::post('{id}/cancelar',     [PedidoController::class, 'cancelar']);
            Route::post('{id}/emitir-nfe',   [PedidoController::class, 'emitirNfe']);
        });

        // Ã¢â€â‚¬Ã¢â€â‚¬ Recibos (comprovante nÃƒÂ£o fiscal Ã¢â‚¬â€ MEI vendendo p/ pessoa fÃƒÂ­sica)
        Route::get('recibos',          [ReciboController::class, 'index']);
        Route::post('recibos',         [ReciboController::class, 'store']);
        Route::get('recibos/{id}',     [ReciboController::class, 'show']);
        Route::get('recibos/{id}/pdf', [ReciboController::class, 'pdf']);

        // Ã¢â€â‚¬Ã¢â€â‚¬ Estoque
        Route::prefix('estoque')->group(function () {
            Route::get('/',         [EstoqueController::class, 'index']);
            Route::post('xml-entrada/preview', [XmlEntradaEstoqueController::class, 'preview']);
            Route::post('xml-entrada/importar', [XmlEntradaEstoqueController::class, 'importar']);
            Route::put('ajustar',   [EstoqueController::class, 'ajustar']);
            Route::post('transferencia', [EstoqueController::class, 'transferir']);
            Route::get('alertas',   [EstoqueController::class, 'alertas']);
        });
        Route::apiResource('depositos',      \App\Http\Controllers\Api\V1\Estoque\DepositoController::class);
        Route::apiResource('movimentacoes',  MovimentacaoController::class)->only(['index', 'show']);
        Route::apiResource('inventarios',    InventarioController::class);
        Route::post('inventarios/{id}/finalizar', [InventarioController::class, 'finalizar']);

        // Ã¢â€â‚¬Ã¢â€â‚¬ Financeiro
        Route::prefix('contas-receber')->group(function () {
            Route::get('/',              [ContasReceberController::class, 'index']);
            Route::post('/',             [ContasReceberController::class, 'store']);
            Route::get('vencidas',       [ContasReceberController::class, 'vencidas']);
            Route::get('a-vencer',       [ContasReceberController::class, 'aVencer']);
            Route::get('{id}',           [ContasReceberController::class, 'show']);
            Route::put('{id}',           [ContasReceberController::class, 'update']);
            Route::post('{id}/baixar',   [ContasReceberController::class, 'baixar']);
            Route::post('{id}/cancelar', [ContasReceberController::class, 'cancelar']);
            Route::post('baixa-lote',    [ContasReceberController::class, 'baixaLote']);
        });

        Route::prefix('contas-pagar')->group(function () {
            Route::get('/',              [ContasPagarController::class, 'index']);
            Route::post('/',             [ContasPagarController::class, 'store']);
            Route::get('vencidas',       [ContasPagarController::class, 'vencidas']);
            Route::get('{id}',           [ContasPagarController::class, 'show']);
            Route::put('{id}',           [ContasPagarController::class, 'update']);
            Route::post('{id}/pagar',    [ContasPagarController::class, 'pagar']);
        });

        Route::get('fluxo-caixa',  [FluxoCaixaController::class, 'index']);
        Route::get('dre',          [FluxoCaixaController::class, 'dre']);

        // Ã¢â€â‚¬Ã¢â€â‚¬ Certificados digitais
        Route::get('certificados-digitais', [CertificadoDigitalController::class, 'index']);
        Route::post('certificados-digitais/inspecionar', [CertificadoDigitalController::class, 'inspect']);
        Route::post('certificados-digitais/dispositivo', [CertificadoDigitalController::class, 'registerDevice']);
        Route::post('certificados-digitais', [CertificadoDigitalController::class, 'store']);
        Route::put('certificados-digitais/{id}', [CertificadoDigitalController::class, 'update']);
        Route::post('certificados-digitais/{id}/ativar', [CertificadoDigitalController::class, 'activate']);
        Route::get('certificados-digitais/{id}/download', [CertificadoDigitalController::class, 'download']);
        Route::delete('certificados-digitais/{id}', [CertificadoDigitalController::class, 'destroy']);

        // Ã¢â€â‚¬Ã¢â€â‚¬ Fiscal NF-e


        // Central Fiscal Profissional - Fase 23
        Route::prefix('fiscal/central')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\Api\V1\Fiscal\CentralFiscalController::class, 'dashboard']);
            Route::get('notas', [\App\Http\Controllers\Api\V1\Fiscal\CentralFiscalController::class, 'index']);
            Route::get('notas/{id}/segunda-via', [\App\Http\Controllers\Api\V1\Fiscal\CentralFiscalController::class, 'segundaVia']);
            Route::get('notas/{id}/xml', [\App\Http\Controllers\Api\V1\Fiscal\CentralFiscalController::class, 'xml']);
            Route::post('notas/{id}/consultar-sefaz', [\App\Http\Controllers\Api\V1\Fiscal\CentralFiscalController::class, 'consultar']);
            Route::get('notas/{id}/historico', [\App\Http\Controllers\Api\V1\Fiscal\CentralFiscalController::class, 'historico']);
        });
        // Controle da sequÃªncia fiscal
        Route::get('fiscal/numeracao', [NumeracaoFiscalController::class, 'show']);
        Route::put('fiscal/numeracao', [NumeracaoFiscalController::class, 'update']);
        // Configuracoes fiscais, ambientes e CSC da NFC-e
        Route::get('fiscal/configuracoes', [\App\Http\Controllers\Api\V1\Fiscal\ConfiguracaoFiscalController::class, 'show']);
        Route::put('fiscal/configuracoes', [\App\Http\Controllers\Api\V1\Fiscal\ConfiguracaoFiscalController::class, 'update']);
        Route::post('fiscal/configuracoes/testar', [\App\Http\Controllers\Api\V1\Fiscal\ConfiguracaoFiscalController::class, 'testar']);
        Route::post('fiscal/csc', [\App\Http\Controllers\Api\V1\Fiscal\ConfiguracaoFiscalController::class, 'storeCsc']);
        Route::delete('fiscal/csc/{id}', [\App\Http\Controllers\Api\V1\Fiscal\ConfiguracaoFiscalController::class, 'destroyCsc']);
        Route::prefix('nfe')->group(function () {
            Route::get('/',                      [NFeController::class, 'index']);
            Route::post('/',                     [NFeController::class, 'store']);
            Route::get('consultar/{chave}',      [NFeController::class, 'consultar']);
            Route::get('{id}',                   [NFeController::class, 'show']);
            Route::put('{id}',                   [NFeController::class, 'update']);
            Route::delete('{id}',                [NFeController::class, 'destroy']);
            Route::post('{id}/emitir',           [NFeController::class, 'emitir']);
            Route::post('{id}/cancelar',         [NFeController::class, 'cancelar']);
            Route::post('{id}/carta-correcao',   [NFeController::class, 'cartaCorrecao']);
            Route::get('{id}/xml',               [NFeController::class, 'downloadXml']);
            Route::get('{id}/danfe',             [NFeController::class, 'downloadDanfe']);
            Route::get('{id}/danfe-arquivo',     [NFeController::class, 'downloadDanfeFile'])->name('fiscal.danfe.file');
            Route::post('{id}/enviar-email',     [NFeController::class, 'enviarEmail']);
        });
        Route::prefix('nfse')->group(function () {
            Route::get('/', [NfseController::class, 'index']);
            Route::post('/', [NfseController::class, 'store']);
        });

        // Ã¢â€â‚¬Ã¢â€â‚¬ Status de Ordem de ServiÃƒÂ§o (colunas do Kanban)
        Route::prefix('os-status')->group(function () {
            Route::get('/',       [OsStatusController::class, 'index']);
            Route::post('/',      [OsStatusController::class, 'store']);
            Route::put('{id}',    [OsStatusController::class, 'update']);
            Route::delete('{id}', [OsStatusController::class, 'destroy']);
        });

        // Ã¢â€â‚¬Ã¢â€â‚¬ Ordens de ServiÃƒÂ§o
        Route::prefix('os')->group(function () {
            Route::get('/',                 [OrdemServicoController::class, 'index']);
            Route::post('/',                [OrdemServicoController::class, 'store']);
            Route::get('kanban',            [OrdemServicoController::class, 'kanban']);
            Route::get('sla',               [OrdemServicoController::class, 'sla']);
            Route::get('{id}',              [OrdemServicoController::class, 'show']);
            Route::put('{id}',              [OrdemServicoController::class, 'update']);
            Route::post('{id}/fotos',       [OrdemServicoController::class, 'uploadFoto']);
            Route::post('{id}/pecas',       [OrdemServicoController::class, 'adicionarPeca']);
            Route::post('{id}/mudar-status',[OrdemServicoController::class, 'mudarStatus']);
            Route::get('{id}/historico',    [OrdemServicoController::class, 'historico']);
            Route::get('{id}/pdf-os',       [OrdemServicoController::class, 'pdfOs']);
        });


        // CRM - Google Meet e Microsoft Teams
        Route::get('crm/reunioes/status', [\App\Http\Controllers\Api\V1\Crm\CrmMeetingController::class, 'status']);
        Route::post('crm/reunioes', [\App\Http\Controllers\Api\V1\Crm\CrmMeetingController::class, 'store']);



        // â”€â”€ Marketplace Hub e integraÃ§Ãµes (consolidado)
        Route::prefix('marketplace-hub')->group(function () {
            Route::get('/', [MarketplaceHubController::class, 'index']);
            Route::get('/dashboard', [MarketplaceHubController::class, 'dashboard']);
        });

        Route::prefix('marketplace/mercado-livre')->group(function () {
            Route::get('status', [MercadoLivreOAuthController::class, 'status']);
            Route::post('connect', [MercadoLivreOAuthController::class, 'connect']);
            Route::post('refresh', [MercadoLivreOAuthController::class, 'refreshToken']);
            Route::post('test', [MercadoLivreOAuthController::class, 'test']);
            Route::post('disconnect', [MercadoLivreOAuthController::class, 'disconnect']);

            Route::get('catalogo', [MercadoLivreCatalogController::class, 'index']);
            Route::post('catalogo/importar', [MercadoLivreCatalogController::class, 'importar']);
            Route::post('catalogo/vincular', [MercadoLivreCatalogController::class, 'vincular']);
            Route::post('catalogo/{id}/sincronizar', [MercadoLivreCatalogController::class, 'sincronizar']);

            Route::get('pedidos', [MercadoLivreOrdersController::class, 'index']);
            Route::post('pedidos/importar', [MercadoLivreOrdersController::class, 'importar']);
            Route::get('pedidos/{id}', [MercadoLivreOrdersController::class, 'show']);
            Route::post('pedidos/{id}/atualizar', [MercadoLivreOrdersController::class, 'atualizar']);
        });

        Route::prefix('marketplace/automacao')->group(function () {
            Route::get('overview', [MercadoLivreAutomationController::class, 'overview']);
            Route::get('pedidos/{id}/validar', [MercadoLivreAutomationController::class, 'validar']);
            Route::post('pedidos/{id}/processar', [MercadoLivreAutomationController::class, 'processar']);
            Route::post('pedidos/processar-lote', [MercadoLivreAutomationController::class, 'processarLote']);
        });

        Route::prefix('marketplace/financeiro-fiscal')->group(function () {
            Route::get('overview', [MarketplaceFinanceFiscalController::class, 'overview']);
            Route::post('pedidos/{id}/preparar', [MarketplaceFinanceFiscalController::class, 'preparar']);
            Route::post('{id}/conciliar', [MarketplaceFinanceFiscalController::class, 'conciliar']);
        });

        Route::prefix('marketplace/emissao-fiscal')->group(function () {
            Route::get('/', [MarketplaceFiscalEmissionController::class, 'index']);
            Route::get('pedidos/{id}/validar', [MarketplaceFiscalEmissionController::class, 'validar']);
            Route::post('pedidos/{id}/gerar', [MarketplaceFiscalEmissionController::class, 'gerar']);
            Route::post('{id}/enfileirar', [MarketplaceFiscalEmissionController::class, 'enfileirar']);
            Route::get('{id}/xml', [MarketplaceFiscalEmissionController::class, 'xml']);
        });


        // â”€â”€ ExpediÃ§Ã£o Inteligente
        Route::prefix('expedicao')->group(function () {
            Route::get('dashboard', [ExpedicaoController::class, 'dashboard']);
            Route::get('/', [ExpedicaoController::class, 'index']);
            Route::post('/', [ExpedicaoController::class, 'criar']);
            Route::get('etiquetas', [\App\Http\Controllers\Api\V1\Expedicao\EtiquetaEnvioController::class, 'index']);
            Route::post('{id}/etiquetas/upload', [\App\Http\Controllers\Api\V1\Expedicao\EtiquetaEnvioController::class, 'upload']);
            Route::post('{id}/etiquetas/gerar', [\App\Http\Controllers\Api\V1\Expedicao\EtiquetaEnvioController::class, 'gerar']);
            Route::get('etiquetas/{etiquetaId}/download', [\App\Http\Controllers\Api\V1\Expedicao\EtiquetaEnvioController::class, 'download']);
            Route::post('etiquetas/{etiquetaId}/imprimir', [\App\Http\Controllers\Api\V1\Expedicao\EtiquetaEnvioController::class, 'imprimir']);
            Route::delete('etiquetas/{etiquetaId}', [\App\Http\Controllers\Api\V1\Expedicao\EtiquetaEnvioController::class, 'destroy']);
            Route::get('{id}', [ExpedicaoController::class, 'show']);
            Route::post('{id}/iniciar-separacao', [ExpedicaoController::class, 'iniciarSeparacao']);
            Route::post('{id}/itens/{itemId}/separar', [ExpedicaoController::class, 'separarItem']);
            Route::post('{id}/concluir-separacao', [ExpedicaoController::class, 'concluirSeparacao']);
            Route::post('{id}/iniciar-conferencia', [ExpedicaoController::class, 'iniciarConferencia']);
            Route::post('{id}/itens/{itemId}/conferir', [ExpedicaoController::class, 'conferirItem']);
            Route::post('{id}/concluir-conferencia', [ExpedicaoController::class, 'concluirConferencia']);
            Route::post('{id}/volumes', [ExpedicaoController::class, 'criarVolume']);
            Route::post('{id}/expedir', [ExpedicaoController::class, 'expedir']);
            Route::get('{id}/romaneio', [ExpedicaoController::class, 'romaneio']);
        });

        // â”€â”€ Central de Transportadoras
        Route::prefix('logistica/transportadoras')->group(function () {
            Route::get('dashboard', [TransportadoraController::class, 'dashboard']);
            Route::post('cotar', [TransportadoraController::class, 'cotar']);
            Route::post('cotacoes/{id}/contratar', [TransportadoraController::class, 'contratar']);
            Route::get('envios', [TransportadoraController::class, 'envios']);
            Route::post('envios/{id}/status', [TransportadoraController::class, 'atualizarStatus']);
            Route::get('envios/{id}/rastreamento', [TransportadoraController::class, 'rastreamento']);
            Route::get('envios/{id}/etiqueta', [TransportadoraController::class, 'etiqueta']);
        });

        // â”€â”€ Central de Transportadoras â€” Fase 11I
        Route::prefix('logistica')->group(function () {
            Route::get('overview', [LogisticaController::class, 'overview']);
            Route::get('contas', [LogisticaController::class, 'contas']);
            Route::post('contas/{transportadoraId}', [LogisticaController::class, 'salvarConta']);
            Route::post('cotar', [LogisticaController::class, 'cotar']);
            Route::get('envios', [LogisticaController::class, 'envios']);
            Route::post('envios', [LogisticaController::class, 'criarEnvio']);
            Route::post('envios/{id}/postar', [LogisticaController::class, 'postar']);
            Route::get('envios/{id}/rastreio', [LogisticaController::class, 'rastreio']);
        });


        // â”€â”€ CatÃ¡logo oficial NCM / Sistema Classif
        Route::prefix('ncm')->group(function () {
            Route::get('buscar', [NcmCatalogoController::class, 'buscar']);
            Route::post('sincronizar', [NcmCatalogoController::class, 'sincronizar']);
            Route::post('registrar-escolha', [NcmCatalogoController::class, 'registrarEscolha']);
        });


        // â”€â”€ EspaÃ§o do Contador â€” Fase 11L.1
        Route::prefix('contador')->group(function () {
            Route::get('/', [ContadorController::class, 'resumo']);
            Route::post('dados', [ContadorController::class, 'salvarDados']);
            Route::post('agendamento', [ContadorController::class, 'salvarAgendamento']);
            Route::post('exportacoes', [ContadorController::class, 'gerar']);
            Route::get('exportacoes/{id}/download', [ContadorController::class, 'download']);
            Route::delete('exportacoes/{id}', [ContadorController::class, 'excluir']);
        });


        // Marketplace de TÃ©cnicos integrado ao ERP

    }); // fim auth
}); // fim v1

Route::get('marketplace/mercado-livre/callback', [MercadoLivreOAuthController::class, 'callback']);

require __DIR__.'/marketplace_tecnicos.php';
