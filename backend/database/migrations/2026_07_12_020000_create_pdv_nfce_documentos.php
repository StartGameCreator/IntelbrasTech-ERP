<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pdv_nfce_documentos')) {
            Schema::create('pdv_nfce_documentos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('pdv_venda_id')->constrained('pdv_vendas')->cascadeOnDelete();
                $table->string('ambiente', 20)->default('homologacao');
                $table->unsignedInteger('serie')->default(1);
                $table->unsignedBigInteger('numero')->nullable();
                $table->string('status', 30)->default('rascunho')->index();
                $table->longText('xml_rascunho')->nullable();
                $table->json('validacoes')->nullable();
                $table->timestamp('gerado_em')->nullable();
                $table->timestamps();
                $table->unique(['empresa_id', 'pdv_venda_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_nfce_documentos');
    }
};
