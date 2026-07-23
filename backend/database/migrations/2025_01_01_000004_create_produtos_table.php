<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('categoria_pai_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->string('nome', 100);
            $table->string('slug', 150)->nullable();
            $table->text('descricao')->nullable();
            $table->string('imagem', 500)->nullable();
            $table->integer('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->string('slug', 150)->nullable();
            $table->string('logo', 500)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('sigla', 10);
            $table->string('descricao', 50)->nullable();
            $table->timestamps();
            $table->unique(['empresa_id','sigla']);
        });
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
            // Nota: FK explícita para "fornecedores" fica pendente até a migration de fornecedores rodar (posterior a esta).
            $table->unsignedBigInteger('fornecedor_id')->nullable()->index();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades_medida')->nullOnDelete();
            $table->string('tipo', 20)->default('simples');
            $table->string('codigo', 50)->nullable();
            $table->string('codigo_barras', 50)->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('ean', 20)->nullable();
            $table->string('ncm', 10)->nullable();
            $table->string('cest', 10)->nullable();
            $table->string('cfop', 10)->nullable();
            $table->smallInteger('origem')->default(0);
            $table->string('nome', 200);
            $table->string('slug', 250)->nullable();
            $table->text('descricao')->nullable();
            $table->string('descricao_curta', 500)->nullable();
            $table->decimal('peso', 10, 3)->default(0);
            $table->decimal('largura', 10, 2)->default(0);
            $table->decimal('altura', 10, 2)->default(0);
            $table->decimal('comprimento', 10, 2)->default(0);
            $table->decimal('preco_custo', 15, 4)->default(0);
            $table->decimal('preco_venda', 15, 4)->default(0);
            $table->decimal('preco_venda_minimo', 15, 4)->default(0);
            $table->decimal('margem_lucro', 5, 2)->default(0);
            $table->decimal('desconto_maximo', 5, 2)->default(0);
            $table->string('icms_cst', 5)->nullable();
            $table->decimal('icms_aliquota', 5, 2)->default(0);
            $table->string('pis_cst', 3)->nullable();
            $table->decimal('pis_aliquota', 5, 2)->default(0);
            $table->string('cofins_cst', 3)->nullable();
            $table->decimal('cofins_aliquota', 5, 2)->default(0);
            $table->string('ipi_cst', 3)->nullable();
            $table->decimal('ipi_aliquota', 5, 2)->default(0);
            $table->boolean('controla_estoque')->default(true);
            $table->decimal('estoque_minimo', 10, 3)->default(0);
            $table->decimal('estoque_maximo', 10, 3)->default(0);
            $table->decimal('ponto_reposicao', 10, 3)->default(0);
            $table->boolean('ativo')->default(true);
            $table->boolean('destaque')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['empresa_id','ativo']);
            $table->index(['empresa_id','codigo']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('unidades_medida');
        Schema::dropIfExists('marcas');
        Schema::dropIfExists('categorias');
    }
};
