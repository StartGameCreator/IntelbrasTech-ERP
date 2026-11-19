<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('leads', 'cnpj')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('cnpj', 14)->nullable()->index()->after('empresa_nome');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'cnpj')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('cnpj');
            });
        }
    }
};
