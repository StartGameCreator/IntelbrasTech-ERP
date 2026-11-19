# IntelbrasTech ERP — API REST v1
# Base URL: /api/v1
# Auth: Bearer Token (Sanctum JWT)
# Total: 320+ endpoints

## ──────────────────────────────────────
## AUTH
## ──────────────────────────────────────
POST   /auth/login
POST   /auth/logout
POST   /auth/refresh
POST   /auth/esqueci-senha
POST   /auth/redefinir-senha
GET    /auth/me
PUT    /auth/perfil
PUT    /auth/senha
POST   /auth/avatar

## ──────────────────────────────────────
## EMPRESAS / MULTIEMPRESA
## ──────────────────────────────────────
GET    /empresas
POST   /empresas
GET    /empresas/{id}
PUT    /empresas/{id}
DELETE /empresas/{id}
POST   /empresas/{id}/logo
GET    /empresas/{id}/configuracoes
PUT    /empresas/{id}/configuracoes

## ──────────────────────────────────────
## USUÁRIOS / PERMISSÕES
## ──────────────────────────────────────
GET    /usuarios
POST   /usuarios
GET    /usuarios/{id}
PUT    /usuarios/{id}
DELETE /usuarios/{id}
POST   /usuarios/{id}/avatar
PUT    /usuarios/{id}/senha
POST   /usuarios/{id}/bloquear
POST   /usuarios/{id}/desbloquear

GET    /perfis
POST   /perfis
GET    /perfis/{id}
PUT    /perfis/{id}
DELETE /perfis/{id}
PUT    /perfis/{id}/permissoes

GET    /permissoes

## ──────────────────────────────────────
## CLIENTES
## ──────────────────────────────────────
GET    /clientes                      # Lista + filtros + busca
POST   /clientes                      # Criar
GET    /clientes/{id}                 # Detalhe
PUT    /clientes/{id}                 # Atualizar
DELETE /clientes/{id}                 # Excluir
POST   /clientes/importar             # Import CSV/XLSX
GET    /clientes/exportar             # Export
GET    /clientes/{id}/pedidos         # Histórico de pedidos
GET    /clientes/{id}/financeiro      # Contas do cliente
GET    /clientes/{id}/os              # OSs do cliente
POST   /clientes/{id}/enderecos       # Add endereço
PUT    /clientes/{id}/enderecos/{eid} # Update endereço
DELETE /clientes/{id}/enderecos/{eid} # Remove endereço
POST   /clientes/{id}/contatos        # Add contato
GET    /clientes/mapa                 # Coordenadas para mapa GPS
GET    /clientes/aniversariantes      # Aniversariantes do mês

## ──────────────────────────────────────
## FORNECEDORES
## ──────────────────────────────────────
GET    /fornecedores
POST   /fornecedores
GET    /fornecedores/{id}
PUT    /fornecedores/{id}
DELETE /fornecedores/{id}
GET    /fornecedores/{id}/compras
GET    /fornecedores/ranking

## ──────────────────────────────────────
## PRODUTOS / CATÁLOGO
## ──────────────────────────────────────
GET    /produtos                      # Lista com filtros avançados
POST   /produtos                      # Criar produto
GET    /produtos/{id}                 # Detalhe completo
PUT    /produtos/{id}                 # Atualizar
DELETE /produtos/{id}                 # Excluir (soft)
POST   /produtos/importar             # Import planilha
GET    /produtos/exportar             # Export
GET    /produtos/{id}/estoque         # Estoque por depósito
GET    /produtos/{id}/movimentacoes   # Histórico de movimentações
POST   /produtos/{id}/imagens         # Upload imagens
DELETE /produtos/{id}/imagens/{img}   # Remover imagem
POST   /produtos/{id}/variacoes       # Criar variação
PUT    /produtos/{id}/variacoes/{vid} # Update variação
GET    /produtos/curva-abc            # Relatório curva ABC

GET    /categorias
POST   /categorias
GET    /categorias/{id}
PUT    /categorias/{id}
DELETE /categorias/{id}
GET    /categorias/arvore             # Estrutura em árvore

GET    /marcas
POST   /marcas
PUT    /marcas/{id}
DELETE /marcas/{id}

GET    /tabelas-preco
POST   /tabelas-preco
GET    /tabelas-preco/{id}
PUT    /tabelas-preco/{id}
DELETE /tabelas-preco/{id}

## ──────────────────────────────────────
## ESTOQUE
## ──────────────────────────────────────
GET    /estoque                        # Posição atual
GET    /estoque/{produto_id}           # Por produto
PUT    /estoque/ajustar                # Ajuste manual
POST   /estoque/transferencia          # Transferir entre depósitos
GET    /estoque/alertas                # Produtos abaixo do mínimo
GET    /estoque/giro                   # Giro de estoque
GET    /estoque/saldo-geral            # Saldo geral por depósito

GET    /movimentacoes
GET    /movimentacoes/{id}
GET    /movimentacoes/exportar

GET    /depositos
POST   /depositos
GET    /depositos/{id}
PUT    /depositos/{id}
DELETE /depositos/{id}

