<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $campos = [
                'bu' => fn () => $table->string('bu', 100)->nullable(),
                'segmento' => fn () => $table->string('segmento', 150)->nullable(),
                'familia' => fn () => $table->string('familia', 150)->nullable(),
                'multiplo_venda' => fn () => $table->decimal('multiplo_venda', 12, 3)->default(1),
                'preco_referencia_tipo' => fn () => $table->string('preco_referencia_tipo', 30)->nullable(),
                'disponivel_bndes' => fn () => $table->boolean('disponivel_bndes')->default(false),
                'segmento_portfolio' => fn () => $table->string('segmento_portfolio', 100)->nullable(),
                'showroom' => fn () => $table->boolean('showroom')->default(false),
                'item_consumo' => fn () => $table->boolean('item_consumo')->default(false),
                'casa_inteligente' => fn () => $table->boolean('casa_inteligente')->default(false),
                'observacoes_importacao' => fn () => $table->text('observacoes_importacao')->nullable(),
                'fonte_importacao' => fn () => $table->string('fonte_importacao', 100)->nullable(),
                'importado_em' => fn () => $table->timestamp('importado_em')->nullable(),
            ];

            foreach ($campos as $nome => $definicao) {
                if (!Schema::hasColumn('produtos', $nome)) {
                    $definicao();
                }
            }
        });

        if (!Schema::hasTable('produto_precos_uf')) {
            Schema::create('produto_precos_uf', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
                $table->string('tabela', 80)->default('Distribuição');
                $table->string('uf_origem', 10)->nullable();
                $table->string('uf_destino', 2);
                $table->decimal('ipi', 8, 4)->default(0);
                $table->decimal('icms', 8, 4)->default(0);
                $table->decimal('pv', 15, 4)->nullable();
                $table->decimal('custo', 15, 4)->nullable();
                $table->decimal('pp', 15, 4)->nullable();
                $table->decimal('psd', 15, 4)->nullable();
                $table->decimal('pscf', 15, 4)->nullable();
                $table->decimal('multiplo', 12, 3)->default(1);
                $table->string('preco_referencia', 30)->nullable();
                $table->boolean('ativo')->default(true);
                $table->timestamp('importado_em')->nullable();
                $table->timestamps();

                $table->unique(
                    ['empresa_id', 'produto_id', 'tabela', 'uf_origem', 'uf_destino'],
                    'produto_precos_uf_unico'
                );
                $table->index(['empresa_id', 'uf_destino']);
            });
        }

        if (!Schema::hasTable('importacoes_produtos')) {
            Schema::create('importacoes_produtos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->uuid('uuid')->unique();
                $table->string('arquivo', 255);
                $table->string('status', 30)->default('processando');
                $table->string('uf_preferencial', 2)->default('GO');
                $table->unsignedInteger('produtos_recebidos')->default(0);
                $table->unsignedInteger('produtos_criados')->default(0);
                $table->unsignedInteger('produtos_atualizados')->default(0);
                $table->unsignedInteger('precos_recebidos')->default(0);
                $table->unsignedInteger('precos_gravados')->default(0);
                $table->unsignedInteger('erros')->default(0);
                $table->json('detalhes')->nullable();
                $table->timestamp('concluida_em')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes_produtos');
        Schema::dropIfExists('produto_precos_uf');

        Schema::table('produtos', function (Blueprint $table) {
            foreach ([
                'bu', 'segmento', 'familia', 'multiplo_venda',
                'preco_referencia_tipo', 'disponivel_bndes',
                'segmento_portfolio', 'showroom', 'item_consumo',
                'casa_inteligente', 'observacoes_importacao',
                'fonte_importacao', 'importado_em',
            ] as $campo) {
                if (Schema::hasColumn('produtos', $campo)) {
                    $table->dropColumn($campo);
                }
            }
        });
    }
};
