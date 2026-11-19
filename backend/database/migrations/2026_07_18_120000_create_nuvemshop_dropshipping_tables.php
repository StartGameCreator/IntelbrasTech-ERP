<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_accounts', 'external_store_id')) $table->string('external_store_id', 80)->nullable()->index();
        });

        if (!Schema::hasTable('dropship_regras')) {
            Schema::create('dropship_regras', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('nome', 150);
                $table->string('tipo', 40)->default('margem_percentual');
                $table->decimal('valor', 15, 4)->default(0);
                $table->decimal('frete_adicional', 15, 2)->default(0);
                $table->boolean('ativo')->default(true);
                $table->unsignedInteger('prioridade')->default(100);
                $table->json('condicoes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('dropship_pedidos_fornecedor')) {
            Schema::create('dropship_pedidos_fornecedor', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('marketplace_order_id')->constrained('marketplace_orders')->cascadeOnDelete();
                $table->foreignId('fornecedor_id')->constrained('fornecedores')->restrictOnDelete();
                $table->string('numero', 80)->index();
                $table->string('status', 40)->default('pendente')->index();
                $table->decimal('custo_produtos', 15, 2)->default(0);
                $table->decimal('frete_fornecedor', 15, 2)->default(0);
                $table->decimal('total_custo', 15, 2)->default(0);
                $table->string('codigo_rastreio', 150)->nullable();
                $table->string('transportadora', 120)->nullable();
                $table->timestamp('enviado_fornecedor_em')->nullable();
                $table->timestamp('despachado_em')->nullable();
                $table->json('dados')->nullable();
                $table->timestamps();
                $table->unique(['marketplace_order_id','fornecedor_id']);
            });
        }

        if (!Schema::hasTable('dropship_pedido_itens')) {
            Schema::create('dropship_pedido_itens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pedido_fornecedor_id')->constrained('dropship_pedidos_fornecedor')->cascadeOnDelete();
                $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
                $table->string('external_product_id', 120)->nullable();
                $table->string('sku', 150)->nullable()->index();
                $table->string('descricao', 255);
                $table->decimal('quantidade', 12, 3)->default(1);
                $table->decimal('custo_unitario', 15, 4)->default(0);
                $table->decimal('total_custo', 15, 2)->default(0);
                $table->json('dados')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('produto_fornecedores', function (Blueprint $table) {
            if (!Schema::hasColumn('produto_fornecedores', 'estoque_fornecedor')) $table->decimal('estoque_fornecedor', 12, 3)->nullable();
            if (!Schema::hasColumn('produto_fornecedores', 'dropship_ativo')) $table->boolean('dropship_ativo')->default(false)->index();
            if (!Schema::hasColumn('produto_fornecedores', 'ultima_sincronizacao_em')) $table->timestamp('ultima_sincronizacao_em')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropship_pedido_itens');
        Schema::dropIfExists('dropship_pedidos_fornecedor');
        Schema::dropIfExists('dropship_regras');
    }
};
