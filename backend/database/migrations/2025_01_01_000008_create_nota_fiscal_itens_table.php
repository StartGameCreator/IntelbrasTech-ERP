<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nota_fiscal_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_fiscal_id')->constrained('notas_fiscais')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $table->integer('numero_item');
            $table->string('codigo', 50)->nullable();
            $table->string('descricao', 300);
            $table->string('ncm', 10)->nullable();
            $table->string('cfop', 10)->nullable();
            $table->string('unidade', 10)->nullable();
            $table->decimal('quantidade', 10, 4);
            $table->decimal('valor_unitario', 15, 10);
            $table->decimal('valor_total', 15, 2);
            $table->decimal('valor_desconto', 15, 2)->default(0);

            $table->smallInteger('icms_origem')->default(0);
            $table->string('icms_cst', 5)->nullable();
            $table->decimal('icms_base', 15, 2)->default(0);
            $table->decimal('icms_aliquota', 5, 2)->default(0);
            $table->decimal('icms_valor', 15, 2)->default(0);

            $table->string('ipi_cst', 3)->nullable();
            $table->decimal('ipi_base', 15, 2)->default(0);
            $table->decimal('ipi_aliquota', 5, 2)->default(0);
            $table->decimal('ipi_valor', 15, 2)->default(0);

            $table->string('pis_cst', 3)->nullable();
            $table->decimal('pis_base', 15, 2)->default(0);
            $table->decimal('pis_aliquota', 5, 2)->default(0);
            $table->decimal('pis_valor', 15, 2)->default(0);

            $table->string('cofins_cst', 3)->nullable();
            $table->decimal('cofins_base', 15, 2)->default(0);
            $table->decimal('cofins_aliquota', 5, 2)->default(0);
            $table->decimal('cofins_valor', 15, 2)->default(0);

            $table->timestamps();
            $table->index('nota_fiscal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_fiscal_itens');
    }
};
