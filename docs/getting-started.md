# Instalação e desenvolvimento

## Pré-requisitos

### Caminho recomendado no Windows

Para desenvolvimento em Windows, use WSL2 e mantenha o repositório dentro do filesystem Linux, por exemplo `~/projetos/IntelbrasTech-ERP`. Isso reduz latência de I/O, melhora o desempenho do Vite/Laravel e evita problemas de permissões em containers.

| Ferramenta | Versão recomendada | Uso |
| --- | --- | --- |
| Git | Atual | Código-fonte e histórico. |
| Docker Engine / Desktop | Compose v2 | Pilha completa. |
| PHP | 8.3+ | Backend fora do Docker, se necessário. |
| Composer | 2.x | Dependências PHP. |
| Node.js | 20+ | Front-end Vue/Vite. |
| PostgreSQL | 16 | Produção e ambiente Docker. |

## Opção A — ambiente Docker

### 1. Clone e variáveis

```bash
git clone git@github.com:StartGameCreator/IntelbrasTech-ERP.git
cd IntelbrasTech-ERP
cp backend/.env.example backend/.env
```

Edite `backend/.env` para o ambiente local. Comece sem credenciais reais de fiscal, marketplace ou e-mail. Não use certificados de produção no computador de desenvolvimento.

### 2. Configure o banco

Para a pilha Docker, use PostgreSQL e Redis:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=intelbrastech
DB_USERNAME=erp_user
DB_PASSWORD=troque-por-uma-senha-local

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PASSWORD=troque-por-uma-senha-local
```

> O arquivo de exemplo prioriza SQLite para uma instalação mínima. Essa opção não representa a topologia de produção e não deve ser copiada para a VPS.

### 3. Suba a pilha

```bash
cd docker
docker compose --env-file ../backend/.env up -d --build
docker compose ps
```

Na primeira execução, instale dependências/migrations dentro do container de aplicação se a imagem não as tiver executado automaticamente:

```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan optimize:clear
```

### 4. Endereços locais

| Serviço | Endereço padrão |
| --- | --- |
| Front-end Vite (desenvolvimento) | `http://localhost:5173` |
| Aplicação via Nginx | `http://localhost` |
| API | `http://localhost/api/v1` |
| Mailpit | `http://localhost:8025` quando exposto pelo compose do ambiente |

Use `docker compose logs -f app frontend queue` para investigar erros. Evite apagar volumes durante diagnóstico: eles guardam banco e arquivos.

## Opção B — execução sem Docker

Essa modalidade é útil para depuração, mas exige serviços locais compatíveis.

```bash
# terminal 1
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# terminal 2
cd frontend
npm ci
npm run dev
```

Ao rodar fora do Docker, ajuste `DB_HOST`, `REDIS_HOST`, URLs de API e os drivers no `.env` para os serviços da sua máquina.

## Qualidade antes de abrir um pull request

```bash
# Front-end
cd frontend
npm run build

# Backend
cd ../backend
php artisan test
php artisan optimize:clear
```

O build do front-end deve concluir sem erro. Avisos de tamanho de chunk são indicadores de performance e não devem ser ignorados indefinidamente: prefira importações dinâmicas em telas pesadas.

## Convenções de configuração

| Ambiente | Banco | Arquivos | Fiscal |
| --- | --- | --- | --- |
| Desenvolvimento mínimo | SQLite | Local | Simulado/homologação, sem documentos reais |
| Desenvolvimento Docker | PostgreSQL | Local/volume | Homologação recomendada |
| Produção | PostgreSQL | Volume persistente ou S3 validado | Driver real, configuração validada |

Nunca copie um `.env` de produção para desenvolvimento, nem publique um `.env` em um commit. Para compartilhar configuração, atualize `backend/.env.example` com valores vazios e comentários seguros.

## Solução rápida de problemas

| Sintoma | Verificação inicial |
| --- | --- |
| API retorna 401 | Confirme login, cookie/token Sanctum, domínio stateful e CORS. |
| API retorna 500 | Veja `storage/logs`, logs do container e variáveis do `.env`. |
| Tela abre mas sem dados | Confirme `VITE_API_URL`, proxy Nginx, autenticação e rede do browser. |
| Fila parada | Verifique o container `queue`, conexão Redis e jobs com falha. |
| Arquivo não aparece | Verifique `FILESYSTEM_DISK`, permissões de `storage` e volume persistente. |
| Emissão fiscal bloqueada | Não force: valide ambiente, driver, certificado, CSC, série e numeração. |
