# IntelbrasTech ERP — Arquitetura Técnica Completa

## Stack Tecnológico

| Camada | Tecnologia | Versão | Função |
|---|---|---|---|
| Backend | Laravel | 11.x | API REST + Regras de negócio |
| Runtime | PHP | 8.4 | Interpretador |
| Frontend | Vue.js | 3.x | Interface de usuário |
| UI | Tailwind CSS + shadcn/vue | Latest | Estilização |
| Admin | Filament PHP | 3.x | Painel admin |
| Banco Principal | PostgreSQL | 16 | Dados transacionais |
| Cache/Fila | Redis | 7 | Cache, sessões, filas |
| Mensageria | RabbitMQ | 3.12 | Integração assíncrona |
| Storage | AWS S3 / MinIO | Latest | Arquivos, XMLs, PDFs |
| Web Server | Nginx | 1.25 | Proxy reverso |
| Containers | Docker + Compose | Latest | Orquestração |
| Auth | Laravel Sanctum | 3.x | JWT / SPA tokens |
| WebSockets | Laravel Reverb | 1.x | Notificações em tempo real |
| Testes | Pest PHP | 2.x | Testes automatizados |

---

## Diagrama de Arquitetura

```
┌─────────────────────────────────────────────────────────────┐
│                     CLIENTE / BROWSER                        │
│          Vue.js 3 + Tailwind CSS + Pinia + Vue Router        │
└───────────────────────────┬─────────────────────────────────┘
                            │ HTTPS / WebSocket
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    NGINX (Reverse Proxy)                      │
│              SSL Termination + Rate Limiting                  │
└───────┬───────────────────────────────────────┬─────────────┘
        │ /api/*                                │ /app/*
        ▼                                       ▼
┌───────────────────────┐         ┌─────────────────────────┐
│   Laravel 11 (API)    │         │  Vue.js SPA (Frontend)  │
│   PHP 8.4 + FPM       │         │  Vite Build             │
│                       │         └─────────────────────────┘
│  ┌─────────────────┐  │
│  │   Middleware     │  │    ┌──────────────────────┐
│  │ - Auth (JWT)     │  │    │  Queue Worker        │
│  │ - Empresa ID     │  │    │  (Laravel Horizon)   │
│  │ - Rate Limit     │  │    │                      │
│  │ - Permissões     │  │    │  Jobs:               │
│  └────────┬────────┘  │    │  - EmitirNFe         │
│           │            │    │  - SyncMarketplace   │
│  ┌────────▼────────┐  │◄──►│  - EnviarEmail       │
│  │   Controllers   │  │    │  - RastrearEnvio     │
│  └────────┬────────┘  │    │  - WhatsApp          │
│           │            │    └──────────┬───────────┘
│  ┌────────▼────────┐  │               │
│  │    Services     │  │    ┌──────────▼───────────┐
│  └────────┬────────┘  │    │      Redis 7          │
│           │            │    │  - Cache             │
│  ┌────────▼────────┐  │    │  - Filas             │
│  │     Models      │  │    │  - Sessões           │
│  │  (Eloquent ORM) │  │    └──────────────────────┘
│  └────────┬────────┘  │
└───────────┼────────────┘
            │
┌───────────▼────────────────────────────────────────────────┐
│                    PostgreSQL 16                             │
│        120+ tabelas | PostGIS | pg_trgm | uuid-ossp         │
└─────────────────────────────────────────────────────────────┘
            │
┌───────────▼──────────────────────────────────────────────┐
│              SERVIÇOS EXTERNOS / APIs                      │
│                                                           │
│  SEFAZ ──── Emissão NF-e/NFC-e/NFS-e                     │
│  Correios ─ Rastreamento / Frete                          │
│  ViaCEP ──  Busca de endereços                            │
│  ML API ──  Mercado Livre                                 │
│  Shopee ──  API Shopee Partners                           │
│  Amazon ──  SP-API                                        │
│  Nuvemshop  API Nuvemshop                                 │
│  WhatsApp ─ Meta Business API                             │
│  OpenAI ──  IA / Análises                                 │
│  Google ──  Maps / Geolocation                            │
│  AWS S3 ──  Storage de arquivos                           │
└──────────────────────────────────────────────────────────┘
```

---

## Fluxo de Emissão NF-e

