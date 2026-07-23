<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tabelas_preco', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->text('descricao')->nullable();
            $table->decimal('desconto_geral', 5, 2)->default(0);
            $table->decimal('acrescimo_geral', 5, 2)->default(0);
            $table->boolean('ativa')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('tabelas_preco'); }
};
