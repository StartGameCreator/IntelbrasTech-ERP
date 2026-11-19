<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\{MarketplaceAccount, MarketplaceConnector};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceHubController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $query = MarketplaceConnector::query()
            ->where('ativo', true)
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', $request->categoria))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = trim((string) $request->busca);
                $q->where(function ($q) use ($termo) {
                    $q->where('nome', 'like', "%{$termo}%")
                        ->orWhere('descricao', 'like', "%{$termo}%");
                });
            })
            ->orderBy('ordem');

        $connectors = $query->get()->map(function ($connector) use ($empresaId) {
            $account = MarketplaceAccount::where('empresa_id', $empresaId)
                ->where('connector_id', $connector->id)
                ->first();

            return [
                'id' => $connector->id,
                'slug' => $connector->slug,
                'nome' => $connector->nome,
                'categoria' => $connector->categoria,
                'descricao' => $connector->descricao,
                'status' => $account?->status ?: 'desconectado',
                'nome_conta' => $account?->nome_conta,
                'ultima_sincronizacao_em' => $account?->ultima_sincronizacao_em,
                'conectado' => $account?->status === 'conectado',
            ];
        });

        $categorias = MarketplaceConnector::where('ativo', true)
            ->select('categoria', DB::raw('count(*) as total'))
            ->groupBy('categoria')
            ->orderBy('categoria')
            ->get();

        return response()->json([
            'connectors' => $connectors,
            'categorias' => $categorias,
            'resumo' => [
                'total' => $connectors->count(),
                'conectados' => $connectors->where('conectado', true)->count(),
                'disponiveis' => $connectors->where('conectado', false)->count(),
                'categorias' => $categorias->count(),
            ],
        ]);
    }

    public function dashboard(): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        return response()->json([
            'resumo' => [
                'contas_conectadas' => MarketplaceAccount::where('empresa_id', $empresaId)
                    ->where('status', 'conectado')->count(),
                'produtos_sincronizados' => DB::table('marketplace_products')
                    ->where('empresa_id', $empresaId)->where('status', 'sincronizado')->count(),
                'produtos_pendentes' => DB::table('marketplace_products')
                    ->where('empresa_id', $empresaId)->where('status', 'pendente')->count(),
                'pedidos_importados' => DB::table('marketplace_orders')
                    ->where('empresa_id', $empresaId)->count(),
                'erros_sincronizacao' => DB::table('marketplace_sync_logs')
                    ->where('empresa_id', $empresaId)->where('status', 'erro')->count(),
                'fila_pendente' => DB::table('marketplace_queue')
                    ->where('empresa_id', $empresaId)->where('status', 'pendente')->count(),
            ],
            'atividade' => DB::table('marketplace_sync_logs')
                ->where('empresa_id', $empresaId)
                ->latest('created_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
