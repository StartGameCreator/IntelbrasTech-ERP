<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('portal_chamados', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('token_publico', 96)->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('ordem_servico_id')->nullable()->constrained('ordens_servico')->nullOnDelete();
            $table->string('protocolo', 32)->unique();
            $table->string('status', 40)->default('recebido');
            $table->string('tipo_demanda', 100);
            $table->string('prioridade', 20)->default('normal');
            $table->string('nome', 200);
            $table->string('email', 150)->nullable();
            $table->string('telefone', 30);
            $table->string('whatsapp', 30)->nullable();
            $table->text('descricao');
            $table->json('dados')->nullable();
            $table->string('orcamento_titulo', 200)->nullable();
            $table->text('orcamento_descricao')->nullable();
            $table->decimal('orcamento_valor', 15, 2)->nullable();
            $table->date('orcamento_validade')->nullable();
            $table->timestamp('orcamento_enviado_em')->nullable();
            $table->timestamp('autorizado_em')->nullable();
            $table->string('autorizacao_ip_hash', 64)->nullable();
            $table->string('autorizacao_user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'status', 'created_at']);
            $table->index(['empresa_id', 'cliente_id']);
        });

        Schema::create('contratos_servico', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('portal_chamado_id')->unique()->constrained('portal_chamados')->cascadeOnDelete();
            $table->string('numero', 40)->nullable()->unique();
            $table->string('status', 30)->default('aceito');
            $table->string('titulo', 200);
            $table->text('objeto');
            $table->decimal('valor', 15, 2)->nullable();
            $table->date('validade_orcamento')->nullable();
            $table->longText('conteudo_html')->nullable();
            $table->string('arquivo_disk', 40)->default('local');
            $table->string('arquivo_path', 500)->nullable();
            $table->string('aceite_nome', 200)->nullable();
            $table->timestamp('aceito_em')->nullable();
            $table->string('aceite_ip_hash', 64)->nullable();
            $table->string('aceite_user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_servico');
        Schema::dropIfExists('portal_chamados');
    }
};
