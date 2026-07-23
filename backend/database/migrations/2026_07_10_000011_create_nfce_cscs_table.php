<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nfce_cscs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedTinyInteger('ambiente')->default(2); // 1=Produção, 2=Homologação
            $table->string('identificador', 10);
            $table->text('codigo_criptografado');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['empresa_id', 'ambiente', 'identificador']);
            $table->index(['empresa_id', 'ambiente', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nfce_cscs');
    }
};
