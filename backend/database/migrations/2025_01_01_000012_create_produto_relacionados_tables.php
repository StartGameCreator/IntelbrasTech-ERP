<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('produto_imagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('titulo', 200)->nullable();
            $table->boolean('principal')->default(false);
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });

        Schema::create('produto_variacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->string('sku', 100)->nullable();
            $table->string('codigo_barras', 50)->nullable();
            $table->decimal('preco_venda', 15, 4)->nullable();
            $table->decimal('preco_custo', 15, 4)->nullable();
            $table->decimal('peso', 10, 3)->nullable();
            $table->string('foto', 500)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('produto_composicao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_pai_id')->constrained('produtos')->cascadeOnDelete();
            $table->foreignId('produto_filho_id')->constrained('produtos')->cascadeOnDelete();
            $table->decimal('quantidade', 10, 3)->default(1);
            $table->timestamps();

            $table->unique(['produto_pai_id', 'produto_filho_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('produto_composicao');
        Schema::dropIfExists('produto_variacoes');
        Schema::dropIfExists('produto_imagens');
    }
};
