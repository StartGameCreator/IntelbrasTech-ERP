<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->char('tipo', 1)->default('F');
            $table->string('razao_social', 200);
            $table->string('nome_fantasia', 200)->nullable();
            $table->string('cpf_cnpj', 18)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('email_nfe', 150)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('logradouro', 200)->nullable();
            $table->string('numero', 10)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->char('estado', 2)->nullable();
            $table->string('ibge', 7)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('limite_credito', 15, 2)->default(0);
            $table->decimal('desconto_padrao', 5, 2)->default(0);
            $table->foreignId('vendedor_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('bloqueado')->default(false);
            $table->text('motivo_bloqueio')->nullable();
            $table->string('origem', 50)->nullable();
            $table->string('canal_aquisicao', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['empresa_id','cpf_cnpj']);
            $table->index(['empresa_id','email']);
        });
    }
    public function down(): void { Schema::dropIfExists('clientes'); }
};
