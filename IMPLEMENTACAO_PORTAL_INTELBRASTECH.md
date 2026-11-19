# Portal institucional IntelbrasTech

## Visão geral

O portal público foi incorporado à SPA Vue existente, preservando o ERP, suas rotas autenticadas e o login em `/login`. A identidade visual é própria, inspirada no universo de tecnologia e energia, sem copiar interfaces de terceiros.

## Rotas públicas

- `/`: página inicial
- `/energia-solar`: apresentação e pré-simulação
- `/solucoes/:slug`: páginas individuais das soluções
- `/assistencia-tecnica`: triagem técnica
- `/projetos`, `/sobre`, `/contato` e `/privacidade`

## API pública

- `GET /api/v1/portal/configuracao`: identidade, contato, loja e credenciais permitidas
- `GET /api/v1/portal/catalogo`: até oito produtos ativos e destacados, apenas com campos públicos
- `POST /api/v1/portal/solicitacoes`: contato, orçamento, solar e assistência

Cada formulário cria uma linha em `portal_solicitacoes` e um lead no pipeline padrão do CRM. Solicitações de assistência passam por triagem antes da criação de cliente e OS, evitando cadastros incompletos.

## Configuração

Preencher no `.env` do backend quando aplicável:

```env
PORTAL_EMPRESA_ID=1
PORTAL_LOJA_URL=https://sua-loja.example
PORTAL_WHATSAPP=5561999999999
PORTAL_INSTAGRAM_URL=
PORTAL_FACEBOOK_URL=
PORTAL_AUTORIZACAO_INTELBRAS=false
```

Mantenha `PORTAL_AUTORIZACAO_INTELBRAS=false` até haver documentação vigente. Quando a URL da loja não for informada, o botão leva ao contato e nunca a um checkout paralelo.

## Segurança e LGPD

- consentimento obrigatório;
- validação no backend;
- honeypot invisível;
- limite de dez envios por minuto por origem;
- IP armazenado somente como hash;
- API não publica custos, estoque interno ou dados fiscais;
- página de privacidade e aviso para não enviar dados sensíveis.

## Aplicação e teste

No Debian/WSL:

```bash
cd ~/projetos/IntelbrasTech-ERP/backend
php artisan migrate
php artisan optimize:clear
cd ../frontend
npm run build
```

No PowerShell, o ambiente completo pode ser iniciado pelo `iniciar.ps1`/atalho já utilizado no projeto. Depois da migração, valide `/`, `/energia-solar`, `/contato`, `/login` e um envio de formulário, confirmando o novo lead no CRM.

## Pendências editoriais

- substituir imagens ilustrativas por fotografias próprias/licenciadas definitivas;
- preencher domínio absoluto no sitemap durante a publicação;
- cadastrar URL da loja e canais sociais;
- publicar projetos reais somente com autorização do cliente;
- ativar a alegação de revenda/serviço autorizado somente após comprovação.
