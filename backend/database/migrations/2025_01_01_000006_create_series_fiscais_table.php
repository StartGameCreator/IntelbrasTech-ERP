<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('series_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo', 10); // NFe, NFCe, NFSe, CTe, MDFe
            $table->string('serie', 5);
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->smallInteger('ambiente')->default(2); // 1=Produção, 2=Homologação
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['empresa_id', 'tipo', 'serie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series_fiscais');
    }
};
