<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('produto_variacoes', function (Blueprint $table) {
            $campos = [
                'nome' => fn () => $table->string('nome', 150)->nullable(),
                'atributos' => fn () => $table->json('atributos')->nullable(),
                'estoque_minimo' => fn () => $table->decimal('estoque_minimo', 12, 3)->default(0),
                'ordem' => fn () => $table->unsignedInteger('ordem')->default(0),
            ];

            foreach ($campos as $nome => $definicao) {
                if (!Schema::hasColumn('produto_variacoes', $nome)) {
                    $definicao();
                }
            }
        });

        if (!Schema::hasTable('produto_codigos')) {
            Schema::create('produto_codigos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
                $table->string('tipo', 30);
                $table->string('codigo', 100);
                $table->decimal('quantidade', 12, 3)->default(1);
                $table->boolean('principal')->default(false);
                $table->timestamps();

                $table->unique(['produto_id', 'tipo', 'codigo']);
                $table->index(['produto_id', 'tipo']);
            });
        }

        if (!Schema::hasTable('produto_listas_preco')) {
            Schema::create('produto_listas_preco', function (Blueprint $table) {
                $table->id();
                $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
                $table->foreignId('tabela_preco_id')->constrained('tabelas_preco')->cascadeOnDelete();
                $table->decimal('preco', 15, 4);
                $table->decimal('preco_minimo', 15, 4)->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamps();

                $table->unique(['produto_id', 'tabela_preco_id']);
            });
        }

        if (!Schema::hasTable('produto_documentos')) {
            Schema::create('produto_documentos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
                $table->string('tipo', 50)->default('outro');
                $table->string('titulo', 200);
                $table->string('origem', 20)->default('url');
                $table->string('url', 1000)->nullable();
                $table->string('arquivo', 500)->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('tamanho_bytes')->nullable();
                $table->boolean('publico')->default(true);
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();

                $table->index(['produto_id', 'tipo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_documentos');
        Schema::dropIfExists('produto_listas_preco');
        Schema::dropIfExists('produto_codigos');
    }
};
