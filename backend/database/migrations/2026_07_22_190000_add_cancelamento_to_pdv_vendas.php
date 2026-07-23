<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pdv_vendas', function (Blueprint $table) {
            if (! Schema::hasColumn('pdv_vendas', 'motivo_cancelamento')) {
                $table->text('motivo_cancelamento')->nullable()->after('observacoes');
            }

            if (! Schema::hasColumn('pdv_vendas', 'cancelada_em')) {
                $table->timestamp('cancelada_em')->nullable()->after('concluida_em');
            }

            if (! Schema::hasColumn('pdv_vendas', 'cancelada_por')) {
                $table->unsignedBigInteger('cancelada_por')->nullable()->after('usuario_id');
                $table->index('cancelada_por');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pdv_vendas', function (Blueprint $table) {
            foreach (['cancelada_por', 'cancelada_em', 'motivo_cancelamento'] as $column) {
                if (Schema::hasColumn('pdv_vendas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
