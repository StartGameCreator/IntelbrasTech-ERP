<?php

namespace App\Http\Controllers\Api\V1\Pdv;

use App\Http\Controllers\Controller;
use App\Models\{DevolucaoFiscal, PdvVenda};
use App\Services\Fiscal\DevolucaoFiscalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevolucaoFiscalController extends Controller
{
    public function __construct(private readonly DevolucaoFiscalService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = DevolucaoFiscal::with(['venda', 'notaOrigem', 'notaDevolucao', 'itens'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(30));
    }

    public function venda(int $id): JsonResponse
    {
        $venda = PdvVenda::with([
            'pedido.itens.produto',
            'notaFiscal',
            'cliente',
        ])->where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);

        return response()->json($venda);
    }

    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'pdv_venda_id' => ['required', 'integer'],
            'motivo' => ['required', 'string', 'max:60'],
            'descricao_motivo' => ['nullable', 'string', 'max:1000'],
            'mercadoria_retornou' => ['required', 'boolean'],
            'forma_reembolso' => ['nullable', 'string', 'max:30'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.pedido_item_id' => ['required', 'integer'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0'],
            'itens.*.condicao' => ['required', 'in:revendavel,avariado,defeituoso'],
        ]);

        $venda = PdvVenda::where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($dados['pdv_venda_id']);

        $devolucao = $this->service->criar($venda, $dados);

        return response()->json([
            'message' => $devolucao->situacao_mercadoria === 'retornada'
                ? 'Devolução registrada e mercadoria recebida.'
                : 'Solicitação registrada. Aguardando retorno da mercadoria.',
            'devolucao' => $devolucao,
        ], 201);
    }

    public function receber(int $id): JsonResponse
    {
        $devolucao = $this->devolucao($id);
        return response()->json([
            'message' => 'Mercadoria recebida e estoque atualizado.',
            'devolucao' => $this->service->receber($devolucao),
        ]);
    }

    public function reembolsar(int $id): JsonResponse
    {
        $devolucao = $this->devolucao($id);
        return response()->json([
            'message' => 'Reembolso registrado no caixa.',
            'devolucao' => $this->service->registrarReembolso($devolucao),
        ]);
    }

    public function prepararNfe(int $id): JsonResponse
    {
        $devolucao = $this->devolucao($id);
        return response()->json([
            'message' => 'Rascunho da NF-e de devolução preparado para conferência.',
            'nota_fiscal' => $this->service->prepararNfe($devolucao),
        ]);
    }

    public function emitirNfe(int $id): JsonResponse
    {
        $devolucao = $this->devolucao($id);
        return response()->json([
            'message' => 'NF-e de devolução processada.',
            'nota_fiscal' => $this->service->emitirNfe($devolucao),
        ]);
    }

    private function devolucao(int $id): DevolucaoFiscal
    {
        return DevolucaoFiscal::with(['itens', 'venda', 'notaOrigem', 'notaDevolucao'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);
    }
}
