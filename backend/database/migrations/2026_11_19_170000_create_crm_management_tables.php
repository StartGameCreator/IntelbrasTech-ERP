<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  Schema::create('crm_motivos_perda',function(Blueprint $t){$t->id();$t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();$t->string('nome',150);$t->boolean('ativo')->default(true);$t->timestamps();$t->unique(['empresa_id','nome']);});
  Schema::table('leads',fn(Blueprint $t)=>$t->foreignId('motivo_perda_id')->nullable()->after('motivo_perda')->constrained('crm_motivos_perda')->nullOnDelete());
  Schema::create('crm_oportunidade_produtos',function(Blueprint $t){$t->id();$t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();$t->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();$t->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();$t->string('descricao',255);$t->decimal('quantidade',12,3)->default(1);$t->decimal('valor_unitario',15,2)->default(0);$t->decimal('desconto',15,2)->default(0);$t->decimal('total',15,2)->default(0);$t->timestamps();});
  Schema::create('crm_metas',function(Blueprint $t){$t->id();$t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();$t->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();$t->date('periodo_inicio');$t->date('periodo_fim');$t->decimal('meta_valor',15,2)->default(0);$t->unsignedInteger('meta_quantidade')->default(0);$t->timestamps();});
  Schema::create('crm_automacoes',function(Blueprint $t){$t->id();$t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();$t->string('nome',180);$t->string('gatilho',50)->index();$t->json('condicoes')->nullable();$t->string('acao',50);$t->json('configuracao')->nullable();$t->boolean('ativo')->default(true);$t->unsignedInteger('execucoes')->default(0);$t->timestamp('ultima_execucao_em')->nullable();$t->timestamps();});
 }
 public function down():void{Schema::dropIfExists('crm_automacoes');Schema::dropIfExists('crm_metas');Schema::dropIfExists('crm_oportunidade_produtos');Schema::table('leads',fn(Blueprint $t)=>$t->dropConstrainedForeignId('motivo_perda_id'));Schema::dropIfExists('crm_motivos_perda');}
};
