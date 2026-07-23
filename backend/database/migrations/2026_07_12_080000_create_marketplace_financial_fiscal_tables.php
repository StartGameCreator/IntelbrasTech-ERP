<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_financial_reconciliations')) {
            Schema::create('marketplace_financial_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('marketplace_order_id')->constrained('marketplace_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('pedido_id')->nullable()->index();
                $table->string('marketplace', 50)->default('mercado_livre')->index();
                $table->string('external_payment_id', 120)->nullable()->index();
                $table->string('status', 40)->default('pendente')->index();
                $table->string('moeda', 10)->default('BRL');
                $table->decimal('valor_bruto', 15, 2)->default(0);
                $table->decimal('taxas', 15, 2)->default(0);
                $table->decimal('frete', 15, 2)->default(0);
                $table->decimal('valor_liquido', 15, 2)->default(0);
                $table->timestamp('data_venda')->nullable();
                $table->timestamp('data_liberacao')->nullable();
                $table->timestamp('conciliado_em')->nullable();
                $table->json('dados')->nullable();
                $table->text('ultimo_erro')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'marketplace_order_id']);
            });
        }

        if (!Schema::hasTable('marketplace_fiscal_queue_items')) {
            Schema::create('marketplace_fiscal_queue_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('marketplace_order_id')->constrained('marketplace_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('pedido_id')->nullable()->index();
                $table->string('documento_tipo', 20)->default('nfe')->index();
                $table->string('status', 40)->default('pendente')->index();
                $table->string('ambiente', 20)->default('homologacao');
                $table->unsignedInteger('serie')->default(1);
                $table->unsignedBigInteger('nota_fiscal_id')->nullable()->index();
                $table->json('validacoes')->nullable();
                $table->text('ultimo_erro')->nullable();
                $table->timestamp('preparado_em')->nullable();
                $table->timestamp('emitido_em')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'marketplace_order_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_fiscal_queue_items');
        Schema::dropIfExists('marketplace_financial_reconciliations');
    }
};
