<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Laravel recria a tabela automaticamente no SQLite ao usar change().
            $table->string('cnpj', 18)->nullable()->change();

            if (!Schema::hasColumn('empresas', 'cadastro_pendente')) {
                $table->boolean('cadastro_pendente')->default(false);
            }

            if (!Schema::hasColumn('empresas', 'cadastro_concluido_em')) {
                $table->timestamp('cadastro_concluido_em')->nullable();
            }

            if (!Schema::hasColumn('empresas', 'dados_fonte')) {
                $table->string('dados_fonte', 80)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (Schema::hasColumn('empresas', 'cadastro_pendente')) {
                $table->dropColumn('cadastro_pendente');
            }

            if (Schema::hasColumn('empresas', 'cadastro_concluido_em')) {
                $table->dropColumn('cadastro_concluido_em');
            }

            if (Schema::hasColumn('empresas', 'dados_fonte')) {
                $table->dropColumn('dados_fonte');
            }
        });
    }
};
