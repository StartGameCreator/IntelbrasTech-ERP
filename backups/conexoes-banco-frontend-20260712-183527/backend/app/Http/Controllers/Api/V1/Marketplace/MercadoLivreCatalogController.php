<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\{
    MarketplaceAccount,
    MarketplaceConnector,
    Produto
};
use App\Services\Marketplace\MercadoLivreCatalogService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Throwable;

class MercadoLivreCatalogController extends Controller
{
    public function __construct(
        private readonly MercadoLivreCatalogService $catalog
    ) {}

    public function index(Request $request): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $account = $this->account();

        $query = DB::table('marketplace_products as mp')
            ->join('produtos as p', 'p.id', '=', 'mp.produto_id')
            ->where('mp.empresa_id', $empresaId)
            ->where('mp.account_id', $account->id)
            ->select([
                'mp.id','mp.external_id','mp.titulo_externo','mp.permalink',
                'mp.thumbnail','mp.categoria_externa','mp.status','mp.preco',
                'mp.estoque','mp.sync_preco','mp.sync_estoque',
                'mp.sincronizado_em','mp.ultimo_erro',
                'p.id as produto_id','p.nome as produto_nome',
                'p.sku','p.codigo','p.preco_venda',
            ])
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = trim((string) $request->busca);
                $q->where(function ($q) use ($termo) {
                    $q->where('p.nome', 'like', "%{$termo}%")
                        ->orWhere('p.sku', 'like', "%{$termo}%")
                        ->orWhere('mp.external_id', 'like', "%{$termo}%")
                        ->orWhere('mp.titulo_externo', 'like', "%{$termo}%");
                });
            })
            ->orderByDesc('mp.id');

        return response()->json(
            $query->paginate(min((int) $request->get('per_page', 30), 100))
        );
    }

    public function importar(Request $request): JsonResponse
    {
        $account = $this->account();
        $empresaId = auth()->user()->empresa_id;
        $offset = max(0, (int) $request->get('offset', 0));

        try {
            $search = $this->catalog->sellerItems($account, $offset, 50);
            $ids = $search['results'] ?? [];
            $items = [];

            foreach (array_chunk($ids, 20) as $chunk) {
                $items = array_merge($items, $this->catalog->items($account, $chunk));
            }

            $vinculados = 0;
            $pendentes = 0;

            foreach ($items as $item) {
                $sku = trim((string) ($item['seller_custom_field'] ?? ''));

                $produto = Produto::where('empresa_id', $empresaId)
                    ->when($sku, function ($q) use ($sku) {
                        $q->where(function ($q) use ($sku) {
                            $q->where('sku', $sku)->orWhere('codigo', $sku);
                        });
                    })
                    ->first();

                if (!$produto) {
                    $pendentes++;
                    continue;
                }

                DB::table('marketplace_products')->updateOrInsert(
                    [
                        'produto_id' => $produto->id,
                        'account_id' => $account->id,
                    ],
                    [
                        'empresa_id' => $empresaId,
                        'external_id' => $item['id'] ?? null,
                        'titulo_externo' => $item['title'] ?? null,
                        'permalink' => $item['permalink'] ?? null,
                        'thumbnail' => $item['thumbnail'] ?? null,
                        'categoria_externa' => $item['category_id'] ?? null,
                        'status' => $item['status'] ?? 'importado',
                        'preco' => $item['price'] ?? null,
                        'estoque' => $item['available_quantity'] ?? null,
                        'sincronizado_em' => now(),
                        'ultimo_erro' => null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $vinculados++;
            }

            return response()->json([
                'message' => 'Catálogo importado.',
                'total_retornado' => count($items),
                'vinculados' => $vinculados,
                'sem_vinculo' => $pendentes,
                'paging' => $search['paging'] ?? null,
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function vincular(Request $request): JsonResponse
    {
        $data = $request->validate([
            'produto_id' => 'required|exists:produtos,id',
            'external_id' => 'required|string|max:150',
            'titulo_externo' => 'nullable|string|max:250',
            'preco' => 'nullable|numeric|min:0',
            'estoque' => 'nullable|numeric|min:0',
        ]);

        $account = $this->account();

        DB::table('marketplace_products')->updateOrInsert(
            [
                'produto_id' => $data['produto_id'],
                'account_id' => $account->id,
            ],
            [
                'empresa_id' => auth()->user()->empresa_id,
                'external_id' => $data['external_id'],
                'titulo_externo' => $data['titulo_externo'] ?? null,
                'preco' => $data['preco'] ?? null,
                'estoque' => $data['estoque'] ?? null,
                'status' => 'vinculado',
                'sincronizado_em' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['message' => 'Produto vinculado ao anúncio.']);
    }

    public function sincronizar(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'sincronizar_preco' => 'required|boolean',
            'sincronizar_estoque' => 'required|boolean',
        ]);

        $account = $this->account();

        $row = DB::table('marketplace_products as mp')
            ->join('produtos as p', 'p.id', '=', 'mp.produto_id')
            ->where('mp.id', $id)
            ->where('mp.empresa_id', auth()->user()->empresa_id)
            ->select('mp.*','p.preco_venda','p.id as produto_id')
            ->first();

        abort_unless($row, 404, 'Vínculo não encontrado.');
        abort_unless($row->external_id, 422, 'O vínculo não possui ID externo.');

        $estoque = DB::table('estoques')
            ->where('produto_id', $row->produto_id)
            ->sum('quantidade_disponivel');

        try {
            $result = $this->catalog->updatePriceStock(
                $account,
                $row->external_id,
                $data['sincronizar_preco'] ? (float) $row->preco_venda : null,
                $data['sincronizar_estoque'] ? (float) $estoque : null
            );

            DB::table('marketplace_products')->where('id', $id)->update([
                'preco' => $result['price'] ?? $row->preco,
                'estoque' => $result['available_quantity'] ?? $row->estoque,
                'status' => $result['status'] ?? 'sincronizado',
                'sync_preco' => $data['sincronizar_preco'],
                'sync_estoque' => $data['sincronizar_estoque'],
                'sincronizado_em' => now(),
                'ultimo_erro' => null,
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Preço e estoque sincronizados.',
                'item' => $result,
            ]);
        } catch (Throwable $e) {
            DB::table('marketplace_products')->where('id', $id)->update([
                'status' => 'erro',
                'ultimo_erro' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function account(): MarketplaceAccount
    {
        $connector = MarketplaceConnector::where('slug', 'mercado_livre')->firstOrFail();

        return MarketplaceAccount::where('empresa_id', auth()->user()->empresa_id)
            ->where('connector_id', $connector->id)
            ->where('status', 'conectado')
            ->whereNotNull('access_token')
            ->firstOrFail();
    }
}