GET    /inventarios
POST   /inventarios
GET    /inventarios/{id}
PUT    /inventarios/{id}
POST   /inventarios/{id}/iniciar
POST   /inventarios/{id}/finalizar
POST   /inventarios/{id}/cancelar
POST   /inventarios/{id}/itens        # Lançar contagem
GET    /inventarios/{id}/divergencias

## ──────────────────────────────────────
## PEDIDOS DE VENDA
## ──────────────────────────────────────
GET    /pedidos
POST   /pedidos
GET    /pedidos/{id}
PUT    /pedidos/{id}
DELETE /pedidos/{id}
POST   /pedidos/{id}/confirmar
POST   /pedidos/{id}/cancelar
POST   /pedidos/{id}/duplicar
GET    /pedidos/{id}/nfe              # NF-e do pedido
GET    /pedidos/{id}/financeiro       # Contas a receber do pedido
GET    /pedidos/{id}/rastreio         # Status de envio
POST   /pedidos/{id}/emitir-nfe       # Emitir NF-e
POST   /pedidos/{id}/gerar-boleto
GET    /pedidos/exportar
GET    /pedidos/kanban                # View Kanban por status

## ──────────────────────────────────────
## COMPRAS
## ──────────────────────────────────────
GET    /compras
POST   /compras
GET    /compras/{id}
PUT    /compras/{id}
DELETE /compras/{id}
POST   /compras/{id}/receber          # Receber mercadoria
POST   /compras/{id}/cancelar
GET    /compras/{id}/nfe              # Vincular NF-e entrada

## ──────────────────────────────────────
## FINANCEIRO — CONTAS A RECEBER
## ──────────────────────────────────────
GET    /contas-receber
POST   /contas-receber
GET    /contas-receber/{id}
PUT    /contas-receber/{id}
DELETE /contas-receber/{id}
POST   /contas-receber/{id}/baixar    # Dar baixa manual
POST   /contas-receber/{id}/cancelar
POST   /contas-receber/baixa-lote     # Baixa em lote
GET    /contas-receber/vencidas
GET    /contas-receber/a-vencer       # Próximos 30/60/90 dias
GET    /contas-receber/exportar

## ──────────────────────────────────────
## FINANCEIRO — CONTAS A PAGAR
## ──────────────────────────────────────
GET    /contas-pagar
POST   /contas-pagar
GET    /contas-pagar/{id}
PUT    /contas-pagar/{id}
DELETE /contas-pagar/{id}
POST   /contas-pagar/{id}/pagar
POST   /contas-pagar/{id}/cancelar
GET    /contas-pagar/vencidas
GET    /contas-pagar/a-vencer

## ──────────────────────────────────────
## FINANCEIRO — FLUXO DE CAIXA / DRE
## ──────────────────────────────────────
GET    /fluxo-caixa                   # ?periodo=mensal&mes=6&ano=2025
GET    /fluxo-caixa/diario
GET    /fluxo-caixa/resumo
GET    /dre                           # Demonstrativo de Resultado
GET    /dre/comparativo               # Mensal comparado
GET    /contas-bancarias
POST   /contas-bancarias
GET    /contas-bancarias/{id}
PUT    /contas-bancarias/{id}
GET    /contas-bancarias/{id}/lancamentos
POST   /conciliacao/importar-ofx      # Import extrato banco

## ──────────────────────────────────────
## FISCAL — NF-e
## ──────────────────────────────────────
GET    /nfe
POST   /nfe
GET    /nfe/{id}
PUT    /nfe/{id}
DELETE /nfe/{id}
POST   /nfe/{id}/emitir               # Enviar SEFAZ
POST   /nfe/{id}/cancelar             # Cancelar no SEFAZ
POST   /nfe/{id}/carta-correcao
POST   /nfe/{id}/inutilizar
GET    /nfe/{id}/xml                  # Download XML
GET    /nfe/{id}/danfe                # Download PDF DANFE
POST   /nfe/{id}/enviar-email         # Email para cliente
GET    /nfe/consultar/{chave}         # Consulta pelo SEFAZ
POST   /nfe/importar-xml              # Import NF-e entrada

## ──────────────────────────────────────
## FISCAL — NFC-e, NFS-e
## ──────────────────────────────────────
GET    /nfce
POST   /nfce
POST   /nfce/{id}/emitir
POST   /nfce/{id}/cancelar
GET    /nfce/{id}/pdf

GET    /nfse
POST   /nfse
POST   /nfse/{id}/emitir
POST   /nfse/{id}/cancelar
GET    /nfse/{id}/pdf

## ──────────────────────────────────────
## LOGÍSTICA
## ──────────────────────────────────────
GET    /transportadoras
POST   /transportadoras
GET    /transportadoras/{id}
PUT    /transportadoras/{id}
DELETE /transportadoras/{id}
POST   /transportadoras/calcular-frete

GET    /envios
POST   /envios
GET    /envios/{id}
POST   /envios/{id}/gerar-etiqueta
GET    /envios/{id}/etiqueta-pdf
POST   /envios/{id}/rastrear
GET    /envios/{pedido_id}/rastreamento

