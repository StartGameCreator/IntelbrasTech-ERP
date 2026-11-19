<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $campos = [
                'seo_titulo' => fn () => $table->string('seo_titulo', 255)->nullable(),
                'seo_descricao' => fn () => $table->string('seo_descricao', 500)->nullable(),
                'seo_palavras_chave' => fn () => $table->json('seo_palavras_chave')->nullable(),
                'ia_ultima_acao' => fn () => $table->string('ia_ultima_acao', 50)->nullable(),
                'ia_ultima_fonte' => fn () => $table->string('ia_ultima_fonte', 50)->nullable(),
            ];

            foreach ($campos as $nome => $definicao) {
                if (!Schema::hasColumn('produtos', $nome)) {
                    $definicao();
                }
            }
        });

        if (!Schema::hasTable('produto_conteudos_ia')) {
            Schema::create('produto_conteudos_ia', function (Blueprint $table) {
                $table->id();
                $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->string('tipo', 50);
                $table->string('canal', 50)->nullable();
                $table->string('fonte', 50)->default('assistente_local');
                $table->string('titulo', 255)->nullable();
                $table->longText('conteudo')->nullable();
                $table->json('metadados')->nullable();
                $table->boolean('aplicado')->default(false);
                $table->timestamps();

                $table->index(['produto_id', 'tipo']);
                $table->index(['produto_id', 'canal']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_conteudos_ia');
    }
};
