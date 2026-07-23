<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('platform_cadastros')) {
            return;
        }

        Schema::table('platform_cadastros', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_cadastros', 'usuario_id')) {
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
            }

            if (!Schema::hasColumn('platform_cadastros', 'senha_hash')) {
                $table->string('senha_hash')->nullable();
            }
        });

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
            $existing = DB::table($userTable)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($cadastro->email)])
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

            $payload = [];

            foreach ([
                'nome' => $cadastro->nome,
                'name' => $cadastro->nome,
                'email' => mb_strtolower($cadastro->email),
                'senha' => $cadastro->senha_hash,
                'password' => $cadastro->senha_hash,
                'tipo' => $cadastro->tipo_perfil,
                'tipo_usuario' => $cadastro->tipo_perfil,
                'perfil' => $cadastro->tipo_perfil,
                'role' => $cadastro->tipo_perfil,
                'ativo' => $cadastro->tipo_perfil !== 'colaborador',
                'active' => $cadastro->tipo_perfil !== 'colaborador',
                'status' => $cadastro->tipo_perfil === 'colaborador' ? 'pendente' : 'ativo',
                'created_at' => now(),
                'updated_at' => now(),
            ] as $column => $value) {
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
        // Não remove usuários nem vínculos para evitar perda de contas.
    }
};
