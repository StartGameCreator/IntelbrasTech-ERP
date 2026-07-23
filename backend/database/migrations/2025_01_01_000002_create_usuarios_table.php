<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('perfis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 100);
            $table->text('descricao')->nullable();
            $table->boolean('admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('permissoes', function (Blueprint $table) {
            $table->id();
            $table->string('modulo', 50);
            $table->string('acao', 50);
            $table->string('descricao', 200)->nullable();
            $table->unique(['modulo','acao']);
        });
        Schema::create('perfil_permissoes', function (Blueprint $table) {
            $table->foreignId('perfil_id')->constrained('perfis')->cascadeOnDelete();
            $table->foreignId('permissao_id')->constrained('permissoes')->cascadeOnDelete();
            $table->primary(['perfil_id','permissao_id']);
        });
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('perfil_id')->nullable()->constrained('perfis')->nullOnDelete();
            $table->string('nome', 150);
            $table->string('email', 150);
            $table->timestamp('email_verificado_em')->nullable();
            $table->string('senha', 255);
            $table->string('avatar', 500)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('cargo', 100)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamp('ultimo_acesso')->nullable();
            $table->string('tema', 10)->default('dark');
            $table->json('preferencias')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->unique(['empresa_id','email']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('perfil_permissoes');
        Schema::dropIfExists('permissoes');
        Schema::dropIfExists('perfis');
    }
};
