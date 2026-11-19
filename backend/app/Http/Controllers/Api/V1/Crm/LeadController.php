<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\{Lead, Pipeline, PipelineEtapa, Cliente, CrmAtividade, CrmHistoricoEtapa};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use App\Services\Crm\CrmAutomationService;

class LeadController extends Controller
{
    // ── GET /leads ─────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('crm.view');

        $q = Lead::with(['etapa:id,nome,cor', 'responsavel:id,nome'])
            ->when($request->pipeline_id, fn($q, $v) => $q->where('pipeline_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->responsavel_id, fn($q, $v) => $q->where('responsavel_id', $v))
            ->when($request->busca, function ($q, $v) {
                $q->where(function ($qq) use ($v) {
                    $qq->whereLike('nome', "%{$v}%")
                       ->orWhereLike('titulo', "%{$v}%")
                       ->orWhereLike('empresa_nome', "%{$v}%")
                       ->orWhereLike('cnpj', "%{$v}%");
                });
            })
            ->orderByDesc('created_at');

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($q->paginate($perPage));
    }

    // ── POST /leads ────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->authorize('crm.create');

        $dados = $request->validate([
            'pipeline_id'              => 'nullable|exists:pipelines,id',
            'etapa_id'                 => 'nullable|exists:pipeline_etapas,id',
            'responsavel_id'           => 'nullable|exists:usuarios,id',
            'cliente_id'               => 'nullable|exists:clientes,id',
            'nome'                     => 'required|string|max:200',
            'email'                    => 'nullable|email|max:150',
            'telefone'                 => 'nullable|string|max:20',
            'empresa_nome'             => 'nullable|string|max:200',
            'cnpj'                     => 'nullable|string|max:18',
            'origem'                   => 'nullable|string|max:50',
            'titulo'                   => 'nullable|string|max:200',
            'valor_estimado'           => 'nullable|numeric|min:0',
            'probabilidade'            => 'nullable|integer|min:0|max:100',
            'data_previsao_fechamento' => 'nullable|date',
            'tags'                     => 'nullable|string',
            'prioridade'               => 'nullable|in:baixa,media,alta,urgente',
            'estagio_ciclo'            => 'nullable|in:lead,qualificado,oportunidade,cliente,evangelista,desqualificado',
            'proxima_acao'             => 'nullable|string|max:200',
            'proxima_acao_em'          => 'nullable|date',
            'necessidade'              => 'nullable|string|max:500',
            'orcamento'                => 'nullable|string|max:120',
            'autoridade'               => 'nullable|string|max:120',
            'prazo_compra'             => 'nullable|string|max:120',
        ]);

        if (!empty($dados['cnpj'])) {
            $dados['cnpj'] = preg_replace('/\D/', '', $dados['cnpj']);
            if (strlen($dados['cnpj']) !== 14) {
                return response()->json(['message' => 'CNPJ inválido. Informe 14 dígitos.'], 422);
            }
        }

        $dados['empresa_id'] = auth()->user()->empresa_id;

        if (empty($dados['pipeline_id'])) {
            $pipelinePadrao = Pipeline::where('empresa_id', $dados['empresa_id'])->where('ativo', true)->first();
            $dados['pipeline_id'] = $pipelinePadrao?->id;
        }
        if (empty($dados['etapa_id']) && $dados['pipeline_id']) {
            $dados['etapa_id'] = PipelineEtapa::where('pipeline_id', $dados['pipeline_id'])->orderBy('ordem')->value('id');
        }
        if ($dados['etapa_id']) $dados['probabilidade'] = $dados['probabilidade'] ?? PipelineEtapa::where('pipeline_id',$dados['pipeline_id'])->find($dados['etapa_id'])?->probabilidade ?? 10;
        $dados['status'] = Lead::STATUS_ABERTO;
        $dados['responsavel_id'] = $dados['responsavel_id'] ?? auth()->id();
        $dados['entrou_etapa_em'] = now();
        $dados['score'] = $this->calcularScore($dados);

        $lead = Lead::create($dados);
        CrmAtividade::create(['empresa_id'=>$lead->empresa_id,'lead_id'=>$lead->id,'usuario_id'=>auth()->id(),'tipo'=>'sistema','titulo'=>'Lead criado','status'=>'concluida','concluida_em'=>now()]);
        app(CrmAutomationService::class)->executar('lead_criado',$lead);

        return response()->json([
            'message' => 'Lead criado com sucesso.',
            'lead'    => $lead->fresh(['etapa', 'responsavel']),
        ], 201);
    }

