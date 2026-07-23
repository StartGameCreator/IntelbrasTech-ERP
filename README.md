# IntelbrasTech ERP

Sistema integrado de gestão empresarial desenvolvido com **Laravel 13**, **Vue 3** e API REST. O checkpoint documentado corresponde aos arquivos `backend(15).zip` e `frontend(14).zip`, recebidos em julho de 2026.

> **Situação:** desenvolvimento ativo. Esta documentação descreve a estrutura encontrada no checkpoint e diferencia funcionalidades implementadas de integrações ainda dependentes de credenciais, homologação ou implantação.

## Principais áreas

- Cadastros: empresas, clientes, fornecedores, produtos, categorias, marcas e unidades.
- Comercial: pedidos, recibos, tabelas de preço e Kanban.
- CRM: leads, pipelines, enriquecimento CNPJ, Google Business e reuniões.
- Estoque: depósitos, saldos, movimentações, inventários e entrada por XML.
- Financeiro: contas a pagar, contas a receber, fluxo de caixa, plano de contas e DRE.
- Fiscal: NF-e, NFC-e, DANFE, certificado A1, CSC, numeração, cancelamento, carta de correção e devolução fiscal.
- PDV: vendas, pagamentos, histórico, emissão NFC-e, cancelamento e devoluções.
- Marketplace: hub, Mercado Livre, Shopee, Nuvemshop, catálogo, pedidos, fiscal e automações.
- Expedição e logística: separação, volumes, etiquetas, transportadoras e rastreamento.
- Ordens de serviço: cadastro, peças, fotos, histórico e status.
- Energia: mapas e captação de oportunidades ONS, ANEEL e CCEE.

## Stack confirmada

### Backend

- PHP `^8.3`
- Laravel `^13.0`
- Laravel Sanctum `^4.0`
- PHPUnit `^12.0`
- NFePHP SPED-NFe `~5.2.6`
- DomPDF, Guzzle, Redis/Predis, AWS S3, QR Code e código de barras

### Frontend

- Vue `^3.4`
- Vue Router `^4.3`
- Pinia `^2.1`
- Vite `^5.2`
- Axios, Tailwind CSS, Leaflet, Chart.js, ApexCharts e XLSX

## Documentação

- [Índice completo](docs/README.md)
- [Arquitetura](docs/01-Arquitetura/README.md)
- [Instalação](docs/02-Instalacao/README.md)
- [Banco de dados](docs/03-Banco-de-Dados/README.md)
- [API](docs/04-API/README.md)
- [Módulos](docs/05-Modulos/README.md)
- [Fiscal](docs/06-Fiscal/README.md)
- [Marketplace](docs/07-Marketplace/README.md)
- [Logística e expedição](docs/08-Logistica/README.md)
- [CRM](docs/09-CRM/README.md)
- [PDV](docs/10-PDV/README.md)
- [OS](docs/11-OS/README.md)
- [Administração](docs/12-Administrador/README.md)
- [Manual do usuário](docs/13-Usuario/README.md)
- [Guia do desenvolvedor](docs/14-Desenvolvedor/README.md)
- [Diagramas](docs/diagrams/README.md)

## Execução local resumida

```powershell
# Backend
cd D:\Skill\IntelbrasTech-ERP\backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# Frontend (outro terminal)
cd D:\Skill\IntelbrasTech-ERP\frontend
npm install
npm run dev
```

Revise os arquivos `.env` antes de executar. Nunca publique certificados, tokens, senhas ou chaves no GitHub.

## Licença

Código proprietário. Consulte [LICENSE](LICENSE).
