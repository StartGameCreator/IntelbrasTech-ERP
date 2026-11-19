<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('etiquetas_envio')) {
            Schema::create('etiquetas_envio', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('expedicao_id')->constrained('expedicoes')->cascadeOnDelete();
                $table->unsignedBigInteger('pedido_id')->index();
                $table->unsignedBigInteger('volume_id')->nullable()->index();
                $table->string('marketplace', 40)->nullable()->index();
                $table->string('transportadora', 80)->nullable();
                $table->string('servico', 100)->nullable();
                $table->string('external_shipment_id', 120)->nullable()->index();
                $table->string('codigo_rastreio', 120)->nullable()->index();
                $table->string('origem', 30)->default('upload');
                $table->string('formato', 15)->default('pdf');
                $table->string('tamanho_papel', 20)->default('100x150');
                $table->string('nome_original', 255)->nullable();
                $table->string('arquivo_path', 500);
                $table->string('arquivo_hash', 64)->index();
                $table->unsignedBigInteger('arquivo_tamanho')->default(0);
                $table->string('mime_type', 100)->nullable();
                $table->string('status', 30)->default('disponivel')->index();
                $table->unsignedInteger('quantidade_impressoes')->default(0);
                $table->timestamp('gerada_em')->nullable();
                $table->timestamp('ultima_impressao_em')->nullable();
                $table->unsignedBigInteger('criado_por')->nullable()->index();
                $table->unsignedBigInteger('ultimo_impresso_por')->nullable()->index();
                $table->text('ultimo_erro')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'status']);
                $table->index(['expedicao_id', 'volume_id']);
            });
        }

        if (! Schema::hasTable('impressoras_etiquetas')) {
            Schema::create('impressoras_etiquetas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('nome', 120);
                $table->string('nome_sistema', 255)->nullable();
                $table->string('tipo', 30)->default('termica');
                $table->string('formato_padrao', 15)->default('pdf');
                $table->string('papel_padrao', 20)->default('100x150');
                $table->unsignedSmallInteger('dpi')->default(203);
                $table->boolean('padrao')->default(false);
                $table->boolean('ativa')->default(true);
                $table->json('configuracoes')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'ativa']);
            });
        }

        if (! Schema::hasTable('historico_impressoes_etiquetas')) {
            Schema::create('historico_impressoes_etiquetas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('etiqueta_envio_id')->constrained('etiquetas_envio')->cascadeOnDelete();
                $table->unsignedBigInteger('empresa_id')->index();
                $table->unsignedBigInteger('impressora_id')->nullable()->index();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->unsignedInteger('copias')->default(1);
                $table->string('modo', 30)->default('navegador');
                $table->string('status', 30)->default('registrada');
                $table->text('erro')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_impressoes_etiquetas');
        Schema::dropIfExists('impressoras_etiquetas');
        Schema::dropIfExists('etiquetas_envio');
    }
};
