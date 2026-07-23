<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'cnpj')) {
                $table->string('cnpj', 14)->nullable()->index();
            }
            if (!Schema::hasColumn('leads', 'cidade')) {
                $table->string('cidade')->nullable();
            }
            if (!Schema::hasColumn('leads', 'uf')) {
                $table->string('uf', 2)->nullable();
            }
            if (!Schema::hasColumn('leads', 'latitude')) {
                $table->decimal('latitude', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('leads', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable();
            }
            if (!Schema::hasColumn('leads', 'fonte_referencia')) {
                $table->string('fonte_referencia')->nullable();
            }
            if (!Schema::hasColumn('leads', 'dados_extras')) {
                $table->text('dados_extras')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach (['cnpj','cidade','uf','latitude','longitude','fonte_referencia','dados_extras'] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
