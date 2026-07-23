# Nuvemshop + Dropshipping — Implantação

## Entregue nesta versão
- OAuth Nuvemshop sem armazenar senha da loja.
- Teste de conexão e leitura dos dados da loja.
- Importação de produtos com vínculo por SKU e criação local quando inexistente.
- Importação de pedidos.
- Webhook público `/api/v1/webhooks/nuvemshop` com entrada em fila.
- Roteamento de itens por fornecedor usando `produto_fornecedores`.
- Ordens separadas quando um pedido possui itens de fornecedores diferentes.
- Painel Vue integrado à Central de Extensões.
- Campos de estoque do fornecedor, custo, prazo, fornecedor principal e ativação dropship.

## Configuração
No `backend/.env`:

```env
NUVEMSHOP_CLIENT_ID=
NUVEMSHOP_CLIENT_SECRET=
NUVEMSHOP_REDIRECT_URI=http://localhost:5173/marketplaces/nuvemshop
NUVEMSHOP_USER_AGENT="IntelbrasTech ERP (elvisvasconcelos@intelbrastech.com.br)"
```

No cadastro do aplicativo Nuvemshop, configure a URL de redirecionamento para a mesma URL definida acima. Solicite escopos de leitura/escrita de produtos e leitura/escrita de pedidos conforme a operação.

## Instalação Windows
Na pasta `backend`:

```bat
php artisan optimize:clear
php artisan migrate
php artisan db:seed --class=MarketplaceConnectorSeeder
php artisan serve --port=8033
```

Na pasta `frontend`:

```bat
npm install --legacy-peer-deps
npm run dev
```

Abra o ERP, entre em **Central de Extensões e Marketplaces**, pesquise por **Nuvemshop** e clique em **Configurar**.

## Preparação dos produtos para dropshipping
Cada produto deve possuir um vínculo na tabela/tela de fornecedores com:
- fornecedor;
- código do fornecedor;
- custo;
- prazo em dias;
- estoque do fornecedor;
- `dropship_ativo = true`;
- `principal = true` para o fornecedor preferencial.

O roteador escolhe primeiro o fornecedor principal e, na ausência dele, o menor custo ativo.

## Observações de produção
- Publique o webhook em HTTPS antes da homologação.
- Use fila persistente (`QUEUE_CONNECTION=database` ou Redis) na implantação final.
- Não coloque `CLIENT_SECRET` no frontend.
- Faça backup do SQLite antes de executar a migração.
- O processamento assíncrono da fila de webhooks e o envio automático por e-mail/API ao fornecedor são a próxima etapa operacional.
