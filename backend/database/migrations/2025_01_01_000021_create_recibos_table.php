<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('numero', 20);

            // Snapshot do cliente no momento da emissão (opcional — MEI vendendo
            // para pessoa física não é obrigado a coletar CPF para este documento).
            $table->string('cliente_nome', 200)->nullable();
            $table->string('cliente_documento', 18)->nullable();
            $table->string('cliente_telefone', 20)->nullable();

            // Snapshot dos itens vendidos (não precisa de tabela relacional:
            // este documento não tem validade fiscal, é só comprovante/controle).
            $table->json('itens_snapshot')->default('[]');

            $table->decimal('valor_total', 15, 2)->default(0);
            $table->string('forma_pagamento', 100)->nullable();
            $table->text('observacoes')->nullable();

            // Controle de garantia do produto
            $table->integer('prazo_garantia_dias')->default(90);
            $table->date('data_garantia_vence')->nullable();

            // Dados do entregador / parceiro de transporte (Uber, 99, app próprio, etc.)
            $table->string('entregador_tipo', 30)->nullable(); // proprio, uber, app_parceiro, outro
            $table->string('entregador_app_nome', 100)->nullable(); // ex: "Uber", "99 Entregas", "Lalamove"
            $table->string('entregador_nome', 150)->nullable();
            $table->string('entregador_documento', 18)->nullable();
            $table->string('entregador_telefone', 20)->nullable();
            $table->string('veiculo_placa', 10)->nullable();
            $table->string('veiculo_modelo', 100)->nullable();

            $table->timestamp('data_emissao')->useCurrent();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'data_garantia_vence']);
        });
    }
    public function down(): void { Schema::dropIfExists('recibos'); }
};
