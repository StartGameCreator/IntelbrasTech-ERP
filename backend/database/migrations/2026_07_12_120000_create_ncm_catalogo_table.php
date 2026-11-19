<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ncm_catalogo')) {
            Schema::create('ncm_catalogo', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 10)->unique();
                $table->text('descricao');
                $table->string('nivel', 30)->nullable()->index();
                $table->string('codigo_pai', 10)->nullable()->index();
                $table->date('vigencia_inicio')->nullable();
                $table->date('vigencia_fim')->nullable();
                $table->boolean('ativo')->default(true)->index();
                $table->string('fonte', 80)->default('classif_receita_federal');
                $table->json('dados')->nullable();
                $table->timestamps();

                $table->index(['ativo', 'codigo']);
            });
        }

        if (!Schema::hasTable('ncm_sugestoes_produto')) {
            Schema::create('ncm_sugestoes_produto', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
                $table->string('consulta', 500);
                $table->string('gtin', 20)->nullable()->index();
                $table->string('ncm_escolhido', 10)->nullable()->index();
                $table->decimal('confianca', 5, 2)->nullable();
                $table->string('origem', 40)->default('busca_classif');
                $table->json('resultados')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ncm_sugestoes_produto');
        Schema::dropIfExists('ncm_catalogo');
    }
};
