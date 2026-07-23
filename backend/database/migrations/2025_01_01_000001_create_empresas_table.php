<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('razao_social', 200);
            $table->string('nome_fantasia', 200)->nullable();
            $table->string('cnpj', 18)->unique();
            $table->string('ie', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('logradouro', 200)->nullable();
            $table->string('numero', 10)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->char('estado', 2)->nullable();
            $table->string('ibge', 7)->nullable();
            $table->smallInteger('regime_tributario')->default(1);
            $table->string('logo', 500)->nullable();
            $table->boolean('ativo')->default(true);
            $table->string('plano', 30)->default('mvp');
            $table->date('trial_ate')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('empresas'); }
};
