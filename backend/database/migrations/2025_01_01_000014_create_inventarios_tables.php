<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('deposito_id')->nullable()->constrained('depositos')->nullOnDelete();
            $table->string('numero', 20)->nullable();
            $table->timestamp('data_inicio')->nullable();
            $table->timestamp('data_fim')->nullable();
            $table->string('status', 20)->default('aberto'); // aberto, em_andamento, finalizado, cancelado
            $table->foreignId('responsavel_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        // Extensão ao schema original: guarda a contagem produto a produto de cada inventário.
        Schema::create('inventario_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventario_id')->constrained('inventarios')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->foreignId('variacao_id')->nullable()->constrained('produto_variacoes')->nullOnDelete();
            $table->decimal('quantidade_sistema', 10, 3)->default(0);
            $table->decimal('quantidade_contada', 10, 3)->nullable();
            $table->decimal('diferenca', 10, 3)->default(0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('inventario_itens');
        Schema::dropIfExists('inventarios');
    }
};
