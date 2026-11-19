# Logística e expedição

## Recursos estruturais

- Pedidos de envio.
- Controle de expedição.
- Cadastro/configuração de transportadoras.
- Etiquetas de envio.
- Upload PDF, PNG e ZPL previsto pela fase de etiquetas.
- Histórico de impressão e prevenção de duplicidade por hash no pacote da fase.

![Expedição](../diagrams/svg/08-expedicao-logistica.svg)

## Ordem de rotas

Rotas estáticas de etiquetas devem aparecer antes de rotas dinâmicas como `expedicao/{id}`, evitando que `etiquetas` seja interpretada como identificador.
