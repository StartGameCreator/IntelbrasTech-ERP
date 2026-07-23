<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_categorias')) {
            Schema::create('marketplace_categorias', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 150)->unique();
                $table->string('slug', 170)->unique();
                $table->text('descricao')->nullable();
                $table->string('icone', 80)->nullable();
                $table->boolean('ativo')->default(true)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('marketplace_tecnicos')) {
            Schema::create('marketplace_tecnicos', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->unsignedBigInteger('empresa_id')->nullable()->index();

                $table->string('nome', 180);
                $table->string('email', 180)->unique();
                $table->string('cpf', 14)->nullable()->index();
                $table->string('rg', 30)->nullable();
                $table->string('crea', 40)->nullable();
                $table->string('crt', 40)->nullable();
                $table->string('cft', 40)->nullable();

                $table->string('empresa', 180)->nullable();
                $table->string('cnpj', 18)->nullable()->index();
                $table->string('telefone', 30)->nullable();
                $table->string('whatsapp', 30)->nullable();

                $table->string('cep', 10)->nullable()->index();
                $table->string('logradouro')->nullable();
                $table->string('numero', 30)->nullable();
                $table->string('complemento')->nullable();
                $table->string('bairro')->nullable();
                $table->string('cidade', 120)->nullable()->index();
                $table->string('estado', 2)->nullable()->index();

                $table->text('regiao_atendimento')->nullable();
                $table->unsignedSmallInteger('raio_km')->default(50);
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();

                $table->string('foto_perfil', 500)->nullable();
                $table->text('experiencia')->nullable();
                $table->text('bio')->nullable();
                $table->json('certificados')->nullable();
                $table->json('cursos')->nullable();
                $table->json('portfolio')->nullable();

                $table->string('status', 30)->default('pendente')->index();
                $table->boolean('online')->default(false)->index();
                $table->decimal('media_avaliacao', 3, 2)->default(0);
                $table->unsignedInteger('total_avaliacoes')->default(0);
                $table->unsignedInteger('total_atendimentos')->default(0);

                $table->timestamp('aprovado_em')->nullable();
                $table->unsignedBigInteger('aprovado_por')->nullable();
                $table->text('motivo_rejeicao')->nullable();

                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('marketplace_clientes')) {
            Schema::create('marketplace_clientes', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->unsignedBigInteger('empresa_id')->nullable()->index();

                $table->string('nome', 180);
                $table->string('email', 180)->unique();
                $table->string('tipo_pessoa', 2)->default('PF');
                $table->string('cpf', 14)->nullable()->index();
                $table->string('cnpj', 18)->nullable()->index();
                $table->string('empresa', 180)->nullable();

                $table->string('telefone', 30)->nullable();
                $table->string('whatsapp', 30)->nullable();

                $table->string('cep', 10)->nullable()->index();
                $table->string('logradouro')->nullable();
                $table->string('numero', 30)->nullable();
                $table->string('complemento')->nullable();
                $table->string('bairro')->nullable();
                $table->string('cidade', 120)->nullable()->index();
                $table->string('estado', 2)->nullable()->index();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();

                $table->string('status', 30)->default('pendente')->index();
                $table->decimal('media_avaliacao', 3, 2)->default(0);
                $table->unsignedInteger('total_avaliacoes')->default(0);

                $table->timestamp('aprovado_em')->nullable();
                $table->unsignedBigInteger('aprovado_por')->nullable();
                $table->text('motivo_rejeicao')->nullable();

                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('marketplace_tecnico_categorias')) {
            Schema::create('marketplace_tecnico_categorias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tecnico_id')
                    ->constrained('marketplace_tecnicos')
                    ->cascadeOnDelete();
                $table->foreignId('categoria_id')
                    ->constrained('marketplace_categorias')
                    ->cascadeOnDelete();
                $table->boolean('principal')->default(false);
                $table->unsignedSmallInteger('anos_experiencia')->nullable();
                $table->timestamps();
                $table->unique(['tecnico_id', 'categoria_id']);
            });
        }

        if (!Schema::hasTable('marketplace_auditoria')) {
            Schema::create('marketplace_auditoria', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->string('acao', 120)->index();
                $table->string('entidade', 120)->nullable()->index();
                $table->unsignedBigInteger('entidade_id')->nullable()->index();
                $table->json('dados')->nullable();
                $table->string('ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_auditoria');
        Schema::dropIfExists('marketplace_tecnico_categorias');
        Schema::dropIfExists('marketplace_clientes');
        Schema::dropIfExists('marketplace_tecnicos');
        Schema::dropIfExists('marketplace_categorias');
    }
};
