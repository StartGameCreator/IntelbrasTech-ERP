# Módulo fiscal - testes e publicação

## Estado entregue

O fluxo NF-e modelo 55 está habilitado para testes locais com o driver `fake`. Esse modo gera chave, XML, protocolo simulado e DANFE, mas não possui validade fiscal.

Por segurança, o driver `fake` é bloqueado quando `APP_ENV=production`. Assim, o servidor nunca marcará uma nota como autorizada sem transmissão real.

## Ambiente de testes

```env
APP_ENV=local
FISCAL_DRIVER=fake
FISCAL_ALLOW_FAKE_IN_PRODUCTION=false
FISCAL_STORAGE_DISK=local
SEFAZ_AMBIENTE=2
```

Cadastre antes do teste:

- empresa com CNPJ, IE, endereço, CEP, município/IBGE e regime tributário;
- certificado A1 ativo e dentro da validade;
- série fiscal NF-e ativa em homologação;
- cliente com CPF/CNPJ e endereço completo;
- produto com NCM, CFOP, unidade, origem, CSOSN/CST, PIS e COFINS.

Para o cenário MEI usado como referência: origem `0`, CSOSN `400`, CFOP `5102`, PIS `49`, COFINS `49` e frete `9`.

## Publicação no servidor

Antes do deploy:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan test
```

O frontend deve ser publicado a partir de `frontend/dist`, gerado com `npm run build`.

## Homologação e produção fiscal real

O adaptador direto NFePHP está instalado. O servidor precisa de PHP 8.3 e das extensões `soap`, `openssl`, `dom`, `simplexml`, `libxml` e `zlib`.

```env
FISCAL_DRIVER=nfephp
FISCAL_ALLOW_FAKE_IN_PRODUCTION=false
FISCAL_SCHEMAS=PL_010_V1.30
SEFAZ_AMBIENTE=2
```

Comece com `ambiente_nfe=2` na configuração fiscal. Cadastre o A1 em PFX/P12, execute **Testar configuração** e confirme o retorno SEFAZ `107 - Serviço em Operação`. Depois emita e confira uma NF-e de homologação.

Somente após a homologação, altere a configuração da empresa para `ambiente_nfe=1` e `SEFAZ_AMBIENTE=1`, limpe o cache de configuração e teste novamente o status antes da primeira NF-e real.

Não altere `FISCAL_ALLOW_FAKE_IN_PRODUCTION` para `true`. Certificado, senha e credenciais pertencem à empresa emissora e não devem ser versionados.

## Credenciais e segredos

- Nunca publique `.env`, `client_secret*.json`, certificados `.pfx/.p12`, chaves `.pem/.key` ou a pasta `private-local`.
- Não crie ZIPs do projeto sem excluir `.env`. Os ZIPs antigos foram isolados porque continham configurações locais.
- Gere uma `APP_KEY` exclusiva no servidor e não a altere depois de salvar certificados/tokens criptografados.
- Chaves `VITE_*` são enviadas ao navegador. Restrinja chaves Google Maps por domínio e pelas APIs estritamente necessárias.
- Se um segredo já foi incluído em arquivo compartilhado ou repositório, remova o arquivo e também revogue/rotacione a credencial no provedor.
