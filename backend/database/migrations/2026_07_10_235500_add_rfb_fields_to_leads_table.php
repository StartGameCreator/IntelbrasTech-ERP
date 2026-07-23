<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $columns = [
                'razao_social' => fn () => $table->string('razao_social')->nullable(),
                'nome_fantasia' => fn () => $table->string('nome_fantasia')->nullable(),
                'situacao_cadastral' => fn () => $table->string('situacao_cadastral')->nullable(),
                'endereco_empresarial' => fn () => $table->text('endereco_empresarial')->nullable(),
                'logradouro' => fn () => $table->string('logradouro')->nullable(),
                'numero_endereco' => fn () => $table->string('numero_endereco', 30)->nullable(),
                'complemento_endereco' => fn () => $table->string('complemento_endereco')->nullable(),
                'bairro' => fn () => $table->string('bairro')->nullable(),
                'cep' => fn () => $table->string('cep', 8)->nullable(),
                'cnae' => fn () => $table->string('cnae', 20)->nullable(),
                'atividade_principal' => fn () => $table->string('atividade_principal')->nullable(),
                'natureza_juridica' => fn () => $table->string('natureza_juridica')->nullable(),
                'porte_empresa' => fn () => $table->string('porte_empresa')->nullable(),
                'capital_social' => fn () => $table->decimal('capital_social', 15, 2)->nullable(),
                'socios' => fn () => $table->text('socios')->nullable(),
                'rfb_provider' => fn () => $table->string('rfb_provider')->nullable(),
                'rfb_consultado_em' => fn () => $table->timestamp('rfb_consultado_em')->nullable(),
                'rfb_dados' => fn () => $table->text('rfb_dados')->nullable(),
            ];

            foreach ($columns as $name => $definition) {
                if (!Schema::hasColumn('leads', $name)) {
                    $definition();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'razao_social',
                'nome_fantasia',
                'situacao_cadastral',
                'endereco_empresarial',
                'logradouro',
                'numero_endereco',
                'complemento_endereco',
                'bairro',
                'cep',
                'cnae',
                'atividade_principal',
                'natureza_juridica',
                'porte_empresa',
                'capital_social',
                'socios',
                'rfb_provider',
                'rfb_consultado_em',
                'rfb_dados',
            ] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
