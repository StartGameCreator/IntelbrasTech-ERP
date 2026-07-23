<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Models\{Lead, Pipeline, PipelineEtapa, Cliente};
use Illuminate\Http\{Request, JsonResponse};

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
        $dados['status'] = Lead::STATUS_ABERTO;
        $dados['responsavel_id'] = $dados['responsavel_id'] ?? auth()->id();

        $lead = Lead::create($dados);

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
            ->get();

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

        $lead = Lead::with(['pipeline', 'etapa', 'responsavel:id,nome', 'cliente'])->findOrFail($id);

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
        ]);

        if (array_key_exists('cnpj', $dados)) {
            $dados['cnpj'] = preg_replace('/\D/', '', (string) $dados['cnpj']);
            $dados['cnpj'] = $dados['cnpj'] !== '' ? $dados['cnpj'] : null;

            if ($dados['cnpj'] !== null && strlen($dados['cnpj']) !== 14) {
                return response()->json(['message' => 'CNPJ inválido. Informe 14 dígitos.'], 422);
            }
        }

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

        $lead->update(['etapa_id' => $dados['etapa_id']]);

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
        $lead->save();

        return response()->json(['message' => 'Lead marcado como ganho!', 'lead' => $lead->fresh(['cliente', 'etapa'])]);
    }

    // ── POST /leads/{id}/perdido ──────────────────────────────────

    public function marcarPerdido(Request $request, int $id): JsonResponse
    {
        $this->authorize('crm.edit');

        $dados = $request->validate(['motivo_perda' => 'nullable|string|max:255']);

        $lead = Lead::findOrFail($id);
        $lead->update([
            'status'       => Lead::STATUS_PERDIDO,
            'probabilidade'=> 0,
            'motivo_perda' => $dados['motivo_perda'] ?? null,
        ]);

        return response()->json(['message' => 'Lead marcado como perdido.', 'lead' => $lead]);
    }
}
