<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\SocialAuthController;
use App\Http\Controllers\Api\V1\Cadastros\{ClienteController, FornecedorController, ProdutoController, CategoriaController, MarcaController, UnidadeMedidaController};
use App\Http\Controllers\Api\V1\Comercial\{PedidoController, ReciboController};
use App\Http\Controllers\Api\V1\Compras\PedidoCompraController;
use App\Http\Controllers\Api\V1\Estoque\{EstoqueController, MovimentacaoController, InventarioController};
use App\Http\Controllers\Api\V1\Financeiro\{ContasReceberController, ContasPagarController, FluxoCaixaController};
use App\Http\Controllers\Api\V1\Fiscal\NFe\NFeController;
use App\Http\Controllers\Api\V1\Fiscal\CertificadoDigitalController;
use App\Http\Controllers\Api\V1\Integracao\{MercadoLivreController, ShopeeController, IntegracaoController, MarketplaceConfigController, TransportadoraConfigController, CsvImportController};
use App\Http\Controllers\Api\V1\OS\{OrdemServicoController, OsStatusController};
use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use App\Http\Controllers\Api\V1\Crm\{LeadController, PipelineController};
use App\Http\Controllers\Api\V1\Configuracao\ApiIntegrationController;

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// PÃšBLICO
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
Route::prefix('v1')->group(function () {

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

    // UtilitÃ¡rios
    Route::get('cep/{cep}',   fn($cep) => response()->json(
        \Illuminate\Support\Facades\Http::get("https://viacep.com.br/ws/{$cep}/json/")->json()
    ));

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// AUTENTICADO
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::middleware(['auth:sanctum', 'empresa'])->group(function () {

        // â”€â”€ Auth

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


        // â”€â”€ Central de APIs
        Route::prefix('configuracoes/apis')->group(function () {
            Route::get('/', [ApiIntegrationController::class, 'index']);
            Route::put('{provider}', [ApiIntegrationController::class, 'save']);
            Route::post('{provider}/testar', [ApiIntegrationController::class, 'test']);
        });

        // â”€â”€ Dashboard

        // Dados completos da empresa
        Route::get('empresa/perfil', [\App\Http\Controllers\Api\V1\Configuracoes\EmpresaController::class, 'show']);
        Route::put('empresa/perfil', [\App\Http\Controllers\Api\V1\Configuracoes\EmpresaController::class, 'update']);
        Route::post('empresa/logo', [\App\Http\Controllers\Api\V1\Configuracoes\EmpresaController::class, 'uploadLogo']);
        Route::delete('empresa/logo', [\App\Http\Controllers\Api\V1\Configuracoes\EmpresaController::class, 'removerLogo']);
        Route::prefix('dashboard')->group(function () {
            Route::get('/',        [DashboardController::class, 'index']);
            Route::get('mapa',     [DashboardController::class, 'mapa']);
            Route::get('vendas',   [DashboardController::class, 'vendas']);
            Route::get('resumo',   [DashboardController::class, 'resumo']);
        });

        // â”€â”€ Clientes
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

        // â”€â”€ Compras
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

        // â”€â”€ IntegraÃ§Ãµes / Marketplace
        Route::get('integracoes',        [IntegracaoController::class, 'index']);
        Route::delete('integracoes/{id}',[IntegracaoController::class, 'destroy']);

        // â”€â”€ ConfiguraÃ§Ã£o manual de APIs dos marketplaces
        Route::get('marketplaces/catalogo', [MarketplaceConfigController::class, 'catalogo']);
        Route::post('marketplaces/{tipo}/configurar', [MarketplaceConfigController::class, 'salvar']);
        Route::get('marketplaces/{tipo}/testar', [MarketplaceConfigController::class, 'testar']);

        // â”€â”€ APIs de logÃ­stica / transportadoras
        Route::get('transportadoras/catalogo', [TransportadoraConfigController::class, 'catalogo']);
        Route::get('transportadoras', [TransportadoraConfigController::class, 'index']);
        Route::post('transportadoras/{apiTipo}/configurar', [TransportadoraConfigController::class, 'salvar']);
        Route::get('transportadoras/{apiTipo}/testar', [TransportadoraConfigController::class, 'testar']);

        // â”€â”€ Cadastro e upload de CSV
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

        // â”€â”€ CRM

        // Mapa ONS - usinas fotovoltaicas e prospeccao CRM
        Route::get('mapa-ons/usinas', [\App\Http\Controllers\Api\V1\Energia\MapaOnsController::class, 'index']);
        Route::get('mapa-ons/resumo', [\App\Http\Controllers\Api\V1\Energia\MapaOnsController::class, 'resumo']);
        Route::post('mapa-ons/sincronizar', [\App\Http\Controllers\Api\V1\Energia\MapaOnsController::class, 'sincronizar']);
        Route::post('mapa-ons/usinas/{id}/importar-lead', [\App\Http\Controllers\Api\V1\Energia\MapaOnsController::class, 'importarLead']);
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
        Route::apiResource('leads', LeadController::class);

        // â”€â”€ Fornecedores
        Route::get('fornecedores/ranking', [FornecedorController::class, 'ranking']);
        Route::apiResource('fornecedores', FornecedorController::class);

        // â”€â”€ Produtos
        Route::prefix('produtos')->group(function () {
            Route::get('/',                  [ProdutoController::class, 'index']);
            Route::post('/',                 [ProdutoController::class, 'store']);
            Route::get('curva-abc',          [ProdutoController::class, 'curvaABC']);
            Route::get('{id}',               [ProdutoController::class, 'show']);
            Route::put('{id}',               [ProdutoController::class, 'update']);
            Route::delete('{id}',            [ProdutoController::class, 'destroy']);
            Route::get('{id}/estoque',       [ProdutoController::class, 'estoque']);
            Route::post('{id}/imagens',      [ProdutoController::class, 'uploadImagem']);
            Route::delete('{id}/imagens/{imgId}', [ProdutoController::class, 'deletarImagem']);
            Route::post('{id}/variacoes',    [ProdutoController::class, 'criarVariacao']);
        });

        // â”€â”€ CatÃ¡logos auxiliares de Produto
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

        // â”€â”€ Pedidos
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

        // â”€â”€ Recibos (comprovante nÃ£o fiscal â€” MEI vendendo p/ pessoa fÃ­sica)
        Route::get('recibos',          [ReciboController::class, 'index']);
        Route::post('recibos',         [ReciboController::class, 'store']);
        Route::get('recibos/{id}',     [ReciboController::class, 'show']);
        Route::get('recibos/{id}/pdf', [ReciboController::class, 'pdf']);

        // â”€â”€ Estoque
        Route::prefix('estoque')->group(function () {
            Route::get('/',         [EstoqueController::class, 'index']);
            Route::put('ajustar',   [EstoqueController::class, 'ajustar']);
            Route::post('transferencia', [EstoqueController::class, 'transferir']);
            Route::get('alertas',   [EstoqueController::class, 'alertas']);
        });
        Route::apiResource('depositos',      \App\Http\Controllers\Api\V1\Estoque\DepositoController::class);
        Route::apiResource('movimentacoes',  MovimentacaoController::class)->only(['index', 'show']);
        Route::apiResource('inventarios',    InventarioController::class);
        Route::post('inventarios/{id}/finalizar', [InventarioController::class, 'finalizar']);

        // â”€â”€ Financeiro
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

        // â”€â”€ Certificados digitais
        Route::get('certificados-digitais', [CertificadoDigitalController::class, 'index']);
        Route::post('certificados-digitais', [CertificadoDigitalController::class, 'store']);
        Route::put('certificados-digitais/{id}', [CertificadoDigitalController::class, 'update']);
        Route::post('certificados-digitais/{id}/ativar', [CertificadoDigitalController::class, 'activate']);
        Route::get('certificados-digitais/{id}/download', [CertificadoDigitalController::class, 'download']);
        Route::delete('certificados-digitais/{id}', [CertificadoDigitalController::class, 'destroy']);

        // â”€â”€ Fiscal NF-e

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
            Route::post('{id}/enviar-email',     [NFeController::class, 'enviarEmail']);
        });

        // â”€â”€ Status de Ordem de ServiÃ§o (colunas do Kanban)
        Route::prefix('os-status')->group(function () {
            Route::get('/',       [OsStatusController::class, 'index']);
            Route::post('/',      [OsStatusController::class, 'store']);
            Route::put('{id}',    [OsStatusController::class, 'update']);
            Route::delete('{id}', [OsStatusController::class, 'destroy']);
        });

        // â”€â”€ Ordens de ServiÃ§o
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

    }); // fim auth
}); // fim v1
