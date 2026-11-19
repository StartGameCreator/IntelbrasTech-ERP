<?php

namespace App\Services\Produtos;

use App\Models\ApiIntegration;
use App\Models\Produto;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProductAiService
{
    public function gerar(Produto $produto, string $acao, ?string $canal = null): array
    {
        $config = $this->resolverConfiguracao($produto->empresa_id);

        if ($config['provider'] === 'openai' && $config['api_key']) {
            try {
                return $this->openAi($produto, $acao, $canal, $config);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($config['provider'] === 'gemini' && $config['api_key']) {
            try {
                return $this->gemini($produto, $acao, $canal, $config);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return [
            'fonte' => 'assistente_local',
            'conteudo' => $this->local($produto, $acao, $canal),
            'aviso' => $config['api_key']
                ? 'A IA externa não respondeu. Foi usado o assistente local.'
                : 'Nenhuma chave de IA foi configurada. Foi usado o assistente local.',
        ];
    }

    private function resolverConfiguracao(?int $empresaId): array
    {
        $integration = null;

        if ($empresaId) {
            $integration = ApiIntegration::query()
                ->where('empresa_id', $empresaId)
                ->where('enabled', true)
                ->whereIn('provider', ['openai', 'gemini', 'google_gemini'])
                ->orderByDesc('is_primary')
                ->first();
        }

        $credentials = $integration?->getCredentials() ?? [];
        $settings = $integration?->settings ?? [];

        $provider = match ($integration?->provider) {
            'gemini', 'google_gemini' => 'gemini',
            'openai' => 'openai',
            default => strtolower((string) config('product_ai.provider', 'local')),
        };

        $apiKey = trim((string) (
            $credentials['api_key']
            ?? $credentials['token']
            ?? ($provider === 'openai'
                ? config('product_ai.openai.api_key')
                : config('product_ai.gemini.api_key'))
        ));

        $model = (string) (
            $settings['model']
            ?? ($provider === 'openai'
                ? config('product_ai.openai.model')
                : config('product_ai.gemini.model'))
        );

        return [
            'provider' => $provider,
            'api_key' => $apiKey,
            'model' => $model,
            'timeout' => max(15, min(120, (int) ($settings['timeout']
                ?? config('product_ai.timeout', 60)))),
        ];
    }

    private function openAi(
        Produto $produto,
        string $acao,
        ?string $canal,
        array $config
    ): array {
        $response = Http::withToken($config['api_key'])
            ->acceptJson()
            ->timeout($config['timeout'])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $config['model'] ?: 'gpt-4.1-mini',
                'temperature' => 0.4,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->userPrompt($produto, $acao, $canal),
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'OpenAI retornou HTTP ' . $response->status() . ': '
                . ($response->json('error.message') ?: 'erro não informado')
            );
        }

        $text = (string) $response->json('choices.0.message.content');
        $decoded = json_decode($text, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('A OpenAI não retornou JSON válido.');
        }

        return [
            'fonte' => 'openai',
            'conteudo' => $this->extrairConteudo($decoded, $acao),
            'aviso' => null,
        ];
    }

    private function gemini(
        Produto $produto,
        string $acao,
        ?string $canal,
        array $config
    ): array {
        $model = $config['model'] ?: 'gemini-2.0-flash';

        $response = Http::acceptJson()
            ->timeout($config['timeout'])
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                [
                    'contents' => [[
                        'parts' => [[
                            'text' => $this->systemPrompt()
                                . "\n\n"
                                . $this->userPrompt($produto, $acao, $canal),
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'responseMimeType' => 'application/json',
                    ],
                ] + [
                    'key' => $config['api_key'],
                ]
            );

        if ($response->failed()) {
            throw new RuntimeException(
                'Gemini retornou HTTP ' . $response->status() . ': '
                . ($response->json('error.message') ?: 'erro não informado')
            );
        }

        $text = (string) $response->json('candidates.0.content.parts.0.text');
        $decoded = json_decode($text, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('O Gemini não retornou JSON válido.');
        }

        return [
            'fonte' => 'gemini',
            'conteudo' => $this->extrairConteudo($decoded, $acao),
            'aviso' => null,
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Você é o assistente de catálogo do IntelbrasTech ERP.
Escreva em português do Brasil.
Nunca invente certificações, garantias, compatibilidades, dimensões, recursos técnicos,
NCM, CEST, GTIN, voltagem ou especificações que não estejam nos dados fornecidos.
Quando faltarem dados, omita a informação.
Evite promessas absolutas.
Retorne somente JSON válido, sem markdown.
PROMPT;
    }

    private function userPrompt(
        Produto $produto,
        string $acao,
        ?string $canal
    ): string {
        $dados = [
            'acao' => $acao,
            'canal' => $canal,
            'produto' => [
                'nome' => $produto->nome,
                'codigo' => $produto->codigo,
                'sku' => $produto->sku,
                'marca' => $produto->marca?->nome,
                'categoria' => $produto->categoria?->nome,
                'bu' => $produto->bu,
                'segmento' => $produto->segmento,
                'familia' => $produto->familia,
                'descricao_atual' => $produto->descricao,
                'descricao_curta' => $produto->descricao_curta,
                'ncm' => $produto->ncm,
                'cest' => $produto->cest,
                'ean' => $produto->ean,
                'peso' => $produto->peso,
                'largura' => $produto->largura,
                'altura' => $produto->altura,
                'comprimento' => $produto->comprimento,
                'preco_venda' => $produto->preco_venda,
                'tags' => $produto->tags,
            ],
        ];

        $formatos = [
            'titulo' => '{"conteudo":"título com no máximo 60 caracteres"}',
            'descricao' => '{"conteudo":"descrição comercial em HTML simples"}',
            'descricao_tecnica' => '{"conteudo":"ficha técnica em HTML simples"}',
            'tags' => '{"conteudo":["tag 1","tag 2"]}',
            'seo' => '{"conteudo":{"titulo":"...","descricao":"...","palavras_chave":["..."]}}',
            'marketplace' => '{"conteudo":{"titulo":"...","conteudo":"descrição adequada ao canal"}}',
        ];

        return 'Gere o conteúdo solicitado usando somente os dados abaixo.'
            . "\nFormato obrigatório: " . ($formatos[$acao] ?? '{"conteudo":null}')
            . "\nDados: "
            . json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function extrairConteudo(array $decoded, string $acao): mixed
    {
        $conteudo = $decoded['conteudo'] ?? $decoded;

        if ($acao === 'tags' && is_string($conteudo)) {
            return array_values(array_filter(array_map(
                'trim',
                preg_split('/[,;]+/', $conteudo)
            )));
        }

        return $conteudo;
    }

    private function local(
        Produto $produto,
        string $acao,
        ?string $canal
    ): mixed {
        $marca = $produto->marca?->nome ?: 'Intelbras';
        $categoria = $produto->categoria?->nome ?: ($produto->familia ?: 'produto');
        $nome = trim($produto->nome);
        $sku = $produto->sku ?: $produto->codigo;

        $tags = array_values(array_unique(array_filter([
            $marca,
            $categoria,
            $produto->segmento,
            $produto->familia,
            $produto->codigo,
            'produto original',
            'tecnologia',
        ])));

        $descricao = "<h2>{$nome}</h2>"
            . "<p>Produto {$marca} indicado para aplicações em {$categoria}.</p>"
            . "<h3>Destaques</h3>"
            . "<ul><li>Produto novo e original</li>"
            . "<li>Cadastro preparado para estoque, nota fiscal e canais de venda</li>"
            . "<li>Consulte disponibilidade e condições comerciais</li></ul>";

        return match ($acao) {
            'titulo' => mb_substr("{$nome} {$marca} {$sku}", 0, 60),
            'descricao' => $descricao,
            'descricao_tecnica' => "<h3>Ficha técnica</h3><table><tbody>"
                . "<tr><td>Produto</td><td>{$nome}</td></tr>"
                . "<tr><td>Marca</td><td>{$marca}</td></tr>"
                . "<tr><td>SKU</td><td>{$sku}</td></tr>"
                . ($produto->ncm ? "<tr><td>NCM</td><td>{$produto->ncm}</td></tr>" : '')
                . ($produto->ean ? "<tr><td>GTIN/EAN</td><td>{$produto->ean}</td></tr>" : '')
                . "</tbody></table>",
            'tags' => $tags,
            'seo' => [
                'titulo' => mb_substr("{$nome} {$marca} | IntelbrasTech", 0, 60),
                'descricao' => mb_substr(
                    "Conheça {$nome} {$marca}. Produto original e preparado para estoque e canais de venda.",
                    0,
                    160
                ),
                'palavras_chave' => $tags,
            ],
            'marketplace' => [
                'titulo' => mb_substr("{$nome} {$marca} Novo Original", 0, 60),
                'conteudo' => strip_tags($descricao),
                'canal' => $canal,
            ],
            default => null,
        };
    }
}
