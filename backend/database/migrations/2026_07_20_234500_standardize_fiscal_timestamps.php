<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tabelas fiscais operadas por Models Eloquent com timestamps ativos.
     *
     * Algumas bases SQLite antigas foram criadas sem created_at/updated_at.
     * A migration adiciona somente as colunas ausentes e preserva todos os dados.
     */
    public function up(): void
    {
        $tables = [
            'series_fiscais',
            'notas_fiscais',
            'nota_fiscal_itens',
            'cartas_correcao',
            'certificados_digitais',
            'nfce_cscs',
            'pdv_nfce_documentos',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            $missingCreatedAt = !Schema::hasColumn($tableName, 'created_at');
            $missingUpdatedAt = !Schema::hasColumn($tableName, 'updated_at');

            if (!$missingCreatedAt && !$missingUpdatedAt) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($missingCreatedAt, $missingUpdatedAt) {
                if ($missingCreatedAt) {
                    $table->timestamp('created_at')->nullable();
                }

                if ($missingUpdatedAt) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Migration de compatibilidade. As colunas não são removidas para evitar
        // perda de rastreabilidade e quebra de versões posteriores do módulo fiscal.
    }
};
