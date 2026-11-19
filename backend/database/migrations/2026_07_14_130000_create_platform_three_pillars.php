<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('platform_categorias_tecnicas')) {
            Schema::create('platform_categorias_tecnicas', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 140)->unique();
                $table->string('slug', 160)->unique();
                $table->text('descricao')->nullable();
                $table->string('icone', 80)->nullable();
                $table->boolean('ativo')->default(true)->index();
                $table->unsignedInteger('ordem')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('platform_cadastros')) {
            Schema::create('platform_cadastros', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('tipo_perfil', 30)->index(); // tecnico, cliente, colaborador
                $table->string('status', 30)->default('pendente')->index();
                $table->string('tipo_pessoa', 2)->default('PF');
                $table->string('nome', 180);
                $table->string('email', 180)->index();
                $table->string('telefone', 30)->nullable();
                $table->string('whatsapp', 30)->nullable();
                $table->string('cpf', 14)->nullable()->index();
                $table->string('rg', 30)->nullable();
                $table->string('cnpj', 18)->nullable()->index();
                $table->string('empresa', 180)->nullable();
                $table->string('cargo', 120)->nullable();
                $table->string('nivel_colaborador', 30)->nullable();
                $table->string('cep', 10)->nullable();
                $table->string('logradouro', 255)->nullable();
                $table->string('numero', 30)->nullable();
                $table->string('complemento', 255)->nullable();
                $table->string('bairro', 150)->nullable();
                $table->string('cidade', 150)->nullable()->index();
                $table->string('estado', 2)->nullable()->index();
                $table->string('crea', 50)->nullable();
                $table->string('crt', 50)->nullable();
                $table->string('cft', 50)->nullable();
                $table->unsignedSmallInteger('anos_experiencia')->nullable();
                $table->unsignedSmallInteger('raio_atendimento_km')->default(50);
                $table->text('especialidades')->nullable();
                $table->text('experiencia')->nullable();
                $table->text('motivacao')->nullable();
                $table->json('categorias')->nullable();
                $table->json('certificados')->nullable();
                $table->json('portfolio')->nullable();
                $table->json('metadados')->nullable();
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
                $table->foreignId('analisado_por')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->timestamp('analisado_em')->nullable();
                $table->text('observacao_analise')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tipo_perfil', 'email']);
            });
        }

        if (!Schema::hasTable('platform_perfis_usuario')) {
            Schema::create('platform_perfis_usuario', function (Blueprint $table) {
                $table->id();
                $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
                $table->string('tipo_perfil', 30)->index();
                $table->string('status', 30)->default('ativo')->index();
                $table->foreignId('cadastro_id')->nullable()->constrained('platform_cadastros')->nullOnDelete();
                $table->json('configuracoes')->nullable();
                $table->timestamps();
                $table->unique(['usuario_id', 'tipo_perfil']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_perfis_usuario');
        Schema::dropIfExists('platform_cadastros');
        Schema::dropIfExists('platform_categorias_tecnicas');
    }
};
