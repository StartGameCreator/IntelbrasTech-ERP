# IntelbrasTech ERP — Estrutura Laravel 11

```
intelbrastech-erp/
│
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── SyncMarketplaceCommand.php
│   │       ├── ProcessarNFeCommand.php
│   │       ├── EnviarBoletoVencimentoCommand.php
│   │       └── BackupBancoDadosCommand.php
│   │
│   ├── Events/
│   │   ├── PedidoCriadoEvent.php
│   │   ├── EstoqueBaixoEvent.php
│   │   ├── NFeAutorizadaEvent.php
│   │   └── OsConcluidaEvent.php
│   │
│   ├── Exceptions/
│   │   ├── EstoqueInsuficienteException.php
│   │   ├── NFeEmissaoException.php
│   │   └── IntegracaoMarketplaceException.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   ├── V1/
│   │   │   │   │   ├── Auth/
│   │   │   │   │   │   ├── AuthController.php
│   │   │   │   │   │   └── PerfilController.php
│   │   │   │   │   ├── Cadastros/
│   │   │   │   │   │   ├── ClienteController.php
│   │   │   │   │   │   ├── FornecedorController.php
│   │   │   │   │   │   ├── ProdutoController.php
│   │   │   │   │   │   ├── CategoriaController.php
│   │   │   │   │   │   └── TransportadoraController.php
│   │   │   │   │   ├── Comercial/
│   │   │   │   │   │   ├── PedidoController.php
│   │   │   │   │   │   ├── PedidoItemController.php
│   │   │   │   │   │   └── OrcamentoController.php
│   │   │   │   │   ├── Estoque/
│   │   │   │   │   │   ├── EstoqueController.php
│   │   │   │   │   │   ├── MovimentacaoController.php
│   │   │   │   │   │   ├── InventarioController.php
│   │   │   │   │   │   └── TransferenciaController.php
│   │   │   │   │   ├── Financeiro/
│   │   │   │   │   │   ├── ContasReceberController.php
│   │   │   │   │   │   ├── ContasPagarController.php
│   │   │   │   │   │   ├── LancamentoController.php
│   │   │   │   │   │   ├── ContaBancariaController.php
│   │   │   │   │   │   └── FluxoCaixaController.php
│   │   │   │   │   ├── Fiscal/
│   │   │   │   │   │   ├── NFe/
│   │   │   │   │   │   │   ├── NFeController.php
│   │   │   │   │   │   │   ├── NFeCancelamentoController.php
│   │   │   │   │   │   │   ├── NFeCartaCorrecaoController.php
│   │   │   │   │   │   │   └── NFeInutilizacaoController.php
│   │   │   │   │   │   ├── NFCeController.php
│   │   │   │   │   │   ├── NFSeController.php
│   │   │   │   │   │   └── MdfeController.php
│   │   │   │   │   ├── Integracao/
│   │   │   │   │   │   ├── MercadoLivreController.php
│   │   │   │   │   │   ├── ShopeeController.php
│   │   │   │   │   │   ├── AmazonController.php
│   │   │   │   │   │   ├── NuvemshopController.php
│   │   │   │   │   │   └── WooCommerceController.php
│   │   │   │   │   ├── CRM/
│   │   │   │   │   │   ├── LeadController.php
│   │   │   │   │   │   ├── PipelineController.php
│   │   │   │   │   │   └── AtividadeController.php
│   │   │   │   │   ├── OS/
│   │   │   │   │   │   ├── OrdemServicoController.php
│   │   │   │   │   │   └── OsFotoController.php
│   │   │   │   │   ├── RH/
│   │   │   │   │   │   ├── FuncionarioController.php
│   │   │   │   │   │   └── ComissaoController.php
│   │   │   │   │   └── Dashboard/
│   │   │   │   │       ├── DashboardController.php
│   │   │   │   │       └── MapaController.php
│   │   │   │
│   │   ├── Middleware/
│   │   │   ├── EmpresaMiddleware.php
│   │   │   ├── PermissaoMiddleware.php
│   │   │   ├── PlanoMiddleware.php
│   │   │   └── RateLimitMiddleware.php
│   │   │
│   │   └── Requests/
│   │       ├── Cliente/
│   │       │   ├── StoreClienteRequest.php
│   │       │   └── UpdateClienteRequest.php
│   │       ├── Produto/
│   │       │   ├── StoreProdutoRequest.php
│   │       │   └── UpdateProdutoRequest.php
│   │       ├── Pedido/
│   │       │   └── StorePedidoRequest.php
│   │       └── NFe/
│   │           └── EmitirNFeRequest.php
│   │
│   ├── Jobs/
│   │   ├── EmitirNFeJob.php
│   │   ├── SyncProdutoMarketplaceJob.php
│   │   ├── SyncPedidoMarketplaceJob.php
│   │   ├── RastrearEnvioJob.php
│   │   ├── EnviarWhatsAppJob.php
│   │   └── ProcessarWebhookJob.php
│   │
│   ├── Listeners/
│   │   ├── PedidoCriado/
│   │   │   ├── ReservarEstoqueListener.php
│   │   │   ├── CriarContaReceberListener.php
│   │   │   └── NotificarVendedorListener.php
│   │   └── NFeAutorizada/
│   │       ├── EnviarEmailClienteListener.php
│   │       └── BaixarEstoqueListener.php
│   │
│   ├── Models/
│   │   ├── Empresa.php
│   │   ├── Usuario.php
│   │   ├── Perfil.php
│   │   ├── Permissao.php
│   │   ├── Cliente.php
│   │   ├── ClienteEndereco.php
│   │   ├── Fornecedor.php
│   │   ├── Produto.php
│   │   ├── ProdutoVariacao.php
│   │   ├── ProdutoImagem.php
│   │   ├── Categoria.php
│   │   ├── Marca.php
│   │   ├── Estoque.php
│   │   ├── MovimentacaoEstoque.php
│   │   ├── Deposito.php
│   │   ├── Inventario.php
│   │   ├── Pedido.php
│   │   ├── PedidoItem.php
│   │   ├── PedidoCompra.php
│   │   ├── ContaReceber.php
│   │   ├── ContaPagar.php
│   │   ├── ContaBancaria.php
│   │   ├── Lancamento.php
│   │   ├── PlanoContas.php
│   │   ├── FormaPagamento.php
│   │   ├── NotaFiscal.php
│   │   ├── NotaFiscalItem.php
│   │   ├── Integracao.php
│   │   ├── MarketplaceProduto.php
│   │   ├── MarketplacePedido.php
│   │   ├── Transportadora.php
│   │   ├── PedidoEnvio.php
│   │   ├── Rastreamento.php
│   │   ├── Lead.php
│   │   ├── Pipeline.php
│   │   ├── PipelineEtapa.php
│   │   ├── Atividade.php
│   │   ├── OrdemServico.php
│   │   ├── OsFoto.php
│   │   ├── OsPeca.php
│   │   ├── Funcionario.php
│   │   ├── Comissao.php
│   │   └── Notificacao.php
│   │
│   ├── Services/
│   │   ├── Auth/
│   │   │   └── AuthService.php
│   │   ├── Estoque/
│   │   │   ├── EstoqueService.php
│   │   │   └── InventarioService.php
│   │   ├── Financeiro/
│   │   │   ├── ContasReceberService.php
│   │   │   ├── ContasPagarService.php
│   │   │   └── FluxoCaixaService.php
│   │   ├── Fiscal/
│   │   │   ├── NFeService.php
│   │   │   ├── NFCeService.php
│   │   │   ├── SefazService.php
│   │   │   └── DanfeService.php
│   │   ├── Integracao/
│   │   │   ├── MercadoLivreService.php
│   │   │   ├── ShopeeService.php
│   │   │   ├── AmazonService.php
│   │   │   ├── NuvemshopService.php
│   │   │   └── WooCommerceService.php
│   │   ├── Logistica/
│   │   │   ├── CorreiosService.php
│   │   │   ├── JadlogService.php
│   │   │   └── EtiquetaService.php
│   │   ├── GeoService.php
│   │   ├── WhatsAppService.php
│   │   └── IAService.php
│   │
│   └── Traits/
│       ├── HasEmpresa.php
│       ├── HasPermissoes.php
│       └── HasAuditoria.php
│
├── config/
│   ├── erp.php          # Configurações globais do ERP
│   ├── nfe.php          # NF-e / SEFAZ
│   ├── integrações.php  # Marketplaces
│   └── ia.php           # IA Config
│
├── database/
│   ├── migrations/      # Uma migration por tabela
│   ├── seeders/
│   │   ├── EmpresaSeeder.php
│   │   ├── PermissaoSeeder.php
│   │   └── DemoSeeder.php
│   └── factories/
│
├── routes/
│   ├── api.php          # Todas as rotas da API
│   ├── api_v1.php       # Rotas v1 (separadas por módulo)
│   └── webhooks.php     # Webhooks dos marketplaces
│
├── docker/
│   ├── nginx/
│   ├── php/
│   └── postgres/
│
├── docker-compose.yml
├── Dockerfile
└── .env.example
```

## Arquitetura de Camadas

```
Request → Middleware → Controller → Service → Repository → Model → DB
                                         ↓
                                       Jobs (async)
                                         ↓
                                     Queue (Redis)
```

## Principais Padrões Utilizados

- **Repository Pattern** — abstração do banco por módulo
- **Service Layer** — lógica de negócio isolada
- **DTO (Data Transfer Objects)** — transferência limpa de dados
- **Events & Listeners** — desacoplamento de ações
- **Jobs & Queues** — processamento assíncrono (NF-e, sync marketplace)
- **Resource API** — respostas padronizadas JSON:API
- **Sanctum JWT** — autenticação stateless
- **Multitenancy** — isolamento por empresa_id em todas as queries
