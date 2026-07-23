<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_products', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_products', 'titulo_externo')) {
                $table->string('titulo_externo', 250)->nullable();
            }
            if (!Schema::hasColumn('marketplace_products', 'permalink')) {
                $table->text('permalink')->nullable();
            }
            if (!Schema::hasColumn('marketplace_products', 'thumbnail')) {
                $table->text('thumbnail')->nullable();
            }
            if (!Schema::hasColumn('marketplace_products', 'categoria_externa')) {
                $table->string('categoria_externa', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('marketplace_products', 'sync_preco')) {
                $table->boolean('sync_preco')->default(true);
            }
            if (!Schema::hasColumn('marketplace_products', 'sync_estoque')) {
                $table->boolean('sync_estoque')->default(true);
            }
            if (!Schema::hasColumn('marketplace_products', 'ultimo_erro')) {
                $table->text('ultimo_erro')->nullable();
            }
        });
    }

    public function down(): void {}
};
