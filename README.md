# IntelbrasTech ERP

Sistema de Gestão Empresarial completo desenvolvido com Laravel 11 + Vue.js 3 + PostgreSQL.

## Stack

- **Backend**: Laravel 11 / PHP 8.4
- **Frontend**: Vue.js 3 / Tailwind CSS / Pinia
- **Banco**: PostgreSQL 16
- **Cache/Filas**: Redis 7
- **Mensageria**: RabbitMQ 3.12
- **Storage**: MinIO (S3-compatível)
- **Container**: Docker + Docker Compose

## Módulos

- Dashboard executivo com KPIs em tempo real
- Gestão de Clientes (com mapa GPS)
- Gestão de Produtos / Catálogo / Variações
- Controle de Estoque (multi-depósito, inventário)
- Pedidos de Venda (multi-canal)
- Compras
- Financeiro (CR, CP, Fluxo de Caixa, DRE)
- Fiscal (NF-e, NFC-e, NFS-e — integração SEFAZ)
- Logística (Correios, Jadlog, Loggi)
- Marketplaces (Mercado Livre, Shopee, Amazon, Nuvemshop)
- CRM (Pipeline de Vendas, Leads)
- Assistência Técnica (OS exclusivo Intelbras)
- RH / Comissões
- Mapa Inteligente GIS
- Dark Mode completo

## Instalação rápida (Docker)

```bash
cp .env.example .env
cd docker
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Acesse: http://localhost

## Credenciais padrão (desenvolvimento)

- **E-mail**: admin@intelbrastech.com.br
- **Senha**: Admin@123

## Estrutura do Projeto

```
intelbrastech-erp/
├── backend/          # Laravel 11 API
│   ├── app/
│   │   ├── Http/Controllers/Api/V1/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Traits/
│   ├── database/
│   └── routes/
├── frontend/         # Vue.js 3 SPA
│   └── src/
│       ├── views/
│       ├── components/
│       ├── stores/
│       ├── composables/
│       ├── router/
│       └── services/
├── docker/           # Docker configs
├── sql/              # Schema PostgreSQL
└── docs/             # Documentação
```

## Fases de Desenvolvimento

- **Fase 1** (concluída): Arquitetura + ERD + SQL + Docker
- **Fase 2** (concluída): Backend Laravel (Models, Controllers, Services)
- **Fase 3** (concluída): Frontend Vue.js 3 (Layout, Dashboard, Clientes, Pedidos, OS, Mapa)
- **Fase 4**: Views completas de todos os módulos
- **Fase 5**: Integração com SEFAZ (NF-e real)
- **Fase 6**: App Mobile (React Native)

## Licença

Proprietário — IntelbrasTech © 2025
