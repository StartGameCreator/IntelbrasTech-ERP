<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->foreignId('variacao_id')->nullable()->constrained('produto_variacoes')->nullOnDelete();
            $table->foreignId('deposito_id')->constrained('depositos');
            $table->foreignId('deposito_destino_id')->nullable()->constrained('depositos')->nullOnDelete();
            $table->string('tipo', 30);    // entrada, saida, transferencia, ajuste, inventario
            $table->string('motivo', 50)->nullable(); // compra, venda, devolucao, perda, ajuste, transferencia, inventario
            $table->string('documento_tipo', 30)->nullable(); // pedido, nota_fiscal, os, compra
            $table->unsignedBigInteger('documento_id')->nullable();
            $table->string('documento_numero', 50)->nullable();
            $table->decimal('quantidade', 10, 3);
            $table->decimal('custo_unitario', 15, 4)->default(0);
            $table->decimal('custo_total', 15, 4)->default(0);
            $table->decimal('saldo_anterior', 10, 3)->nullable();
            $table->decimal('saldo_posterior', 10, 3)->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['empresa_id', 'produto_id']);
            $table->index(['empresa_id', 'tipo']);
        });
    }
    public function down(): void { Schema::dropIfExists('movimentacoes_estoque'); }
};
