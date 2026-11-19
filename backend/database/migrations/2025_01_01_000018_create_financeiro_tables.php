<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('plano_contas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('conta_pai_id')->nullable()->constrained('plano_contas')->nullOnDelete();
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 150);
            $table->string('tipo', 20); // receita, despesa, ativo, passivo
            $table->char('natureza', 1)->nullable(); // D=Débito, C=Crédito
            $table->integer('nivel')->default(1);
            $table->boolean('analitica')->default(true); // false = sintética (apenas agrupador)
            $table->boolean('ativo')->default(true);
        });

        Schema::create('contas_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->string('banco', 100)->nullable();
            $table->string('agencia', 10)->nullable();
            $table->string('conta', 20)->nullable();
            $table->string('tipo', 20)->default('corrente'); // corrente, poupanca, caixa, investimento
            $table->decimal('saldo_inicial', 15, 2)->default(0);
            $table->decimal('saldo_atual', 15, 2)->default(0);
            $table->string('cor', 7)->nullable();
            $table->string('icone', 50)->nullable();
            $table->boolean('principal')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->softDeletes();
        });

        Schema::create('formas_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->string('tipo', 30); // dinheiro, pix, boleto, cartao_credito, cartao_debito, transferencia, cheque
            $table->decimal('taxa', 5, 2)->default(0);
            $table->integer('prazo_recebimento_dias')->default(0);
            $table->foreignId('conta_bancaria_id')->nullable()->constrained('contas_bancarias')->nullOnDelete();
            $table->boolean('ativo')->default(true);
        });

        Schema::create('contas_pagar', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
            // FK para pedidos_compra fica pendente até a migration do módulo de Compras existir.
            $table->unsignedBigInteger('pedido_compra_id')->nullable()->index();
            $table->foreignId('conta_pai_id')->nullable()->constrained('contas_pagar')->nullOnDelete();
            $table->foreignId('plano_conta_id')->nullable()->constrained('plano_contas')->nullOnDelete();
            $table->foreignId('forma_pagamento_id')->nullable()->constrained('formas_pagamento')->nullOnDelete();
            $table->foreignId('conta_bancaria_id')->nullable()->constrained('contas_bancarias')->nullOnDelete();
            $table->string('descricao', 300);
            $table->string('numero_documento', 50)->nullable();
            $table->integer('parcela')->default(1);
            $table->integer('total_parcelas')->default(1);
            $table->decimal('valor', 15, 2);
            $table->decimal('valor_desconto', 15, 2)->default(0);
            $table->decimal('valor_juros', 15, 2)->default(0);
            $table->decimal('valor_multa', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->date('data_emissao')->useCurrent();
            $table->date('data_vencimento');
            $table->date('data_pagamento')->nullable();
            $table->string('status', 20)->default('aberto'); // aberto, parcial, pago, cancelado, atrasado
            $table->boolean('recorrente')->default(false);
            $table->string('recorrencia_tipo', 20)->nullable(); // mensal, semanal, anual
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'data_vencimento']);
        });

        Schema::create('contas_receber', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->foreignId('nota_fiscal_id')->nullable()->constrained('notas_fiscais')->nullOnDelete();
            $table->foreignId('conta_pai_id')->nullable()->constrained('contas_receber')->nullOnDelete();
            $table->foreignId('plano_conta_id')->nullable()->constrained('plano_contas')->nullOnDelete();
            $table->foreignId('forma_pagamento_id')->nullable()->constrained('formas_pagamento')->nullOnDelete();
            $table->foreignId('conta_bancaria_id')->nullable()->constrained('contas_bancarias')->nullOnDelete();
            $table->string('descricao', 300);
            $table->string('numero_documento', 50)->nullable();
            $table->integer('parcela')->default(1);
            $table->integer('total_parcelas')->default(1);
            $table->decimal('valor', 15, 2);
            $table->decimal('valor_desconto', 15, 2)->default(0);
            $table->decimal('valor_juros', 15, 2)->default(0);
            $table->decimal('valor_multa', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->date('data_emissao')->useCurrent();
            $table->date('data_vencimento');
            $table->date('data_pagamento')->nullable();
            $table->string('status', 20)->default('aberto'); // aberto, parcial, pago, cancelado, atrasado
            $table->text('observacoes')->nullable();
            $table->string('nosso_numero', 50)->nullable();
            $table->text('linha_digitavel')->nullable();
            $table->text('codigo_pix')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'data_vencimento']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('contas_receber');
        Schema::dropIfExists('contas_pagar');
        Schema::dropIfExists('formas_pagamento');
        Schema::dropIfExists('contas_bancarias');
        Schema::dropIfExists('plano_contas');
    }
};
