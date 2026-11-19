<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('logs_sistema', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('acao', 100);
            $table->string('modulo', 50)->nullable();
            $table->string('tabela', 100)->nullable();
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->json('dados_antes')->nullable();
            $table->json('dados_depois')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['empresa_id', 'tabela', 'registro_id']);
        });

        Schema::create('os_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->string('cor', 7)->nullable();
            $table->integer('ordem')->default(0);
            $table->string('tipo', 20)->default('andamento'); // aberto, andamento, aguardando, concluido, cancelado
        });

        Schema::create('ordens_servico', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('numero', 20);
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('tecnico_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('os_status')->nullOnDelete();

            $table->string('equipamento_tipo', 100)->nullable();
            $table->string('equipamento_marca', 100)->default('Intelbras');
            $table->string('equipamento_modelo', 200)->nullable();
            $table->string('equipamento_serie', 100)->nullable();
            $table->string('equipamento_imei', 50)->nullable();

            $table->text('defeito_relatado')->nullable();
            $table->text('defeito_constatado')->nullable();
            $table->text('laudo_tecnico')->nullable();
            $table->text('solucao')->nullable();

            $table->boolean('garantia')->default(false);
            $table->string('numero_nf_compra', 50)->nullable();
            $table->date('data_compra')->nullable();
            $table->boolean('em_garantia')->default(false);

            $table->decimal('valor_servico', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->boolean('orcamento_aprovado')->nullable();
            $table->timestamp('data_aprovacao_orcamento')->nullable();

            $table->timestamp('data_entrada')->useCurrent();
            $table->date('data_previsao')->nullable();
            $table->timestamp('data_conclusao')->nullable();
            $table->timestamp('data_entrega')->nullable();
            $table->integer('prazo_garantia_dias')->default(90);
            $table->date('data_garantia_vence')->nullable();

            $table->smallInteger('nota_cliente')->nullable();
            $table->text('comentario_cliente')->nullable();

            $table->text('observacoes')->nullable();
            $table->text('observacoes_internas')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'status_id']);
            $table->index(['empresa_id', 'numero']);
        });

        Schema::create('os_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('os_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('descricao', 200)->nullable();
            $table->string('tipo', 20)->default('entrada'); // entrada, diagnostico, conclusao
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('os_historico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('os_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('status_anterior', 100)->nullable();
            $table->string('status_novo', 100)->nullable();
            $table->text('observacao')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('os_pecas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('os_id')->constrained('ordens_servico')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $table->string('descricao', 300)->nullable();
            $table->decimal('quantidade', 10, 3)->default(1);
            $table->decimal('preco_unitario', 15, 4)->nullable();
            $table->decimal('total', 15, 2)->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('os_pecas');
        Schema::dropIfExists('os_historico');
        Schema::dropIfExists('os_fotos');
        Schema::dropIfExists('ordens_servico');
        Schema::dropIfExists('os_status');
        Schema::dropIfExists('logs_sistema');
    }
};
