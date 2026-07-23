<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('empresas')) {
            return;
        }

        Schema::table('empresas', function (Blueprint $table) {
            if (!Schema::hasColumn('empresas', 'regime_apuracao')) {
                $table->string('regime_apuracao', 30)->nullable();
            }

            if (!Schema::hasColumn('empresas', 'regime_fiscal_confirmado')) {
                $table->boolean('regime_fiscal_confirmado')->default(false);
            }

            if (!Schema::hasColumn('empresas', 'regime_fiscal_confirmado_em')) {
                $table->timestamp('regime_fiscal_confirmado_em')->nullable();
            }

            if (!Schema::hasColumn('empresas', 'icms_csosn_padrao')) {
                $table->string('icms_csosn_padrao', 3)->nullable();
            }

            if (!Schema::hasColumn('empresas', 'icms_cst_padrao')) {
                $table->string('icms_cst_padrao', 2)->nullable();
            }

            if (!Schema::hasColumn('empresas', 'pis_cst_padrao')) {
                $table->string('pis_cst_padrao', 2)->nullable();
            }

            if (!Schema::hasColumn('empresas', 'cofins_cst_padrao')) {
                $table->string('cofins_cst_padrao', 2)->nullable();
            }

            if (!Schema::hasColumn('empresas', 'fiscal_observacao')) {
                $table->text('fiscal_observacao')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Compatibilidade fiscal: não removemos os campos para evitar perda
        // de configuração e quebra de documentos já preparados.
    }
};
