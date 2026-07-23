<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('provider', 80);
            $table->string('name', 120);
            $table->string('category', 50);
            $table->text('credentials_encrypted')->nullable();
            $table->json('settings')->default('{}');
            $table->boolean('enabled')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_fallback')->default(false);
            $table->string('status', 30)->default('not_configured');
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'provider']);
            $table->index(['empresa_id', 'category', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_integrations');
    }
};
