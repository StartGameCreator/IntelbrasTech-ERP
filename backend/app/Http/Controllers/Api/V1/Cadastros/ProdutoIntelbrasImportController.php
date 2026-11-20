<?php

namespace App\Http\Controllers\Api\V1\Cadastros;

use App\Http\Controllers\Controller;
use App\Models\{
    Categoria,
    ImportacaoProduto,
    Produto,
    ProdutoPrecoUf,
    UnidadeMedida
};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProdutoIntelbrasImportController extends Controller
{
    public function iniciar(Request $request): JsonResponse
    {
        $this->authorize('produtos.create');

        $data = $request->validate([
            'arquivo' => ['required', 'string', 'max:255'],
            'uf_preferencial' => ['required', 'string', 'size:2'],
        ]);

        $importacao = ImportacaoProduto::create([
            'empresa_id' => auth()->user()->empresa_id,
            'usuario_id' => auth()->id(),
            'uuid' => (string) Str::uuid(),
            'arquivo' => $data['arquivo'],
            'status' => 'processando',
            'uf_preferencial' => strtoupper($data['uf_preferencial']),
            'detalhes' => [
                'fonte' => 'Tabela de Preços Intelbras',
                'iniciada_em' => now()->toIso8601String(),
            ],
        ]);

        return response()->json([
            'message' => 'Importação iniciada.',
            'importacao' => $importacao,
        ], 201);
    }

    public function produtos(Request $request, string $uuid): JsonResponse
    {
        $this->authorize('produtos.create');

        $data = $request->validate([
            'produtos' => ['required', 'array', 'min:1', 'max:100'],
            'produtos.*.codigo' => ['required', 'string', 'max:50'],
            'produtos.*.nome' => ['required', 'string', 'max:5000'],
            'produtos.*.descricao' => ['nullable', 'string', 'max:50000'],
            'produtos.*.resumo' => ['nullable', 'string', 'max:5000'],
            'produtos.*.bu' => ['nullable', 'string', 'max:100'],
            'produtos.*.segmento' => ['nullable', 'string', 'max:150'],
            'produtos.*.familia' => ['nullable', 'string', 'max:150'],
            'produtos.*.multiplo' => ['nullable', 'numeric', 'min:0'],
            'produtos.*.preco_referencia' => ['nullable', 'string', 'max:30'],
            'produtos.*.preco_venda' => ['nullable', 'numeric', 'min:0'],
            'produtos.*.preco_custo' => ['nullable', 'numeric', 'min:0'],
            'produtos.*.ipi' => ['nullable', 'numeric', 'min:0'],
            'produtos.*.icms' => ['nullable', 'numeric', 'min:0'],
            'produtos.*.bndes' => ['nullable', 'boolean'],
            'produtos.*.segmento_portfolio' => ['nullable', 'string', 'max:100'],
            'produtos.*.showroom' => ['nullable', 'boolean'],
            'produtos.*.item_consumo' => ['nullable', 'boolean'],
            'produtos.*.casa_inteligente' => ['nullable', 'boolean'],
            'produtos.*.observacoes' => ['nullable', 'string', 'max:50000'],
        ]);

        $empresaId = auth()->user()->empresa_id;
        $importacao = ImportacaoProduto::where('empresa_id', $empresaId)
            ->where('uuid', $uuid)->firstOrFail();

        $unidade = UnidadeMedida::firstOrCreate(
            ['empresa_id' => $empresaId, 'sigla' => 'UN'],
            ['descricao' => 'Unidade']
        );

        $criados = 0;
        $atualizados = 0;
        $erros = [];
        $cacheCategorias = [];

        foreach ($data['produtos'] as $item) {
            try {
                DB::transaction(function () use (
                    $item, $empresaId, $unidade, &$criados, &$atualizados, &$cacheCategorias
                ) {
                    $categoriaId = $this->categoriaFamilia(
                        $empresaId,
                        $item['bu'] ?? null,
                        $item['segmento'] ?? null,
                        $item['familia'] ?? null,
                        $cacheCategorias
                    );

                    $produto = Produto::withTrashed()
                        ->where('empresa_id', $empresaId)
                        ->where('codigo', $item['codigo'])
                        ->first();

                    $nome = trim((string) ($item['resumo'] ?: $item['nome']));
                    if ($nome === '') $nome = trim((string) $item['nome']);
                    $nomeCompleto = $nome;
                    $nome = mb_substr($nomeCompleto, 0, 200);

                    $payload = [
                        'empresa_id' => $empresaId,
                        'categoria_id' => $categoriaId,
                        'unidade_id' => $unidade->id,
                        'tipo' => 'simples',
                        'formato' => 'simples',
                        'condicao' => 'novo',
                        'codigo' => $item['codigo'],
                        'sku' => $item['codigo'],
                        'nome' => $nome,
                        'descricao' => $item['descricao'] ?: ($nomeCompleto !== $nome ? $nomeCompleto : $item['nome']),
                        'descricao_curta' => mb_substr((string) ($item['resumo'] ?: ''), 0, 5000),
                        'preco_custo' => (float) (($item['preco_venda'] ?? 0) > 0 ? $item['preco_venda'] : ($item['preco_custo'] ?? 0)),
                        'preco_venda' => 0,
                        'preco_venda_minimo' => 0,
                        'modo_preco_venda' => 'manual',
                        'markup_venda' => 0,
                        'icms_aliquota' => ((float) ($item['icms'] ?? 0)) * 100,
                        'ipi_aliquota' => ((float) ($item['ipi'] ?? 0)) * 100,
                        'controla_estoque' => true,
                        'ativo' => true,
                        'bu' => $item['bu'] ?? null,
                        'segmento' => $item['segmento'] ?? null,
                        'familia' => $item['familia'] ?? null,
                        'multiplo_venda' => max(0.001, (float) ($item['multiplo'] ?? 1)),
                        'preco_referencia_tipo' => $item['preco_referencia'] ?? null,
                        'disponivel_bndes' => (bool) ($item['bndes'] ?? false),
                        'segmento_portfolio' => $item['segmento_portfolio'] ?? null,
                        'showroom' => (bool) ($item['showroom'] ?? false),
                        'item_consumo' => (bool) ($item['item_consumo'] ?? false),
                        'casa_inteligente' => (bool) ($item['casa_inteligente'] ?? false),
                        'observacoes_importacao' => $item['observacoes'] ?? null,
                        'fonte_importacao' => 'Tabela de Preços Intelbras',
                        'importado_em' => now(),
                    ];

                    $payload['margem_lucro'] = 0;

                    if ($produto) {
                        if ($produto->trashed()) $produto->restore();
                        $produto->update($payload);
                        $atualizados++;
                    } else {
                        Produto::create($payload);
                        $criados++;
                    }
                });
            } catch (\Throwable $exception) {
                report($exception);
                $erros[] = [
                    'codigo' => $item['codigo'] ?? null,
                    'mensagem' => $exception->getMessage(),
                ];
            }
        }

        $importacao->increment('produtos_recebidos', count($data['produtos']));
        $importacao->increment('produtos_criados', $criados);
        $importacao->increment('produtos_atualizados', $atualizados);
        $importacao->increment('erros', count($erros));

        if ($erros) {
            $detalhes = $importacao->detalhes ?? [];
            $detalhes['ultimos_erros'] = array_slice($erros, -20);
            $importacao->update(['detalhes' => $detalhes]);
        }

        return response()->json([
            'message' => $erros
                ? 'Lote processado com alguns itens rejeitados.'
                : 'Lote de produtos processado.',
            'criados' => $criados,
            'atualizados' => $atualizados,
            'erros' => $erros,
        ], 200);
    }

    public function precos(Request $request, string $uuid): JsonResponse
    {
        $this->authorize('produtos.create');

        $data = $request->validate([
            'precos' => ['required', 'array', 'min:1', 'max:700'],
            'precos.*.codigo' => ['required', 'string', 'max:50'],
            'precos.*.tabela' => ['nullable', 'string', 'max:80'],
            'precos.*.uf_origem' => ['nullable', 'string', 'max:10'],
            'precos.*.uf_destino' => ['required', 'string', 'size:2'],
            'precos.*.ipi' => ['nullable', 'numeric'],
            'precos.*.icms' => ['nullable', 'numeric'],
            'precos.*.pv' => ['nullable', 'numeric'],
            'precos.*.custo' => ['nullable', 'numeric'],
            'precos.*.pp' => ['nullable', 'numeric'],
            'precos.*.psd' => ['nullable', 'numeric'],
            'precos.*.pscf' => ['nullable', 'numeric'],
            'precos.*.multiplo' => ['nullable', 'numeric'],
            'precos.*.preco_referencia' => ['nullable', 'string', 'max:30'],
        ]);

        $empresaId = auth()->user()->empresa_id;
        $importacao = ImportacaoProduto::where('empresa_id', $empresaId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $codigos = collect($data['precos'])
            ->pluck('codigo')
            ->unique()
            ->values();

        $produtoIds = Produto::where('empresa_id', $empresaId)
            ->whereIn('codigo', $codigos)
            ->pluck('id', 'codigo');

        $agora = now();
        $linhas = [];

        foreach ($data['precos'] as $preco) {
            $produtoId = $produtoIds[$preco['codigo']] ?? null;

            if (!$produtoId) {
                continue;
            }

            $linhas[] = [
                'empresa_id' => $empresaId,
                'produto_id' => $produtoId,
                'tabela' => $preco['tabela'] ?: 'Distribuição',
                'uf_origem' => $preco['uf_origem'] ?: null,
                'uf_destino' => strtoupper($preco['uf_destino']),
                'ipi' => (float) ($preco['ipi'] ?? 0),
                'icms' => (float) ($preco['icms'] ?? 0),
                'pv' => $preco['pv'] ?? null,
                'custo' => $preco['custo'] ?? null,
                'pp' => $preco['pp'] ?? null,
                'psd' => $preco['psd'] ?? null,
                'pscf' => $preco['pscf'] ?? null,
                'multiplo' => max(0.001, (float) ($preco['multiplo'] ?? 1)),
                'preco_referencia' => $preco['preco_referencia'] ?? null,
                'ativo' => true,
                'importado_em' => $agora,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        if ($linhas) {
            ProdutoPrecoUf::upsert(
                $linhas,
                ['empresa_id', 'produto_id', 'tabela', 'uf_origem', 'uf_destino'],
                [
                    'ipi', 'icms', 'pv', 'custo', 'pp', 'psd', 'pscf',
                    'multiplo', 'preco_referencia', 'ativo',
                    'importado_em', 'updated_at',
                ]
            );
        }

        $importacao->increment('precos_recebidos', count($data['precos']));
        $importacao->increment('precos_gravados', count($linhas));

        return response()->json([
            'message' => 'Lote de preços processado.',
            'gravados' => count($linhas),
        ]);
    }

    public function finalizar(string $uuid): JsonResponse
    {
        $this->authorize('produtos.create');

        $importacao = ImportacaoProduto::where(
            'empresa_id',
            auth()->user()->empresa_id
        )->where('uuid', $uuid)->firstOrFail();

        $importacao->update([
            'status' => 'concluida',
            'concluida_em' => now(),
            'detalhes' => array_merge($importacao->detalhes ?? [], [
                'concluida_em' => now()->toIso8601String(),
            ]),
        ]);

        return response()->json([
            'message' => 'Tabela Intelbras importada com sucesso.',
            'importacao' => $importacao->fresh(),
        ]);
    }

    private function categoriaFamilia(
        int $empresaId,
        ?string $bu,
        ?string $segmento,
        ?string $familia,
        array &$cache
    ): ?int {
        $nomes = array_values(array_filter([
            trim((string) $bu),
            trim((string) $segmento),
            trim((string) $familia),
        ]));

        if (!$nomes) {
            return null;
        }

        $paiId = null;
        $caminho = '';

        foreach ($nomes as $nivel => $nome) {
            $caminho .= '/' . mb_strtolower($nome);

            if (isset($cache[$caminho])) {
                $paiId = $cache[$caminho];
                continue;
            }

            $categoria = Categoria::firstOrCreate(
                [
                    'empresa_id' => $empresaId,
                    'categoria_pai_id' => $paiId,
                    'nome' => $nome,
                ],
                [
                    'slug' => Str::slug($nome),
                    'descricao' => match ($nivel) {
                        0 => 'Unidade de negócio importada da tabela Intelbras.',
                        1 => 'Segmento importado da tabela Intelbras.',
                        default => 'Família importada da tabela Intelbras.',
                    },
                    'ativo' => true,
                ]
            );

            $paiId = $categoria->id;
            $cache[$caminho] = $paiId;
        }

        return $paiId;
    }
}
