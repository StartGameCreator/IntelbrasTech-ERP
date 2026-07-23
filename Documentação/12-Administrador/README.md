# Manual do administrador

## Rotina diária

- Verificar dashboard, filas e integrações.
- Conferir erros fiscais e pedidos pendentes.
- Monitorar espaço em disco e logs.

## Backup

Realizar backup de:

- banco de dados;
- `.env` em cofre seguro;
- certificados e credenciais fora do repositório;
- XMLs e PDFs fiscais;
- arquivos enviados e etiquetas;
- configurações de servidor.

## Atualização segura

1. Criar checkpoint e backup.
2. Atualizar código em ambiente de teste.
3. Executar `composer install` e `npm install` conforme lockfiles.
4. Executar testes.
5. Aplicar migrations.
6. Limpar caches.
7. Validar login, vendas, fiscal, estoque e integrações.
8. Somente então atualizar produção.

## Comandos úteis

```bash
php artisan about
php artisan route:list
php artisan migrate:status
php artisan optimize:clear
php artisan test
```
