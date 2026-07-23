# Visão geral

O IntelbrasTech ERP é uma aplicação web modular para centralizar operações comerciais, financeiras, fiscais, logísticas e de relacionamento com clientes. O frontend Vue consome uma API Laravel protegida por autenticação, com persistência configurável por ambiente.

## Perfis de uso previstos

- Administrador e proprietário da empresa
- Comercial e CRM
- Caixa e operadores de PDV
- Estoque, compras e expedição
- Financeiro e contador
- Técnicos de ordem de serviço
- Operadores de marketplace

## Princípios do projeto

- Uma API central para múltiplos módulos.
- Separação entre frontend e backend.
- Integrações externas isoladas em controllers/services próprios.
- Rastreabilidade fiscal, financeira e operacional.
- Compatibilidade com SQLite em desenvolvimento e preparação para bancos de produção.

## Limites desta documentação

A presença de controller, model, migration ou tela confirma a existência estrutural do módulo, mas não garante que toda integração externa esteja homologada. Credenciais, certificados, CSC, webhooks e ambientes de produção devem ser validados separadamente.
