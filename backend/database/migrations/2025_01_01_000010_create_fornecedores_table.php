<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('fornecedores', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->char('tipo', 1)->default('J'); // F = física, J = jurídica
            $table->string('razao_social', 200);
            $table->string('nome_fantasia', 200)->nullable();
            $table->string('cpf_cnpj', 18)->nullable();
            $table->string('ie', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('site', 200)->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('logradouro', 200)->nullable();
            $table->string('numero', 10)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->char('estado', 2)->nullable();
            $table->string('ibge', 7)->nullable();
            $table->integer('prazo_entrega_dias')->default(0);
            $table->integer('prazo_pagamento_dias')->default(0);
            $table->decimal('desconto_padrao', 5, 2)->default(0);
            $table->smallInteger('avaliacao')->default(0); // 0-5
            $table->string('banco', 100)->nullable();
            $table->string('agencia', 10)->nullable();
            $table->string('conta', 20)->nullable();
            $table->string('pix', 100)->nullable();
            $table->string('contato_principal', 150)->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'ativo']);
            $table->index(['empresa_id', 'razao_social']);
        });
    }
    public function down(): void { Schema::dropIfExists('fornecedores'); }
};
