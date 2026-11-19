<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pedido_compra_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_compra_id')->constrained('pedidos_compra')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->foreignId('variacao_id')->nullable()->constrained('produto_variacoes')->nullOnDelete();
            $table->string('descricao', 300)->nullable();
            $table->decimal('quantidade', 10, 3);
            $table->decimal('quantidade_recebida', 10, 3)->default(0);
            $table->decimal('preco_unitario', 15, 4);
            $table->decimal('desconto_percentual', 5, 2)->default(0);
            $table->decimal('total', 15, 2);
        });
    }
    public function down(): void { Schema::dropIfExists('pedido_compra_itens'); }
};
