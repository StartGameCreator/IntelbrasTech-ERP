<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('transportadoras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 150);
            $table->string('cnpj', 18)->nullable();
            $table->string('ie', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('api_tipo', 30)->nullable(); // correios, jadlog, loggi, azul, total_express, melhor_envio
            $table->string('api_usuario', 200)->nullable();
            $table->string('api_senha', 200)->nullable();
            $table->text('api_token')->nullable();
            $table->string('api_contrato', 50)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        // ── Vendas ───────────────────────────────────────────────
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('numero', 20);
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('canal', 30)->default('interno'); // interno, marketplace, ecommerce, whatsapp
            $table->string('marketplace', 30)->nullable();
            $table->string('marketplace_pedido_id', 100)->nullable();
            $table->foreignId('tabela_preco_id')->nullable()->constrained('tabelas_preco')->nullOnDelete();

            $table->string('entrega_cep', 9)->nullable();
            $table->string('entrega_logradouro', 200)->nullable();
            $table->string('entrega_numero', 10)->nullable();
            $table->string('entrega_complemento', 100)->nullable();
            $table->string('entrega_bairro', 100)->nullable();
            $table->string('entrega_cidade', 100)->nullable();
            $table->char('entrega_estado', 2)->nullable();
            $table->string('entrega_ibge', 7)->nullable();

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('desconto_percentual', 5, 2)->default(0);
            $table->decimal('desconto_valor', 15, 2)->default(0);
            $table->decimal('acrescimo_percentual', 5, 2)->default(0);
            $table->decimal('acrescimo_valor', 15, 2)->default(0);
            $table->decimal('frete', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->string('status', 30)->default('orcamento'); // orcamento, confirmado, faturado, entregue, cancelado
            $table->string('status_pagamento', 30)->default('pendente');
            $table->string('status_entrega', 30)->default('pendente');

            $table->timestamp('data_pedido')->useCurrent();
            $table->timestamp('data_confirmacao')->nullable();
            $table->timestamp('data_faturamento')->nullable();
            $table->timestamp('data_entrega')->nullable();
            $table->timestamp('data_cancelamento')->nullable();
            $table->text('motivo_cancelamento')->nullable();

            // Sem FK forte: tabela de transportadoras/condições de pagamento podem
            // não existir ainda no momento do pedido (mesmo comportamento do schema original).
            $table->unsignedBigInteger('transportadora_id')->nullable()->index();
            $table->char('tipo_frete', 1)->default('C'); // C=CIF, F=FOB, T=Terceiros
            $table->integer('prazo_entrega_dias')->nullable();
            $table->string('codigo_rastreio', 50)->nullable();
            $table->unsignedBigInteger('condicao_pagamento_id')->nullable();

            $table->text('observacoes')->nullable();
            $table->text('observacoes_nfe')->nullable();
            $table->unsignedBigInteger('origem_id')->nullable(); // pedido de origem (devolução)
            $table->timestamps();

            $table->index(['empresa_id', 'status']);
            $table->index(['empresa_id', 'numero']);
        });

        Schema::create('pedido_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->foreignId('variacao_id')->nullable()->constrained('produto_variacoes')->nullOnDelete();
            $table->foreignId('deposito_id')->nullable()->constrained('depositos')->nullOnDelete();
            $table->string('codigo', 50)->nullable();
            $table->string('descricao', 300)->nullable();
            $table->string('ncm', 10)->nullable();
            $table->string('cfop', 10)->nullable();
            $table->string('unidade', 10)->nullable();
            $table->decimal('quantidade', 10, 3);
            $table->decimal('preco_unitario', 15, 4);
            $table->decimal('desconto_percentual', 5, 2)->default(0);
            $table->decimal('desconto_valor', 15, 2)->default(0);
            $table->decimal('acrescimo_valor', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->string('icms_cst', 5)->nullable();
            $table->decimal('icms_base', 15, 2)->nullable();
            $table->decimal('icms_aliquota', 5, 2)->nullable();
            $table->decimal('icms_valor', 15, 2)->nullable();
            $table->string('pis_cst', 3)->nullable();
            $table->decimal('pis_base', 15, 2)->nullable();
            $table->decimal('pis_aliquota', 5, 2)->nullable();
            $table->decimal('pis_valor', 15, 2)->nullable();
            $table->string('cofins_cst', 3)->nullable();
            $table->decimal('cofins_base', 15, 2)->nullable();
            $table->decimal('cofins_aliquota', 5, 2)->nullable();
            $table->decimal('cofins_valor', 15, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('pedido_envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('nota_fiscal_id')->nullable()->constrained('notas_fiscais')->nullOnDelete();
            $table->foreignId('transportadora_id')->nullable()->constrained('transportadoras')->nullOnDelete();
            $table->string('servico', 100)->nullable();
            $table->string('codigo_rastreio', 100)->nullable();
            $table->string('etiqueta_url', 500)->nullable();
            $table->text('etiqueta_base64')->nullable();
            $table->string('status', 30)->default('pendente');
            $table->decimal('peso', 10, 3)->nullable();
            $table->decimal('altura', 10, 2)->nullable();
            $table->decimal('largura', 10, 2)->nullable();
            $table->decimal('comprimento', 10, 2)->nullable();
            $table->decimal('valor_frete', 15, 2)->nullable();
            $table->integer('prazo_dias')->nullable();
            $table->date('data_postagem')->nullable();
            $table->date('data_previsao')->nullable();
            $table->date('data_entrega')->nullable();
            $table->timestamps();
        });

        // ── Compras ──────────────────────────────────────────────
        Schema::create('pedidos_compra', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('numero', 20);
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
            $table->foreignId('deposito_id')->nullable()->constrained('depositos')->nullOnDelete();
            $table->string('status', 30)->default('rascunho'); // rascunho, enviado, parcial, recebido, cancelado
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('desconto_valor', 15, 2)->default(0);
            $table->decimal('frete', 15, 2)->default(0);
            $table->decimal('outras_despesas', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->date('data_emissao')->useCurrent();
            $table->date('data_previsao')->nullable();
            $table->date('data_recebimento')->nullable();
            $table->unsignedBigInteger('condicao_pagamento_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });

        // ── Integrações / Marketplace ────────────────────────────
        Schema::create('integracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo', 50); // mercadolivre, shopee, amazon, nuvemshop, woocommerce, etc
            $table->string('nome', 100)->nullable();
            $table->json('credenciais')->default('{}');
            $table->json('config')->default('{}');
            $table->string('status', 20)->default('inativo');
            $table->timestamp('ultimo_sync')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('marketplace_produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integracao_id')->constrained('integracoes')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $table->foreignId('variacao_id')->nullable()->constrained('produto_variacoes')->nullOnDelete();
            $table->string('marketplace_sku', 200)->nullable();
            $table->string('marketplace_id', 200)->nullable();
            $table->string('marketplace_url', 500)->nullable();
            $table->string('titulo', 300)->nullable();
            $table->decimal('preco_anunciado', 15, 2)->nullable();
            $table->integer('estoque_sincronizado')->default(0);
            $table->string('status_anuncio', 30)->nullable();
            $table->timestamp('ultimo_sync')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('marketplace_pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integracao_id')->constrained('integracoes');
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->string('marketplace_pedido_id', 200);
            $table->string('marketplace', 50)->nullable();
            $table->string('status_marketplace', 50)->nullable();
            $table->json('dados_raw')->nullable();
            $table->boolean('sincronizado')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        // ── CRM (estrutura mínima do funil, usada por Lead) ───────
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->boolean('ativo')->default(true);
        });

        Schema::create('pipeline_etapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->integer('ordem')->default(0);
            $table->string('cor', 7)->nullable();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('pipeline_id')->nullable()->constrained('pipelines')->nullOnDelete();
            $table->foreignId('etapa_id')->nullable()->constrained('pipeline_etapas')->nullOnDelete();
            $table->foreignId('responsavel_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('nome', 200);
            $table->string('email', 150)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('empresa_nome', 200)->nullable();
            $table->string('origem', 50)->nullable();
            $table->string('titulo', 200)->nullable();
            $table->decimal('valor_estimado', 15, 2)->nullable();
            $table->smallInteger('probabilidade')->default(0);
            $table->date('data_previsao_fechamento')->nullable();
            $table->string('status', 20)->default('aberto'); // aberto, ganho, perdido
            $table->text('motivo_perda')->nullable();
            $table->text('tags')->nullable(); // lista separada por vírgula (evita depender de array do Postgres no Eloquent)
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('pipeline_etapas');
        Schema::dropIfExists('pipelines');
        Schema::dropIfExists('marketplace_pedidos');
        Schema::dropIfExists('marketplace_produtos');
        Schema::dropIfExists('integracoes');
        Schema::dropIfExists('pedidos_compra');
        Schema::dropIfExists('pedido_envios');
        Schema::dropIfExists('pedido_itens');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('transportadoras');
    }
};
