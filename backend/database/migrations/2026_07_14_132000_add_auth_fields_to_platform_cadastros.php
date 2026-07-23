<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('platform_cadastros')) {
            return;
        }

        Schema::table('platform_cadastros', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_cadastros', 'senha_hash')) {
                $table->string('senha_hash')->nullable()->after('email');
            }

            if (!Schema::hasColumn('platform_cadastros', 'social_provider')) {
                $table->string('social_provider', 30)->nullable()->after('senha_hash');
            }

            if (!Schema::hasColumn('platform_cadastros', 'social_provider_id')) {
                $table->string('social_provider_id', 190)->nullable()->after('social_provider');
            }

            if (!Schema::hasColumn('platform_cadastros', 'email_verificado_em')) {
                $table->timestamp('email_verificado_em')->nullable()->after('social_provider_id');
            }
        });
    }

    public function down(): void
    {
        // Compatibilidade: não remove dados de autenticação já registrados.
    }
};
