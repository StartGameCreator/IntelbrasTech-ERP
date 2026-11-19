<?php

namespace App\Http\Controllers\Api\V1\Financeiro;

use App\Http\Controllers\Controller;
use App\Models\{ContaPagar, ContaBancaria};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContasPagarController extends Controller
{
    // ── GET /contas-pagar ──────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('financeiro.view');

        $q = ContaPagar::with(['fornecedor:id,razao_social,nome_fantasia', 'formaPagamento:id,nome'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->fornecedor_id, fn($q, $v) => $q->where('fornecedor_id', $v))
            ->when($request->data_inicio && $request->data_fim,
                fn($q) => $q->whereBetween('data_vencimento', [$request->data_inicio, $request->data_fim]))
            ->when($request->busca, fn($q, $v) => $q->whereLike('descricao', "%{$v}%"))
            ->orderBy('data_vencimento');

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($q->paginate($perPage));
    }

    // ── POST /contas-pagar ─────────────────────────────────────
    // Suporta parcelamento, igual ao Contas a Receber.

    public function store(Request $request): JsonResponse
    {
        $this->authorize('financeiro.create');

        $dados = $request->validate([
            'fornecedor_id'       => 'nullable|exists:fornecedores,id',
            'pedido_compra_id'    => 'nullable|integer',
            'plano_conta_id'      => 'nullable|exists:plano_contas,id',
            'forma_pagamento_id'  => 'nullable|exists:formas_pagamento,id',
            'conta_bancaria_id'   => 'nullable|exists:contas_bancarias,id',
            'descricao'           => 'required|string|max:300',
            'numero_documento'    => 'nullable|string|max:50',
            'valor'               => 'required|numeric|min:0.01',
            'valor_desconto'      => 'nullable|numeric|min:0',
            'data_emissao'        => 'nullable|date',
            'data_vencimento'     => 'required|date',
            'total_parcelas'      => 'nullable|integer|min:1|max:360',
            'recorrente'          => 'nullable|boolean',
            'recorrencia_tipo'    => 'nullable|in:mensal,semanal,anual',
            'observacoes'         => 'nullable|string',
        ]);

        $dados['empresa_id'] = auth()->user()->empresa_id;
        $totalParcelas = $dados['total_parcelas'] ?? 1;
        unset($dados['total_parcelas']);

        $contas = DB::transaction(function () use ($dados, $totalParcelas) {
            $valorParcela = round($dados['valor'] / $totalParcelas, 2);
            $criadas = [];
            $contaPaiId = null;

            for ($i = 1; $i <= $totalParcelas; $i++) {
                $item = $dados;
                $item['uuid'] = (string) Str::uuid();
                $item['valor'] = $i === $totalParcelas
                    ? $dados['valor'] - ($valorParcela * ($totalParcelas - 1))
                    : $valorParcela;
                $item['parcela'] = $i;
                $item['total_parcelas'] = $totalParcelas;
                $item['conta_pai_id'] = $contaPaiId;
                $item['data_vencimento'] = \Carbon\Carbon::parse($dados['data_vencimento'])->addMonthsNoOverflow($i - 1);
                $item['status'] = ContaPagar::STATUS_ABERTO;

                $conta = ContaPagar::create($item);
                $criadas[] = $conta;
                $contaPaiId = $contaPaiId ?? $conta->id;
            }

            return $criadas;
        });

        return response()->json([
            'message' => count($contas) > 1
                ? count($contas) . ' parcelas geradas com sucesso.'
                : 'Conta a pagar criada com sucesso.',
            'data' => $contas,
        ], 201);
    }

    // ── GET /contas-pagar/vencidas ──────────────────────────────

    public function vencidas(): JsonResponse
    {
        $this->authorize('financeiro.view');

        $contas = ContaPagar::with('fornecedor:id,razao_social,nome_fantasia')
            ->whereNotIn('status', [ContaPagar::STATUS_PAGO, ContaPagar::STATUS_CANCELADO])
            ->where('data_vencimento', '<', today())
            ->orderBy('data_vencimento')
            ->get();

        return response()->json([
            'total'       => $contas->count(),
            'valor_total' => round($contas->sum('saldo'), 2),
            'dados'       => $contas,
        ]);
    }

    // ── GET /contas-pagar/{id} ───────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $this->authorize('financeiro.view');

        $conta = ContaPagar::with(['fornecedor', 'formaPagamento', 'contaBancaria'])->findOrFail($id);

        return response()->json($conta);
    }

    // ── PUT /contas-pagar/{id} ───────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('financeiro.edit');

        $conta = ContaPagar::findOrFail($id);

        if ($conta->status === ContaPagar::STATUS_PAGO) {
            return response()->json(['message' => 'Conta já paga não pode ser editada.'], 422);
        }

        $dados = $request->validate([
            'descricao'          => 'sometimes|string|max:300',
            'numero_documento'   => 'nullable|string|max:50',
            'valor'              => 'sometimes|numeric|min:0.01',
            'valor_desconto'     => 'nullable|numeric|min:0',
            'data_vencimento'    => 'sometimes|date',
            'plano_conta_id'     => 'nullable|exists:plano_contas,id',
            'forma_pagamento_id' => 'nullable|exists:formas_pagamento,id',
            'observacoes'        => 'nullable|string',
        ]);

        $conta->update($dados);

        return response()->json(['message' => 'Conta a pagar atualizada.', 'data' => $conta]);
    }

    // ── POST /contas-pagar/{id}/pagar ────────────────────────────

    public function pagar(Request $request, int $id): JsonResponse
    {
        $this->authorize('financeiro.edit');

        $conta = ContaPagar::findOrFail($id);

        if ($conta->status === ContaPagar::STATUS_PAGO) {
            return response()->json(['message' => 'Esta conta já está totalmente paga.'], 422);
        }
        if ($conta->status === ContaPagar::STATUS_CANCELADO) {
            return response()->json(['message' => 'Não é possível pagar uma conta cancelada.'], 422);
        }

        $dados = $request->validate([
            'valor_pago'        => 'required|numeric|min:0.01',
            'data_pagamento'    => 'nullable|date',
            'valor_juros'       => 'nullable|numeric|min:0',
            'valor_multa'       => 'nullable|numeric|min:0',
            'conta_bancaria_id' => 'nullable|exists:contas_bancarias,id',
        ]);

        if (! empty($dados['conta_bancaria_id'])) {
            $bancaria = ContaBancaria::findOrFail($dados['conta_bancaria_id']);
            if ($bancaria->saldo_atual < $dados['valor_pago']) {
                return response()->json(['message' => "Saldo insuficiente na conta {$bancaria->nome}."], 422);
            }
        }

        DB::transaction(function () use ($conta, $dados) {
            $novoValorPago = $conta->valor_pago + $dados['valor_pago'];
            $conta->valor_juros = $dados['valor_juros'] ?? $conta->valor_juros;
            $conta->valor_multa = $dados['valor_multa'] ?? $conta->valor_multa;
            $conta->valor_pago = $novoValorPago;
            $conta->data_pagamento = $dados['data_pagamento'] ?? today();
            $conta->conta_bancaria_id = $dados['conta_bancaria_id'] ?? $conta->conta_bancaria_id;

            $saldoDevedor = round($conta->valor + $conta->valor_juros + $conta->valor_multa - $conta->valor_desconto - $novoValorPago, 2);
            $conta->status = $saldoDevedor <= 0 ? ContaPagar::STATUS_PAGO : ContaPagar::STATUS_PARCIAL;
            $conta->save();

            if ($conta->conta_bancaria_id) {
                ContaBancaria::where('id', $conta->conta_bancaria_id)
                    ->decrement('saldo_atual', $dados['valor_pago']);
            }
        });

        return response()->json(['message' => 'Pagamento registrado com sucesso.', 'data' => $conta->fresh()]);
    }
}
