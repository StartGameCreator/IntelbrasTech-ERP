# PDV

O PDV possui estrutura de vendas, pagamentos, histórico, documento NFC-e, cancelamento e devolução fiscal.

## Fluxo

1. Abrir venda.
2. Identificar cliente, quando necessário.
3. Adicionar produtos e validar estoque.
4. Registrar pagamentos.
5. Concluir venda.
6. Emitir NFC-e conforme configuração.
7. Gerar comprovante.
8. Permitir cancelamento/devolução conforme regras.

As operações devem ser transacionais para impedir venda concluída sem baixa de estoque ou registro financeiro correspondente.
