# API REST

A API está organizada sob controllers `Api/V1`. O arquivo `routes/api.php` é a referência operacional das rotas.

## Áreas

- `Auth`: login, usuário autenticado e login social.
- `Cadastros`: clientes, fornecedores, produtos e auxiliares.
- `Comercial`: pedidos e recibos.
- `Compras`: pedidos de compra.
- `CRM`: leads, pipelines, CNPJ, Google Business e reuniões.
- `Estoque`: depósitos, estoque, inventário, movimentação e XML.
- `Fiscal`: certificado, configuração, NCM, numeração, NF-e e contador.
- `PDV`: venda, NFC-e, cancelamento e devolução.
- `Marketplace`: hub, OAuth, catálogo, pedidos, automação e Nuvemshop.
- `Expedição/Logística`: pedidos de envio, etiquetas e transportadoras.

## Convenções recomendadas

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

Respostas devem manter padrão consistente:

```json
{
  "success": true,
  "message": "Operação realizada com sucesso",
  "data": {}
}
```

## Segurança

- Proteja rotas privadas com Sanctum e autorização.
- Não retorne tokens, segredos ou senhas em respostas.
- Valide upload por MIME, extensão, tamanho e conteúdo.
- Registre operações fiscais e administrativas em log de auditoria.

## Próxima evolução

Gerar `openapi.yaml` automaticamente a partir das rotas e contratos validados. A documentação atual não inventa parâmetros que não foram confirmados nos Form Requests/controllers.
