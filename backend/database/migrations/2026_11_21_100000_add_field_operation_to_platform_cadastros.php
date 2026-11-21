<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('platform_cadastros')) {
            return;
        }

        Schema::table('platform_cadastros', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_cadastros', 'disponivel_para_atendimento')) {
                $table->boolean('disponivel_para_atendimento')
                    ->default(false)
                    ->after('localizacao_atualizada_em')
                    ->index();
            }

            if (! Schema::hasColumn('platform_cadastros', 'disponibilidade_atualizada_em')) {
                $table->timestamp('disponibilidade_atualizada_em')
                    ->nullable()
                    ->after('disponivel_para_atendimento');
            }
        });
    }

    public function down(): void
    {
        // Dados operacionais de campo não são removidos automaticamente.
    }
};
