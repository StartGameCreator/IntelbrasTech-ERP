<?php

namespace App\Http\Controllers\Api\V1\Gestao;

use App\Http\Controllers\Controller;
use App\Models\OrdemServico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestaoEquipeController extends Controller
{
    /**
     * Este painel é operacional: aprova vendedores e organiza somente a equipe de campo.
     * Leads comerciais permanecem no CRM, onde o vendedor trabalha seu funil.
     */
    public function index(Request $request): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;
        $equipe = $this->equipeTecnica($empresaId);
        $categoriaPorId = DB::table('platform_categorias_tecnicas')->pluck('nome', 'id')->all();
        $visitas = collect($this->visitas($empresaId, $categoriaPorId));
        $ids = $equipe->pluck('usuario_id')->filter()->values();

        $osPorTecnico = OrdemServico::query()
            ->where('empresa_id', $empresaId)
            ->whereNull('data_conclusao')
            ->when($ids->isNotEmpty(), fn ($query) => $query->whereIn('tecnico_id', $ids))
            ->selectRaw('tecnico_id, count(*) as total')
            ->groupBy('tecnico_id')
            ->pluck('total', 'tecnico_id');

        $visitasPorTecnico = $visitas
            ->filter(fn (array $visita) => filled($visita['responsavel_id']))
            ->countBy('responsavel_id');

        $equipe = $equipe->map(function (array $membro) use ($osPorTecnico, $visitasPorTecnico) {
            $usuarioId = $membro['usuario_id'];
            $membro['carga_tecnica'] = (int) ($osPorTecnico[$usuarioId] ?? 0) + (int) ($visitasPorTecnico[$usuarioId] ?? 0);

            return $membro;
        })->values();

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

        $demandas = $visitas
            ->concat($ordens)
            ->sortBy([['responsavel_id', 'asc'], ['criada_em', 'desc']])
            ->values();
        $vendedoresPendentes = $this->vendedoresPendentes($empresaId);

        return response()->json([
            'resumo' => [
                'tecnicos' => $equipe->where('tipo_perfil', 'tecnico')->count(),
                'tecnicos_disponiveis' => $equipe->where('tipo_perfil', 'tecnico')->where('disponivel', true)->count(),
                'sem_responsavel' => $demandas->whereNull('responsavel_id')->count(),
                'vendedores_pendentes' => $vendedoresPendentes->count(),
            ],
            'equipe' => $equipe,
            'demandas' => $demandas,
            'vendedores_pendentes' => $vendedoresPendentes,
        ]);
    }

    public function atribuir(Request $request, string $origem, int $id): JsonResponse
    {
        $origem = strtolower($origem);
        abort_unless(in_array($origem, ['os', 'visita'], true), 404);

        $dados = $request->validate(['usuario_id' => 'nullable|integer|exists:usuarios,id']);
        $empresaId = (int) $request->user()->empresa_id;
        $usuarioId = $dados['usuario_id'] ?? null;
        $membro = $usuarioId ? $this->membroTecnico($empresaId, (int) $usuarioId) : null;

        if ($usuarioId && ! $membro) {
            throw ValidationException::withMessages(['usuario_id' => ['Selecione um técnico ou colaborador de campo ativo.']]);
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

        return response()->json(['message' => $usuarioId ? 'Demanda técnica atribuída com sucesso.' : 'Responsável removido da demanda.']);
    }

    private function equipeTecnica(int $empresaId)
    {
        return DB::table('platform_cadastros as cadastro')
            ->join('usuarios as usuario', 'usuario.id', '=', 'cadastro.usuario_id')
            ->where('cadastro.empresa_id', $empresaId)
            ->whereNull('cadastro.deleted_at')
            ->whereIn('cadastro.tipo_perfil', ['tecnico', 'colaborador'])
            ->orderBy('cadastro.tipo_perfil')
            ->orderBy('cadastro.nome')
            ->get([
                'cadastro.id as cadastro_id', 'cadastro.tipo_perfil', 'cadastro.nome', 'cadastro.email',
                'cadastro.telefone', 'cadastro.whatsapp', 'cadastro.cidade', 'cadastro.estado', 'cadastro.status',
                'cadastro.disponivel_para_atendimento', 'cadastro.localizacao_atualizada_em',
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
                'disponivel' => $membro->tipo_perfil === 'tecnico'
                    ? $membro->status === 'aprovado' && (bool) $membro->usuario_ativo && (bool) $membro->disponivel_para_atendimento
                    : $membro->status === 'aprovado' && (bool) $membro->usuario_ativo,
                'localizacao_atualizada_em' => $membro->localizacao_atualizada_em,
            ]);
    }

    private function membroTecnico(int $empresaId, int $usuarioId): ?object
    {
        return DB::table('platform_cadastros as cadastro')
            ->join('usuarios as usuario', 'usuario.id', '=', 'cadastro.usuario_id')
            ->where('cadastro.empresa_id', $empresaId)
            ->where('cadastro.usuario_id', $usuarioId)
            ->whereNull('cadastro.deleted_at')
            ->where('cadastro.status', 'aprovado')
            ->where('usuario.ativo', true)
            ->whereIn('cadastro.tipo_perfil', ['tecnico', 'colaborador'])
            ->select('cadastro.tipo_perfil', 'cadastro.nome')
            ->first();
    }

    private function vendedoresPendentes(int $empresaId)
    {
        return DB::table('platform_cadastros')
            ->where('empresa_id', $empresaId)
            ->where('tipo_perfil', 'vendedor')
            ->where('status', 'pendente')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->get(['id', 'nome', 'email', 'telefone', 'whatsapp', 'empresa', 'cargo', 'cidade', 'estado', 'created_at'])
            ->map(fn ($vendedor) => [
                'id' => $vendedor->id,
                'nome' => $vendedor->nome,
                'email' => $vendedor->email,
                'telefone' => $vendedor->whatsapp ?: $vendedor->telefone,
                'empresa' => $vendedor->empresa,
                'cargo' => $vendedor->cargo,
                'cidade' => $vendedor->cidade,
                'estado' => $vendedor->estado,
                'solicitado_em' => $vendedor->created_at,
            ]);
    }

    private function visitas(int $empresaId, array $categoriaPorId): array
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

        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
