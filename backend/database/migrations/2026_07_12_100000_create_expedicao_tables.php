<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('expedicoes')) {
            Schema::create('expedicoes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
                $table->string('codigo', 50)->unique();
                $table->string('status', 40)->default('aguardando_separacao')->index();
                $table->unsignedBigInteger('separador_id')->nullable()->index();
                $table->unsignedBigInteger('conferente_id')->nullable()->index();
                $table->unsignedBigInteger('embalador_id')->nullable()->index();
                $table->unsignedInteger('total_itens')->default(0);
                $table->unsignedInteger('itens_separados')->default(0);
                $table->unsignedInteger('itens_conferidos')->default(0);
                $table->unsignedInteger('total_volumes')->default(0);
                $table->text('observacoes')->nullable();
                $table->timestamp('separacao_iniciada_em')->nullable();
                $table->timestamp('separacao_concluida_em')->nullable();
                $table->timestamp('conferencia_iniciada_em')->nullable();
                $table->timestamp('conferencia_concluida_em')->nullable();
                $table->timestamp('expedida_em')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id','pedido_id']);
                $table->index(['empresa_id','status']);
            });
        }

        if (!Schema::hasTable('expedicao_itens')) {
            Schema::create('expedicao_itens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expedicao_id')->constrained('expedicoes')->cascadeOnDelete();
                $table->unsignedBigInteger('pedido_item_id')->index();
                $table->unsignedBigInteger('produto_id')->nullable()->index();
                $table->string('codigo', 120)->nullable()->index();
                $table->string('descricao', 300);
                $table->decimal('quantidade', 15, 4);
                $table->decimal('quantidade_separada', 15, 4)->default(0);
                $table->decimal('quantidade_conferida', 15, 4)->default(0);
                $table->string('status', 30)->default('pendente')->index();
                $table->string('localizacao', 100)->nullable();
                $table->text('observacoes')->nullable();
                $table->timestamps();

                $table->unique(['expedicao_id','pedido_item_id']);
            });
        }

        if (!Schema::hasTable('expedicao_volumes')) {
            Schema::create('expedicao_volumes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expedicao_id')->constrained('expedicoes')->cascadeOnDelete();
                $table->unsignedInteger('numero');
                $table->string('codigo_barras', 100)->unique();
                $table->string('qr_code', 255)->nullable();
                $table->decimal('peso', 10, 3)->nullable();
                $table->decimal('altura', 10, 2)->nullable();
                $table->decimal('largura', 10, 2)->nullable();
                $table->decimal('comprimento', 10, 2)->nullable();
                $table->string('status', 30)->default('aberto')->index();
                $table->text('observacoes')->nullable();
                $table->timestamps();

                $table->unique(['expedicao_id','numero']);
            });
        }

        if (!Schema::hasTable('expedicao_eventos')) {
            Schema::create('expedicao_eventos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('expedicao_id')->constrained('expedicoes')->cascadeOnDelete();
                $table->unsignedBigInteger('usuario_id')->nullable()->index();
                $table->string('tipo', 60)->index();
                $table->string('descricao', 255);
                $table->json('dados')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expedicao_eventos');
        Schema::dropIfExists('expedicao_volumes');
        Schema::dropIfExists('expedicao_itens');
        Schema::dropIfExists('expedicoes');
    }
};