    // ── GET /leads/kanban ──────────────────────────────────────

    public function kanban(Request $request): JsonResponse
    {
        $this->authorize('crm.view');

        $empresaId = auth()->user()->empresa_id;

        $pipeline = $request->filled('pipeline_id')
            ? Pipeline::with('etapas')->findOrFail($request->pipeline_id)
            : Pipeline::with('etapas')->where('empresa_id', $empresaId)->where('ativo', true)->first();

        if (! $pipeline) {
            return response()->json(['message' => 'Nenhum pipeline configurado.'], 404);
        }

        $leads = Lead::with(['responsavel:id,nome', 'cliente:id,razao_social,nome_fantasia'])
            ->where('pipeline_id', $pipeline->id)
            ->where('status', Lead::STATUS_ABERTO)
            ->orderByRaw("CASE WHEN proxima_acao_em < CURRENT_TIMESTAMP THEN 0 WHEN proxima_acao_em IS NULL THEN 2 ELSE 1 END")
            ->orderBy('proxima_acao_em')->orderByDesc('score')->get();

        $colunas = $pipeline->etapas->map(fn ($etapa) => [
            'etapa'       => $etapa,
            'leads'       => $leads->where('etapa_id', $etapa->id)->values(),
            'total'       => $leads->where('etapa_id', $etapa->id)->count(),
            'valor_total' => round((float) $leads->where('etapa_id', $etapa->id)->sum('valor_estimado'), 2),
        ]);

        return response()->json([
            'pipeline' => ['id' => $pipeline->id, 'nome' => $pipeline->nome],
            'colunas'  => $colunas,
            'resumo'   => [
                'total_leads'  => $leads->count(),
                'valor_total'  => round((float) $leads->sum('valor_estimado'), 2),
            ],
        ]);
    }

    // ── GET /leads/{id} ─────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $this->authorize('crm.view');

        $lead = Lead::with(['pipeline', 'etapa', 'responsavel:id,nome', 'cliente', 'atividades.usuario:id,nome', 'historicoEtapas'])->findOrFail($id);

