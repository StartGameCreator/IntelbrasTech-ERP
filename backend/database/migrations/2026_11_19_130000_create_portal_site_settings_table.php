<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_site_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained('empresas')->cascadeOnDelete();
            $table->string('site_nome', 120)->nullable();
            $table->string('seo_titulo', 180)->nullable();
            $table->text('seo_descricao')->nullable();
            $table->text('seo_palavras_chave')->nullable();
            $table->string('seo_canonical_url', 500)->nullable();
            $table->string('seo_og_image_url', 1000)->nullable();
            $table->string('google_site_verification', 500)->nullable();
            $table->string('meta_pixel_id', 80)->nullable();
            $table->boolean('meta_pixel_ativo')->default(false);
            $table->string('tiktok_pixel_id', 80)->nullable();
            $table->boolean('tiktok_pixel_ativo')->default(false);
            $table->string('logo_url', 1000)->nullable();
            $table->string('favicon_url', 1000)->nullable();
            $table->boolean('indexar_site')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_site_settings');
    }
};
