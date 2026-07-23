# IntelbrasTech ERP — Fase 22.2: QR Code oficial da NFC-e

Este patch corrige a origem do QR Code do DANFE. A imagem passa a ser gerada exclusivamente com a URL contida em `infNFeSupl/qrCode` do XML fiscal.

## Instalação

Execute `INSTALAR_PATCH_QRCODE_SEFAZ.cmd`.

## O que é alterado

- inclui `infNFeSupl`, `qrCode` e `urlChave` no XML assinado da NFC-e antes da transmissão;
- usa a tabela de URLs do pacote `nfephp-org/sped-nfe`, sem inventar endereço da SEFAZ;
- salva `qr_code_url` e `url_chave` na nota autorizada;
- gera a imagem do QR Code a partir da URL fiscal real;
- remove do DANFE a indicação genérica do Portal Nacional da NF-e modelo 55;
- cria backup antes de qualquer alteração.

## Validação obrigatória

Faça o teste com uma **nova NFC-e de homologação**. Notas antigas cujo XML foi criado sem `infNFeSupl` não devem ser reconstruídas ou alteradas depois da autorização.

Caso o pacote NFePHP não contenha `urlChave`, configure no `.env`:

```env
SEFAZ_NFCE_CONSULTA_URL=https://ENDERECO-OFICIAL-DA-SEFAZ
```

e mapeie a variável em `config/services.php`, dentro de `sefaz`:

```php
'nfce_consulta_url' => env('SEFAZ_NFCE_CONSULTA_URL'),
'nfce_qrcode_url' => env('SEFAZ_NFCE_QRCODE_URL'),
```

Não publique CSC, certificado A1 ou `.env` no GitHub.
