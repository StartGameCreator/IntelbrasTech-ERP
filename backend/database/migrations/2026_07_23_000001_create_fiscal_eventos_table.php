<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('fiscal_eventos')) return;
        Schema::create('fiscal_eventos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('empresa_id')->index();
            $table->uuid('nota_fiscal_id')->index();
            $table->uuid('usuario_id')->nullable()->index();
            $table->string('tipo', 60)->index();
            $table->string('descricao', 255)->nullable();
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('dados')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_eventos');
    }
};
