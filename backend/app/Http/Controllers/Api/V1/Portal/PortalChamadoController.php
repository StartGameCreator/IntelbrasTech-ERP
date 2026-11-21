<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\{Cliente, ContratoServico, Empresa, Lead, Pipeline, PipelineEtapa, PortalChamado, PortalSolicitacao};
use App\Services\Crm\CrmLeadIntakeService;
use App\Services\Portal\ContratoServicoPdfService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PortalChamadoController extends Controller
{
    public function abrir(Request $request): JsonResponse
    {
        if ($request->filled('website') || $request->filled('portal_guard')) {
            return response()->json(['message' => 'Não foi possível validar o envio. Atualize a página e tente novamente.'], 422);
        }

        $request->merge([
            'cpf_cnpj' => preg_replace('/\D/', '', (string) $request->input('cpf_cnpj')),
            'cep' => preg_replace('/\D/', '', (string) $request->input('cep')),
            'ibge' => preg_replace('/\D/', '', (string) $request->input('ibge')),
        ]);

        $dados = $request->validate([
            'tipo_pessoa' => 'required|in:F,J',
            'nome' => 'required|string|max:200',
            'cpf_cnpj' => ['required', 'string', 'regex:/^(\d{11}|\d{14})$/'],
            'email' => 'required|email|max:150',
            'telefone' => 'required|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'cep' => ['required', 'string', 'size:8'],
            'logradouro' => 'required|string|max:200',
            'numero' => 'required|string|max:20',
            'complemento' => 'nullable|string|max:100',
            'bairro' => 'required|string|max:100',
            'cidade' => 'required|string|max:100',
            'estado' => 'required|string|size:2',
            'ibge' => ['nullable', 'string', 'regex:/^\d{7}$/'],
            'tipo_demanda' => 'required|string|max:100',
            'prioridade' => 'nullable|in:baixa,normal,alta,urgente',
            'descricao' => 'required|string|min:15|max:4000',
            'melhor_horario' => 'nullable|string|max:120',
            'consentimento' => 'accepted',
            'website' => 'nullable|string|max:0',
            'portal_guard' => 'nullable|string|max:0',
        ], [
            'cpf_cnpj.regex' => 'Informe um CPF com 11 dígitos ou CNPJ com 14 dígitos.',
            'cep.size' => 'Informe um CEP com 8 dígitos.',
            'consentimento.accepted' => 'É necessário autorizar o uso dos dados para atender sua solicitação.',
        ]);

        $empresa = $this->empresa();
        $pipeline = Pipeline::withoutGlobalScope('empresa')->where('empresa_id', $empresa->id)->where('ativo', true)->first();
        $etapa = $pipeline ? PipelineEtapa::where('pipeline_id', $pipeline->id)->orderBy('ordem')->first() : null;

        [$chamado, $lead] = DB::transaction(function () use ($dados, $empresa, $pipeline, $etapa, $request) {
            // Nunca sobrescrevemos um cadastro existente a partir de um formulário público.
            // O atendimento apenas reutiliza a identidade já conhecida pelo CRM.
            $cliente = Cliente::withoutGlobalScope('empresa')
                ->where('empresa_id', $empresa->id)
                ->where('cpf_cnpj', $dados['cpf_cnpj'])
                ->first();

            if (! $cliente) {
                $cliente = Cliente::withoutGlobalScope('empresa')->create([
                    'empresa_id' => $empresa->id,
                    'tipo' => $dados['tipo_pessoa'],
                    'razao_social' => $dados['nome'],
                    'nome_fantasia' => $dados['tipo_pessoa'] === 'J' ? $dados['nome'] : null,
                    'cpf_cnpj' => $dados['cpf_cnpj'],
                    'email' => $dados['email'],
                    'telefone' => $dados['telefone'],
                    'celular' => $dados['whatsapp'] ?: $dados['telefone'],
                    'whatsapp' => $dados['whatsapp'] ?: $dados['telefone'],
                    'cep' => $dados['cep'],
                    'logradouro' => $dados['logradouro'],
                    'numero' => $dados['numero'],
                    'complemento' => $dados['complemento'] ?? null,
                    'bairro' => $dados['bairro'],
                    'cidade' => $dados['cidade'],
                    'estado' => strtoupper($dados['estado']),
                    'ibge' => $dados['ibge'] ?? null,
                    'origem' => 'portal_chamado',
                    'canal_aquisicao' => 'whatsapp_portal',
                    'tags' => ['portal', 'chamado', 'servicos'],
                    'ativo' => true,
                ]);
            }

            $lead = Lead::withoutGlobalScope('empresa')->create([
                'empresa_id' => $empresa->id,
                'pipeline_id' => $pipeline?->id,
                'etapa_id' => $etapa?->id,
                'cliente_id' => $cliente->id,
                'nome' => $dados['nome'],
                'email' => $dados['email'],
                'telefone' => $dados['whatsapp'] ?: $dados['telefone'],
                'cidade' => $dados['cidade'],
                'uf' => strtoupper($dados['estado']),
                'origem' => 'portal_chamado',
                'titulo' => 'Chamado de serviço: ' . $dados['tipo_demanda'],
                'necessidade' => $dados['descricao'],
                'status' => Lead::STATUS_ABERTO,
                'probabilidade' => $etapa?->probabilidade ?? 10,
                'prioridade' => in_array($dados['prioridade'] ?? 'normal', ['alta', 'urgente'], true) ? 'alta' : 'media',
                'score' => 24,
                'entrou_etapa_em' => now(),
                'tags' => 'portal,chamado,servicos',
                'dados_extras' => collect($dados)->except(['consentimento', 'website', 'portal_guard'])->all(),
            ]);

            $chamado = PortalChamado::withoutGlobalScope('empresa')->create([
                'empresa_id' => $empresa->id,
                'cliente_id' => $cliente->id,
                'lead_id' => $lead->id,
                'token_publico' => Str::random(64),
                'protocolo' => $this->proximoProtocolo($empresa->id),
                'status' => 'recebido',
                'tipo_demanda' => $dados['tipo_demanda'],
                'prioridade' => $dados['prioridade'] ?? 'normal',
                'nome' => $dados['nome'],
                'email' => $dados['email'],
                'telefone' => $dados['telefone'],
                'whatsapp' => $dados['whatsapp'] ?: $dados['telefone'],
                'descricao' => $dados['descricao'],
                'dados' => collect($dados)->except(['consentimento', 'website', 'portal_guard'])->all(),
            ]);

            PortalSolicitacao::create([
                'uuid' => (string) Str::uuid(),
                'empresa_id' => $empresa->id,
                'lead_id' => $lead->id,
                'tipo' => 'chamado',
                'nome' => $dados['nome'],
                'email' => $dados['email'],
                'telefone' => $dados['telefone'],
                'cidade' => $dados['cidade'],
                'status' => 'recebida',
                'dados' => ['protocolo' => $chamado->protocolo, 'tipo_demanda' => $dados['tipo_demanda']],
                'ip_hash' => hash('sha256', (string) $request->ip()),
            ]);

            app(CrmLeadIntakeService::class)->registrar($lead, null, 'Chamado recebido pelo portal');

            return [$chamado, $lead];
        });

        return response()->json([
            'message' => 'Chamado registrado com sucesso. Nossa equipe analisará sua solicitação.',
            'protocolo' => $chamado->protocolo,
            'acompanhamento_token' => $chamado->token_publico,
            'lead_id' => $lead->uuid,
        ], 201);
    }

    public function acompanhar(string $token): JsonResponse
    {
        $chamado = PortalChamado::withoutGlobalScope('empresa')
            ->with(['contrato:id,portal_chamado_id,numero,status,aceito_em'])
            ->where('token_publico', $token)
            ->firstOrFail();

        return response()->json(['data' => $this->publico($chamado)]);
    }

    public function autorizar(Request $request, string $token, ContratoServicoPdfService $pdfService): JsonResponse
    {
        $dados = $request->validate([
            'aceite' => 'accepted',
            'nome_aceite' => 'required|string|max:200',
        ], ['aceite.accepted' => 'Confirme que leu e aceita as condições do orçamento.']);

        $chamado = DB::transaction(function () use ($token, $dados, $request, $pdfService) {
            $chamado = PortalChamado::withoutGlobalScope('empresa')
                ->with(['cliente', 'contrato'])
                ->where('token_publico', $token)
                ->lockForUpdate()
                ->firstOrFail();

            if ($chamado->status === 'autorizado' && $chamado->contrato) {
                return $chamado;
            }

            if ($chamado->status !== 'aguardando_autorizacao' || ! $chamado->orcamento_valor) {
                throw ValidationException::withMessages(['aceite' => ['Este orçamento não está disponível para autorização.']]);
            }

            if ($chamado->orcamento_validade?->isPast()) {
                throw ValidationException::withMessages(['aceite' => ['Este orçamento expirou. Solicite uma nova proposta à equipe.']]);
            }

            $chamado->update([
                'status' => 'autorizado',
                'autorizado_em' => now(),
                'autorizacao_ip_hash' => hash('sha256', (string) $request->ip()),
                'autorizacao_user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);

            $contrato = ContratoServico::withoutGlobalScope('empresa')->create([
                'empresa_id' => $chamado->empresa_id,
                'cliente_id' => $chamado->cliente_id,
                'portal_chamado_id' => $chamado->id,
                'status' => 'aceito',
                'titulo' => 'Termo de prestação de serviços',
                'objeto' => $chamado->orcamento_descricao ?: $chamado->descricao,
                'valor' => $chamado->orcamento_valor,
                'validade_orcamento' => $chamado->orcamento_validade,
                'aceite_nome' => $dados['nome_aceite'],
                'aceito_em' => now(),
                'aceite_ip_hash' => hash('sha256', (string) $request->ip()),
                'aceite_user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);
            $contrato->update(['numero' => 'CTR-' . str_pad((string) $contrato->id, 7, '0', STR_PAD_LEFT)]);
            $pdfService->gerar($contrato);

            return $chamado->fresh(['contrato']);
        });

        return response()->json([
            'message' => 'Orçamento autorizado. O termo de prestação de serviços foi gerado.',
            'data' => $this->publico($chamado),
        ]);
    }

    public function contrato(string $token)
    {
        $chamado = PortalChamado::withoutGlobalScope('empresa')
            ->with('contrato')
            ->where('token_publico', $token)
            ->firstOrFail();
        $contrato = $chamado->contrato;

        abort_unless($contrato?->arquivo_path && Storage::disk($contrato->arquivo_disk)->exists($contrato->arquivo_path), 404, 'Contrato ainda não disponível.');

        return Storage::disk($contrato->arquivo_disk)->download(
            $contrato->arquivo_path,
            "contrato-{$contrato->numero}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('os.view');
        $empresaId = (int) $request->user()->empresa_id;
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $chamados = PortalChamado::withoutGlobalScope('empresa')
            ->with(['cliente:id,razao_social,nome_fantasia,telefone,whatsapp', 'lead:id,uuid,titulo', 'contrato:id,portal_chamado_id,numero,status'])
            ->where('empresa_id', $empresaId)
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->input('status')))
            ->when($request->filled('busca'), function ($query) use ($request) {
                $busca = trim((string) $request->input('busca'));
                $query->where(function ($q) use ($busca) {
                    $q->where('protocolo', 'like', "%{$busca}%")
                        ->orWhere('nome', 'like', "%{$busca}%")
                        ->orWhere('telefone', 'like', "%{$busca}%")
                        ->orWhere('tipo_demanda', 'like', "%{$busca}%");
                });
            })
            ->latest()
            ->paginate($perPage);

        return response()->json($chamados);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorize('os.view');
        $chamado = PortalChamado::withoutGlobalScope('empresa')
            ->with(['cliente', 'lead', 'contrato'])
            ->where('empresa_id', $request->user()->empresa_id)
            ->findOrFail($id);

        return response()->json(['data' => $chamado]);
    }

    public function orcamento(Request $request, int $id): JsonResponse
    {
        $this->authorize('os.edit');
        $dados = $request->validate([
            'orcamento_titulo' => 'required|string|max:200',
            'orcamento_descricao' => 'required|string|min:15|max:6000',
            'orcamento_valor' => 'required|numeric|min:0.01|max:99999999',
            'orcamento_validade' => 'required|date|after_or_equal:today',
        ]);

        $chamado = PortalChamado::withoutGlobalScope('empresa')
            ->where('empresa_id', $request->user()->empresa_id)
            ->findOrFail($id);

        if (in_array($chamado->status, ['autorizado', 'cancelado'], true)) {
            return response()->json(['message' => 'Não é possível alterar o orçamento deste chamado no status atual.'], 422);
        }

        $chamado->update(array_merge($dados, [
            'status' => 'aguardando_autorizacao',
            'orcamento_enviado_em' => now(),
        ]));

        $link = rtrim($request->getSchemeAndHttpHost(), '/') . '/atendimento/' . $chamado->token_publico;
        $textoWhatsapp = rawurlencode("Olá, {$chamado->nome}! Sua proposta para o chamado {$chamado->protocolo} está disponível para consulta e autorização: {$link}");

        return response()->json([
            'message' => 'Orçamento preparado e liberado para autorização do cliente.',
            'data' => $chamado->fresh(['cliente', 'lead', 'contrato']),
            'link_cliente' => $link,
            'whatsapp_url' => 'https://wa.me/' . preg_replace('/\D/', '', (string) $chamado->whatsapp) . '?text=' . $textoWhatsapp,
        ]);
    }

    private function publico(PortalChamado $chamado): array
    {
        return [
            'protocolo' => $chamado->protocolo,
            'status' => $chamado->status,
            'tipo_demanda' => $chamado->tipo_demanda,
            'prioridade' => $chamado->prioridade,
            'descricao' => $chamado->descricao,
            'criado_em' => $chamado->created_at?->toIso8601String(),
            'orcamento' => $chamado->status === 'aguardando_autorizacao' || $chamado->status === 'autorizado' ? [
                'titulo' => $chamado->orcamento_titulo,
                'descricao' => $chamado->orcamento_descricao,
                'valor' => $chamado->orcamento_valor,
                'validade' => $chamado->orcamento_validade?->toDateString(),
            ] : null,
            'contrato' => $chamado->contrato ? [
                'numero' => $chamado->contrato->numero,
                'status' => $chamado->contrato->status,
                'aceito_em' => $chamado->contrato->aceito_em?->toIso8601String(),
                'disponivel' => (bool) $chamado->contrato->arquivo_path,
            ] : null,
        ];
    }

    private function empresa(): Empresa
    {
        $query = Empresa::query()->where('ativo', true);
        if (config('portal.empresa_id')) {
            $query->whereKey(config('portal.empresa_id'));
        }

        return $query->orderBy('id')->firstOrFail();
    }

    private function proximoProtocolo(int $empresaId): string
    {
        // Serializa a contagem por empresa para evitar dois protocolos iguais
        // quando dois cadastros públicos chegam ao mesmo tempo.
        Empresa::query()->whereKey($empresaId)->lockForUpdate()->firstOrFail();
        $sequencia = PortalChamado::withoutGlobalScope('empresa')
            ->where('empresa_id', $empresaId)
            ->lockForUpdate()
            ->count() + 1;

        return 'ATD-' . now()->format('Y') . '-' . str_pad((string) $sequencia, 6, '0', STR_PAD_LEFT);
    }
}
