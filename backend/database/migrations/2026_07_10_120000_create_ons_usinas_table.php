<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ons_usinas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_ceg', 40)->unique();
            $table->string('nome');
            $table->string('uf', 2)->nullable()->index();
            $table->string('municipio')->nullable()->index();
            $table->string('fase')->nullable()->index();
            $table->string('fonte')->nullable();
            $table->decimal('potencia_mw', 14, 6)->nullable()->index();
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->date('entrada_operacao')->nullable();
            $table->text('proprietarios')->nullable();
            $table->string('sub_bacia')->nullable();
            $table->string('fonte_dados')->default('ANEEL SIGA');
            $table->timestamp('fonte_atualizada_em')->nullable();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->timestamp('importada_crm_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ons_usinas');
    }
};