        return response()->json($lead);
    }

    // ── PUT /leads/{id} ─────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('crm.edit');

        $lead = Lead::findOrFail($id);

        $dados = $request->validate([
            'responsavel_id'           => 'nullable|exists:usuarios,id',
            'nome'                     => 'sometimes|string|max:200',
            'email'                    => 'nullable|email|max:150',
            'telefone'                 => 'nullable|string|max:20',
            'empresa_nome'             => 'nullable|string|max:200',
            'cnpj'                     => 'nullable|string|max:18',
            'titulo'                   => 'nullable|string|max:200',
            'valor_estimado'           => 'nullable|numeric|min:0',
            'probabilidade'            => 'nullable|integer|min:0|max:100',
            'data_previsao_fechamento' => 'nullable|date',
            'tags'                     => 'nullable|string',
            'prioridade'               => 'nullable|in:baixa,media,alta,urgente',
            'estagio_ciclo'            => 'nullable|in:lead,qualificado,oportunidade,cliente,evangelista,desqualificado',
            'proxima_acao'             => 'nullable|string|max:200',
            'proxima_acao_em'          => 'nullable|date',
            'necessidade'              => 'nullable|string|max:500',
            'orcamento'                => 'nullable|string|max:120',
            'autoridade'               => 'nullable|string|max:120',
            'prazo_compra'             => 'nullable|string|max:120',
        ]);

        if (array_key_exists('cnpj', $dados)) {
            $dados['cnpj'] = preg_replace('/\D/', '', (string) $dados['cnpj']);
            $dados['cnpj'] = $dados['cnpj'] !== '' ? $dados['cnpj'] : null;

            if ($dados['cnpj'] !== null && strlen($dados['cnpj']) !== 14) {
                return response()->json(['message' => 'CNPJ inválido. Informe 14 dígitos.'], 422);
            }
        }

        $dados['score'] = $this->calcularScore(array_merge($lead->toArray(), $dados));
        $lead->update($dados);

        return response()->json(['message' => 'Lead atualizado.', 'lead' => $lead->fresh(['etapa', 'responsavel'])]);
    }

    // ── DELETE /leads/{id} ──────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('crm.delete');

        Lead::findOrFail($id)->delete();

        return response()->json(['message' => 'Lead excluído.']);
    }

    // ── POST /leads/{id}/mover-etapa ─────────────────────────────

    public function moverEtapa(Request $request, int $id): JsonResponse
    {
        $this->authorize('crm.edit');

        $lead = Lead::findOrFail($id);

        $dados = $request->validate(['etapa_id' => 'required|exists:pipeline_etapas,id']);
        $destino = PipelineEtapa::where('pipeline_id',$lead->pipeline_id)->findOrFail($dados['etapa_id']);
        $origem = $lead->etapa_id; $entrou = $lead->entrou_etapa_em;
        DB::transaction(function() use($lead,$destino,$origem,$entrou){
            CrmHistoricoEtapa::create(['empresa_id'=>$lead->empresa_id,'lead_id'=>$lead->id,'etapa_origem_id'=>$origem,'etapa_destino_id'=>$destino->id,'usuario_id'=>auth()->id(),'movido_em'=>now(),'tempo_etapa_minutos'=>$entrou?now()->diffInMinutes($entrou):null]);
            CrmAtividade::create(['empresa_id'=>$lead->empresa_id,'lead_id'=>$lead->id,'usuario_id'=>auth()->id(),'tipo'=>'etapa','titulo'=>'Etapa alterada para '.$destino->nome,'status'=>'concluida','concluida_em'=>now()]);
            $lead->update(['etapa_id'=>$destino->id,'probabilidade'=>$destino->probabilidade,'entrou_etapa_em'=>now()]);
        });
        app(CrmAutomationService::class)->executar('etapa_alterada',$lead->fresh());

        return response()->json(['message' => 'Lead movido de etapa.', 'lead' => $lead->fresh('etapa')]);
    }

    // ── POST /leads/{id}/ganho ────────────────────────────────────
    // Marca o lead como ganho e, opcionalmente, já cria o Cliente correspondente.

    public function marcarGanho(Request $request, int $id): JsonResponse
    {
        $this->authorize('crm.edit');

        $lead = Lead::findOrFail($id);

        if (! $lead->cliente_id && $request->boolean('criar_cliente', true)) {
            $cliente = Cliente::create([
                'empresa_id'      => $lead->empresa_id,
                'tipo'            => 'J',
                'razao_social'    => $lead->empresa_nome ?: $lead->nome,
                'cpf_cnpj'        => $lead->cnpj,
                'email'           => $lead->email,
                'celular'         => $lead->telefone,
                'origem'          => $lead->origem,
                'canal_aquisicao' => 'crm',
                'ativo'           => true,
            ]);
            $lead->cliente_id = $cliente->id;
        }

        $lead->status = Lead::STATUS_GANHO;
        $lead->probabilidade = 100;
        $lead->estagio_ciclo = 'cliente';
        $lead->fechado_em = now();
        $lead->save();
        CrmAtividade::create(['empresa_id'=>$lead->empresa_id,'lead_id'=>$lead->id,'usuario_id'=>auth()->id(),'tipo'=>'fechamento','titulo'=>'Oportunidade ganha','status'=>'concluida','concluida_em'=>now()]);
        app(CrmAutomationService::class)->executar('lead_ganho',$lead);

        return response()->json(['message' => 'Lead marcado como ganho!', 'lead' => $lead->fresh(['cliente', 'etapa'])]);
    }

    // ── POST /leads/{id}/perdido ──────────────────────────────────

    public function marcarPerdido(Request $request, int $id): JsonResponse
    {
        $this->authorize('crm.edit');

        $dados = $request->validate(['motivo_perda' => 'nullable|string|max:255','motivo_perda_id'=>'nullable|exists:crm_motivos_perda,id']);

        $lead = Lead::findOrFail($id);
        $lead->update([
            'status'       => Lead::STATUS_PERDIDO,
            'probabilidade'=> 0,
            'motivo_perda' => $dados['motivo_perda'] ?? null,
            'motivo_perda_id'=>$dados['motivo_perda_id']??null,
            'estagio_ciclo'=> 'desqualificado', 'fechado_em'=>now(),
        ]);
        CrmAtividade::create(['empresa_id'=>$lead->empresa_id,'lead_id'=>$lead->id,'usuario_id'=>auth()->id(),'tipo'=>'fechamento','titulo'=>'Oportunidade perdida','descricao'=>$dados['motivo_perda']??null,'status'=>'concluida','concluida_em'=>now()]);
        app(CrmAutomationService::class)->executar('lead_perdido',$lead->fresh());

        return response()->json(['message' => 'Lead marcado como perdido.', 'lead' => $lead]);
    }

    public function timeline(int $id): JsonResponse
    {
        $this->authorize('crm.view'); $lead=Lead::findOrFail($id);
        return response()->json(['atividades'=>CrmAtividade::with('usuario:id,nome')->where('lead_id',$lead->id)->orderByDesc('created_at')->get(),'historico_etapas'=>$lead->historicoEtapas]);
    }

    public function registrarAtividade(Request $request,int $id): JsonResponse
    {
        $this->authorize('crm.edit'); $lead=Lead::findOrFail($id);
        $d=$request->validate(['tipo'=>'required|in:tarefa,ligacao,email,reuniao,whatsapp,nota,visita','titulo'=>'required|string|max:200','descricao'=>'nullable|string|max:5000','status'=>'required|in:pendente,concluida,cancelada','agendada_para'=>'nullable|date','resultado'=>'nullable|string|max:120']);
        $d['empresa_id']=$lead->empresa_id;$d['lead_id']=$lead->id;$d['usuario_id']=auth()->id();if($d['status']==='concluida')$d['concluida_em']=now();
        $atividade=CrmAtividade::create($d);$update=['ultima_atividade_em'=>now()];if($d['status']==='pendente'){ $update['proxima_acao']=$d['titulo'];$update['proxima_acao_em']=$d['agendada_para']??now()->addDay(); }
        $lead->update($update); return response()->json(['message'=>'Atividade registrada.','atividade'=>$atividade->load('usuario:id,nome')],201);
    }

    public function atualizarAtividade(Request $request,int $id,int $atividade): JsonResponse
    {
        $this->authorize('crm.edit');$lead=Lead::findOrFail($id);$item=CrmAtividade::where('lead_id',$lead->id)->findOrFail($atividade);
        $d=$request->validate(['status'=>'required|in:pendente,concluida,cancelada','resultado'=>'nullable|string|max:120']);$d['concluida_em']=$d['status']==='concluida'?now():null;$item->update($d);$lead->update(['ultima_atividade_em'=>now()]);return response()->json(['message'=>'Atividade atualizada.']);
    }

    public function resumoOperacional(): JsonResponse
    {
        $this->authorize('crm.view');$q=Lead::where('status',Lead::STATUS_ABERTO);$hoje=now()->endOfDay();
        return response()->json(['abertos'=>(clone $q)->count(),'valor_pipeline'=>(float)(clone $q)->sum('valor_estimado'),'previsao_ponderada'=>(float)(clone $q)->selectRaw('COALESCE(SUM(valor_estimado * probabilidade / 100),0) total')->value('total'),'atividades_vencidas'=>(clone $q)->where('proxima_acao_em','<',now())->count(),'atividades_hoje'=>(clone $q)->whereBetween('proxima_acao_em',[now()->startOfDay(),$hoje])->count(),'sem_proxima_acao'=>(clone $q)->whereNull('proxima_acao_em')->count()]);
    }

    private function calcularScore(array $d): int
    {
        $score=0;foreach(['email','telefone','empresa_nome','cnpj'] as $field)if(!empty($d[$field]))$score+=8;if(!empty($d['necessidade']))$score+=18;if(!empty($d['orcamento']))$score+=14;if(!empty($d['autoridade']))$score+=14;if(!empty($d['prazo_compra']))$score+=12;if(!empty($d['proxima_acao_em']))$score+=10;return min(100,$score);
    }
}