```
Pedido Confirmado
      │
      ▼
Controller NFeController::emitir()
      │
      ▼
NFeService::montar()  ← Busca dados pedido, cliente, produtos, config fiscal
      │
      ▼
NFeService::assinar() ← Certificado Digital A1 (openssl)
      │
      ▼
NFeService::enviarSefaz() ← SOAP/HTTPS para SEFAZ da UF
      │
      ├── Autorizada → Salva XML + protocolo + DANFE
      │                Notifica cliente por email
      │                Baixa estoque
      │                Lança financeiro
      │
      └── Rejeitada → Loga erro + notifica usuário
```

---

## Fluxo de Sincronização com Marketplaces

```
Marketplace (ML/Shopee/Amazon)
      │
      ├── Webhook → POST /webhooks/{marketplace}
      │                │
      │                ▼
      │          Job: ProcessarWebhookJob
      │                │
      │                ▼
      │          Service: criar pedido interno
      │
      └── Polling (cron a cada 15min)
               │
               ▼
         Job: SyncPedidoMarketplaceJob
               │
               ▼
         Criar/atualizar pedidos, clientes, estoque
```

---

## Módulos e Fases de Desenvolvimento

### FASE 1 — MVP (meses 1–6)
- [x] Autenticação + Multiempresa + Permissões
- [x] Clientes + Fornecedores
- [x] Produtos + Categorias + Variações
- [x] Estoque + Depósitos
- [x] Pedidos de Venda
- [x] NF-e completo
- [x] Contas a Receber / Pagar básico
- [x] Mercado Livre + Shopee
- [x] Dashboard básico
- [x] Dark Mode

### FASE 2 — Expansão Comercial (meses 7–12)
- [ ] NFC-e (PDV)
- [ ] NFS-e
- [ ] Compras
- [ ] Amazon + Nuvemshop + WooCommerce
- [ ] Logística + Etiquetas
- [ ] CRM + Pipeline de Vendas
- [ ] Tabelas de Preço
- [ ] WhatsApp API
- [ ] Comissões

### FASE 3 — Enterprise (meses 13–18)
- [ ] Assistência Técnica completa (OS Intelbras)
- [ ] RH + Folha simplificada
- [ ] DRE completo
- [ ] Mapa GIS avançado
- [ ] MDF-e / CT-e
- [ ] IA: previsão de vendas
- [ ] App Mobile (React Native)
- [ ] API pública para terceiros

---

## Segurança

| Item | Implementação |
|---|---|
| Autenticação | Sanctum Tokens (Bearer) |
| Autorização | Gates + Policies por módulo |
| Multitenancy | Scoped queries por empresa_id |
| Rate Limiting | 60 req/min (autenticado), 10 (público) |
| SQL Injection | Eloquent ORM + prepared statements |
| XSS | Vue.js escaping automático |
| CSRF | Token CSRF (SPA com Sanctum) |
| Upload | Validação MIME + antivírus ClamAV |
| Senhas | bcrypt + min. 8 caracteres |
| Logs | Auditoria completa em logs_sistema |
| HTTPS | SSL obrigatório (Let's Encrypt) |

---

## Performance

| Técnica | Onde |
|---|---|
| Redis Cache | Consultas de dashboard, catálogo |
| Query Optimization | Índices PostgreSQL em todas as FK |
| Eager Loading | Relações Eloquent sempre com with() |
| Pagination | Cursor pagination para listas grandes |
| Queue | NF-e, sync marketplace, emails |
| CDN | Imagens de produtos via S3 + CloudFront |
| pg_trgm | Busca fulltext em nomes (ILIKE otimizado) |
| PostGIS | Cálculos geográficos no servidor |

---

## Estimativa de Tabelas por Módulo

| Módulo | Tabelas |
|---|---|
| Sistema / Auth | 8 |
| Clientes | 4 |
| Fornecedores | 1 |
| Produtos | 9 |
| Estoque | 5 |
| Pedidos Venda | 2 |
| Compras | 2 |
| Financeiro | 8 |
| Fiscal | 5 |
| Logística | 3 |
| Marketplaces | 4 |
| CRM | 4 |
| Assistência Técnica | 5 |
| RH | 3 |
| Dashboard/Config | 5 |
| **Total** | **~68 core + views e partitioned tables** |
