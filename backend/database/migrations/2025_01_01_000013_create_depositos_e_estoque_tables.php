<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('depositos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->text('descricao')->nullable();
            $table->string('responsavel', 150)->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('logradouro', 200)->nullable();
            $table->string('numero', 10)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->char('estado', 2)->nullable();
            $table->boolean('principal')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('variacao_id')->nullable()->constrained('produto_variacoes')->nullOnDelete();
            $table->foreignId('deposito_id')->constrained('depositos');
            $table->decimal('quantidade', 10, 3)->default(0);
            $table->decimal('quantidade_reservada', 10, 3)->default(0);
            // Coluna gerada: Postgres calcula automaticamente, não é preenchida pela aplicação.
            // Coluna calculada pela aplicação (não pelo banco) para funcionar de forma
            // idêntica em Postgres e SQLite — ver Estoque::booted() no model.
            $table->decimal('quantidade_disponivel', 10, 3)->default(0);
            $table->decimal('custo_medio', 15, 4)->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->unique(['produto_id', 'variacao_id', 'deposito_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('estoque');
        Schema::dropIfExists('depositos');
    }
};
