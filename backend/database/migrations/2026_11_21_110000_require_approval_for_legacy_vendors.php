<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('platform_cadastros')) {
            return;
        }

        // Antes da regra de aceite, vendedores eram liberados automaticamente.
        // Apenas esses registros sem análise humana retornam para a fila de aprovação.
        $cadastros = DB::table('platform_cadastros')
            ->where('tipo_perfil', 'vendedor')
            ->where('status', 'aprovado')
            ->whereNull('analisado_em')
            ->whereNull('deleted_at')
            ->get(['id', 'usuario_id']);

        foreach ($cadastros as $cadastro) {
            DB::table('platform_cadastros')->where('id', $cadastro->id)->update([
                'status' => 'pendente',
                'updated_at' => now(),
            ]);

            if ($cadastro->usuario_id) {
                DB::table('usuarios')->where('id', $cadastro->usuario_id)->update([
                    'ativo' => false,
                    'updated_at' => now(),
                ]);

                if (Schema::hasTable('platform_perfis_usuario')) {
                    DB::table('platform_perfis_usuario')
                        ->where('usuario_id', $cadastro->usuario_id)
                        ->where('tipo_perfil', 'vendedor')
                        ->update(['status' => 'pendente', 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        // Não reativa vendedores automaticamente; o aceite deve ser explícito.
    }
};