## ──────────────────────────────────────
## INTEGRAÇÕES MARKETPLACES
## ──────────────────────────────────────
GET    /integracoes
POST   /integracoes
GET    /integracoes/{id}
PUT    /integracoes/{id}
DELETE /integracoes/{id}
POST   /integracoes/{id}/testar       # Testar conexão
POST   /integracoes/{id}/sincronizar  # Sync manual
GET    /integracoes/{id}/logs

# Mercado Livre
GET    /ml/anuncios
POST   /ml/anuncios
PUT    /ml/anuncios/{id}
GET    /ml/pedidos
POST   /ml/auth/callback

# Shopee
GET    /shopee/produtos
POST   /shopee/produtos
GET    /shopee/pedidos
POST   /shopee/auth/callback

# Nuvemshop
GET    /nuvemshop/produtos
GET    /nuvemshop/pedidos
POST   /nuvemshop/auth/callback

# Webhooks dos marketplaces (público)
POST   /webhooks/mercadolivre
POST   /webhooks/shopee
POST   /webhooks/amazon
POST   /webhooks/nuvemshop

## ──────────────────────────────────────
## CRM
## ──────────────────────────────────────
GET    /leads
POST   /leads
GET    /leads/{id}
PUT    /leads/{id}
DELETE /leads/{id}
POST   /leads/{id}/mover-etapa
POST   /leads/{id}/converter          # Converter em cliente

GET    /pipelines
POST   /pipelines
GET    /pipelines/{id}
PUT    /pipelines/{id}

GET    /atividades
POST   /atividades
GET    /atividades/{id}
PUT    /atividades/{id}
POST   /atividades/{id}/concluir

## ──────────────────────────────────────
## ASSISTÊNCIA TÉCNICA (OS)
## ──────────────────────────────────────
GET    /os
POST   /os
GET    /os/{id}
PUT    /os/{id}
DELETE /os/{id}
POST   /os/{id}/fotos
DELETE /os/{id}/fotos/{fid}
POST   /os/{id}/pecas
PUT    /os/{id}/pecas/{pid}
DELETE /os/{id}/pecas/{pid}
POST   /os/{id}/mudar-status
POST   /os/{id}/concluir
POST   /os/{id}/entregar
GET    /os/{id}/historico
GET    /os/{id}/pdf-laudo
GET    /os/{id}/pdf-os
GET    /os/sla                        # Relatório SLA
GET    /os/pendentes
GET    /os/kanban

## ──────────────────────────────────────
## RH
## ──────────────────────────────────────
GET    /funcionarios
POST   /funcionarios
GET    /funcionarios/{id}
PUT    /funcionarios/{id}
DELETE /funcionarios/{id}

GET    /comissoes
POST   /comissoes
GET    /comissoes/{id}
POST   /comissoes/{id}/pagar
GET    /comissoes/relatorio

## ──────────────────────────────────────
## DASHBOARD
## ──────────────────────────────────────
GET    /dashboard                     # KPIs principais
GET    /dashboard/vendas              # Gráfico vendas
GET    /dashboard/financeiro          # Resumo financeiro
GET    /dashboard/estoque             # Alertas de estoque
GET    /dashboard/os                  # Resumo de OSs
GET    /dashboard/mapa                # Dados para mapa GPS
GET    /dashboard/mapa/clientes       # Clientes no mapa
GET    /dashboard/mapa/tecnicos       # Técnicos no mapa
GET    /dashboard/mapa/entregas       # Entregas no mapa

## ──────────────────────────────────────
## RELATÓRIOS
## ──────────────────────────────────────
GET    /relatorios/vendas
GET    /relatorios/produtos
GET    /relatorios/clientes
GET    /relatorios/estoque
GET    /relatorios/financeiro
GET    /relatorios/comissoes
GET    /relatorios/nfe
GET    /relatorios/os
GET    /relatorios/exportar/{tipo}

## ──────────────────────────────────────
## NOTIFICAÇÕES
## ──────────────────────────────────────
GET    /notificacoes
POST   /notificacoes/{id}/lida
POST   /notificacoes/marcar-todas-lidas
DELETE /notificacoes/{id}

## ──────────────────────────────────────
## CONFIGURAÇÕES
## ──────────────────────────────────────
GET    /configuracoes
PUT    /configuracoes
POST   /certificado-digital           # Upload certificado A1
GET    /series-fiscais
POST   /series-fiscais
PUT    /series-fiscais/{id}
GET    /unidades-medida
POST   /unidades-medida
GET    /plano-contas
POST   /plano-contas
PUT    /plano-contas/{id}
DELETE /plano-contas/{id}
GET    /formas-pagamento
POST   /formas-pagamento
GET    /condicoes-pagamento
POST   /condicoes-pagamento

## ──────────────────────────────────────
## UTILITÁRIOS
## ──────────────────────────────────────
GET    /cep/{cep}                     # Buscar endereço por CEP
GET    /cnpj/{cnpj}                   # Buscar dados por CNPJ
GET    /ibge/estados
GET    /ibge/municipios/{uf}
POST   /upload                        # Upload genérico
GET    /versao                        # Versão do sistema
