<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('platform_cadastros')) {
            Schema::table('platform_cadastros', function (Blueprint $table) {
                if (!Schema::hasColumn('platform_cadastros', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable()->index();
                }
                if (!Schema::hasColumn('platform_cadastros', 'delete_reason')) {
                    $table->text('delete_reason')->nullable();
                }
            });
        }

        if (!Schema::hasTable('platform_user_deletion_logs')) {
            Schema::create('platform_user_deletion_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cadastro_id')->index();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->string('acao', 40)->index();
                $table->text('motivo')->nullable();
                $table->unsignedBigInteger('executado_por')->nullable()->index();
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('usuarios') || !Schema::hasTable('perfis')) {
            return;
        }

        $empresas = DB::table('empresas')->pluck('id');

        foreach ($empresas as $empresaId) {
            foreach (['Técnico Marketplace', 'Cliente Marketplace', 'Colaborador Marketplace'] as $nome) {
                DB::table('perfis')->updateOrInsert(
                    ['empresa_id' => $empresaId, 'nome' => $nome],
                    [
                        'descricao' => 'Perfil criado pelo Identity Core da IntelbrasTech Platform',
                        'admin' => false,
                        'ativo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $cadastros = DB::table('platform_cadastros')->whereNotNull('usuario_id')->get();

        foreach ($cadastros as $cadastro) {
            $usuario = DB::table('usuarios')->where('id', $cadastro->usuario_id)->first();
            if (!$usuario) continue;

            $empresaId = $cadastro->empresa_id ?: $usuario->empresa_id;
            $nomePerfil = match ($cadastro->tipo_perfil) {
                'tecnico' => 'Técnico Marketplace',
                'cliente' => 'Cliente Marketplace',
                default => 'Colaborador Marketplace',
            };

            $perfilId = DB::table('perfis')
                ->where('empresa_id', $empresaId)
                ->where('nome', $nomePerfil)
                ->value('id');

            DB::table('usuarios')->where('id', $usuario->id)->update([
                'empresa_id' => $empresaId,
                'perfil_id' => $perfilId,
                'ativo' => $cadastro->status === 'aprovado' && $cadastro->deleted_at === null,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Preserva usuários, perfis e logs.
    }
};
