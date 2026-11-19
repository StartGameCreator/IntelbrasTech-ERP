<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_connectors')) {
            Schema::create('marketplace_connectors', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 100)->unique();
                $table->string('nome', 150);
                $table->string('categoria', 60)->index();
                $table->string('descricao', 300)->nullable();
                $table->string('icone', 255)->nullable();
                $table->string('status', 30)->default('disponivel');
                $table->boolean('ativo')->default(true);
                $table->unsignedInteger('ordem')->default(0);
                $table->json('recursos')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('marketplace_accounts')) {
            Schema::create('marketplace_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('connector_id')->constrained('marketplace_connectors')->cascadeOnDelete();
                $table->string('nome_conta', 150)->nullable();
                $table->string('status', 30)->default('desconectado')->index();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->json('configuracoes')->nullable();
                $table->timestamp('ultima_sincronizacao_em')->nullable();
                $table->timestamps();
                $table->unique(['empresa_id','connector_id']);
            });
        }

        if (!Schema::hasTable('marketplace_sync_logs')) {
            Schema::create('marketplace_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('marketplace_accounts')->nullOnDelete();
                $table->string('tipo', 50)->index();
                $table->string('status', 30)->index();
                $table->string('titulo', 200);
                $table->text('mensagem')->nullable();
                $table->json('dados')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('marketplace_queue')) {
            Schema::create('marketplace_queue', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('marketplace_accounts')->nullOnDelete();
                $table->string('tipo', 50)->index();
                $table->string('status', 30)->default('pendente')->index();
                $table->unsignedBigInteger('referencia_id')->nullable()->index();
                $table->json('payload')->nullable();
                $table->unsignedInteger('tentativas')->default(0);
                $table->timestamp('processar_em')->nullable();
                $table->timestamp('processado_em')->nullable();
                $table->text('erro')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('marketplace_products')) {
            Schema::create('marketplace_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('marketplace_accounts')->cascadeOnDelete();
                $table->string('external_id', 150)->nullable()->index();
                $table->string('status', 30)->default('pendente')->index();
                $table->decimal('preco', 15, 2)->nullable();
                $table->decimal('estoque', 15, 4)->nullable();
                $table->json('dados')->nullable();
                $table->timestamp('sincronizado_em')->nullable();
                $table->timestamps();
                $table->unique(['produto_id','account_id']);
            });
        }

        if (!Schema::hasTable('marketplace_orders')) {
            Schema::create('marketplace_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('marketplace_accounts')->cascadeOnDelete();
                $table->unsignedBigInteger('pedido_id')->nullable()->index();
                $table->string('external_id', 150)->index();
                $table->string('status', 50)->index();
                $table->decimal('total', 15, 2)->default(0);
                $table->json('dados')->nullable();
                $table->timestamp('importado_em')->nullable();
                $table->timestamps();
                $table->unique(['account_id','external_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_orders');
        Schema::dropIfExists('marketplace_products');
        Schema::dropIfExists('marketplace_queue');
        Schema::dropIfExists('marketplace_sync_logs');
        Schema::dropIfExists('marketplace_accounts');
        Schema::dropIfExists('marketplace_connectors');
    }
};
