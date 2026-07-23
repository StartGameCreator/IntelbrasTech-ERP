<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('configuracoes_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();
            $table->string('versao_layout', 10)->default('4.00');
            $table->char('uf', 2)->nullable();
            $table->unsignedTinyInteger('ambiente_nfe')->default(2); // 1=Produção, 2=Homologação
            $table->unsignedTinyInteger('ambiente_nfce')->default(2);
            $table->string('serie_nfe', 5)->default('1');
            $table->string('serie_nfce', 5)->default('1');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_fiscais');
    }
};
