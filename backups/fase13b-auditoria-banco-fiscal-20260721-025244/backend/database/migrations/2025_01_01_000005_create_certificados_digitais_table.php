<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('certificados_digitais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->char('tipo', 2)->default('A1'); // A1, A3
            $table->string('arquivo', 500)->nullable();
            $table->string('senha')->nullable();
            $table->date('validade')->nullable();
            $table->string('serie', 50)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['empresa_id', 'ativo']);
        });
    }
    public function down(): void { Schema::dropIfExists('certificados_digitais'); }
};
