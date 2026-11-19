<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use App\Models\Usuario;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA busy_timeout = 30000');
            DB::statement('PRAGMA foreign_keys = ON');
            DB::statement('PRAGMA journal_mode = WAL');
            DB::statement('PRAGMA synchronous = NORMAL');
            DB::statement('PRAGMA wal_autocheckpoint = 1000');
        }
        // Toda a aplicação chama $this->authorize('modulo.acao') nos controllers
        // (ex: 'produtos.view', 'fiscal.emitir', 'estoque.ajustar'). Em vez de
        // registrar uma Gate::define para cada uma das dezenas de permissões,
        // interceptamos aqui e delegamos para Usuario::pode(), que já contém
        // toda a lógica (perfil admin libera tudo, senão consulta a tabela
        // de permissões do perfil do usuário).
        Gate::before(function (Usuario $usuario, string $ability) {
            if (! str_contains($ability, '.')) {
                return null; // deixa o Laravel resolver normalmente (ex: policies de model binding)
            }

            return $usuario->pode($ability) ? true : false;
        });
    }
}
