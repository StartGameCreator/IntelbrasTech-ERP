<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('logistica_transportadoras')) {
            Schema::create('logistica_transportadoras', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 100)->unique();
                $table->string('nome', 150);
                $table->string('tipo', 50)->index();
                $table->boolean('ativo')->default(true);
                $table->unsignedInteger('ordem')->default(0);
                $table->json('recursos')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('logistica_contas')) {
            Schema::create('logistica_contas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('transportadora_id')->constrained('logistica_transportadoras')->cascadeOnDelete();
                $table->string('nome_conta', 150)->nullable();
                $table->string('status', 30)->default('desconectado')->index();
                $table->text('token')->nullable();
                $table->text('secret')->nullable();
                $table->json('configuracoes')->nullable();
                $table->text('ultimo_erro')->nullable();
                $table->timestamp('ultima_sincronizacao_em')->nullable();
                $table->timestamps();
                $table->unique(['empresa_id','transportadora_id']);
            });
        }

        if (!Schema::hasTable('logistica_cotacoes')) {
            Schema::create('logistica_cotacoes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->unsignedBigInteger('pedido_id')->nullable()->index();
                $table->unsignedBigInteger('expedicao_id')->nullable()->index();
                $table->string('cep_origem', 8);
                $table->string('cep_destino', 8);
                $table->decimal('peso', 10, 3)->default(0);
                $table->decimal('altura', 10, 2)->default(0);
                $table->decimal('largura', 10, 2)->default(0);
                $table->decimal('comprimento', 10, 2)->default(0);
                $table->decimal('valor_declarado', 15, 2)->default(0);
                $table->string('status', 30)->default('processada')->index();
                $table->json('resultados')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('logistica_envios')) {
            Schema::create('logistica_envios', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->unsignedBigInteger('pedido_id')->nullable()->index();
                $table->unsignedBigInteger('expedicao_id')->nullable()->index();
                $table->foreignId('transportadora_id')->constrained('logistica_transportadoras');
                $table->foreignId('conta_id')->nullable()->constrained('logistica_contas')->nullOnDelete();
                $table->string('servico', 120)->nullable();
                $table->string('codigo_rastreio', 120)->nullable()->index();
                $table->string('status', 40)->default('aguardando_postagem')->index();
                $table->decimal('valor_frete', 15, 2)->default(0);
                $table->unsignedInteger('prazo_dias')->nullable();
                $table->text('etiqueta_url')->nullable();
                $table->json('dados')->nullable();
                $table->timestamp('postado_em')->nullable();
                $table->timestamp('entregue_em')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('logistica_rastreios')) {
            Schema::create('logistica_rastreios', function (Blueprint $table) {
                $table->id();
                $table->foreignId('envio_id')->constrained('logistica_envios')->cascadeOnDelete();
                $table->string('status', 80)->index();
                $table->string('descricao', 300);
                $table->string('local', 200)->nullable();
                $table->timestamp('evento_em')->nullable();
                $table->json('dados')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // Correção de segurança: não remove tabelas existentes.
    }
};
