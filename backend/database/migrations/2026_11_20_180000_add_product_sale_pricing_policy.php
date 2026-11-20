<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('modo_preco_venda', 20)->default('manual')->after('desconto_maximo');
            $table->decimal('markup_venda', 10, 2)->default(0)->after('modo_preco_venda');
        });

        // Os preços trazidos da tabela Intelbras são custos de compra.
        DB::table('produtos')
            ->where('fonte_importacao', 'Tabela de Preços Intelbras')
            ->where('preco_venda', '>', 0)
            ->update([
                'preco_custo' => DB::raw('preco_venda'),
                'preco_venda' => 0,
                'preco_venda_minimo' => 0,
                'margem_lucro' => 0,
                'modo_preco_venda' => 'manual',
                'markup_venda' => 0,
            ]);
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn(['modo_preco_venda', 'markup_venda']);
        });
    }
};
