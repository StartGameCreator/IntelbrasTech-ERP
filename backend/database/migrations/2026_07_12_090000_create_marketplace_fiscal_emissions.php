<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_fiscal_emissions')) {
            Schema::create('marketplace_fiscal_emissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('marketplace_order_id')->constrained('marketplace_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('pedido_id')->nullable()->index();
                $table->unsignedBigInteger('fiscal_queue_item_id')->nullable()->index();
                $table->string('documento_tipo', 20)->default('nfe')->index();
                $table->string('ambiente', 20)->default('homologacao')->index();
                $table->unsignedInteger('serie')->default(1);
                $table->unsignedBigInteger('numero')->nullable();
                $table->string('status', 40)->default('rascunho')->index();
                $table->string('chave_acesso', 44)->nullable()->index();
                $table->string('protocolo', 100)->nullable();
                $table->string('codigo_status', 20)->nullable();
                $table->text('mensagem_status')->nullable();
                $table->longText('xml_rascunho')->nullable();
                $table->longText('xml_assinado')->nullable();
                $table->longText('xml_autorizado')->nullable();
                $table->text('danfe_path')->nullable();
                $table->json('validacoes')->nullable();
                $table->unsignedInteger('tentativas')->default(0);
                $table->timestamp('gerado_em')->nullable();
                $table->timestamp('assinado_em')->nullable();
                $table->timestamp('transmitido_em')->nullable();
                $table->timestamp('autorizado_em')->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'marketplace_order_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_fiscal_emissions');
    }
};
