<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_order_processings')) {
            Schema::create('marketplace_order_processings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('marketplace_order_id')->constrained('marketplace_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('pedido_id')->nullable()->index();
                $table->unsignedBigInteger('deposito_id')->nullable()->index();
                $table->string('status', 40)->default('pendente')->index();
                $table->boolean('estoque_baixado')->default(false);
                $table->boolean('financeiro_criado')->default(false);
                $table->boolean('fiscal_preparado')->default(false);
                $table->json('mapeamentos')->nullable();
                $table->json('validacoes')->nullable();
                $table->text('ultimo_erro')->nullable();
                $table->timestamp('processado_em')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'marketplace_order_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_order_processings');
    }
};
