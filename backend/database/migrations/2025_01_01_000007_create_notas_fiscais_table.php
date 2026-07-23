<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('notas_fiscais', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('empresa_id')->constrained('empresas');
            // Nota: FK explícita para "pedidos" fica pendente até a migration de pedidos existir.
            $table->unsignedBigInteger('pedido_id')->nullable()->index();
            $table->string('tipo', 10); // NFe, NFCe, NFSe
            $table->string('modelo', 5)->nullable();
            $table->string('serie', 5)->nullable();
            $table->unsignedBigInteger('numero')->nullable();
            $table->string('chave_acesso', 44)->nullable()->index();
            $table->string('natureza_operacao', 100)->nullable();
            $table->smallInteger('ambiente')->default(2);
            $table->smallInteger('finalidade')->default(1);

            // Emitente
            $table->string('emitente_cnpj', 18)->nullable();
            $table->string('emitente_ie', 30)->nullable();
            $table->string('emitente_razao', 200)->nullable();

            // Destinatário
            $table->string('destinatario_cpf_cnpj', 18)->nullable();
            $table->string('destinatario_ie', 30)->nullable();
            $table->string('destinatario_nome', 200)->nullable();
            $table->string('destinatario_email', 150)->nullable();
            $table->string('destinatario_cep', 9)->nullable();
            $table->string('destinatario_logradouro', 200)->nullable();
            $table->string('destinatario_numero', 10)->nullable();
            $table->string('destinatario_complemento', 100)->nullable();
            $table->string('destinatario_bairro', 100)->nullable();
            $table->string('destinatario_cidade', 100)->nullable();
            $table->char('destinatario_uf', 2)->nullable();
            $table->string('destinatario_ibge', 7)->nullable();

            // Totais
            $table->decimal('base_calculo_icms', 15, 2)->default(0);
            $table->decimal('valor_icms', 15, 2)->default(0);
            $table->decimal('valor_produtos', 15, 2)->default(0);
            $table->decimal('valor_frete', 15, 2)->default(0);
            $table->decimal('valor_seguro', 15, 2)->default(0);
            $table->decimal('outras_despesas', 15, 2)->default(0);
            $table->decimal('valor_desconto', 15, 2)->default(0);
            $table->decimal('valor_ipi', 15, 2)->default(0);
            $table->decimal('base_pis', 15, 2)->default(0);
            $table->decimal('valor_pis', 15, 2)->default(0);
            $table->decimal('base_cofins', 15, 2)->default(0);
            $table->decimal('valor_cofins', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);

            // Status
            $table->string('status', 30)->default('rascunho');
            $table->timestamp('data_emissao')->nullable();
            $table->timestamp('data_autorizacao')->nullable();
            $table->timestamp('data_cancelamento')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->string('protocolo', 20)->nullable();
            $table->longText('xml')->nullable();
            $table->longText('xml_cancelamento')->nullable();
            $table->string('danfe_url', 500)->nullable();

            // Transporte
            // Nota: FK explícita para "transportadoras" fica pendente até a migration existir.
            $table->unsignedBigInteger('transportadora_id')->nullable()->index();
            $table->char('tipo_frete', 1)->nullable();
            $table->integer('volumes')->nullable();
            $table->string('especie', 30)->nullable();
            $table->decimal('peso_bruto', 10, 3)->nullable();
            $table->decimal('peso_liquido', 10, 3)->nullable();

            // Outros
            $table->text('informacoes_adicionais')->nullable();
            $table->text('informacoes_fisco')->nullable();
            $table->text('qr_code_url')->nullable();
            $table->text('url_chave')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'tipo', 'status']);
            $table->index(['empresa_id', 'serie', 'numero']);
        });
    }
    public function down(): void { Schema::dropIfExists('notas_fiscais'); }
};
