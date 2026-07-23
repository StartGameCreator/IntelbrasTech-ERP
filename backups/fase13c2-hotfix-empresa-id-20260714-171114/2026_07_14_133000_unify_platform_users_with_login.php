<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('platform_cadastros')) {
            return;
        }

        $userTable = Schema::hasTable('usuarios')
            ? 'usuarios'
            : (Schema::hasTable('users') ? 'users' : null);

        if (!$userTable) {
            return;
        }

        $userColumns = array_flip(Schema::getColumnListing($userTable));

        $cadastros = DB::table('platform_cadastros')
            ->whereNull('deleted_at')
            ->whereNull('usuario_id')
            ->orderBy('id')
            ->get();

        foreach ($cadastros as $cadastro) {
            $email = mb_strtolower(trim((string) $cadastro->email));

            $existing = DB::table($userTable)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($existing) {
                DB::table('platform_cadastros')
                    ->where('id', $cadastro->id)
                    ->update(['usuario_id' => $existing->id]);

                continue;
            }

            if (empty($cadastro->senha_hash)) {
                continue;
            }

            $payloadBase = [
                'uuid' => (string) Str::uuid(),
                'nome' => $cadastro->nome,
                'name' => $cadastro->nome,
                'email' => $email,
                'senha' => $cadastro->senha_hash,
                'password' => $cadastro->senha_hash,
                'tipo' => $cadastro->tipo_perfil,
                'tipo_usuario' => $cadastro->tipo_perfil,
                'perfil' => $cadastro->tipo_perfil,
                'role' => $cadastro->tipo_perfil,
                'ativo' => $cadastro->tipo_perfil !== 'colaborador',
                'active' => $cadastro->tipo_perfil !== 'colaborador',
                'status' => $cadastro->tipo_perfil === 'colaborador' ? 'pendente' : 'ativo',
                'email_verified_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $payload = [];

            foreach ($payloadBase as $column => $value) {
                if (array_key_exists($column, $userColumns)) {
                    $payload[$column] = $value;
                }
            }

            $usuarioId = DB::table($userTable)->insertGetId($payload);

            DB::table('platform_cadastros')
                ->where('id', $cadastro->id)
                ->update(['usuario_id' => $usuarioId]);
        }
    }

    public function down(): void
    {
        // Não exclui contas nem vínculos para evitar perda de dados.
    }
};
