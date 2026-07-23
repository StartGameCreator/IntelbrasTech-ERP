<?php

namespace App\Http\Controllers\Api\V1\Estoque;

use App\Http\Controllers\Controller;
use App\Models\MovimentacaoEstoque;
use Illuminate\Http\{Request, JsonResponse};

class MovimentacaoController extends Controller
{
    // ── GET /movimentacoes ────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('estoque.view');

        $q = MovimentacaoEstoque::with(['produto:id,nome,sku', 'deposito:id,nome', 'depositoDestino:id,nome', 'usuario:id,nome'])
            ->when($request->produto_id, fn($q, $v) => $q->where('produto_id', $v))
            ->when($request->deposito_id, fn($q, $v) => $q->where('deposito_id', $v))
            ->when($request->tipo, fn($q, $v) => $q->where('tipo', $v))
            ->when($request->motivo, fn($q, $v) => $q->where('motivo', $v))
            ->when($request->data_inicio && $request->data_fim,
                fn($q) => $q->whereBetween('created_at', [$request->data_inicio, $request->data_fim]))
            ->orderByDesc('created_at');

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($q->paginate($perPage));
    }

    // ── GET /movimentacoes/{id} ───────────────────────────────

    public function show(int $id): JsonResponse
    {
        $this->authorize('estoque.view');

        $mov = MovimentacaoEstoque::with(['produto', 'deposito', 'depositoDestino', 'usuario:id,nome'])
            ->findOrFail($id);

        return response()->json($mov);
    }
}
