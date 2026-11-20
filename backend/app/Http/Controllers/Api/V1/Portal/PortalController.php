<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\{Empresa, Lead, Pipeline, PipelineEtapa, PortalSolicitacao, Produto};
use App\Services\Crm\CrmLeadIntakeService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PortalController extends Controller
{
    public function configuracao(): JsonResponse
    {
        $empresa = $this->empresa();
        $site = DB::table('portal_site_settings')->where('empresa_id', $empresa->id)->first();

        return response()->json([
            'empresa' => [
                'nome' => $empresa->nome_fantasia ?: $empresa->razao_social,
                'email' => $empresa->email,
                'telefone' => $empresa->telefone ?: $empresa->celular,
                'cidade' => $empresa->cidade,
                'uf' => $empresa->estado,
                'endereco' => trim(implode(', ', array_filter([$empresa->logradouro, $empresa->numero, $empresa->bairro]))),
                'logo_url' => $site?->logo_url ?: $empresa->logo_url,
            ],
            'canais' => [
                'whatsapp' => config('portal.whatsapp') ?: $empresa->celular ?: $empresa->telefone,
                'loja_url' => config('portal.loja_url'),
                'instagram_url' => config('portal.instagram_url'),
                'facebook_url' => config('portal.facebook_url'),
            ],
            'credenciais' => [
                'autorizacao_intelbras_confirmada' => (bool) config('portal.autorizacao_intelbras_confirmada'),
            ],
            'site' => [
                'nome' => $site?->site_nome,
                'titulo' => $site?->seo_titulo,
                'descricao' => $site?->seo_descricao,
                'palavras_chave' => $site?->seo_palavras_chave,
                'canonical_url' => $site?->seo_canonical_url,
                'og_image_url' => $site?->seo_og_image_url,
                'google_site_verification' => $site?->google_site_verification,
                'meta_pixel_id' => $site?->meta_pixel_id,
                'meta_pixel_ativo' => (bool) ($site?->meta_pixel_ativo ?? false),
                'tiktok_pixel_id' => $site?->tiktok_pixel_id,
                'tiktok_pixel_ativo' => (bool) ($site?->tiktok_pixel_ativo ?? false),
                'favicon_url' => $site?->favicon_url,
                'indexar_site' => (bool) ($site?->indexar_site ?? true),
            ],
        ]);
    }

    public function catalogo(): JsonResponse
    {
        $empresa = $this->empresa();
        $produtos = Produto::withoutGlobalScope('empresa')
            ->where('empresa_id', $empresa->id)
            ->where('ativo', true)
            ->where('destaque', true)
            ->with(['imagemPrincipal:id,produto_id,url,titulo', 'categoria:id,nome', 'marca:id,nome'])
            ->orderBy('nome')->limit(8)->get()
            ->map(fn (Produto $produto) => [
                'id' => $produto->uuid,
                'nome' => $produto->nome,
                'descricao' => $produto->descricao_curta,
                'preco' => $produto->preco_venda,
                'categoria' => $produto->categoria?->nome,
                'marca' => $produto->marca?->nome,
                'imagem' => $produto->imagemPrincipal->first()?->url,
                'link' => $produto->link_externo,
            ]);

        return response()->json(['data' => $produtos]);
    }

    public function solicitar(Request $request): JsonResponse
    {
        if ($request->filled('portal_guard') || $request->filled('website')) {
            return response()->json(['message' => 'Não foi possível validar o envio. Recarregue a página e tente novamente.'], 422);
        }

        $dados = $request->validate([
            'tipo' => 'required|in:contato,orcamento,solar,assistencia',
            'nome' => 'required|string|max:200',
            'email' => 'nullable|email|max:150|required_without:telefone',
            'telefone' => 'required|string|max:30',
            'cidade' => 'nullable|string|max:120',
            'mensagem' => 'nullable|string|max:3000',
            'consumo_kwh' => 'nullable|numeric|min:0|max:999999',
            'valor_conta' => 'nullable|numeric|min:0|max:9999999',
            'tipo_imovel' => 'nullable|string|max:80',
            'tipo_local' => 'nullable|string|max:80',
            'cep' => 'nullable|string|max:12',
            'uf' => 'nullable|string|size:2',
            'municipio_ibge' => 'nullable|string|max:10',
            'endereco' => 'nullable|string|max:250',
            'concessionaria' => 'nullable|string|max:120',
            'modalidade' => 'nullable|string|max:80',
            'ligacao' => 'nullable|string|max:50',
            'telhado' => 'nullable|string|max:80',
            'area_disponivel' => 'nullable|numeric|min:0|max:999999',
            'sistema_interesse' => 'nullable|string|max:80',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'potencia_estimada_kwp' => 'nullable|numeric|min:0|max:999999',
            'geracao_estimada_kwh' => 'nullable|numeric|min:0|max:99999999',
            'economia_estimada' => 'nullable|numeric|min:0|max:99999999',
            'investimento_min' => 'nullable|numeric|min:0|max:999999999',
            'investimento_max' => 'nullable|numeric|min:0|max:999999999',
            'payback_min' => 'nullable|numeric|min:0|max:100',
            'payback_max' => 'nullable|numeric|min:0|max:100',
            'aceita_marketing' => 'nullable|boolean',
            'equipamento' => 'nullable|string|max:200',
            'modelo' => 'nullable|string|max:150',
            'defeito' => 'nullable|string|max:2000',
            'consentimento' => 'accepted',
            'website' => 'nullable|string|max:0',
            'portal_guard' => 'nullable|string|max:0',
        ]);

        $empresa = $this->empresa();
        $pipeline = Pipeline::withoutGlobalScope('empresa')->where('empresa_id', $empresa->id)->where('ativo', true)->first();
        $etapa = $pipeline ? PipelineEtapa::where('pipeline_id', $pipeline->id)->orderBy('ordem')->first() : null;

        $rotulos = [
            'contato' => 'Contato pelo portal', 'orcamento' => 'Pedido de orçamento',
            'solar' => 'Pré-simulação de energia solar', 'assistencia' => 'Solicitação de assistência técnica',
        ];

        [$lead, $solicitacao] = DB::transaction(function () use ($dados, $empresa, $pipeline, $etapa, $rotulos, $request) {
            $lead = Lead::withoutGlobalScope('empresa')->create([
                'uuid' => (string) Str::uuid(), 'empresa_id' => $empresa->id,
                'pipeline_id' => $pipeline?->id, 'etapa_id' => $etapa?->id,
                'nome' => $dados['nome'], 'email' => $dados['email'] ?? null,
                'telefone' => $dados['telefone'], 'cidade' => $dados['cidade'] ?? null,
                'origem' => 'portal', 'titulo' => $rotulos[$dados['tipo']],
                'status' => Lead::STATUS_ABERTO, 'probabilidade' => $etapa?->probabilidade ?? 10,
                'prioridade' => in_array($dados['tipo'], ['orcamento', 'assistencia'], true) ? 'alta' : 'media',
                'score' => (! empty($dados['email']) ? 8 : 0) + (! empty($dados['telefone']) ? 8 : 0),
                'entrou_etapa_em' => now(),
                'tags' => 'portal,' . $dados['tipo'], 'dados_extras' => $dados,
            ]);

            $solicitacao = PortalSolicitacao::create([
                'uuid' => (string) Str::uuid(), 'empresa_id' => $empresa->id, 'lead_id' => $lead->id,
                'tipo' => $dados['tipo'], 'nome' => $dados['nome'], 'email' => $dados['email'] ?? null,
                'telefone' => $dados['telefone'], 'cidade' => $dados['cidade'] ?? null,
                'dados' => collect($dados)->except(['consentimento', 'website', 'portal_guard'])->all(),
                'ip_hash' => hash('sha256', (string) $request->ip()),
            ]);

            app(CrmLeadIntakeService::class)->registrar($lead, null, 'Lead recebido pelo portal');

            return [$lead, $solicitacao];
        });

        return response()->json([
            'message' => 'Recebemos sua solicitação. Nossa equipe entrará em contato.',
            'protocolo' => $solicitacao->uuid,
            'lead_id' => $lead->uuid,
        ], 201);
    }

    private function empresa(): Empresa
    {
        $query = Empresa::query()->where('ativo', true);
        if (config('portal.empresa_id')) {
            $query->whereKey(config('portal.empresa_id'));
        }
        return $query->orderBy('id')->firstOrFail();
    }
}
