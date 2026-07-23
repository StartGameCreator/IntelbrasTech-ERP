<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('series_fiscais')) {
            return;
        }

        Schema::table('series_fiscais', function (Blueprint $table) {
            if (!Schema::hasColumn('series_fiscais', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (!Schema::hasColumn('series_fiscais', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Compatibilidade fiscal: não removemos timestamps para evitar perda
        // de rastreabilidade ou quebra de versões posteriores do ERP.
    }
};
