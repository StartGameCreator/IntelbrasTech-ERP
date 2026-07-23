<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devolucoes_fiscais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('pdv_venda_id');
            $table->unsignedBigInteger('nota_fiscal_origem_id');
            $table->unsignedBigInteger('nota_fiscal_devolucao_id')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->string('numero', 30)->unique();
            $table->string('tipo', 20)->default('total');
            $table->string('motivo', 60);
            $table->text('descricao_motivo')->nullable();
            $table->string('status', 40)->default('aguardando_retorno');
            $table->string('situacao_mercadoria', 20)->default('nao_retornada');
            $table->string('forma_reembolso', 30)->nullable();
            $table->string('status_reembolso', 30)->default('pendente');
            $table->decimal('valor_produtos', 15, 2)->default(0);
            $table->decimal('valor_reembolso', 15, 2)->default(0);
            $table->timestamp('solicitada_em');
            $table->timestamp('recebida_em')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'status']);
            $table->index('pdv_venda_id');
            $table->index('nota_fiscal_origem_id');
        });

        Schema::create('devolucao_fiscal_itens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('devolucao_fiscal_id');
            $table->unsignedBigInteger('pedido_item_id')->nullable();
            $table->unsignedBigInteger('produto_id');
            $table->unsignedBigInteger('variacao_id')->nullable();
            $table->unsignedBigInteger('deposito_id')->nullable();
            $table->string('codigo', 60)->nullable();
            $table->string('descricao', 255);
            $table->string('ncm', 10)->nullable();
            $table->string('unidade', 10)->default('UN');
            $table->decimal('quantidade_vendida', 15, 4);
            $table->decimal('quantidade_devolvida', 15, 4);
            $table->decimal('valor_unitario', 15, 4);
            $table->decimal('valor_total', 15, 2);
            $table->string('condicao', 30)->default('revendavel');
            $table->boolean('retornou_estoque')->default(false);
            $table->timestamps();

            $table->index('devolucao_fiscal_id');
            $table->index('produto_id');
        });

        Schema::table('notas_fiscais', function (Blueprint $table) {
            if (! Schema::hasColumn('notas_fiscais', 'nota_fiscal_referenciada_id')) {
                $table->unsignedBigInteger('nota_fiscal_referenciada_id')->nullable()->after('pedido_id');
            }
            if (! Schema::hasColumn('notas_fiscais', 'chave_referenciada')) {
                $table->string('chave_referenciada', 44)->nullable()->after('chave_acesso');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devolucao_fiscal_itens');
        Schema::dropIfExists('devolucoes_fiscais');

        Schema::table('notas_fiscais', function (Blueprint $table) {
            foreach (['nota_fiscal_referenciada_id', 'chave_referenciada'] as $column) {
                if (Schema::hasColumn('notas_fiscais', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
