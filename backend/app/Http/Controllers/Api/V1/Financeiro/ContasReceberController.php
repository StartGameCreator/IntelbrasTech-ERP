<?php

namespace App\Http\Controllers\Api\V1\Financeiro;

use App\Http\Controllers\Controller;
use App\Models\{ContaReceber, ContaBancaria};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContasReceberController extends Controller
{
    // ── GET /contas-receber ────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('financeiro.view');

        $q = ContaReceber::with(['cliente:id,razao_social,nome_fantasia', 'formaPagamento:id,nome'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->cliente_id, fn($q, $v) => $q->where('cliente_id', $v))
            ->when($request->data_inicio && $request->data_fim,
                fn($q) => $q->whereBetween('data_vencimento', [$request->data_inicio, $request->data_fim]))
            ->when($request->busca, fn($q, $v) => $q->whereLike('descricao', "%{$v}%"))
            ->orderBy('data_vencimento');

        $perPage = min((int) $request->get('per_page', 20), 100);
        $paginado = $q->paginate($perPage);

        return response()->json($paginado);
    }

    // ── POST /contas-receber ───────────────────────────────────
    // Suporta parcelamento: se total_parcelas > 1, gera N lançamentos filhos
    // vinculados ao primeiro (conta_pai_id) com vencimentos mensais sucessivos.

    public function store(Request $request): JsonResponse
    {
        $this->authorize('financeiro.create');

        $dados = $request->validate([
            'cliente_id'          => 'nullable|exists:clientes,id',
            'pedido_id'           => 'nullable|exists:pedidos,id',
            'nota_fiscal_id'      => 'nullable|exists:notas_fiscais,id',
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
            'observacoes'         => 'nullable|string',
            'nosso_numero'        => 'nullable|string|max:50',
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
                    ? $dados['valor'] - ($valorParcela * ($totalParcelas - 1)) // ajusta arredondamento na última parcela
                    : $valorParcela;
                $item['parcela'] = $i;
                $item['total_parcelas'] = $totalParcelas;
                $item['conta_pai_id'] = $contaPaiId;
                $item['data_vencimento'] = \Carbon\Carbon::parse($dados['data_vencimento'])->addMonthsNoOverflow($i - 1);
                $item['status'] = ContaReceber::STATUS_ABERTO;

                $conta = ContaReceber::create($item);
                $criadas[] = $conta;
                $contaPaiId = $contaPaiId ?? $conta->id;
            }

            return $criadas;
        });

        return response()->json([
            'message' => count($contas) > 1
                ? count($contas) . ' parcelas geradas com sucesso.'
                : 'Conta a receber criada com sucesso.',
            'data' => $contas,
        ], 201);
    }

    // ── GET /contas-receber/vencidas ───────────────────────────

    public function vencidas(): JsonResponse
    {
        $this->authorize('financeiro.view');

        $contas = ContaReceber::with('cliente:id,razao_social,nome_fantasia')
            ->whereNotIn('status', [ContaReceber::STATUS_PAGO, ContaReceber::STATUS_CANCELADO])
            ->where('data_vencimento', '<', today())
            ->orderBy('data_vencimento')
            ->get();

        return response()->json([
            'total'       => $contas->count(),
            'valor_total' => round($contas->sum('saldo'), 2),
            'dados'       => $contas,
        ]);
    }

    // ── GET /contas-receber/a-vencer ───────────────────────────

    public function aVencer(Request $request): JsonResponse
    {
        $this->authorize('financeiro.view');

        $dias = (int) $request->get('dias', 7);

        $contas = ContaReceber::with('cliente:id,razao_social,nome_fantasia')
            ->whereNotIn('status', [ContaReceber::STATUS_PAGO, ContaReceber::STATUS_CANCELADO])
            ->whereBetween('data_vencimento', [today(), today()->addDays($dias)])
            ->orderBy('data_vencimento')
            ->get();

        return response()->json([
            'total'       => $contas->count(),
            'valor_total' => round($contas->sum('saldo'), 2),
            'dados'       => $contas,
        ]);
    }

    // ── GET /contas-receber/{id} ────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $this->authorize('financeiro.view');

        $conta = ContaReceber::with(['cliente', 'pedido:id,numero', 'notaFiscal:id,numero,serie', 'formaPagamento', 'contaBancaria'])
            ->findOrFail($id);

        return response()->json($conta);
    }

    // ── PUT /contas-receber/{id} ────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('financeiro.edit');

        $conta = ContaReceber::findOrFail($id);

        if ($conta->status === ContaReceber::STATUS_PAGO) {
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

        return response()->json(['message' => 'Conta a receber atualizada.', 'data' => $conta]);
    }

    // ── POST /contas-receber/{id}/baixar ───────────────────────
    // Registra o recebimento (total ou parcial) de uma conta.

    public function baixar(Request $request, int $id): JsonResponse
    {
        $this->authorize('financeiro.edit');

        $conta = ContaReceber::findOrFail($id);

        if ($conta->status === ContaReceber::STATUS_PAGO) {
            return response()->json(['message' => 'Esta conta já está totalmente paga.'], 422);
        }
        if ($conta->status === ContaReceber::STATUS_CANCELADO) {
            return response()->json(['message' => 'Não é possível baixar uma conta cancelada.'], 422);
        }

        $dados = $request->validate([
            'valor_pago'        => 'required|numeric|min:0.01',
            'data_pagamento'    => 'nullable|date',
            'valor_juros'       => 'nullable|numeric|min:0',
            'valor_multa'       => 'nullable|numeric|min:0',
            'conta_bancaria_id' => 'nullable|exists:contas_bancarias,id',
        ]);

        DB::transaction(function () use ($conta, $dados) {
            $novoValorPago = $conta->valor_pago + $dados['valor_pago'];
            $conta->valor_juros = $dados['valor_juros'] ?? $conta->valor_juros;
            $conta->valor_multa = $dados['valor_multa'] ?? $conta->valor_multa;
            $conta->valor_pago = $novoValorPago;
            $conta->data_pagamento = $dados['data_pagamento'] ?? today();
            $conta->conta_bancaria_id = $dados['conta_bancaria_id'] ?? $conta->conta_bancaria_id;

            $saldoDevedor = round($conta->valor + $conta->valor_juros + $conta->valor_multa - $conta->valor_desconto - $novoValorPago, 2);
            $conta->status = $saldoDevedor <= 0 ? ContaReceber::STATUS_PAGO : ContaReceber::STATUS_PARCIAL;
            $conta->save();

            if ($conta->conta_bancaria_id) {
                ContaBancaria::where('id', $conta->conta_bancaria_id)
                    ->increment('saldo_atual', $dados['valor_pago']);
            }
        });

        return response()->json(['message' => 'Recebimento registrado com sucesso.', 'data' => $conta->fresh()]);
    }

    // ── POST /contas-receber/{id}/cancelar ─────────────────────

    public function cancelar(Request $request, int $id): JsonResponse
    {
        $this->authorize('financeiro.edit');

        $conta = ContaReceber::findOrFail($id);

        if ($conta->status === ContaReceber::STATUS_PAGO) {
            return response()->json(['message' => 'Não é possível cancelar uma conta já paga.'], 422);
        }

        $conta->update([
            'status'      => ContaReceber::STATUS_CANCELADO,
            'observacoes' => trim(($conta->observacoes ?? '') . "\nCancelada: " . $request->get('motivo', 'sem motivo informado')),
        ]);

        return response()->json(['message' => 'Conta a receber cancelada.', 'data' => $conta]);
    }

    // ── POST /contas-receber/baixa-lote ─────────────────────────

    public function baixaLote(Request $request): JsonResponse
    {
        $this->authorize('financeiro.edit');

        $dados = $request->validate([
            'ids'               => 'required|array|min:1',
            'ids.*'             => 'exists:contas_receber,id',
            'data_pagamento'    => 'nullable|date',
            'conta_bancaria_id' => 'nullable|exists:contas_bancarias,id',
        ]);

        $processadas = 0;
        $erros = [];

        DB::transaction(function () use ($dados, &$processadas, &$erros) {
            $contas = ContaReceber::whereIn('id', $dados['ids'])
                ->whereNotIn('status', [ContaReceber::STATUS_PAGO, ContaReceber::STATUS_CANCELADO])
                ->get();

            foreach ($contas as $conta) {
                $conta->update([
                    'valor_pago'        => $conta->valor,
                    'data_pagamento'    => $dados['data_pagamento'] ?? today(),
                    'conta_bancaria_id' => $dados['conta_bancaria_id'] ?? $conta->conta_bancaria_id,
                    'status'            => ContaReceber::STATUS_PAGO,
                ]);

                if ($conta->conta_bancaria_id) {
                    ContaBancaria::where('id', $conta->conta_bancaria_id)->increment('saldo_atual', $conta->valor);
                }

                $processadas++;
            }
        });

        return response()->json(['message' => "{$processadas} conta(s) baixada(s) com sucesso."]);
    }
}
