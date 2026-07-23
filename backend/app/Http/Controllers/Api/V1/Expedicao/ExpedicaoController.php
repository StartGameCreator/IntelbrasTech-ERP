<?php

namespace App\Http\Controllers\Api\V1\Expedicao;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Services\Expedicao\ExpedicaoService;
use Illuminate\Http\{JsonResponse, Request, Response};
use Illuminate\Support\Facades\DB;
use Throwable;

class ExpedicaoController extends Controller
{
    public function __construct(
        private readonly ExpedicaoService $service
    ) {}

    public function dashboard(): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $base = DB::table('expedicoes')->where('empresa_id', $empresaId);

        return response()->json([
            'resumo' => [
                'aguardando_separacao' => (clone $base)->where('status', 'aguardando_separacao')->count(),
                'separando' => (clone $base)->where('status', 'separando')->count(),
                'aguardando_conferencia' => (clone $base)->where('status', 'aguardando_conferencia')->count(),
                'conferindo' => (clone $base)->where('status', 'conferindo')->count(),
                'aguardando_embalagem' => (clone $base)->where('status', 'aguardando_embalagem')->count(),
                'pronto_expedicao' => (clone $base)->where('status', 'pronto_expedicao')->count(),
                'expedidas_hoje' => (clone $base)->where('status', 'expedida')->whereDate('expedida_em', today())->count(),
            ],
            'recentes' => DB::table('expedicoes as e')
                ->join('pedidos as p', 'p.id', '=', 'e.pedido_id')
                ->where('e.empresa_id', $empresaId)
                ->select('e.*','p.numero as pedido_numero','p.canal','p.total')
                ->orderByDesc('e.updated_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $query = DB::table('expedicoes as e')
            ->join('pedidos as p', 'p.id', '=', 'e.pedido_id')
            ->where('e.empresa_id', $empresaId)
            ->select('e.*','p.numero as pedido_numero','p.canal','p.total','p.status_entrega')
            ->when($request->filled('status'), fn ($q) => $q->where('e.status', $request->status))
            ->when($request->filled('busca'), function ($q) use ($request) {
                $termo = trim((string) $request->busca);
                $q->where(function ($q) use ($termo) {
                    $q->where('e.codigo', 'like', "%{$termo}%")
                        ->orWhere('p.numero', 'like', "%{$termo}%");
                });
            })
            ->orderByDesc('e.updated_at');

        return response()->json(
            $query->paginate(min((int) $request->get('per_page', 30), 100))
        );
    }

    public function criar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pedido_id' => 'required|integer|exists:pedidos,id',
        ]);

        $pedido = Pedido::with('itens')
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($data['pedido_id']);

        $expedicao = $this->service->criarParaPedido($pedido);

        return response()->json([
            'message' => 'Expedição criada.',
            'expedicao' => $expedicao,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $expedicao = DB::table('expedicoes as e')
            ->join('pedidos as p', 'p.id', '=', 'e.pedido_id')
            ->where('e.id', $id)
            ->where('e.empresa_id', $empresaId)
            ->select('e.*','p.numero as pedido_numero','p.canal','p.total','p.status_entrega')
            ->first();

        abort_unless($expedicao, 404, 'Expedição não encontrada.');

        return response()->json([
            'expedicao' => $expedicao,
            'itens' => DB::table('expedicao_itens')->where('expedicao_id', $id)->orderBy('id')->get(),
            'volumes' => DB::table('expedicao_volumes')->where('expedicao_id', $id)->orderBy('numero')->get(),
            'eventos' => DB::table('expedicao_eventos')->where('expedicao_id', $id)->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function iniciarSeparacao(int $id): JsonResponse
    {
        return $this->executar(fn () => $this->service->iniciarSeparacao(
            auth()->user()->empresa_id,
            $id,
            auth()->id()
        ), 'Separação iniciada.');
    }

    public function separarItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $data = $request->validate(['quantidade' => 'required|numeric|min:0']);

        return $this->executar(fn () => $this->service->separarItem(
            auth()->user()->empresa_id,
            $id,
            $itemId,
            (float) $data['quantidade'],
            auth()->id()
        ), 'Item separado.');
    }

    public function concluirSeparacao(int $id): JsonResponse
    {
        return $this->executar(fn () => $this->service->concluirSeparacao(
            auth()->user()->empresa_id,
            $id,
            auth()->id()
        ), 'Separação concluída.');
    }

    public function iniciarConferencia(int $id): JsonResponse
    {
        return $this->executar(fn () => $this->service->iniciarConferencia(
            auth()->user()->empresa_id,
            $id,
            auth()->id()
        ), 'Conferência iniciada.');
    }

    public function conferirItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $data = $request->validate(['quantidade' => 'required|numeric|min:0']);

        return $this->executar(fn () => $this->service->conferirItem(
            auth()->user()->empresa_id,
            $id,
            $itemId,
            (float) $data['quantidade'],
            auth()->id()
        ), 'Item conferido.');
    }

    public function concluirConferencia(int $id): JsonResponse
    {
        return $this->executar(fn () => $this->service->concluirConferencia(
            auth()->user()->empresa_id,
            $id,
            auth()->id()
        ), 'Conferência concluída.');
    }

    public function criarVolume(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'peso' => 'nullable|numeric|min:0',
            'altura' => 'nullable|numeric|min:0',
            'largura' => 'nullable|numeric|min:0',
            'comprimento' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        try {
            $volume = $this->service->criarVolume(
                auth()->user()->empresa_id,
                $id,
                $data,
                auth()->id()
            );

            return response()->json([
                'message' => 'Volume criado.',
                'volume' => $volume,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function expedir(int $id): JsonResponse
    {
        return $this->executar(fn () => $this->service->expedir(
            auth()->user()->empresa_id,
            $id,
            auth()->id()
        ), 'Expedição finalizada.');
    }

    public function romaneio(int $id): Response
    {
        $empresaId = auth()->user()->empresa_id;

        $expedicao = DB::table('expedicoes as e')
            ->join('pedidos as p', 'p.id', '=', 'e.pedido_id')
            ->where('e.id', $id)
            ->where('e.empresa_id', $empresaId)
            ->select('e.*','p.numero as pedido_numero','p.total','p.canal')
            ->first();

        abort_unless($expedicao, 404, 'Expedição não encontrada.');

        $itens = DB::table('expedicao_itens')->where('expedicao_id', $id)->get();
        $volumes = DB::table('expedicao_volumes')->where('expedicao_id', $id)->get();

        $html = view('expedicao.romaneio', compact('expedicao','itens','volumes'))->render();

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function executar(callable $callback, string $message): JsonResponse
    {
        try {
            $callback();
            return response()->json(['message' => $message]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
