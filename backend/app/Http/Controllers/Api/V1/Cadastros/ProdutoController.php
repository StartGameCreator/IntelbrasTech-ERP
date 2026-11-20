<?php

namespace App\Http\Controllers\Api\V1\Cadastros;


use App\Services\Produtos\ProductAiService;
use App\Http\Controllers\Controller;
use App\Models\{Produto, ProdutoImagem, ProdutoVariacao, ProdutoComposicao, ProdutoCodigo, ProdutoListaPreco, ProdutoDocumento, ProdutoConteudoIa, ProdutoCanal, TabelaPreco, Estoque, PedidoItem};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class ProdutoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('produtos.view');

        $q = Produto::with([
            'categoria:id,nome','marca:id,nome','unidade:id,sigla',
            'imagens' => fn ($q) => $q->where('principal', true)->limit(1),
        ])
            ->when($request->busca, fn($q, $v) => $q->busca($v))
            ->when($request->categoria_id, fn($q, $v) => $q->porCategoria($v))
            ->when($request->tipo, fn($q, $v) => $q->tipo($v))
            ->when($request->ativo !== null, fn($q) => $q->where('ativo', $request->boolean('ativo')))
            ->when($request->estoque_baixo, fn($q) => $q->estoqueBaixo())
            ->orderBy($request->get('order_by', 'nome'), $request->get('order', 'asc'));

        return response()->json($q->paginate(min((int) $request->get('per_page', 15), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('produtos.create');
        $dados = $this->validarDados($request);
        $dados['empresa_id'] = auth()->user()->empresa_id;
        $dados = $this->aplicarFormacaoPreco($dados);
        $dados['margem_lucro'] = (new Produto())->calcularMargem(
            (float) ($dados['preco_custo'] ?? 0),
            (float) ($dados['preco_venda'] ?? 0)
        );
        $produto = Produto::create($dados);

        return response()->json([
            'message' => 'Produto cadastrado com sucesso.',
            'produto' => $produto->fresh(['categoria','marca','unidade','imagens']),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorize('produtos.view');

        return response()->json(
            Produto::with([
                'categoria:id,nome','marca:id,nome','unidade:id,sigla',
                'fornecedor:id,razao_social,nome_fantasia','imagens',
                'variacoes','composicao.produtoFilho:id,nome,sku,preco_venda',
                'estoque.deposito:id,nome','fornecedoresProduto','canais','codigos','listasPreco.tabela','documentos',
            ])->findOrFail($id)
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('produtos.edit');
        $produto = Produto::findOrFail($id);
        $dados = $this->validarDados($request, true);
        $dados = $this->aplicarFormacaoPreco($dados, $produto);
        $dados['margem_lucro'] = $produto->calcularMargem(
            (float) ($dados['preco_custo'] ?? $produto->preco_custo),
            (float) ($dados['preco_venda'] ?? $produto->preco_venda)
        );
        $produto->update($dados);

        return response()->json([
            'message' => 'Produto atualizado com sucesso.',
            'produto' => $produto->fresh(['categoria','marca','unidade','imagens']),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('produtos.delete');
        $produto = Produto::findOrFail($id);

        if ($produto->estoque()->sum('quantidade') > 0) {
            return response()->json(['message' => 'Produto possui saldo em estoque.'], 422);
        }

        $produto->delete();
        return response()->json(['message' => 'Produto excluído com sucesso.']);
    }

    public function estoque(int $id): JsonResponse
    {
        $this->authorize('produtos.view');
        $produto = Produto::findOrFail($id);
        $porDeposito = Estoque::with('deposito:id,nome')->where('produto_id', $id)->get();

        return response()->json([
            'produto_id' => $produto->id,
            'controla_estoque' => $produto->controla_estoque,
            'estoque_minimo' => (float) $produto->estoque_minimo,
            'estoque_maximo' => (float) $produto->estoque_maximo,
            'total_disponivel' => (float) $porDeposito->sum('quantidade_disponivel'),
            'abaixo_minimo' => $produto->abaixo_minimo,
            'por_deposito' => $porDeposito,
        ]);
    }

    public function uploadImagem(Request $request, int $id): JsonResponse
    {
        $this->authorize('produtos.edit');
        $produto = Produto::findOrFail($id);

        $request->validate([
            'imagem' => 'nullable|image|max:10240',
            'imagens' => 'nullable|array|max:20',
            'imagens.*' => 'image|max:10240',
            'principal' => 'nullable|boolean',
            'titulo' => 'nullable|string|max:200',
            'alt_text' => 'nullable|string|max:250',
        ]);

        $arquivos = [];

        if ($request->hasFile('imagem')) {
            $arquivos[] = $request->file('imagem');
        }

        foreach ($request->file('imagens', []) as $arquivo) {
            $arquivos[] = $arquivo;
        }

        if (!$arquivos) {
            return response()->json(['message' => 'Nenhuma imagem foi recebida.'], 422);
        }

        $criadas = [];

        DB::transaction(function () use ($request, $produto, $arquivos, &$criadas) {
            $primeiraDoProduto = $produto->imagens()->count() === 0;
            $definirPrincipal = $request->boolean('principal') || $primeiraDoProduto;

            if ($definirPrincipal) {
                $produto->imagens()->update(['principal' => false]);
            }

            $ordem = (int) $produto->imagens()->max('ordem');

            foreach ($arquivos as $indice => $file) {
                $caminho = $file->store("produtos/{$produto->id}", 'public');
                [$largura, $altura] = @getimagesize($file->getRealPath()) ?: [null, null];

                $imagem = $produto->imagens()->create([
                    'url' => Storage::disk('public')->url($caminho),
                    'arquivo' => $caminho,
                    'origem' => 'upload',
                    'titulo' => $request->get('titulo'),
                    'alt_text' => $request->get('alt_text') ?: $produto->nome,
                    'principal' => $definirPrincipal && $indice === 0,
                    'ordem' => ++$ordem,
                    'mime_type' => $file->getMimeType(),
                    'extensao' => strtolower($file->getClientOriginalExtension()),
                    'largura' => $largura,
                    'altura' => $altura,
                    'tamanho_bytes' => $file->getSize(),
                    'status' => 'ativa',
                ]);

                $criadas[] = $imagem;
            }
        });

        return response()->json([
            'message' => count($criadas) > 1 ? 'Imagens enviadas.' : 'Imagem enviada.',
            'imagens' => $criadas,
        ], 201);
    }

    public function adicionarImagemUrl(Request $request, int $id): JsonResponse
    {
        $this->authorize('produtos.edit');
        $produto = Produto::findOrFail($id);

        $data = $request->validate([
            'url' => 'required|url|max:1000',
            'titulo' => 'nullable|string|max:200',
            'alt_text' => 'nullable|string|max:250',
            'principal' => 'nullable|boolean',
        ]);

        if ($request->boolean('principal') || $produto->imagens()->count() === 0) {
            $produto->imagens()->update(['principal' => false]);
        }

        $ext = strtolower(pathinfo(parse_url($data['url'], PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        $imagem = $produto->imagens()->create([
            'url' => $data['url'],
            'origem' => 'url',
            'titulo' => $data['titulo'] ?? null,
            'alt_text' => $data['alt_text'] ?? null,
            'principal' => $request->boolean('principal') || $produto->imagens()->count() === 0,
            'ordem' => (int) $produto->imagens()->max('ordem') + 1,
            'extensao' => $ext ?: null,
            'status' => 'ativa',
        ]);

        return response()->json(['message' => 'Imagem por URL adicionada.', 'imagem' => $imagem], 201);
    }

    public function definirImagemPrincipal(int $id, int $imgId): JsonResponse
    {
        $this->authorize('produtos.edit');
        $produto = Produto::findOrFail($id);
        $imagem = $produto->imagens()->findOrFail($imgId);

        DB::transaction(function () use ($produto, $imagem) {
            $produto->imagens()->update(['principal' => false]);
            $imagem->update(['principal' => true]);
        });

        return response()->json(['message' => 'Imagem principal definida.']);
    }

    public function ordenarImagens(Request $request, int $id): JsonResponse
    {
        $this->authorize('produtos.edit');
        $produto = Produto::findOrFail($id);
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        foreach ($data['ids'] as $ordem => $imgId) {
            $produto->imagens()->whereKey($imgId)->update(['ordem' => $ordem]);
        }

        return response()->json(['message' => 'Ordem das imagens atualizada.']);
    }

    public function deletarImagem(int $id, int $imgId): JsonResponse
    {
        $this->authorize('produtos.edit');
        $imagem = ProdutoImagem::where('produto_id', $id)->findOrFail($imgId);

        if ($imagem->origem === 'upload' && $imagem->arquivo) {
            Storage::disk('public')->delete($imagem->arquivo);
        }

        $eraPrincipal = $imagem->principal;
        $imagem->delete();

        if ($eraPrincipal) {
            ProdutoImagem::where('produto_id', $id)->orderBy('ordem')->first()?->update(['principal' => true]);
        }

        return response()->json(['message' => 'Imagem removida.']);
    }

        public function assistenteConteudo(
        Request $request,
        int $id,
        ProductAiService $aiService
    ): JsonResponse {
        $this->authorize('produtos.edit');

        $produto = Produto::with([
            'marca:id,nome',
            'categoria:id,nome',
        ])->findOrFail($id);

        $data = $request->validate([
            'acao' => 'required|in:titulo,descricao,descricao_tecnica,tags,seo,marketplace',
            'canal' => 'nullable|in:mercado_livre,shopee,amazon,magalu,nuvemshop,loja_propria',
        ]);

        $acao = $data['acao'];
        $canal = $data['canal'] ?? null;
        $resultado = $aiService->gerar($produto, $acao, $canal);
        $conteudo = $resultado['conteudo'];

        $registro = ProdutoConteudoIa::create([
            'produto_id' => $produto->id,
            'usuario_id' => auth()->id(),
            'tipo' => $acao,
            'canal' => $canal,
            'fonte' => $resultado['fonte'],
            'titulo' => is_array($conteudo)
                ? ($conteudo['titulo'] ?? null)
                : ($acao === 'titulo' ? (string) $conteudo : null),
            'conteudo' => is_array($conteudo)
                ? json_encode(
                    $conteudo,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
                : (string) $conteudo,
            'metadados' => [
                'provider' => $resultado['fonte'],
                'aviso' => $resultado['aviso'] ?? null,
            ],
        ]);

        if ($acao === 'marketplace' && $canal && is_array($conteudo)) {
            ProdutoCanal::updateOrCreate(
                [
                    'produto_id' => $produto->id,
                    'canal' => $canal,
                ],
                [
                    'titulo' => $conteudo['titulo'] ?? null,
                    'descricao' => $conteudo['conteudo'] ?? null,
                    'status' => 'rascunho',
                    'preco' => $produto->preco_venda,
                ]
            );
        }

        $produto->update([
            'conteudo_ia_status' => 'rascunho_gerado',
            'conteudo_ia_em' => now(),
            'ia_ultima_acao' => $acao,
            'ia_ultima_fonte' => $resultado['fonte'],
        ]);

        return response()->json([
            'message' => $resultado['aviso']
                ?: 'Conteúdo gerado por IA e salvo no histórico.',
            'acao' => $acao,
            'canal' => $canal,
            'conteudo' => $conteudo,
            'registro_id' => $registro->id,
            'modo' => $resultado['fonte'],
        ]);
    }

    public function historicoConteudoIa(int $id): JsonResponse
    {
        $this->authorize('produtos.view');

        $historico = ProdutoConteudoIa::where('produto_id', $id)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json(['data' => $historico]);
    }

    public function aplicarConteudoIa(Request $request, int $id, int $conteudoId): JsonResponse
    {
        $this->authorize('produtos.edit');

        $produto = Produto::findOrFail($id);
        $registro = ProdutoConteudoIa::where('produto_id', $id)->findOrFail($conteudoId);

        $raw = $registro->conteudo;
        $decoded = json_decode((string) $raw, true);

        switch ($registro->tipo) {
            case 'titulo':
                $produto->nome = (string) $raw;
                break;
            case 'descricao':
                $produto->descricao = (string) $raw;
                break;
            case 'descricao_tecnica':
                $produto->descricao_complementar = (string) $raw;
                break;
            case 'tags':
                $produto->tags = is_array($decoded) ? $decoded : [];
                break;
            case 'seo':
                $produto->seo_titulo = $decoded['titulo'] ?? null;
                $produto->seo_descricao = $decoded['descricao'] ?? null;
                $produto->seo_palavras_chave = $decoded['palavras_chave'] ?? [];
                break;
            case 'marketplace':
                if ($registro->canal && is_array($decoded)) {
                    ProdutoCanal::updateOrCreate(
                        ['produto_id' => $produto->id, 'canal' => $registro->canal],
                        [
                            'titulo' => $decoded['titulo'] ?? null,
                            'descricao' => $decoded['conteudo'] ?? null,
                            'status' => 'rascunho',
                            'preco' => $produto->preco_venda,
                        ]
                    );
                }
                break;
        }

        $produto->save();
        $registro->update(['aplicado' => true]);

        return response()->json([
            'message' => 'Conteúdo aplicado ao cadastro.',
            'produto' => $produto->fresh(['canais']),
        ]);
    }

    public function criarVariacao(Request $request, int $id): JsonResponse
    {
        $this->authorize('produtos.edit');
        $produto = Produto::findOrFail($id);
        $dados = $request->validate([
            'sku'=>'nullable|string|max:100','codigo_barras'=>'nullable|string|max:50',
            'preco_venda'=>'nullable|numeric|min:0','preco_custo'=>'nullable|numeric|min:0',
            'peso'=>'nullable|numeric|min:0','ativo'=>'nullable|boolean',
        ]);
        return response()->json([
            'message'=>'Variação criada.',
            'variacao'=>$produto->variacoes()->create($dados),
        ], 201);
    }

    public function salvarCodigos(Request $request, int $id): JsonResponse
    {
        $this->authorize('produtos.edit');
        $produto = Produto::findOrFail($id);

        $data = $request->validate([
            'codigos' => 'required|array|max:100',
            'codigos.*.tipo' => 'required|in:dun,ean,gtin,sku,interno,outro',
            'codigos.*.codigo' => 'required|string|max:100',
            'codigos.*.quantidade' => 'nullable|numeric|min:0.001',
            'codigos.*.principal' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($produto, $data) {
            $produto->codigos()->delete();

            foreach ($data['codigos'] as $item) {
                $produto->codigos()->create([
                    'tipo' => $item['tipo'],
                    'codigo' => trim($item['codigo']),
                    'quantidade' => $item['quantidade'] ?? 1,
                    'principal' => (bool) ($item['principal'] ?? false),
                ]);
            }
        });

        return response()->json([
            'message' => 'Códigos adicionais atualizados.',
            'data' => $produto->codigos()->get(),
        ]);
    }

    public function salvarListasPreco(Request $request, int $id): JsonResponse
    {
        $this->authorize('produtos.edit');
        $produto = Produto::findOrFail($id);

        $data = $request->validate([
            'listas' => 'required|array|max:100',
            'listas.*.tabela_preco_id' => 'required|exists:tabelas_preco,id',
            'listas.*.preco' => 'required|numeric|min:0',
            'listas.*.preco_minimo' => 'nullable|numeric|min:0',
            'listas.*.ativo' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($produto, $data) {
            $ids = [];

            foreach ($data['listas'] as $item) {
                $registro = ProdutoListaPreco::updateOrCreate(
                    [
                        'produto_id' => $produto->id,
                        'tabela_preco_id' => $item['tabela_preco_id'],
                    ],
                    [
                        'preco' => $item['preco'],
                        'preco_minimo' => $item['preco_minimo'] ?? null,
                        'ativo' => (bool) ($item['ativo'] ?? true),
                    ]
                );

                $ids[] = $registro->id;
            }

            ProdutoListaPreco::where('produto_id', $produto->id)
                ->whereNotIn('id', $ids)
                ->delete();
        });

        return response()->json([
            'message' => 'Listas de preço atualizadas.',
            'data' => ProdutoListaPreco::with('tabela')
                ->where('produto_id', $produto->id)
                ->get(),
        ]);
    }

    public function salvarVariacoes(Request $request, int $id): JsonResponse
    {
        $this->authorize('produtos.edit');
        $produto = Produto::findOrFail($id);

        $data = $request->validate([
            'variacoes' => 'required|array|max:300',
            'variacoes.*.id' => 'nullable|integer',
            'variacoes.*.nome' => 'nullable|string|max:150',
            'variacoes.*.sku' => 'nullable|string|max:100',
            'variacoes.*.codigo_barras' => 'nullable|string|max:50',
            'variacoes.*.preco_venda' => 'nullable|numeric|min:0',
            'variacoes.*.preco_custo' => 'nullable|numeric|min:0',
            'variacoes.*.peso' => 'nullable|numeric|min:0',
            'variacoes.*.atributos' => 'nullable|array',
            'variacoes.*.estoque_minimo' => 'nullable|numeric|min:0',
            'variacoes.*.ativo' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($produto, $data) {
            $ids = [];

            foreach ($data['variacoes'] as $ordem => $item) {
                $variacao = $produto->variacoes()->updateOrCreate(
                    ['id' => $item['id'] ?? null],
                    [
                        'nome' => $item['nome'] ?? null,
                        'sku' => $item['sku'] ?? null,
                        'codigo_barras' => $item['codigo_barras'] ?? null,
                        'preco_venda' => $item['preco_venda'] ?? $produto->preco_venda,
                        'preco_custo' => $item['preco_custo'] ?? $produto->preco_custo,
                        'peso' => $item['peso'] ?? $produto->peso,
                        'atributos' => $item['atributos'] ?? [],
                        'estoque_minimo' => $item['estoque_minimo'] ?? 0,
                        'ativo' => (bool) ($item['ativo'] ?? true),
                        'ordem' => $ordem,
                    ]
                );

                $ids[] = $variacao->id;
            }

            ProdutoVariacao::where('produto_id', $produto->id)
                ->whereNotIn('id', $ids)
                ->delete();
        });

        return response()->json([
            'message' => 'Variações atualizadas.',
            'data' => ProdutoVariacao::where('produto_id', $produto->id)
                ->orderBy('ordem')
                ->get(),
        ]);
    }

    public function salvarComposicao(Request $request, int $id): JsonResponse
    {
        $this->authorize('produtos.edit');
        $produto = Produto::findOrFail($id);

        $data = $request->validate([
            'itens' => 'required|array|max:300',
            'itens.*.produto_id' => 'required|exists:produtos,id',
            'itens.*.quantidade' => 'required|numeric|min:0.001',
        ]);

        DB::transaction(function () use ($produto, $data) {
            ProdutoComposicao::where('produto_pai_id', $produto->id)->delete();

            foreach ($data['itens'] as $item) {
                if ((int) $item['produto_id'] === $produto->id) {
                    continue;
                }

                ProdutoComposicao::create([
                    'produto_pai_id' => $produto->id,
                    'produto_filho_id' => $item['produto_id'],
                    'quantidade' => $item['quantidade'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Composição atualizada.',
            'data' => ProdutoComposicao::with('produtoFilho:id,nome,sku,preco_venda')
                ->where('produto_pai_id', $produto->id)
                ->get(),
        ]);
    }

    public function adicionarDocumento(Request $request, int $id): JsonResponse
    {
        $this->authorize('produtos.edit');
        $produto = Produto::findOrFail($id);

        $request->validate([
            'tipo' => 'required|in:manual,ficha_tecnica,certificado,garantia,video,outro',
            'titulo' => 'required|string|max:200',
            'url' => 'nullable|url|max:1000',
            'arquivo' => 'nullable|file|max:20480',
            'publico' => 'nullable|boolean',
        ]);

        if (!$request->filled('url') && !$request->hasFile('arquivo')) {
            return response()->json(['message' => 'Informe uma URL ou selecione um arquivo.'], 422);
        }

        $payload = [
            'tipo' => $request->get('tipo'),
            'titulo' => $request->get('titulo'),
            'publico' => $request->boolean('publico', true),
            'ordem' => (int) $produto->documentos()->max('ordem') + 1,
        ];

        if ($request->hasFile('arquivo')) {
            $file = $request->file('arquivo');
            $path = $file->store("produtos/{$produto->id}/documentos", 'public');

            $payload += [
                'origem' => 'upload',
                'arquivo' => $path,
                'url' => Storage::disk('public')->url($path),
                'mime_type' => $file->getMimeType(),
                'tamanho_bytes' => $file->getSize(),
            ];
        } else {
            $payload += [
                'origem' => 'url',
                'url' => $request->get('url'),
            ];
        }

        $documento = $produto->documentos()->create($payload);

        return response()->json([
            'message' => 'Documento adicionado.',
            'data' => $documento,
        ], 201);
    }

    public function removerDocumento(int $id, int $documentoId): JsonResponse
    {
        $this->authorize('produtos.edit');

        $documento = ProdutoDocumento::where('produto_id', $id)
            ->findOrFail($documentoId);

        if ($documento->origem === 'upload' && $documento->arquivo) {
            Storage::disk('public')->delete($documento->arquivo);
        }

        $documento->delete();

        return response()->json(['message' => 'Documento removido.']);
    }

    public function curvaABC(Request $request): JsonResponse
    {
        $this->authorize('produtos.view');
        $dataInicio = $request->get('data_inicio', now()->subMonths(3)->toDateString());
        $dataFim = $request->get('data_fim', now()->toDateString());

        $vendas = PedidoItem::query()
            ->join('pedidos','pedidos.id','=','pedido_itens.pedido_id')
            ->join('produtos','produtos.id','=','pedido_itens.produto_id')
            ->where('produtos.empresa_id', auth()->user()->empresa_id)
            ->whereIn('pedidos.status',['confirmado','faturado','entregue'])
            ->whereBetween('pedidos.data_pedido',[$dataInicio,$dataFim])
            ->groupBy('pedido_itens.produto_id','produtos.nome','produtos.codigo')
            ->select([
                'pedido_itens.produto_id','produtos.nome','produtos.codigo',
                DB::raw('SUM(pedido_itens.total) as valor_total'),
                DB::raw('SUM(pedido_itens.quantidade) as quantidade_total'),
            ])->orderByDesc('valor_total')->get();

        $total = (float) $vendas->sum('valor_total');
        $acumulado = 0.0;
        $produtos = $vendas->map(function ($item) use (&$acumulado, $total) {
            $valor = (float) $item->valor_total;
            $acumulado += $valor;
            $pct = $total > 0 ? ($acumulado / $total) * 100 : 0;
            return [
                'produto_id'=>$item->produto_id,'nome'=>$item->nome,'codigo'=>$item->codigo,
                'valor_total'=>round($valor,2),'quantidade_total'=>(float)$item->quantidade_total,
                'percentual_acumulado'=>round($pct,2),
                'classe'=>$pct <= 80 ? 'A' : ($pct <= 95 ? 'B' : 'C'),
            ];
        });

        return response()->json(['periodo'=>['inicio'=>$dataInicio,'fim'=>$dataFim],'total_geral'=>round($total,2),'produtos'=>$produtos]);
    }

    private function validarDados(Request $request, bool $atualizando = false): array
    {
        $req = $atualizando ? 'sometimes' : 'required';

        return $request->validate([
            'categoria_id'=>'nullable|exists:categorias,id','marca_id'=>'nullable|exists:marcas,id',
            'fornecedor_id'=>'nullable|exists:fornecedores,id','unidade_id'=>'nullable|exists:unidades_medida,id',
            'tipo'=>'nullable|in:simples,composto,kit,servico,digital',
            'formato'=>'nullable|in:simples,variacao,kit,composto','condicao'=>'nullable|in:novo,usado,recondicionado',
            'producao'=>'nullable|in:propria,terceiros','codigo'=>'nullable|string|max:50',
            'codigo_barras'=>'nullable|string|max:50','sku'=>'nullable|string|max:100',
            'ean'=>'nullable|string|max:20','ean_tributario'=>'nullable|string|max:20',
            'ncm'=>'nullable|string|max:10','cest'=>'nullable|string|max:10','cfop'=>'nullable|string|max:10',
            'origem'=>'nullable|integer|min:0|max:8','nome'=>"$req|string|max:200",
            'descricao'=>'nullable|string','descricao_curta'=>'nullable|string|max:5000',
            'descricao_complementar'=>'nullable|string','link_externo'=>'nullable|url|max:1000',
            'video_url'=>'nullable|url|max:1000','observacoes'=>'nullable|string','tags'=>'nullable|array','seo_titulo'=>'nullable|string|max:255','seo_descricao'=>'nullable|string|max:500','seo_palavras_chave'=>'nullable|array',
            'peso'=>'nullable|numeric|min:0','peso_bruto'=>'nullable|numeric|min:0',
            'largura'=>'nullable|numeric|min:0','altura'=>'nullable|numeric|min:0',
            'comprimento'=>'nullable|numeric|min:0','volumes'=>'nullable|integer|min:1',
            'itens_caixa'=>'nullable|numeric|min:0','unidade_dimensao'=>'nullable|string|max:20',
            'data_validade'=>'nullable|date','frete_gratis'=>'nullable|boolean',
            'preco_custo'=>'nullable|numeric|min:0','preco_venda'=>"$req|numeric|min:0",
            'modo_preco_venda'=>'nullable|in:manual,markup','markup_venda'=>'nullable|numeric|min:0|max:10000',
            'preco_venda_minimo'=>'nullable|numeric|min:0','desconto_maximo'=>'nullable|numeric|min:0|max:100',
            'icms_cst'=>'nullable|string|max:5','icms_aliquota'=>'nullable|numeric|min:0|max:100',
            'pis_cst'=>'nullable|string|max:3','pis_aliquota'=>'nullable|numeric|min:0|max:100',
            'cofins_cst'=>'nullable|string|max:3','cofins_aliquota'=>'nullable|numeric|min:0|max:100',
            'ipi_cst'=>'nullable|string|max:3','ipi_aliquota'=>'nullable|numeric|min:0|max:100',
            'tipo_item'=>'nullable|string|max:50','grupo_produtos'=>'nullable|string|max:100',
            'percentual_tributos'=>'nullable|numeric|min:0','icms_st_base'=>'nullable|numeric|min:0',
            'icms_st_valor'=>'nullable|numeric|min:0','icms_st_substituto'=>'nullable|numeric|min:0',
            'ipi_excecao'=>'nullable|string|max:30','pis_valor_fixo'=>'nullable|numeric|min:0',
            'cofins_valor_fixo'=>'nullable|numeric|min:0','informacoes_adicionais'=>'nullable|string',
            'controla_estoque'=>'nullable|boolean','estoque_minimo'=>'nullable|numeric|min:0',
            'estoque_maximo'=>'nullable|numeric|min:0','ponto_reposicao'=>'nullable|numeric|min:0',
            'ativo'=>'nullable|boolean','destaque'=>'nullable|boolean',
        ]);
    }

    private function aplicarFormacaoPreco(array $dados, ?Produto $produto = null): array
    {
        $modo = $dados['modo_preco_venda'] ?? $produto?->modo_preco_venda ?? 'manual';
        $custo = (float) ($dados['preco_custo'] ?? $produto?->preco_custo ?? 0);
        $markup = (float) ($dados['markup_venda'] ?? $produto?->markup_venda ?? 0);

        if ($modo === 'markup') {
            $dados['preco_venda'] = round($custo * (1 + ($markup / 100)), 2);
        }

        return $dados;
    }
}
