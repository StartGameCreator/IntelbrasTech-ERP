<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('contador_configuracoes')) {
            Schema::create('contador_configuracoes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('nome_contador', 150)->nullable();
                $table->string('cpf_cnpj', 20)->nullable();
                $table->string('crc', 40)->nullable();
                $table->string('escritorio', 180)->nullable();
                $table->string('email', 180)->nullable();
                $table->string('telefone', 30)->nullable();
                $table->string('responsavel', 150)->nullable();
                $table->boolean('possui_escritorio')->default(false);
                $table->string('frequencia', 30)->default('mensal');
                $table->unsignedTinyInteger('dia_envio')->nullable();
                $table->string('hora_envio', 5)->default('08:00');
                $table->boolean('envio_automatico')->default(false);
                $table->json('tipos_arquivo')->nullable();
                $table->json('destinos')->nullable();
                $table->timestamps();
                $table->unique('empresa_id');
            });
        }

        if (!Schema::hasTable('contador_exportacoes')) {
            Schema::create('contador_exportacoes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('tipo', 80)->index();
                $table->string('formato', 20)->default('csv');
                $table->string('periodo_inicio', 10)->nullable();
                $table->string('periodo_fim', 10)->nullable();
                $table->string('status', 30)->default('processado')->index();
                $table->string('nome_arquivo', 255)->nullable();
                $table->text('caminho_arquivo')->nullable();
                $table->unsignedBigInteger('tamanho_bytes')->nullable();
                $table->text('erro')->nullable();
                $table->foreignId('gerado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('gerado_em')->nullable();
                $table->timestamp('enviado_em')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contador_envios')) {
            Schema::create('contador_envios', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('destinatario', 180);
                $table->string('assunto', 255);
                $table->json('exportacoes')->nullable();
                $table->string('status', 30)->default('pendente')->index();
                $table->text('erro')->nullable();
                $table->timestamp('agendado_para')->nullable();
                $table->timestamp('enviado_em')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contador_envios');
        Schema::dropIfExists('contador_exportacoes');
        Schema::dropIfExists('contador_configuracoes');
    }
};
