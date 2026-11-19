<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('tipo', 50)->nullable();
            $table->string('titulo', 200);
            $table->text('mensagem')->nullable();
            $table->string('icone', 50)->nullable();
            $table->string('cor', 7)->nullable();
            $table->boolean('lida')->default(false);
            $table->string('url', 500)->nullable();
            $table->json('dados')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['empresa_id', 'usuario_id', 'lida']);
        });
    }
    public function down(): void { Schema::dropIfExists('notificacoes'); }
};
