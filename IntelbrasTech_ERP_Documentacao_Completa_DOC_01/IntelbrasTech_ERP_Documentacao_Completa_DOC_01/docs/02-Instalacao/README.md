# Instalação

## Requisitos

- Windows 10/11 ou Linux
- PHP 8.3 ou superior
- Composer 2
- Node.js LTS e npm
- Extensões PHP exigidas pelo Laravel e banco escolhido
- Git

## Backend

```powershell
cd D:\Skill\IntelbrasTech-ERP\backend
composer install
copy .env.example .env
php artisan key:generate
```

### SQLite para desenvolvimento

```powershell
New-Item -ItemType File -Force database\database.sqlite
```

No `.env`:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=D:/Skill/IntelbrasTech-ERP/backend/database/database.sqlite
```

Em seguida:

```powershell
php artisan migrate
php artisan optimize:clear
php artisan serve
```

## Frontend

```powershell
cd D:\Skill\IntelbrasTech-ERP\frontend
npm install
npm run dev
```

Configure a URL da API no arquivo de ambiente do frontend, de acordo com a implementação existente.

## Verificações

```powershell
# Backend
php artisan about
php artisan route:list
php artisan migrate:status
php artisan test

# Frontend
npm run build
```

## Dados que não podem ir ao GitHub

- `.env`
- bancos SQLite reais
- certificados `.pfx`/`.p12`
- senhas de certificado
- tokens OAuth e marketplace
- CSC e chaves fiscais
- logs com dados pessoais
- arquivos XML fiscais de clientes
