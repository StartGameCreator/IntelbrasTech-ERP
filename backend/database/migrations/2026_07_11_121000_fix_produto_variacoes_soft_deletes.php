<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('produto_variacoes')
            && !Schema::hasColumn('produto_variacoes', 'deleted_at')) {
            Schema::table('produto_variacoes', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('produto_variacoes')
            && Schema::hasColumn('produto_variacoes', 'deleted_at')) {
            Schema::table('produto_variacoes', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
