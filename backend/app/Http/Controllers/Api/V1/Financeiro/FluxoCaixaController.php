<?php

namespace App\Http\Controllers\Api\V1\Financeiro;

use App\Http\Controllers\Controller;
use App\Models\{ContaPagar, ContaReceber, ContaBancaria, PlanoConta};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Carbon;

class FluxoCaixaController extends Controller
{
    // ── GET /fluxo-caixa ─────────────────────────────────────────
    // Fluxo de caixa realizado (já pago/recebido) e projetado (em aberto)
    // dentro do período informado, agrupado por dia.

    public function index(Request $request): JsonResponse
    {
        $this->authorize('financeiro.view');

        $empresaId = auth()->user()->empresa_id;
        $inicio = $request->get('data_inicio', now()->startOfMonth()->toDateString());
        $fim    = $request->get('data_fim', now()->endOfMonth()->toDateString());

        // Realizado: baseado na data de pagamento efetivo
        $entradasRealizadas = ContaReceber::where('empresa_id', $empresaId)
            ->whereNotNull('data_pagamento')
            ->whereBetween('data_pagamento', [$inicio, $fim])
            ->selectRaw('data_pagamento as data, SUM(valor_pago) as total')
            ->groupBy('data_pagamento')
            ->get();

        $saidasRealizadas = ContaPagar::where('empresa_id', $empresaId)
            ->whereNotNull('data_pagamento')
            ->whereBetween('data_pagamento', [$inicio, $fim])
            ->selectRaw('data_pagamento as data, SUM(valor_pago) as total')
            ->groupBy('data_pagamento')
            ->get();

        // Projetado: contas em aberto com vencimento dentro do período
        $entradasPrevistas = ContaReceber::where('empresa_id', $empresaId)
            ->whereNotIn('status', [ContaReceber::STATUS_PAGO, ContaReceber::STATUS_CANCELADO])
            ->whereBetween('data_vencimento', [$inicio, $fim])
            ->sum('valor');

        $saidasPrevistas = ContaPagar::where('empresa_id', $empresaId)
            ->whereNotIn('status', [ContaPagar::STATUS_PAGO, ContaPagar::STATUS_CANCELADO])
            ->whereBetween('data_vencimento', [$inicio, $fim])
            ->sum('valor');

        // Série diária consolidada (entradas - saídas realizadas, com saldo acumulado)
        $periodo = Carbon::parse($inicio)->daysUntil(Carbon::parse($fim));
        $saldoAcumulado = 0;
        $serie = collect($periodo)->map(function ($dia) use ($entradasRealizadas, $saidasRealizadas, &$saldoAcumulado) {
            $data = $dia->toDateString();
            $entrada = (float) ($entradasRealizadas->firstWhere('data', $data)->total ?? 0);
            $saida   = (float) ($saidasRealizadas->firstWhere('data', $data)->total ?? 0);
            $saldoAcumulado += ($entrada - $saida);

            return [
                'data'             => $data,
                'entradas'         => round($entrada, 2),
                'saidas'           => round($saida, 2),
                'saldo_dia'        => round($entrada - $saida, 2),
                'saldo_acumulado'  => round($saldoAcumulado, 2),
            ];
        });

        $saldoContasBancarias = ContaBancaria::where('empresa_id', $empresaId)->where('ativo', true)->sum('saldo_atual');

        return response()->json([
            'periodo' => ['inicio' => $inicio, 'fim' => $fim],
            'resumo'  => [
                'entradas_realizadas' => round($entradasRealizadas->sum('total'), 2),
                'saidas_realizadas'   => round($saidasRealizadas->sum('total'), 2),
                'saldo_realizado'     => round($entradasRealizadas->sum('total') - $saidasRealizadas->sum('total'), 2),
                'entradas_previstas'  => round((float) $entradasPrevistas, 2),
                'saidas_previstas'    => round((float) $saidasPrevistas, 2),
                'saldo_contas_bancarias' => round((float) $saldoContasBancarias, 2),
            ],
            'serie_diaria' => $serie->values(),
        ]);
    }

    // ── GET /dre ───────────────────────────────────────────────
    // Demonstrativo de Resultado do Exercício: agrupa receitas e despesas
    // (efetivamente pagas/recebidas) por plano de contas dentro do período.

    public function dre(Request $request): JsonResponse
    {
        $this->authorize('financeiro.view');

        $empresaId = auth()->user()->empresa_id;
        $inicio = $request->get('data_inicio', now()->startOfYear()->toDateString());
        $fim    = $request->get('data_fim', now()->toDateString());

        $receitas = ContaReceber::where('empresa_id', $empresaId)
            ->whereNotNull('data_pagamento')
            ->whereBetween('data_pagamento', [$inicio, $fim])
            ->with('planoConta:id,nome')
            ->get()
            ->groupBy(fn ($c) => $c->planoConta?->nome ?? 'Sem categoria')
            ->map(fn ($grupo, $nome) => ['categoria' => $nome, 'total' => round($grupo->sum('valor_pago'), 2)])
            ->values();

        $despesas = ContaPagar::where('empresa_id', $empresaId)
            ->whereNotNull('data_pagamento')
            ->whereBetween('data_pagamento', [$inicio, $fim])
            ->with('planoConta:id,nome')
            ->get()
            ->groupBy(fn ($c) => $c->planoConta?->nome ?? 'Sem categoria')
            ->map(fn ($grupo, $nome) => ['categoria' => $nome, 'total' => round($grupo->sum('valor_pago'), 2)])
            ->values();

        $totalReceitas = round($receitas->sum('total'), 2);
        $totalDespesas = round($despesas->sum('total'), 2);

        return response()->json([
            'periodo'         => ['inicio' => $inicio, 'fim' => $fim],
            'receitas'        => $receitas,
            'despesas'        => $despesas,
            'total_receitas'  => $totalReceitas,
            'total_despesas'  => $totalDespesas,
            'resultado_liquido' => round($totalReceitas - $totalDespesas, 2),
            'margem_liquida'  => $totalReceitas > 0 ? round((($totalReceitas - $totalDespesas) / $totalReceitas) * 100, 2) : 0,
        ]);
    }
}
