<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (!Schema::hasColumn('empresas', 'tipo_pessoa')) $table->string('tipo_pessoa', 20)->default('juridica');
            if (!Schema::hasColumn('empresas', 'ie_isento')) $table->boolean('ie_isento')->default(false);
            if (!Schema::hasColumn('empresas', 'im')) $table->string('im', 30)->nullable();
            if (!Schema::hasColumn('empresas', 'cnae')) $table->string('cnae', 20)->nullable();
            if (!Schema::hasColumn('empresas', 'atividade_principal')) $table->string('atividade_principal', 150)->nullable();
            if (!Schema::hasColumn('empresas', 'porte_empresa')) $table->string('porte_empresa', 40)->nullable();
            if (!Schema::hasColumn('empresas', 'faturamento_anual_faixa')) $table->string('faturamento_anual_faixa', 60)->nullable();
            if (!Schema::hasColumn('empresas', 'quantidade_funcionarios')) $table->unsignedInteger('quantidade_funcionarios')->nullable();
            if (!Schema::hasColumn('empresas', 'segmentos')) $table->text('segmentos')->nullable();
            if (!Schema::hasColumn('empresas', 'celular')) $table->string('celular', 25)->nullable();
            if (!Schema::hasColumn('empresas', 'complemento')) $table->string('complemento', 100)->nullable();
            if (!Schema::hasColumn('empresas', 'site')) $table->string('site', 200)->nullable();
            if (!Schema::hasColumn('empresas', 'contato_nome')) $table->string('contato_nome', 120)->nullable();
            if (!Schema::hasColumn('empresas', 'inscricoes_substitutas')) $table->text('inscricoes_substitutas')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $colunas = ['tipo_pessoa','ie_isento','im','cnae','atividade_principal','porte_empresa','faturamento_anual_faixa','quantidade_funcionarios','segmentos','celular','complemento','site','contato_nome','inscricoes_substitutas'];
            foreach ($colunas as $coluna) {
                if (Schema::hasColumn('empresas', $coluna)) $table->dropColumn($coluna);
            }
        });
    }
};
