<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_carousel_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('tipo', 30)->default('servico');
            $table->string('categoria', 120)->nullable();
            $table->string('titulo', 180);
            $table->text('descricao')->nullable();
            $table->string('imagem_url', 1000)->nullable();
            $table->string('link_url', 1000)->nullable();
            $table->string('texto_link', 80)->nullable();
            $table->boolean('link_externo')->default(false);
            $table->boolean('ativo')->default(true)->index();
            $table->unsignedInteger('ordem')->default(0)->index();
            $table->timestamp('publicar_em')->nullable();
            $table->timestamp('expirar_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_carousel_items');
    }
};
