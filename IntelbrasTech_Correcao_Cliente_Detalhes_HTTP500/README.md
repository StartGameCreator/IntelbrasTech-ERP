# IntelbrasTech ERP — Correção da tela de detalhes do cliente

Base analisada: `backend(16).zip` e `frontend(15).zip`.

## Causa confirmada

Ao abrir `/clientes/{id}`, o backend executava eager loading das relações:

- `enderecos`
- `contatos`

Entretanto, a base atual não contém os models:

- `App\Models\ClienteEndereco`
- `App\Models\ClienteContato`

Isso gerava:

`Class "App\Models\ClienteEndereco" not found`

A requisição retornava HTTP 500 e o frontend exibia a tela vazia com “Erro interno do servidor”.

## Correção

O método `ClienteController::show()` passa a carregar somente as relações realmente existentes e utilizadas pela tela:

- `vendedor`
- `tabelaPreco`

Nenhum dado do cliente, pedido ou financeiro é apagado.

## Instalação

Execute `INSTALAR_CORRECAO_CLIENTE.cmd`.

O instalador:

1. localiza `D:\Skill\IntelbrasTech-ERP\backend`;
2. cria backup do controller atual;
3. instala a correção;
4. valida a sintaxe PHP;
5. limpa os caches Laravel;
6. restaura o backup automaticamente em caso de falha.
