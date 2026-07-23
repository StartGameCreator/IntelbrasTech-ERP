<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('caixas')) {
            Schema::create('caixas', function (Blueprint $table) {
                $table->id();
                $table->uuid()->unique();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
                $table->foreignId('deposito_id')->nullable()->constrained('depositos')->nullOnDelete();
                $table->string('numero', 30);
                $table->string('status', 20)->default('aberto');
                $table->decimal('valor_abertura', 15, 2)->default(0);
                $table->decimal('valor_fechamento_informado', 15, 2)->nullable();
                $table->decimal('valor_fechamento_calculado', 15, 2)->nullable();
                $table->decimal('diferenca', 15, 2)->nullable();
                $table->timestamp('aberto_em')->useCurrent();
                $table->timestamp('fechado_em')->nullable();
                $table->text('observacoes_abertura')->nullable();
                $table->text('observacoes_fechamento')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'status']);
                $table->index(['empresa_id', 'usuario_id', 'status']);
            });
        }

        if (!Schema::hasTable('caixa_movimentacoes')) {
            Schema::create('caixa_movimentacoes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('caixa_id')->constrained('caixas')->cascadeOnDelete();
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->string('tipo', 30);
                $table->decimal('valor', 15, 2);
                $table->string('forma_pagamento', 30)->nullable();
                $table->string('descricao', 250)->nullable();
                $table->unsignedBigInteger('pedido_id')->nullable()->index();
                $table->json('metadados')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['caixa_id', 'tipo']);
            });
        }

        if (!Schema::hasTable('pdv_vendas')) {
            Schema::create('pdv_vendas', function (Blueprint $table) {
                $table->id();
                $table->uuid()->unique();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('caixa_id')->constrained('caixas')->cascadeOnDelete();
                $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
                $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
                $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
                $table->string('numero', 30);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('desconto', 15, 2)->default(0);
                $table->decimal('acrescimo', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->decimal('troco', 15, 2)->default(0);
                $table->string('status', 20)->default('concluida');
                $table->text('observacoes')->nullable();
                $table->timestamp('concluida_em')->useCurrent();
                $table->timestamps();

                $table->index(['empresa_id', 'concluida_em']);
            });
        }

        if (!Schema::hasTable('pdv_pagamentos')) {
            Schema::create('pdv_pagamentos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pdv_venda_id')->constrained('pdv_vendas')->cascadeOnDelete();
                $table->foreignId('forma_pagamento_id')->nullable()->constrained('formas_pagamento')->nullOnDelete();
                $table->string('tipo', 30);
                $table->decimal('valor', 15, 2);
                $table->unsignedInteger('parcelas')->default(1);
                $table->string('nsu', 100)->nullable();
                $table->string('autorizacao', 100)->nullable();
                $table->json('metadados')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pdv_pagamentos');
        Schema::dropIfExists('pdv_vendas');
        Schema::dropIfExists('caixa_movimentacoes');
        Schema::dropIfExists('caixas');
    }
};
