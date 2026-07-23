<?php

namespace App\Http\Controllers\Api\V1\Logistica;

use App\Http\Controllers\Controller;
use App\Services\Logistica\LogisticaService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Throwable;

class LogisticaController extends Controller
{
    public function __construct(
        private readonly LogisticaService $service
    ) {}

    public function overview(): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        return response()->json([
            'resumo' => [
                'transportadoras' => DB::table('logistica_transportadoras')->where('ativo', true)->count(),
                'contas_conectadas' => DB::table('logistica_contas')
                    ->where('empresa_id', $empresaId)->where('status', 'conectado')->count(),
                'aguardando_postagem' => DB::table('logistica_envios')
                    ->where('empresa_id', $empresaId)->where('status', 'aguardando_postagem')->count(),
                'postados' => DB::table('logistica_envios')
                    ->where('empresa_id', $empresaId)->where('status', 'postado')->count(),
                'entregues' => DB::table('logistica_envios')
                    ->where('empresa_id', $empresaId)->where('status', 'entregue')->count(),
            ],
            'transportadoras' => DB::table('logistica_transportadoras')
                ->where('ativo', true)->orderBy('ordem')->get(),
        ]);
    }

    public function contas(): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $items = DB::table('logistica_transportadoras as t')
            ->leftJoin('logistica_contas as c', function ($join) use ($empresaId) {
                $join->on('c.transportadora_id', '=', 't.id')
                    ->where('c.empresa_id', '=', $empresaId);
            })
            ->where('t.ativo', true)
            ->orderBy('t.ordem')
            ->get([
                't.id','t.slug','t.nome','t.tipo',
                'c.id as conta_id','c.nome_conta','c.status','c.ultimo_erro','c.ultima_sincronizacao_em',
            ]);

        return response()->json(['data' => $items]);
    }

    public function salvarConta(Request $request, int $transportadoraId): JsonResponse
    {
        $data = $request->validate([
            'nome_conta' => 'nullable|string|max:150',
            'token' => 'nullable|string',
            'secret' => 'nullable|string',
            'configuracoes' => 'nullable|array',
        ]);

        DB::table('logistica_contas')->updateOrInsert(
            [
                'empresa_id' => auth()->user()->empresa_id,
                'transportadora_id' => $transportadoraId,
            ],
            [
                'nome_conta' => $data['nome_conta'] ?? null,
                'status' => ($data['token'] ?? null) ? 'configurado' : 'desconectado',
                'token' => isset($data['token']) && $data['token'] !== '' ? encrypt($data['token']) : null,
                'secret' => isset($data['secret']) && $data['secret'] !== '' ? encrypt($data['secret']) : null,
                'configuracoes' => isset($data['configuracoes'])
                    ? json_encode($data['configuracoes'], JSON_UNESCAPED_UNICODE)
                    : null,
                'ultimo_erro' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json(['message' => 'Configuração salva.']);
    }

    public function cotar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pedido_id' => 'nullable|integer',
            'expedicao_id' => 'nullable|integer',
            'cep_origem' => 'required|string|min:8|max:9',
            'cep_destino' => 'required|string|min:8|max:9',
            'peso' => 'required|numeric|min:0.1',
            'altura' => 'required|numeric|min:1',
            'largura' => 'required|numeric|min:1',
            'comprimento' => 'required|numeric|min:1',
            'valor_declarado' => 'nullable|numeric|min:0',
        ]);

        return response()->json([
            'data' => $this->service->cotar(auth()->user()->empresa_id, $data),
            'aviso' => 'Valores estimados até que as credenciais reais das transportadoras sejam configuradas.',
        ]);
    }

    public function envios(Request $request): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $query = DB::table('logistica_envios as e')
            ->join('logistica_transportadoras as t', 't.id', '=', 'e.transportadora_id')
            ->leftJoin('pedidos as p', 'p.id', '=', 'e.pedido_id')
            ->where('e.empresa_id', $empresaId)
            ->select([
                'e.*','t.nome as transportadora_nome','t.slug as transportadora_slug',
                'p.numero as pedido_numero',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('e.status', $request->status))
            ->orderByDesc('e.id');

        return response()->json(
            $query->paginate(min((int) $request->get('per_page', 40), 100))
        );
    }

    public function criarEnvio(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pedido_id' => 'nullable|integer',
            'expedicao_id' => 'nullable|integer',
            'transportadora_id' => 'required|integer|exists:logistica_transportadoras,id',
            'servico' => 'nullable|string|max:120',
            'valor_frete' => 'nullable|numeric|min:0',
            'prazo_dias' => 'nullable|integer|min:1',
        ]);

        try {
            $id = $this->service->criarEnvio(auth()->user()->empresa_id, $data);
            return response()->json(['message' => 'Envio criado.', 'id' => $id], 201);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function postar(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'codigo_rastreio' => 'required|string|max:120',
            'local' => 'nullable|string|max:200',
        ]);

        try {
            $this->service->registrarPostagem(auth()->user()->empresa_id, $id, $data);
            return response()->json(['message' => 'Postagem registrada.']);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function rastreio(int $id): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $envio = DB::table('logistica_envios')
            ->where('id', $id)->where('empresa_id', $empresaId)->first();

        abort_unless($envio, 404, 'Envio não encontrado.');

        return response()->json([
            'envio' => $envio,
            'eventos' => DB::table('logistica_rastreios')
                ->where('envio_id', $id)
                ->orderByDesc('evento_em')
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
