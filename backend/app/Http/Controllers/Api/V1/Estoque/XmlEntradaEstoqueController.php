<?php

namespace App\Http\Controllers\Api\V1\Estoque;

use App\Http\Controllers\Controller;
use App\Models\{Deposito, Empresa, MovimentacaoEstoque, Produto};
use App\Services\Estoque\EstoqueService;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\{JsonResponse, Request, UploadedFile};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\Rule;
use RuntimeException;

class XmlEntradaEstoqueController extends Controller
{
    public function __construct(private readonly EstoqueService $estoqueService) {}

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('estoque.ajustar');
        $request->validate(['xml' => ['required', 'file', 'max:10240', 'extensions:xml']]);

        $nota = $this->parse($request->file('xml'));
        $this->validarNota($nota);

        return response()->json([
            'nota' => $nota,
            'catalogo' => Produto::query()->select('id', 'nome', 'codigo', 'sku', 'ean', 'codigo_barras')->orderBy('nome')->get(),
        ]);
    }

    public function importar(Request $request): JsonResponse
    {
        $this->authorize('estoque.ajustar');
        $dados = $request->validate([
            'xml' => ['required', 'file', 'max:10240', 'extensions:xml'],
            'deposito_id' => ['required', Rule::exists('depositos', 'id')],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.numero' => ['required', 'integer', 'min:1'],
            'itens.*.produto_id' => ['required', Rule::exists('produtos', 'id')],
        ]);

        $nota = $this->parse($request->file('xml'));
        $this->validarNota($nota);
        $deposito = Deposito::findOrFail($dados['deposito_id']);
        $mapeamento = collect($dados['itens'])->keyBy('numero');

        abort_if(count($nota['itens']) !== $mapeamento->count(), 422, 'Todos os itens do XML precisam estar associados a um produto.');

        DB::transaction(function () use ($nota, $deposito, $mapeamento, $request) {
            $duplicada = MovimentacaoEstoque::where('documento_tipo', 'nfe_entrada_xml')
                ->where('documento_numero', $nota['chave_acesso'])
                ->lockForUpdate()
                ->exists();
            abort_if($duplicada, 422, 'Esta NF-e já foi importada anteriormente e não pode movimentar o estoque novamente.');

            foreach ($nota['itens'] as $item) {
                $produto = Produto::findOrFail($mapeamento->get($item['numero'])['produto_id']);
                $this->estoqueService->entrada(
                    $produto,
                    $deposito,
                    $item['quantidade'],
                    $item['valor_unitario'],
                    'compra_xml',
                    null,
                    'nfe_entrada_xml',
                    $nota['chave_acesso'],
                );
            }

            Storage::disk('local')->put(
                'xml-entrada/'.auth()->user()->empresa_id.'/'.$nota['chave_acesso'].'.xml',
                $request->file('xml')->getContent()
            );
        });

        return response()->json([
            'message' => 'NF-e importada e estoque atualizado com sucesso.',
            'chave_acesso' => $nota['chave_acesso'],
            'itens_atualizados' => count($nota['itens']),
        ]);
    }

    private function validarNota(array $nota): void
    {
        abort_if(
            MovimentacaoEstoque::where('documento_tipo', 'nfe_entrada_xml')->where('documento_numero', $nota['chave_acesso'])->exists(),
            422,
            'Esta NF-e já foi importada anteriormente.'
        );

        $empresa = Empresa::findOrFail(auth()->user()->empresa_id);
        $cnpjEmpresa = preg_replace('/\D/', '', (string) $empresa->cnpj);
        abort_if($cnpjEmpresa && $nota['destinatario_cnpj'] && $cnpjEmpresa !== $nota['destinatario_cnpj'], 422, 'O CNPJ destinatário do XML não corresponde à empresa deste ERP.');
    }

    private function parse(UploadedFile $arquivo): array
    {
        $dom = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($arquivo->getContent(), LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        abort_unless($ok, 422, 'O arquivo não contém um XML válido.');

        $xp = new DOMXPath($dom);
        $valor = static fn (string $expressao) => trim((string) $xp->evaluate('string('.$expressao.')'));
        $cStat = $valor("//*[local-name()='protNFe']/*[local-name()='infProt']/*[local-name()='cStat']");
        abort_unless($cStat === '100', 422, 'Apenas XML de NF-e autorizada pode atualizar o estoque.');

        $infNFe = $xp->query("//*[local-name()='infNFe']")->item(0);
        abort_unless($infNFe, 422, 'Estrutura infNFe não encontrada no XML.');
        $chave = preg_replace('/\D/', '', (string) $infNFe->attributes?->getNamedItem('Id')?->nodeValue);
        if (strlen($chave) !== 44) $chave = preg_replace('/\D/', '', $valor("//*[local-name()='infProt']/*[local-name()='chNFe']"));
        abort_unless(strlen($chave) === 44, 422, 'A chave de acesso da NF-e não foi identificada.');

        $itens = [];
        foreach ($xp->query("//*[local-name()='infNFe']/*[local-name()='det']") as $det) {
            $n = (int) $det->attributes?->getNamedItem('nItem')?->nodeValue;
            $get = static fn (string $tag) => trim((string) $xp->evaluate("string(.//*[local-name()='{$tag}'])", $det));
            $codigo = $get('cProd');
            $gtin = preg_replace('/\D/', '', $get('cEAN'));
            if (in_array(strtoupper($get('cEAN')), ['', 'SEM GTIN'], true)) $gtin = '';
            $produto = null;
            if ($gtin) {
                $produto = Produto::query()
                    ->where(fn ($q) => $q->where('ean', $gtin)->orWhere('codigo_barras', $gtin))
                    ->first();
            }
            if (! $produto && $codigo) {
                $produto = Produto::query()
                    ->where(fn ($q) => $q->where('codigo', $codigo)->orWhere('sku', $codigo))
                    ->first();
            }

            $itens[] = [
                'numero' => $n,
                'codigo' => $codigo,
                'gtin' => $gtin,
                'descricao' => $get('xProd'),
                'unidade' => $get('uCom'),
                'quantidade' => (float) $get('qCom'),
                'valor_unitario' => (float) $get('vUnCom'),
                'valor_total' => (float) $get('vProd'),
                'produto_id' => $produto?->id,
                'produto_nome' => $produto?->nome,
            ];
        }
        abort_if(empty($itens), 422, 'Nenhum produto foi encontrado no XML.');

        return [
            'chave_acesso' => $chave,
            'numero' => $valor("//*[local-name()='infNFe']/*[local-name()='ide']/*[local-name()='nNF']"),
            'data_emissao' => $valor("//*[local-name()='infNFe']/*[local-name()='ide']/*[local-name()='dhEmi']"),
            'emitente_nome' => $valor("//*[local-name()='infNFe']/*[local-name()='emit']/*[local-name()='xNome']"),
            'emitente_cnpj' => preg_replace('/\D/', '', $valor("//*[local-name()='infNFe']/*[local-name()='emit']/*[local-name()='CNPJ']")),
            'destinatario_cnpj' => preg_replace('/\D/', '', $valor("//*[local-name()='infNFe']/*[local-name()='dest']/*[local-name()='CNPJ']")),
            'valor_total' => (float) $valor("//*[local-name()='infNFe']/*[local-name()='total']//*[local-name()='vNF']"),
            'itens' => $itens,
        ];
    }
}
