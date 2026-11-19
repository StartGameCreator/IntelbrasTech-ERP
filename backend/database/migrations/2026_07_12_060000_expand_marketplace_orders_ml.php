<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_orders', 'numero_externo')) {
                $table->string('numero_externo', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('marketplace_orders', 'comprador_nome')) {
                $table->string('comprador_nome', 200)->nullable();
            }
            if (!Schema::hasColumn('marketplace_orders', 'comprador_documento')) {
                $table->string('comprador_documento', 30)->nullable()->index();
            }
            if (!Schema::hasColumn('marketplace_orders', 'comprador_email')) {
                $table->string('comprador_email', 200)->nullable();
            }
            if (!Schema::hasColumn('marketplace_orders', 'data_venda')) {
                $table->timestamp('data_venda')->nullable()->index();
            }
            if (!Schema::hasColumn('marketplace_orders', 'data_atualizacao_externa')) {
                $table->timestamp('data_atualizacao_externa')->nullable();
            }
            if (!Schema::hasColumn('marketplace_orders', 'status_pagamento')) {
                $table->string('status_pagamento', 50)->nullable()->index();
            }
            if (!Schema::hasColumn('marketplace_orders', 'status_envio')) {
                $table->string('status_envio', 50)->nullable()->index();
            }
            if (!Schema::hasColumn('marketplace_orders', 'shipping_id')) {
                $table->string('shipping_id', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('marketplace_orders', 'moeda')) {
                $table->string('moeda', 10)->default('BRL');
            }
            if (!Schema::hasColumn('marketplace_orders', 'itens')) {
                $table->json('itens')->nullable();
            }
            if (!Schema::hasColumn('marketplace_orders', 'endereco_entrega')) {
                $table->json('endereco_entrega')->nullable();
            }
            if (!Schema::hasColumn('marketplace_orders', 'ultimo_erro')) {
                $table->text('ultimo_erro')->nullable();
            }
            if (!Schema::hasColumn('marketplace_orders', 'sincronizado_em')) {
                $table->timestamp('sincronizado_em')->nullable();
            }
        });
    }

    public function down(): void {}
};
