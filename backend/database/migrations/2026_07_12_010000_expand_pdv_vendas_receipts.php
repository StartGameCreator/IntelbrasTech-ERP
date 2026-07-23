<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pdv_vendas', function (Blueprint $table) {
            if (!Schema::hasColumn('pdv_vendas', 'comprovante_codigo')) {
                $table->string('comprovante_codigo', 50)->nullable()->index();
            }
            if (!Schema::hasColumn('pdv_vendas', 'nfce_status')) {
                $table->string('nfce_status', 30)->default('nao_emitida')->index();
            }
            if (!Schema::hasColumn('pdv_vendas', 'nota_fiscal_id')) {
                $table->unsignedBigInteger('nota_fiscal_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pdv_vendas', function (Blueprint $table) {
            foreach (['comprovante_codigo','nfce_status','nota_fiscal_id'] as $column) {
                if (Schema::hasColumn('pdv_vendas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
