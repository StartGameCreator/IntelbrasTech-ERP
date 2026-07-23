<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $campos = [
                'formato' => fn () => $table->string('formato', 30)->default('simples'),
                'condicao' => fn () => $table->string('condicao', 30)->default('novo'),
                'producao' => fn () => $table->string('producao', 30)->default('terceiros'),
                'data_validade' => fn () => $table->date('data_validade')->nullable(),
                'frete_gratis' => fn () => $table->boolean('frete_gratis')->default(false),
                'peso_bruto' => fn () => $table->decimal('peso_bruto', 10, 3)->default(0),
                'volumes' => fn () => $table->unsignedInteger('volumes')->default(1),
                'itens_caixa' => fn () => $table->decimal('itens_caixa', 10, 3)->default(1),
                'unidade_dimensao' => fn () => $table->string('unidade_dimensao', 20)->default('cm'),
                'ean_tributario' => fn () => $table->string('ean_tributario', 20)->nullable(),
                'descricao_complementar' => fn () => $table->longText('descricao_complementar')->nullable(),
                'link_externo' => fn () => $table->string('link_externo', 1000)->nullable(),
                'video_url' => fn () => $table->string('video_url', 1000)->nullable(),
                'observacoes' => fn () => $table->longText('observacoes')->nullable(),
                'tags' => fn () => $table->json('tags')->nullable(),
                'tipo_item' => fn () => $table->string('tipo_item', 50)->nullable(),
                'grupo_produtos' => fn () => $table->string('grupo_produtos', 100)->nullable(),
                'percentual_tributos' => fn () => $table->decimal('percentual_tributos', 8, 4)->default(0),
                'icms_st_base' => fn () => $table->decimal('icms_st_base', 15, 4)->default(0),
                'icms_st_valor' => fn () => $table->decimal('icms_st_valor', 15, 4)->default(0),
                'icms_st_substituto' => fn () => $table->decimal('icms_st_substituto', 15, 4)->default(0),
                'ipi_excecao' => fn () => $table->string('ipi_excecao', 30)->nullable(),
                'pis_valor_fixo' => fn () => $table->decimal('pis_valor_fixo', 15, 4)->default(0),
                'cofins_valor_fixo' => fn () => $table->decimal('cofins_valor_fixo', 15, 4)->default(0),
                'informacoes_adicionais' => fn () => $table->longText('informacoes_adicionais')->nullable(),
                'conteudo_ia_status' => fn () => $table->string('conteudo_ia_status', 30)->default('nao_gerado'),
                'conteudo_ia_em' => fn () => $table->timestamp('conteudo_ia_em')->nullable(),
            ];

            foreach ($campos as $nome => $definicao) {
                if (!Schema::hasColumn('produtos', $nome)) {
                    $definicao();
                }
            }
        });

        Schema::table('produto_imagens', function (Blueprint $table) {
            $campos = [
                'origem' => fn () => $table->string('origem', 20)->default('url'),
                'arquivo' => fn () => $table->string('arquivo', 500)->nullable(),
                'alt_text' => fn () => $table->string('alt_text', 250)->nullable(),
                'mime_type' => fn () => $table->string('mime_type', 100)->nullable(),
                'extensao' => fn () => $table->string('extensao', 20)->nullable(),
                'largura' => fn () => $table->unsignedInteger('largura')->nullable(),
                'altura' => fn () => $table->unsignedInteger('altura')->nullable(),
                'tamanho_bytes' => fn () => $table->unsignedBigInteger('tamanho_bytes')->nullable(),
                'status' => fn () => $table->string('status', 30)->default('ativa'),
            ];

            foreach ($campos as $nome => $definicao) {
                if (!Schema::hasColumn('produto_imagens', $nome)) {
                    $definicao();
                }
            }
        });

        if (!Schema::hasTable('produto_fornecedores')) {
            Schema::create('produto_fornecedores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
                $table->foreignId('fornecedor_id')->constrained('fornecedores')->cascadeOnDelete();
                $table->string('codigo_fornecedor', 100)->nullable();
                $table->decimal('custo', 15, 4)->default(0);
                $table->decimal('quantidade_minima', 12, 3)->default(1);
                $table->unsignedInteger('prazo_dias')->default(0);
                $table->boolean('principal')->default(false);
                $table->timestamp('ultima_compra_em')->nullable();
                $table->timestamps();
                $table->unique(['produto_id', 'fornecedor_id']);
            });
        }

        if (!Schema::hasTable('produto_canais')) {
            Schema::create('produto_canais', function (Blueprint $table) {
                $table->id();
                $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
                $table->string('canal', 50);
                $table->string('id_externo', 150)->nullable();
                $table->string('sku_externo', 150)->nullable();
                $table->string('status', 30)->default('nao_publicado');
                $table->decimal('preco', 15, 4)->nullable();
                $table->decimal('estoque', 12, 3)->nullable();
                $table->text('titulo')->nullable();
                $table->longText('descricao')->nullable();
                $table->string('categoria_externa', 150)->nullable();
                $table->text('ultimo_erro')->nullable();
                $table->timestamp('sincronizado_em')->nullable();
                $table->timestamps();
                $table->unique(['produto_id', 'canal']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_canais');
        Schema::dropIfExists('produto_fornecedores');
    }
};
