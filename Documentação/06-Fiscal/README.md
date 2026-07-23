# Módulo fiscal

## Componentes encontrados

- Configuração fiscal por empresa.
- Certificado digital A1.
- CSC para NFC-e.
- Séries e numeração fiscal.
- Catálogo NCM.
- Emissão e consulta de NF-e.
- Documentos NFC-e do PDV.
- DANFE via PDF.
- Cancelamento.
- Carta de correção.
- Devolução fiscal e itens.
- Integração fiscal de marketplace.

![Fluxo fiscal](../diagrams/svg/05-fluxo-fiscal.svg)

## Fluxo operacional

1. Validar empresa, regime, endereço e inscrição estadual.
2. Validar certificado e senha.
3. Validar série, ambiente e numeração.
4. Montar documento e itens fiscais.
5. Assinar XML.
6. Transmitir à SEFAZ.
7. Armazenar XML, protocolo, chave e status.
8. Gerar DANFE.
9. Registrar eventos posteriores, quando aplicável.

## Cuidados

- Homologação e produção devem usar configurações separadas.
- O relógio do servidor deve estar correto.
- O certificado nunca deve ser versionado.
- XML e protocolos devem ser preservados pelo prazo legal aplicável.
- Regras tributárias precisam de validação por profissional contábil.
