<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('clientes')) {
            return;
        }

        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'rg_ie')) {
                $table->string('rg_ie', 30)->nullable();
            }
            if (!Schema::hasColumn('clientes', 'site')) {
                $table->string('site', 255)->nullable();
            }
            if (!Schema::hasColumn('clientes', 'inscricao_estadual')) {
                $table->string('inscricao_estadual', 30)->nullable();
            }
            if (!Schema::hasColumn('clientes', 'inscricao_municipal')) {
                $table->string('inscricao_municipal', 30)->nullable();
            }
            if (!Schema::hasColumn('clientes', 'contribuinte_icms')) {
                $table->boolean('contribuinte_icms')->default(false);
            }
            if (!Schema::hasColumn('clientes', 'data_nascimento')) {
                $table->date('data_nascimento')->nullable();
            }
            if (!Schema::hasColumn('clientes', 'sexo')) {
                $table->string('sexo', 20)->nullable();
            }
            if (!Schema::hasColumn('clientes', 'profissao')) {
                $table->string('profissao', 120)->nullable();
            }
            if (!Schema::hasColumn('clientes', 'estado_civil')) {
                $table->string('estado_civil', 30)->nullable();
            }
            if (!Schema::hasColumn('clientes', 'tabela_preco_id')) {
                $table->foreignId('tabela_preco_id')->nullable()
                    ->constrained('tabelas_preco')->nullOnDelete();
            }
            if (!Schema::hasColumn('clientes', 'foto')) {
                $table->string('foto', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Migration de compatibilidade: não remove dados do cadastro.
    }
};
