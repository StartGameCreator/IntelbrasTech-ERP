<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pipeline_etapas', function (Blueprint $t) {
            $t->unsignedTinyInteger('probabilidade')->default(10);
            $t->boolean('etapa_ganha')->default(false);
            $t->boolean('etapa_perdida')->default(false);
        });
        Schema::table('leads', function (Blueprint $t) {
            $t->unsignedTinyInteger('score')->default(0)->index();
            $t->string('prioridade', 20)->default('media')->index();
            $t->string('estagio_ciclo', 30)->default('lead')->index();
            $t->string('proxima_acao', 200)->nullable();
            $t->timestamp('proxima_acao_em')->nullable()->index();
            $t->timestamp('ultima_atividade_em')->nullable();
            $t->timestamp('entrou_etapa_em')->nullable();
            $t->timestamp('fechado_em')->nullable();
            $t->string('necessidade', 500)->nullable();
            $t->string('orcamento', 120)->nullable();
            $t->string('autoridade', 120)->nullable();
            $t->string('prazo_compra', 120)->nullable();
        });
        Schema::create('crm_atividades', function (Blueprint $t) {
            $t->id(); $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $t->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $t->string('tipo', 30)->index(); $t->string('titulo', 200); $t->text('descricao')->nullable();
            $t->string('status', 20)->default('concluida')->index(); $t->timestamp('agendada_para')->nullable()->index();
            $t->timestamp('concluida_em')->nullable(); $t->string('resultado', 120)->nullable(); $t->timestamps();
        });
        Schema::create('crm_historico_etapas', function (Blueprint $t) {
            $t->id(); $t->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $t->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $t->foreignId('etapa_origem_id')->nullable()->constrained('pipeline_etapas')->nullOnDelete();
            $t->foreignId('etapa_destino_id')->nullable()->constrained('pipeline_etapas')->nullOnDelete();
            $t->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $t->timestamp('movido_em')->useCurrent(); $t->unsignedInteger('tempo_etapa_minutos')->nullable();
        });

        DB::table('pipeline_etapas')->orderBy('pipeline_id')->orderBy('ordem')->get()->groupBy('pipeline_id')->each(function ($stages) {
            $probabilities = [10, 25, 45, 65, 85];
            foreach ($stages->values() as $i => $stage) {
                $name = mb_strtolower($stage->nome);
                DB::table('pipeline_etapas')->where('id', $stage->id)->update([
                    'probabilidade' => str_contains($name, 'ganh') ? 100 : (str_contains($name, 'perdid') ? 0 : ($probabilities[$i] ?? 80)),
                    'etapa_ganha' => str_contains($name, 'ganh'), 'etapa_perdida' => str_contains($name, 'perdid'),
                ]);
            }
        });
        DB::table('leads')->whereNull('entrou_etapa_em')->update(['entrou_etapa_em' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_historico_etapas'); Schema::dropIfExists('crm_atividades');
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn(['score','prioridade','estagio_ciclo','proxima_acao','proxima_acao_em','ultima_atividade_em','entrou_etapa_em','fechado_em','necessidade','orcamento','autoridade','prazo_compra']));
        Schema::table('pipeline_etapas', fn (Blueprint $t) => $t->dropColumn(['probabilidade','etapa_ganha','etapa_perdida']));
    }
};
