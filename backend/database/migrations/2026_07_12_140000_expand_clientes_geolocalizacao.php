<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('clientes')) {
            return;
        }

        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->index();
            }

            if (!Schema::hasColumn('clientes', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->index();
            }

            if (!Schema::hasColumn('clientes', 'geolocalizacao_fonte')) {
                $table->string('geolocalizacao_fonte', 50)->nullable();
            }

            if (!Schema::hasColumn('clientes', 'geolocalizacao_em')) {
                $table->timestamp('geolocalizacao_em')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Patch de compatibilidade: não remove colunas de geolocalização.
    }
};
