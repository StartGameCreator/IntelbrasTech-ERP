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
            if (! Schema::hasColumn('platform_cadastros', 'modalidade_cliente')) {
                $table->string('modalidade_cliente', 30)->nullable()->after('tipo_pessoa')->index();
            }

            if (! Schema::hasColumn('platform_cadastros', 'latitude')) {
                $table->decimal('latitude', 11, 8)->nullable()->after('estado')->index();
            }

            if (! Schema::hasColumn('platform_cadastros', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude')->index();
            }

            if (! Schema::hasColumn('platform_cadastros', 'localizacao_tipo')) {
                $table->string('localizacao_tipo', 30)->nullable()->after('longitude');
            }

            if (! Schema::hasColumn('platform_cadastros', 'localizacao_compartilhada')) {
                $table->boolean('localizacao_compartilhada')->default(false)->after('localizacao_tipo')->index();
            }

            if (! Schema::hasColumn('platform_cadastros', 'localizacao_atualizada_em')) {
                $table->timestamp('localizacao_atualizada_em')->nullable()->after('localizacao_compartilhada');
            }
        });
    }

    public function down(): void
    {
        // Mantem compatibilidade e nao remove dados de localizacao ja coletados.
    }
};
