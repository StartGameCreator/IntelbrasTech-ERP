# Guia do desenvolvedor

## Backend

- Controllers recebem e devolvem HTTP; regras complexas devem ir para serviços.
- Validação deve ocorrer no backend.
- Models devem declarar casts, relações, fillable/guarded e soft deletes quando necessário.
- Integrações externas devem ter timeout, tratamento de erro, idempotência e logs sem segredos.
- Mudanças de banco devem usar migrations novas.

## Frontend

- Views representam páginas.
- Componentes reutilizáveis ficam em `components`.
- Estado global somente quando necessário, via Pinia.
- Chamadas HTTP centralizadas em `services`.
- Guards devem impedir acesso não autenticado.
- Erros da API devem gerar mensagens úteis sem expor detalhes internos.

## Git

- `main`: versão estável.
- `develop`: integração, caso adotada.
- `feature/<nome>`: funcionalidades.
- `fix/<nome>`: correções.
- Commits pequenos e descritivos.

## Testes mínimos por entrega

```bash
php artisan test
npm run build
```

Também validar manualmente autenticação, migrations, rotas, permissões e fluxo afetado.
