<?php

namespace App\Http\Controllers\Api\V1\Logistica;

use App\Http\Controllers\Controller;
use App\Services\Logistica\TransportadoraHubService;
use Illuminate\Http\{JsonResponse, Request, Response};
use Illuminate\Support\Facades\DB;
use Throwable;

class TransportadoraController extends Controller
{
    public function __construct(
        private readonly TransportadoraHubService $service
    ) {}

    public function dashboard(): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $this->service->garantirCatalogo($empresaId);

        return response()->json([
            'resumo' => [
                'transportadoras' => DB::table('transportadoras_integracoes')
                    ->where('empresa_id', $empresaId)->where('ativo', true)->count(),
                'conectadas' => DB::table('transportadoras_integracoes')
                    ->where('empresa_id', $empresaId)->where('status', 'conectado')->count(),
                'aguardando_postagem' => DB::table('envios_logisticos')
                    ->where('empresa_id', $empresaId)
                    ->whereIn('status', ['etiqueta_gerada','aguardando_postagem'])->count(),
                'em_transito' => DB::table('envios_logisticos')
                    ->where('empresa_id', $empresaId)->where('status', 'em_transito')->count(),
                'entregues_hoje' => DB::table('envios_logisticos')
                    ->where('empresa_id', $empresaId)
                    ->where('status', 'entregue')
                    ->whereDate('entregue_em', today())->count(),
            ],
            'transportadoras' => DB::table('transportadoras_integracoes')
                ->where('empresa_id', $empresaId)
                ->orderBy('nome')
                ->get(),
            'envios_recentes' => DB::table('envios_logisticos')
                ->where('empresa_id', $empresaId)
                ->orderByDesc('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function cotar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'expedicao_id' => 'nullable|integer',
            'pedido_id' => 'nullable|integer',
            'cep_origem' => 'required|string|max:20',
            'cep_destino' => 'required|string|max:20',
            'peso' => 'required|numeric|min:0.01',
            'altura' => 'nullable|numeric|min:0',
            'largura' => 'nullable|numeric|min:0',
            'comprimento' => 'nullable|numeric|min:0',
        ]);

        try {
            return response()->json([
                'message' => 'Cotação concluída.',
                'cotacoes' => $this->service->cotar(auth()->user()->empresa_id, $data),
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function contratar(int $cotacaoId): JsonResponse
    {
        try {
            $envio = $this->service->contratar(auth()->user()->empresa_id, $cotacaoId);

            return response()->json([
                'message' => 'Frete contratado e etiqueta gerada.',
                'envio' => $envio,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function envios(Request $request): JsonResponse
    {
        $query = DB::table('envios_logisticos')
            ->where('empresa_id', auth()->user()->empresa_id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = trim((string) $request->busca);
                $q->where(function ($q) use ($termo) {
                    $q->where('codigo_rastreio', 'like', "%{$termo}%")
                        ->orWhere('servico_nome', 'like', "%{$termo}%")
                        ->orWhere('transportadora_slug', 'like', "%{$termo}%");
                });
            })
            ->orderByDesc('id');

        return response()->json($query->paginate(30));
    }

    public function atualizarStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:aguardando_postagem,postado,em_transito,saiu_para_entrega,entregue,atrasado,extraviado,cancelado',
            'descricao' => 'nullable|string|max:255',
        ]);

        try {
            $this->service->atualizarStatus(
                auth()->user()->empresa_id,
                $id,
                $data['status'],
                $data['descricao'] ?? null
            );

            return response()->json(['message' => 'Status atualizado.']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function rastreamento(int $id): JsonResponse
    {
        $envio = DB::table('envios_logisticos')
            ->where('id', $id)
            ->where('empresa_id', auth()->user()->empresa_id)
            ->first();

        abort_unless($envio, 404, 'Envio não encontrado.');

        return response()->json([
            'envio' => $envio,
            'eventos' => DB::table('rastreamento_eventos')
                ->where('envio_id', $id)
                ->orderByDesc('ocorrido_em')
                ->get(),
        ]);
    }

    public function etiqueta(int $id): Response
    {
        $envio = DB::table('envios_logisticos')
            ->where('id', $id)
            ->where('empresa_id', auth()->user()->empresa_id)
            ->first();

        abort_unless($envio, 404, 'Envio não encontrado.');
        abort_unless($envio->etiqueta_conteudo, 404, 'Etiqueta não gerada.');

        return response(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Etiqueta</title>'
            . '<style>body{display:flex;justify-content:center;padding:20px}button{position:fixed;top:10px;right:10px}@media print{button{display:none}}</style>'
            . '</head><body><button onclick="window.print()">Imprimir</button>'
            . $envio->etiqueta_conteudo
            . '</body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }
}
