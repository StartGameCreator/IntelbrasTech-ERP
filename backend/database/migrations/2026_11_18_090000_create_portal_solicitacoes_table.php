<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_solicitacoes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('tipo', 30);
            $table->string('nome', 200);
            $table->string('email', 150)->nullable();
            $table->string('telefone', 30);
            $table->string('cidade', 120)->nullable();
            $table->string('status', 30)->default('recebida');
            $table->json('dados')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['empresa_id', 'tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_solicitacoes');
    }
};
