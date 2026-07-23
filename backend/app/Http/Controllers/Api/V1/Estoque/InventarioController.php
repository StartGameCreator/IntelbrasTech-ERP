<?php

namespace App\Http\Controllers\Api\V1\Estoque;

use App\Http\Controllers\Controller;
use App\Models\{Inventario, InventarioItem, Produto, Deposito, Estoque};
use App\Services\Estoque\EstoqueService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Exception;

class InventarioController extends Controller
{
    public function __construct(private readonly EstoqueService $estoqueService) {}

    // ── GET /inventarios ──────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('estoque.view');

        $q = Inventario::with(['deposito:id,nome', 'responsavel:id,nome'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->deposito_id, fn($q, $v) => $q->where('deposito_id', $v))
            ->orderByDesc('created_at');

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($q->paginate($perPage));
    }

    // ── POST /inventarios ─────────────────────────────────────
    // Abre um novo inventário e tira uma "foto" do saldo atual de cada produto
    // que controla estoque (no depósito informado, ou em todos os depósitos).

    public function store(Request $request): JsonResponse
    {
        $this->authorize('estoque.create');

        $dados = $request->validate([
            'deposito_id'    => 'nullable|exists:depositos,id',
            'responsavel_id' => 'nullable|exists:usuarios,id',
            'observacoes'    => 'nullable|string',
        ]);

        $empresaId = auth()->user()->empresa_id;

        $inventario = DB::transaction(function () use ($dados, $empresaId) {
            $inventario = Inventario::create([
                'empresa_id'     => $empresaId,
                'deposito_id'    => $dados['deposito_id'] ?? null,
                'numero'         => 'INV-' . now()->format('YmdHis'),
                'data_inicio'    => now(),
                'status'         => Inventario::STATUS_ABERTO,
                'responsavel_id' => $dados['responsavel_id'] ?? auth()->id(),
                'observacoes'    => $dados['observacoes'] ?? null,
            ]);

            $estoques = Estoque::query()
                ->where('empresa_id', $empresaId)
                ->when($dados['deposito_id'] ?? null, fn($q, $v) => $q->where('deposito_id', $v))
                ->whereHas('produto', fn($q) => $q->where('controla_estoque', true))
                ->get();

            foreach ($estoques as $estoque) {
                InventarioItem::create([
                    'inventario_id'      => $inventario->id,
                    'produto_id'         => $estoque->produto_id,
                    'variacao_id'        => $estoque->variacao_id,
                    'quantidade_sistema' => $estoque->quantidade,
                ]);
            }

            return $inventario;
        });

        return response()->json([
            'message'    => 'Inventário aberto com sucesso.',
            'inventario' => $inventario->fresh(['deposito', 'itens.produto']),
        ], 201);
    }

    // ── GET /inventarios/{id} ─────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $this->authorize('estoque.view');

        $inventario = Inventario::with(['deposito', 'responsavel:id,nome', 'itens.produto:id,nome,sku,codigo'])
            ->findOrFail($id);

        return response()->json($inventario);
    }

    // ── PUT /inventarios/{id} ─────────────────────────────────
    // Lança as contagens físicas dos itens e/ou atualiza dados gerais.

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('estoque.edit');

        $inventario = Inventario::findOrFail($id);

        if (! $inventario->pode_editar) {
            return response()->json(['message' => 'Este inventário já foi finalizado ou cancelado.'], 422);
        }

        $dados = $request->validate([
            'observacoes'                  => 'nullable|string',
            'itens'                        => 'nullable|array',
            'itens.*.id'                   => 'required_with:itens|exists:inventario_itens,id',
            'itens.*.quantidade_contada'   => 'required_with:itens|numeric|min:0',
        ]);

        if (! empty($dados['itens'])) {
            foreach ($dados['itens'] as $itemDados) {
                $item = InventarioItem::where('inventario_id', $inventario->id)->findOrFail($itemDados['id']);
                $item->update([
                    'quantidade_contada' => $itemDados['quantidade_contada'],
                    'diferenca'          => $itemDados['quantidade_contada'] - $item->quantidade_sistema,
                ]);
            }
            if ($inventario->status === Inventario::STATUS_ABERTO) {
                $inventario->update(['status' => Inventario::STATUS_EM_ANDAMENTO]);
            }
        }

        if (array_key_exists('observacoes', $dados)) {
            $inventario->update(['observacoes' => $dados['observacoes']]);
        }

        return response()->json([
            'message'    => 'Inventário atualizado.',
            'inventario' => $inventario->fresh(['itens.produto']),
        ]);
    }

    // ── POST /inventarios/{id}/finalizar ──────────────────────
    // Aplica os ajustes de estoque para cada item com diferença e fecha o inventário.

    public function finalizar(int $id): JsonResponse
    {
        $this->authorize('estoque.ajustar');

        $inventario = Inventario::with('itens.produto')->findOrFail($id);

        if (! $inventario->pode_editar) {
            return response()->json(['message' => 'Este inventário já foi finalizado ou cancelado.'], 422);
        }

        $pendentes = $inventario->itens->whereNull('quantidade_contada')->count();
        if ($pendentes > 0) {
            return response()->json([
                'message' => "Existem {$pendentes} itens sem contagem lançada. Finalize a contagem antes de aplicar os ajustes.",
            ], 422);
        }

        try {
            DB::transaction(function () use ($inventario) {
                foreach ($inventario->itens as $item) {
                    if ((float) $item->diferenca === 0.0) {
                        continue;
                    }

                    $deposito = $inventario->deposito_id
                        ? Deposito::find($inventario->deposito_id)
                        : Estoque::where('produto_id', $item->produto_id)->where('variacao_id', $item->variacao_id)->first()?->deposito;

                    if (! $deposito) {
                        continue;
                    }

                    $this->estoqueService->ajustar(
                        $item->produto,
                        $deposito,
                        (float) $item->quantidade_contada,
                        0,
                        "Inventário {$inventario->numero}",
                        $item->variacao_id,
                    );
                }

                $inventario->update([
                    'status'   => Inventario::STATUS_FINALIZADO,
                    'data_fim' => now(),
                ]);
            });

            return response()->json([
                'message'    => 'Inventário finalizado e ajustes aplicados ao estoque.',
                'inventario' => $inventario->fresh(['itens.produto']),
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── DELETE /inventarios/{id} ──────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $this->authorize('estoque.delete');

        $inventario = Inventario::findOrFail($id);

        if ($inventario->status === Inventario::STATUS_FINALIZADO) {
            return response()->json(['message' => 'Inventário finalizado não pode ser excluído.'], 422);
        }

        $inventario->update(['status' => Inventario::STATUS_CANCELADO, 'data_fim' => now()]);

        return response()->json(['message' => 'Inventário cancelado.']);
    }
}
