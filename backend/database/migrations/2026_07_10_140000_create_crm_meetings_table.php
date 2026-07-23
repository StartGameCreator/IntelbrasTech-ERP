<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('crm_meetings')) return;
        Schema::create('crm_meetings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('usuario_id')->index();
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->string('provider', 30);
            $table->string('external_id')->nullable()->index();
            $table->string('titulo', 180);
            $table->text('descricao')->nullable();
            $table->string('cliente_nome', 180)->nullable();
            $table->string('cliente_email', 180)->nullable();
            $table->text('participantes')->nullable();
            $table->dateTime('inicio');
            $table->dateTime('fim');
            $table->string('timezone', 80)->default('America/Sao_Paulo');
            $table->text('join_url')->nullable();
            $table->text('event_url')->nullable();
            $table->string('status', 30)->default('agendada');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_meetings');
    }
};
