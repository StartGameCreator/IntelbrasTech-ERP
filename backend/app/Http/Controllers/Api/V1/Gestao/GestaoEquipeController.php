<?php

namespace App\Http\Controllers\Api\V1\Gestao;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\OrdemServico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestaoEquipeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;
        $equipe = $this->equipe($empresaId);
        $categoriaPorId = DB::table('platform_categorias_tecnicas')->pluck('nome', 'id')->all();
        $visitas = $this->visitasPendentes($empresaId, $categoriaPorId);
        $ids = $equipe->pluck('usuario_id')->filter()->values();

        $leadsPorResponsavel = Lead::query()
            ->where('empresa_id', $empresaId)
            ->where('status', Lead::STATUS_ABERTO)
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('responsavel_id', $ids))
            ->selectRaw('responsavel_id, count(*) as total')
            ->groupBy('responsavel_id')
            ->pluck('total', 'responsavel_id');

        $osPorTecnico = OrdemServico::query()
            ->where('empresa_id', $empresaId)
            ->whereNull('data_conclusao')
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('tecnico_id', $ids))
            ->selectRaw('tecnico_id, count(*) as total')
            ->groupBy('tecnico_id')
            ->pluck('total', 'tecnico_id');

        $visitasPorResponsavel = collect($visitas)
            ->filter(fn (array $visita) => filled($visita['responsavel_id']))
            ->countBy('responsavel_id');

        $equipe = $equipe->map(function (array $membro) use ($leadsPorResponsavel, $osPorTecnico, $visitasPorResponsavel) {
            $usuarioId = $membro['usuario_id'];
            $membro['carga_comercial'] = (int) ($leadsPorResponsavel[$usuarioId] ?? 0);
            $membro['carga_tecnica'] = (int) ($osPorTecnico[$usuarioId] ?? 0) + (int) ($visitasPorResponsavel[$usuarioId] ?? 0);
            $membro['carga_total'] = $membro['carga_comercial'] + $membro['carga_tecnica'];

            return $membro;
        })->values();

        $leads = Lead::query()
            ->with('responsavel:id,nome')
            ->where('empresa_id', $empresaId)
            ->where('status', Lead::STATUS_ABERTO)
            ->latest('updated_at')
            ->limit(120)
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'origem' => 'lead',
                'tipo' => 'comercial',
                'titulo' => $lead->titulo ?: ($lead->nome ?: 'Oportunidade comercial'),
                'cliente' => $lead->empresa_nome ?: $lead->nome,
                'descricao' => $lead->necessidade ?: $lead->origem,
                'status' => $lead->status,
                'prioridade' => $lead->prioridade ?: 'normal',
                'responsavel_id' => $lead->responsavel_id,
                'responsavel_nome' => $lead->responsavel?->nome,
                'criada_em' => optional($lead->created_at)->toIso8601String(),
                'url' => '/crm',
            ]);

        $ordens = OrdemServico::query()
            ->with(['cliente:id,razao_social,nome_fantasia', 'tecnico:id,nome', 'status:id,nome'])
            ->where('empresa_id', $empresaId)
            ->whereNull('data_conclusao')
            ->latest('updated_at')
            ->limit(120)
            ->get()
            ->map(fn (OrdemServico $ordem) => [
                'id' => $ordem->id,
                'origem' => 'os',
                'tipo' => 'tecnica',
                'titulo' => 'OS #' . $ordem->numero . ' — ' . ($ordem->equipamento_modelo ?: $ordem->equipamento_tipo ?: 'Atendimento técnico'),
                'cliente' => $ordem->cliente?->razao_social ?: $ordem->cliente?->nome_fantasia ?: 'Cliente não identificado',
                'descricao' => $ordem->defeito_relatado,
                'status' => $ordem->status?->nome ?: 'Em aberto',
                'prioridade' => $ordem->data_previsao && $ordem->data_previsao->isPast() ? 'alta' : 'normal',
                'responsavel_id' => $ordem->tecnico_id,
                'responsavel_nome' => $ordem->tecnico?->nome,
                'criada_em' => optional($ordem->created_at)->toIso8601String(),
                'url' => '/os/' . $ordem->id,
            ]);

        $demandas = collect($visitas)
            ->concat($leads)
            ->concat($ordens)
            ->sortBy([['responsavel_id', 'asc'], ['criada_em', 'desc']])
            ->values();

        return response()->json([
            'resumo' => [
                'membros_ativos' => $equipe->where('disponivel', true)->count(),
                'tecnicos' => $equipe->where('tipo_perfil', 'tecnico')->count(),
                'vendedores' => $equipe->where('tipo_perfil', 'vendedor')->count(),
                'sem_responsavel' => $demandas->whereNull('responsavel_id')->count(),
            ],
            'equipe' => $equipe,
            'demandas' => $demandas,
        ]);
    }

    public function atribuir(Request $request, string $origem, int $id): JsonResponse
    {
        $origem = strtolower($origem);
        abort_unless(in_array($origem, ['lead', 'os', 'visita'], true), 404);

        $dados = $request->validate(['usuario_id' => 'nullable|integer|exists:usuarios,id']);
        $empresaId = (int) $request->user()->empresa_id;
        $usuarioId = $dados['usuario_id'] ?? null;
        $membro = $usuarioId ? $this->membro($empresaId, (int) $usuarioId) : null;

        if ($usuarioId && ! $membro) {
            throw ValidationException::withMessages(['usuario_id' => ['Selecione um membro ativo da equipe.']]);
        }

        $perfisPermitidos = $origem === 'lead' ? ['vendedor', 'colaborador'] : ['tecnico', 'colaborador'];

        if ($membro && ! in_array($membro->tipo_perfil, $perfisPermitidos, true)) {
            throw ValidationException::withMessages(['usuario_id' => ['Este perfil não pode receber essa demanda.']]);
        }

        if ($origem === 'lead') {
            $lead = Lead::query()->where('empresa_id', $empresaId)->findOrFail($id);
            $lead->update(['responsavel_id' => $usuarioId]);
        }

        if ($origem === 'os') {
            $ordem = OrdemServico::query()->where('empresa_id', $empresaId)->findOrFail($id);
            $ordem->update(['tecnico_id' => $usuarioId]);
        }

        if ($origem === 'visita') {
            $cadastro = DB::table('platform_cadastros')
                ->where('empresa_id', $empresaId)
                ->where('tipo_perfil', 'cliente')
                ->whereNull('deleted_at')
                ->where('id', $id)
                ->first();

            abort_unless($cadastro, 404, 'Solicitação não encontrada.');
            $metadados = $this->json($cadastro->metadados ?? null);
            $metadados['gestao_visita'] = $usuarioId ? [
                'responsavel_id' => $usuarioId,
                'responsavel_nome' => $membro->nome,
                'atribuido_em' => now()->toIso8601String(),
                'status' => 'atribuida',
            ] : [];

            DB::table('platform_cadastros')->where('id', $id)->update([
                'metadados' => json_encode($metadados, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => $usuarioId ? 'Demanda atribuída com sucesso.' : 'Responsável removido da demanda.']);
    }

    private function equipe(int $empresaId)
    {
        return DB::table('platform_cadastros as cadastro')
            ->join('usuarios as usuario', 'usuario.id', '=', 'cadastro.usuario_id')
            ->where('cadastro.empresa_id', $empresaId)
            ->whereNull('cadastro.deleted_at')
            ->whereIn('cadastro.tipo_perfil', ['tecnico', 'vendedor', 'colaborador'])
            ->orderBy('cadastro.tipo_perfil')
            ->orderBy('cadastro.nome')
            ->get([
                'cadastro.id as cadastro_id', 'cadastro.tipo_perfil', 'cadastro.nome', 'cadastro.email',
                'cadastro.telefone', 'cadastro.whatsapp', 'cadastro.cidade', 'cadastro.estado', 'cadastro.status',
                'usuario.id as usuario_id', 'usuario.ativo as usuario_ativo',
            ])
            ->map(fn ($membro) => [
                'cadastro_id' => $membro->cadastro_id,
                'usuario_id' => $membro->usuario_id,
                'tipo_perfil' => $membro->tipo_perfil,
                'nome' => $membro->nome,
                'email' => $membro->email,
                'telefone' => $membro->whatsapp ?: $membro->telefone,
                'cidade' => $membro->cidade,
                'estado' => $membro->estado,
                'status' => $membro->status,
                'disponivel' => $membro->status === 'aprovado' && (bool) $membro->usuario_ativo,
            ]);
    }

    private function membro(int $empresaId, int $usuarioId): ?object
    {
        return DB::table('platform_cadastros as cadastro')
            ->join('usuarios as usuario', 'usuario.id', '=', 'cadastro.usuario_id')
            ->where('cadastro.empresa_id', $empresaId)
            ->where('cadastro.usuario_id', $usuarioId)
            ->whereNull('cadastro.deleted_at')
            ->where('cadastro.status', 'aprovado')
            ->where('usuario.ativo', true)
            ->whereIn('cadastro.tipo_perfil', ['tecnico', 'vendedor', 'colaborador'])
            ->select('cadastro.tipo_perfil', 'cadastro.nome')
            ->first();
    }

    private function visitasPendentes(int $empresaId, array $categoriaPorId): array
    {
        return DB::table('platform_cadastros')
            ->where('empresa_id', $empresaId)
            ->where('tipo_perfil', 'cliente')
            ->whereNull('deleted_at')
            ->latest('updated_at')
            ->limit(120)
            ->get(['id', 'nome', 'empresa', 'metadados', 'created_at'])
            ->map(function ($cadastro) use ($categoriaPorId) {
                $metadados = $this->json($cadastro->metadados ?? null);
                $solicitacao = (array) ($metadados['solicitacao_visita'] ?? []);
                $gestao = (array) ($metadados['gestao_visita'] ?? []);

                if (! $solicitacao) {
                    return null;
                }

                $categoria = $categoriaPorId[$solicitacao['categoria_id'] ?? null] ?? null;

                return [
                    'id' => $cadastro->id,
                    'origem' => 'visita',
                    'tipo' => 'tecnica',
                    'titulo' => $solicitacao['servico'] ?: ($categoria ?: 'Solicitação de visita técnica'),
                    'cliente' => $cadastro->empresa ?: $cadastro->nome,
                    'descricao' => $solicitacao['descricao'] ?? $solicitacao['observacoes'] ?? null,
                    'status' => $gestao['status'] ?? $solicitacao['status'] ?? 'solicitada',
                    'prioridade' => 'normal',
                    'responsavel_id' => $gestao['responsavel_id'] ?? null,
                    'responsavel_nome' => $gestao['responsavel_nome'] ?? null,
                    'criada_em' => $solicitacao['solicitada_em'] ?? $cadastro->created_at,
                    'url' => null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function json(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
