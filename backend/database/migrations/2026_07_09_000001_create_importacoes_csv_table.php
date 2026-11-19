<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('importacoes_csv', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo', 50);
            $table->string('nome_original', 255);
            $table->string('arquivo', 500);
            $table->string('status', 30)->default('recebido');
            $table->integer('total_linhas')->default(0);
            $table->integer('linhas_processadas')->default(0);
            $table->json('erros')->nullable();
            $table->json('mapeamento')->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'tipo']);
            $table->index(['empresa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes_csv');
    }
};
