# IntelbrasTech ERP — Fase 23.1

## Correções incluídas

1. Compatibilidade do QR Code com `endroid/qr-code 6.1.3`.
   - Remove a chamada inexistente `Builder::create()`.
   - Usa o construtor oficial do Builder 6.x.
   - Mantém a URL oficial extraída do XML autorizado.

2. Botão **Gerar segunda via** na tela NFC-e do PDV.
   - Disponível somente para documento autorizado.
   - Usa a nota já autorizada e não retransmite para a SEFAZ.
   - Baixa o arquivo como `Segunda-via-NFCe-<numero>.pdf`.

3. Backup automático e validações.
   - Backup antes de substituir arquivos.
   - `php -l` nos arquivos PHP.
   - Limpeza do cache Laravel.
   - Build do frontend.

## Instalação

Execute:

`INSTALAR_FASE23_1.cmd`

O caminho padrão do projeto é:

`D:\Skill\IntelbrasTech-ERP`

## Teste

Abra:

`NFC-e do PDV`

Selecione a venda autorizada e teste:

- **Baixar DANFE**
- **Gerar segunda via**
