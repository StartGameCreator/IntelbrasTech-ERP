<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Controller;
use App\Models\{Empresa, Lead, Pipeline, PipelineEtapa, PortalSolicitacao, Produto};
use App\Services\Crm\CrmLeadIntakeService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
                'google_analytics_id' => $site?->google_analytics_id,
                'google_analytics_ativo' => (bool) ($site?->google_analytics_ativo ?? false),
                'google_tag_manager_id' => $site?->google_tag_manager_id,
                'google_tag_manager_ativo' => (bool) ($site?->google_tag_manager_ativo ?? false),
                'microsoft_clarity_id' => $site?->microsoft_clarity_id,
                'microsoft_clarity_ativo' => (bool) ($site?->microsoft_clarity_ativo ?? false),
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

    /**
     * Redes sociais não executam o JavaScript da SPA. Esta URL estável resolve
     * para a imagem configurada no ERP antes de o cartão ser montado.
     */
    public function imagemCompartilhamento()
    {
        $empresa = $this->empresa();
        $site = DB::table('portal_site_settings')->where('empresa_id', $empresa->id)->first();
        $imagem = $site?->seo_og_image_url ?: $site?->logo_url ?: $empresa->logo_url;

        // Os rastreadores do WhatsApp e de redes sociais são mais confiáveis
        // quando recebem o binário da imagem em vez de um redirecionamento.
        // Arquivos do próprio portal (armazenamento público) são enviados aqui
        // diretamente; URLs externas permanecem compatíveis via redirecionamento.
        $caminhoUrl = $imagem ? parse_url($imagem, PHP_URL_PATH) : null;
        if ($caminhoUrl && Str::startsWith($caminhoUrl, '/storage/')) {
            $arquivo = Storage::disk('public')->path(ltrim(Str::after($caminhoUrl, '/storage/'), '/'));

            if (is_file($arquivo)) {
                $cartao = $this->criarCartaoCompartilhamento($arquivo);

                if ($cartao !== null) {
                    return response($cartao, 200, [
                        'Content-Type' => 'image/png',
                        'Cache-Control' => 'public, max-age=300',
                        'Content-Disposition' => 'inline; filename="intelbrastech-compartilhamento.png"',
                    ]);
                }

                return response()->file($arquivo, [
                    'Cache-Control' => 'public, max-age=300',
                    'Content-Disposition' => 'inline',
                ]);
            }
        }

        if ($imagem && filter_var($imagem, FILTER_VALIDATE_URL)) {
            return redirect()->away($imagem, 302, ['Cache-Control' => 'public, max-age=300']);
        }

        return redirect('/pwa-icon.svg', 302, ['Cache-Control' => 'public, max-age=300']);
    }

    /**
     * Cria um cartão Open Graph 1200x630 a partir da logo da empresa.
     * Esse formato é o mais aceito por WhatsApp, Meta, LinkedIn e X.
     */
    private function criarCartaoCompartilhamento(string $arquivo): ?string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagecreatefromstring')) {
            return null;
        }

        $conteudo = @file_get_contents($arquivo);
        $logo = $conteudo ? @imagecreatefromstring($conteudo) : false;

        if ($logo === false) {
            return null;
        }

        $cartao = imagecreatetruecolor(1200, 630);
        $verde = imagecolorallocate($cartao, 0, 163, 53);
        $verdeEscuro = imagecolorallocate($cartao, 0, 120, 41);
        $fundo = imagecolorallocate($cartao, 246, 251, 247);
        $moldura = imagecolorallocate($cartao, 229, 241, 233);
        $branco = imagecolorallocate($cartao, 255, 255, 255);

        imagefill($cartao, 0, 0, $fundo);
        imagefilledrectangle($cartao, 0, 0, 475, 629, $verde);
        imagefilledrectangle($cartao, 0, 560, 475, 629, $verdeEscuro);
        imagefilledrectangle($cartao, 475, 0, 1199, 15, $verde);
        imagefilledrectangle($cartao, 620, 65, 1125, 565, $moldura);
        imagefilledrectangle($cartao, 642, 87, 1103, 543, $branco);

        $larguraOriginal = imagesx($logo);
        $alturaOriginal = imagesy($logo);
        $escala = min(390 / $larguraOriginal, 390 / $alturaOriginal);
        $largura = max(1, (int) round($larguraOriginal * $escala));
        $altura = max(1, (int) round($alturaOriginal * $escala));
        $x = 872 - (int) floor($largura / 2);
        $y = 315 - (int) floor($altura / 2);

        imagealphablending($cartao, true);
        imagecopyresampled($cartao, $logo, $x, $y, 0, 0, $largura, $altura, $larguraOriginal, $alturaOriginal);

        ob_start();
        imagepng($cartao, null, 6);
        $png = ob_get_clean();

        imagedestroy($logo);
        imagedestroy($cartao);

        return is_string($png) ? $png : null;
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
