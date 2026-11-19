<?php

namespace App\Http\Controllers\Api\V1\OS;

use App\Http\Controllers\Controller;
use App\Models\{OrdemServico, OsStatus, OsPeca, Produto, Deposito};
use App\Services\Estoque\EstoqueService;
use App\Services\OS\OsPdfService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\Storage;
use Exception;

class OrdemServicoController extends Controller
{
    public function __construct(
        private readonly EstoqueService $estoqueService,
        private readonly OsPdfService $pdfService,
    ) {}

    // ── GET /os ────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('os.view');

        $q = OrdemServico::with(['cliente:id,razao_social,nome_fantasia', 'tecnico:id,nome', 'status'])
            ->when($request->status_id, fn($q, $v) => $q->where('status_id', $v))
            ->when($request->tecnico_id, fn($q, $v) => $q->tecnico($v))
            ->when($request->cliente_id, fn($q, $v) => $q->where('cliente_id', $v))
            ->when($request->em_garantia !== null, fn($q) => $q->where('em_garantia', $request->boolean('em_garantia')))
            ->when($request->boolean('atrasadas'), fn($q) => $q->atrasadas())
            ->when($request->busca, function ($q, $v) {
                $q->where(function ($qq) use ($v) {
                    $qq->whereLike('numero', "%{$v}%")
                       ->orWhereLike('equipamento_modelo', "%{$v}%")
                       ->orWhereLike('equipamento_serie', "%{$v}%");
                });
            })
            ->orderByDesc('data_entrada');

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($q->paginate($perPage));
    }

    // ── POST /os ───────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->authorize('os.create');

        $dados = $request->validate([
            'cliente_id'          => 'required|exists:clientes,id',
            'tecnico_id'          => 'nullable|exists:usuarios,id',
            'status_id'           => 'nullable|exists:os_status,id',
            'equipamento_tipo'    => 'nullable|string|max:100',
            'equipamento_marca'   => 'nullable|string|max:100',
            'equipamento_modelo'  => 'nullable|string|max:200',
            'equipamento_serie'   => 'nullable|string|max:100',
            'equipamento_imei'    => 'nullable|string|max:50',
            'defeito_relatado'    => 'nullable|string',
            'garantia'            => 'nullable|boolean',
            'numero_nf_compra'    => 'nullable|string|max:50',
            'data_compra'         => 'nullable|date',
            'data_previsao'       => 'nullable|date',
            'valor_servico'       => 'nullable|numeric|min:0',
            'observacoes'         => 'nullable|string',
        ]);

        $dados['empresa_id'] = auth()->user()->empresa_id;

        // Garantia: se dentro do prazo padrão de 90 dias da nota de compra, marca automaticamente.
        if (! empty($dados['garantia']) && ! empty($dados['data_compra'])) {
            $dados['em_garantia'] = now()->diffInDays($dados['data_compra']) <= 90;
            $dados['data_garantia_vence'] = \Carbon\Carbon::parse($dados['data_compra'])->addDays(90);
        }

        if (empty($dados['status_id'])) {
            $dados['status_id'] = OsStatus::where('empresa_id', $dados['empresa_id'])
                ->orderBy('ordem')->value('id');
        }

        $os = new OrdemServico($dados);
        $os->numero = $os->gerarNumero();
        $os->save();
        $os->registrarHistorico($os->status?->nome ?? 'Aberta', 'Ordem de serviço criada.');

        return response()->json([
            'message' => 'Ordem de serviço criada com sucesso.',
            'os'      => $os->fresh(['cliente', 'tecnico', 'status']),
        ], 201);
    }

    // ── GET /os/kanban ─────────────────────────────────────────

    public function kanban(Request $request): JsonResponse
    {
        $this->authorize('os.view');

        $empresaId = auth()->user()->empresa_id;

        $statuses = OsStatus::where('empresa_id', $empresaId)->orderBy('ordem')->get();

        $ordens = OrdemServico::with(['cliente:id,razao_social,nome_fantasia', 'tecnico:id,nome'])
            ->when($request->tecnico_id, fn($q, $v) => $q->tecnico($v))
            ->whereIn('status_id', $statuses->pluck('id'))
            ->orderByDesc('data_entrada')
            ->get();

        $colunas = $statuses->map(function ($status) use ($ordens) {
            return [
                'status'  => $status,
                'ordens'  => $ordens->where('status_id', $status->id)->values(),
                'total'   => $ordens->where('status_id', $status->id)->count(),
            ];
        });

        return response()->json($colunas);
    }

    // ── GET /os/sla ────────────────────────────────────────────

    public function sla(): JsonResponse
    {
        $this->authorize('os.view');

        $empresaId = auth()->user()->empresa_id;

        $concluidas = OrdemServico::where('empresa_id', $empresaId)
            ->whereNotNull('data_conclusao')
            ->get();

        $tempoMedioHoras = $concluidas->isEmpty() ? 0 : round(
            $concluidas->avg(fn ($os) => $os->data_entrada->diffInHours($os->data_conclusao)),
            1
        );

        $abertas = OrdemServico::with('status')
            ->where('empresa_id', $empresaId)
            ->whereNull('data_conclusao')
            ->get();

        $atrasadas = $abertas->filter(fn ($os) => $os->data_previsao && $os->data_previsao->isPast());

        return response()->json([
            'tempo_medio_reparo_horas' => $tempoMedioHoras,
            'total_concluidas'         => $concluidas->count(),
            'total_em_andamento'       => $abertas->count(),
            'total_atrasadas'          => $atrasadas->count(),
            'pendencias'               => $atrasadas->map(fn ($os) => [
                'id'            => $os->id,
                'numero'        => $os->numero,
                'status'        => $os->status?->nome,
                'data_previsao' => $os->data_previsao?->toDateString(),
                'dias_atraso'   => $os->data_previsao ? (int) $os->data_previsao->diffInDays(today()) : 0,
            ])->values(),
        ]);
    }

    // ── GET /os/{id} ───────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $this->authorize('os.view');

        $os = OrdemServico::with([
            'cliente', 'tecnico:id,nome', 'status', 'fotos', 'pecas.produto:id,nome,sku', 'historico',
        ])->findOrFail($id);

        return response()->json($os);
    }

    // ── PUT /os/{id} ───────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('os.edit');

        $os = OrdemServico::findOrFail($id);

        $dados = $request->validate([
            'tecnico_id'           => 'nullable|exists:usuarios,id',
            'equipamento_tipo'     => 'nullable|string|max:100',
            'equipamento_marca'    => 'nullable|string|max:100',
            'equipamento_modelo'   => 'nullable|string|max:200',
            'equipamento_serie'    => 'nullable|string|max:100',
            'equipamento_imei'     => 'nullable|string|max:50',
            'defeito_relatado'     => 'nullable|string',
            'defeito_constatado'   => 'nullable|string',
            'laudo_tecnico'        => 'nullable|string',
            'solucao'              => 'nullable|string',
            'data_previsao'        => 'nullable|date',
            'valor_servico'        => 'nullable|numeric|min:0',
            'desconto'             => 'nullable|numeric|min:0',
            'orcamento_aprovado'   => 'nullable|boolean',
            'nota_cliente'         => 'nullable|integer|min:1|max:5',
            'comentario_cliente'   => 'nullable|string',
            'observacoes'          => 'nullable|string',
            'observacoes_internas' => 'nullable|string',
        ]);

        if (! empty($dados['orcamento_aprovado']) && ! $os->orcamento_aprovado) {
            $dados['data_aprovacao_orcamento'] = now();
        }

        $os->update($dados);
        $os->refresh();
        $os->update(['total' => $os->total_geral]);

        return response()->json([
            'message' => 'Ordem de serviço atualizada com sucesso.',
            'os'      => $os->fresh(['cliente', 'tecnico', 'status']),
        ]);
    }

    // ── POST /os/{id}/fotos ────────────────────────────────────

    public function uploadFoto(Request $request, int $id): JsonResponse
    {
        $this->authorize('os.edit');

        $os = OrdemServico::findOrFail($id);

        $request->validate([
            'foto' => 'required|image|max:5120',
            'tipo' => 'nullable|in:entrada,diagnostico,conclusao',
        ]);

        $caminho = $request->file('foto')->store("os/{$os->id}", 's3');
        $url     = Storage::disk('s3')->url($caminho);

        $foto = $os->fotos()->create([
            'url'       => $url,
            'descricao' => $request->get('descricao'),
            'tipo'      => $request->get('tipo', 'entrada'),
        ]);

        return response()->json(['message' => 'Foto adicionada.', 'foto' => $foto], 201);
    }

    // ── POST /os/{id}/pecas ────────────────────────────────────

    public function adicionarPeca(Request $request, int $id): JsonResponse
    {
        $this->authorize('os.edit');

        $os = OrdemServico::findOrFail($id);

        $dados = $request->validate([
            'produto_id'     => 'nullable|exists:produtos,id',
            'descricao'      => 'required_without:produto_id|nullable|string|max:300',
            'quantidade'     => 'required|numeric|min:0.001',
            'preco_unitario' => 'required|numeric|min:0',
            'deposito_id'    => 'nullable|exists:depositos,id',
        ]);

        $dados['total'] = round($dados['quantidade'] * $dados['preco_unitario'], 2);

        if (! empty($dados['produto_id'])) {
            $produto = Produto::findOrFail($dados['produto_id']);
            $dados['descricao'] = $dados['descricao'] ?? $produto->nome;

            if ($produto->controla_estoque && ! empty($dados['deposito_id'])) {
                try {
                    $this->estoqueService->saida(
                        $produto,
                        Deposito::findOrFail($dados['deposito_id']),
                        (float) $dados['quantidade'],
                        'servico',
                        $os->id,
                        'os',
                        $os->numero,
                    );
                } catch (Exception $e) {
                    return response()->json(['message' => $e->getMessage()], 422);
                }
            }
        }

        unset($dados['deposito_id']);
        $peca = $os->pecas()->create($dados);

        $os->refresh();
        $os->update(['total' => $os->total_geral]);

        return response()->json(['message' => 'Peça adicionada à OS.', 'peca' => $peca], 201);
    }

    // ── POST /os/{id}/mudar-status ─────────────────────────────

    public function mudarStatus(Request $request, int $id): JsonResponse
    {
        $this->authorize('os.edit');

        $os = OrdemServico::with('status')->findOrFail($id);

        $dados = $request->validate([
            'status_id'  => 'required|exists:os_status,id',
            'observacao' => 'nullable|string|max:500',
        ]);

        $statusAnterior = $os->status?->nome;
        $novoStatus = OsStatus::findOrFail($dados['status_id']);

        $os->status_id = $novoStatus->id;

        if ($novoStatus->tipo === 'concluido' && ! $os->data_conclusao) {
            $os->data_conclusao = now();
        }
        if ($novoStatus->tipo === 'entregue' && ! $os->data_entrega) {
            $os->data_entrega = now();
        }

        $os->save();
        $os->historico()->create([
            'usuario_id'      => auth()->id(),
            'status_anterior' => $statusAnterior,
            'status_novo'     => $novoStatus->nome,
            'observacao'      => $dados['observacao'] ?? null,
        ]);

        return response()->json([
            'message' => 'Status atualizado com sucesso.',
            'os'      => $os->fresh(['status', 'historico']),
        ]);
    }

    // ── GET /os/{id}/historico ──────────────────────────────────

    public function historico(int $id): JsonResponse
    {
        $this->authorize('os.view');

        $os = OrdemServico::findOrFail($id);

        return response()->json($os->historico()->get());
    }

    // ── GET /os/{id}/pdf-os ─────────────────────────────────────

    public function pdfOs(int $id): JsonResponse
    {
        $this->authorize('os.view');

        $os = OrdemServico::findOrFail($id);

        try {
            $url = $this->pdfService->gerar($os);
            return response()->json(['url' => $url]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Não foi possível gerar o PDF: ' . $e->getMessage()], 422);
        }
    }
}
