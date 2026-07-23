<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardAnalyticsController extends Controller
{
    public function show(Request $request, string $key): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;
        [$inicio, $fim] = $this->periodo((string) $request->get('periodo', '30d'));

        $pedidos = $this->pedidos($empresaId, $inicio, $fim);
        $clientes = $this->clientes($empresaId, $inicio, $fim);
        $produtos = $this->produtos($empresaId);
        $estoque = $this->estoque($empresaId);
        $financeiro = $this->financeiro($empresaId, $inicio, $fim);

        $serie = $this->serieVendas($empresaId, $inicio, $fim);
        $ranking = $this->ranking($empresaId, $inicio, $fim, $key);
        $linhas = $this->linhas($empresaId, $inicio, $fim, $key);

        $cards = $this->cards(
            $key,
            $pedidos,
            $clientes,
            $produtos,
            $estoque,
            $financeiro
        );

        return response()->json([
            'key' => $key,
            'periodo' => [
                'inicio' => $inicio->toDateString(),
                'fim' => $fim->toDateString(),
            ],
            'cards' => $cards,
            'serie' => $serie,
            'ranking' => $ranking,
            'indicadores' => [
                [
                    'label' => 'Pedidos aprovados',
                    'valor' => $this->numero($pedidos['aprovados']),
                    'detalhe' => 'Excluindo cancelamentos',
                ],
                [
                    'label' => 'Ticket médio',
                    'valor' => $this->moeda($pedidos['ticket_medio']),
                    'detalhe' => 'Valor médio por pedido',
                ],
                [
                    'label' => 'Clientes compradores',
                    'valor' => $this->numero($pedidos['clientes']),
                    'detalhe' => 'Clientes com pedido no período',
                ],
                [
                    'label' => 'Estoque disponível',
                    'valor' => $this->numero($estoque['quantidade']),
                    'detalhe' => 'Soma dos depósitos',
                ],
            ],
            'colunas' => ['Registro', 'Detalhe', 'Valor', 'Status'],
            'linhas' => $linhas,
        ]);
    }

    private function periodo(string $periodo): array
    {
        $fim = now()->endOfDay();

        $inicio = match ($periodo) {
            'hoje' => now()->startOfDay(),
            '7d' => now()->subDays(6)->startOfDay(),
            'mes' => now()->startOfMonth(),
            '12m' => now()->subMonths(11)->startOfMonth(),
            '24m' => now()->subMonths(23)->startOfMonth(),
            default => now()->subDays(29)->startOfDay(),
        };

        return [$inicio, $fim];
    }

    private function pedidos(int $empresaId, Carbon $inicio, Carbon $fim): array
    {
        if (!Schema::hasTable('pedidos')) {
            return [
                'total' => 0,
                'valor' => 0,
                'aprovados' => 0,
                'cancelados' => 0,
                'clientes' => 0,
                'ticket_medio' => 0,
            ];
        }

        $base = DB::table('pedidos')
            ->where('empresa_id', $empresaId)
            ->whereBetween('data_pedido', [$inicio, $fim]);

        $total = (clone $base)->count();
        $cancelados = (clone $base)->whereIn('status', ['cancelado', 'cancelada'])->count();

        $validos = (clone $base)->whereNotIn('status', ['cancelado', 'cancelada']);
        $valor = (float) $validos->sum('total');
        $aprovados = $validos->count();
        $clientes = (clone $validos)->whereNotNull('cliente_id')->distinct()->count('cliente_id');

        return [
            'total' => $total,
            'valor' => $valor,
            'aprovados' => $aprovados,
            'cancelados' => $cancelados,
            'clientes' => $clientes,
            'ticket_medio' => $aprovados > 0 ? $valor / $aprovados : 0,
        ];
    }

    private function clientes(int $empresaId, Carbon $inicio, Carbon $fim): array
    {
        if (!Schema::hasTable('clientes')) {
            return ['ativos' => 0, 'novos' => 0, 'inativos' => 0];
        }

        $base = DB::table('clientes')->where('empresa_id', $empresaId);

        return [
            'ativos' => (clone $base)->where('ativo', true)->count(),
            'novos' => (clone $base)->whereBetween('created_at', [$inicio, $fim])->count(),
            'inativos' => (clone $base)->where('ativo', false)->count(),
        ];
    }

    private function produtos(int $empresaId): array
    {
        if (!Schema::hasTable('produtos')) {
            return ['ativos' => 0, 'sem_ncm' => 0, 'sem_movimento' => 0];
        }

        $base = DB::table('produtos')->where('empresa_id', $empresaId);

        $semMovimento = 0;
        if (Schema::hasTable('movimentacoes_estoque')) {
            $semMovimento = DB::table('produtos as p')
                ->leftJoin('movimentacoes_estoque as m', function ($join) {
                    $join->on('m.produto_id', '=', 'p.id')
                        ->where('m.created_at', '>=', now()->subDays(90));
                })
                ->where('p.empresa_id', $empresaId)
                ->whereNull('m.id')
                ->count('p.id');
        }

        return [
            'ativos' => (clone $base)->where('ativo', true)->count(),
            'sem_ncm' => (clone $base)->where(function ($q) {
                $q->whereNull('ncm')->orWhere('ncm', '');
            })->count(),
            'sem_movimento' => $semMovimento,
        ];
    }

    private function estoque(int $empresaId): array
    {
        if (!Schema::hasTable('estoque')) {
            return ['quantidade' => 0, 'valor' => 0, 'abaixo_minimo' => 0];
        }

        $quantidade = (float) DB::table('estoque')
            ->where('empresa_id', $empresaId)
            ->sum('quantidade_disponivel');

        $valor = (float) DB::table('estoque')
            ->where('empresa_id', $empresaId)
            ->selectRaw('COALESCE(SUM(quantidade_disponivel * custo_medio),0) as total')
            ->value('total');

        $abaixo = 0;
        if (Schema::hasTable('produtos')) {
            $abaixo = DB::table('produtos as p')
                ->leftJoin('estoque as e', function ($join) {
                    $join->on('e.produto_id', '=', 'p.id');
                })
                ->where('p.empresa_id', $empresaId)
                ->groupBy('p.id', 'p.estoque_minimo')
                ->havingRaw('COALESCE(SUM(e.quantidade_disponivel),0) < p.estoque_minimo')
                ->get()
                ->count();
        }

        return [
            'quantidade' => $quantidade,
            'valor' => $valor,
            'abaixo_minimo' => $abaixo,
        ];
    }

    private function financeiro(int $empresaId, Carbon $inicio, Carbon $fim): array
    {
        $receber = 0;
        $pagar = 0;

        if (Schema::hasTable('contas_receber')) {
            $receber = (float) DB::table('contas_receber')
                ->where('empresa_id', $empresaId)
                ->whereBetween('data_emissao', [$inicio->toDateString(), $fim->toDateString()])
                ->sum('valor');
        }

        if (Schema::hasTable('contas_pagar')) {
            $pagar = (float) DB::table('contas_pagar')
                ->where('empresa_id', $empresaId)
                ->whereBetween('data_emissao', [$inicio->toDateString(), $fim->toDateString()])
                ->sum('valor');
        }

        return [
            'receber' => $receber,
            'pagar' => $pagar,
            'saldo' => $receber - $pagar,
        ];
    }

    private function serieVendas(int $empresaId, Carbon $inicio, Carbon $fim): array
    {
        if (!Schema::hasTable('pedidos')) {
            return [];
        }

        $rows = DB::table('pedidos')
            ->where('empresa_id', $empresaId)
            ->whereBetween('data_pedido', [$inicio, $fim])
            ->whereNotIn('status', ['cancelado', 'cancelada'])
            ->selectRaw("strftime('%Y-%m', data_pedido) as periodo, SUM(total) as valor")
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get();

        $max = max(1, (float) ($rows->max('valor') ?? 1));

        return $rows->map(function ($row) use ($max) {
            $date = Carbon::createFromFormat('Y-m', $row->periodo);

            return [
                'label' => $date->translatedFormat('M/y'),
                'percentual' => max(3, round(((float) $row->valor / $max) * 100)),
                'valorLabel' => $this->compacto((float) $row->valor),
            ];
        })->values()->all();
    }

    private function ranking(int $empresaId, Carbon $inicio, Carbon $fim, string $key): array
    {
        if (!Schema::hasTable('pedidos')) {
            return [];
        }

        $rows = DB::table('pedidos')
            ->where('empresa_id', $empresaId)
            ->whereBetween('data_pedido', [$inicio, $fim])
            ->whereNotIn('status', ['cancelado', 'cancelada'])
            ->selectRaw("COALESCE(NULLIF(marketplace,''), NULLIF(canal,''), 'Interno') as nome")
            ->selectRaw('COUNT(*) as pedidos, SUM(total) as valor')
            ->groupBy('nome')
            ->orderByDesc('valor')
            ->limit(8)
            ->get();

        $max = max(1, (float) ($rows->max('valor') ?? 1));

        return $rows->map(fn ($row) => [
            'nome' => $row->nome,
            'detalhe' => $this->numero($row->pedidos) . ' pedidos',
            'valor' => $this->moeda((float) $row->valor),
            'percentual' => round(((float) $row->valor / $max) * 100),
        ])->values()->all();
    }

    private function linhas(int $empresaId, Carbon $inicio, Carbon $fim, string $key): array
    {
        if (!Schema::hasTable('pedidos')) {
            return [];
        }

        return DB::table('pedidos')
            ->where('empresa_id', $empresaId)
            ->whereBetween('data_pedido', [$inicio, $fim])
            ->orderByDesc('data_pedido')
            ->limit(12)
            ->get(['numero', 'canal', 'total', 'status'])
            ->map(fn ($row) => [
                $row->numero,
                $row->canal ?: 'interno',
                $this->moeda((float) $row->total),
                $row->status,
            ])
            ->all();
    }

    private function cards(
        string $key,
        array $pedidos,
        array $clientes,
        array $produtos,
        array $estoque,
        array $financeiro
    ): array {
        $map = [
            'produtos' => [
                ['label' => 'Produtos ativos', 'valor' => $this->numero($produtos['ativos']), 'variacao' => 'Banco de dados'],
                ['label' => 'Sem NCM', 'valor' => $this->numero($produtos['sem_ncm']), 'variacao' => 'Exigem revisão'],
                ['label' => 'Sem movimentação', 'valor' => $this->numero($produtos['sem_movimento']), 'variacao' => 'Últimos 90 dias'],
                ['label' => 'Valor em estoque', 'valor' => $this->moeda($estoque['valor']), 'variacao' => 'Custo armazenado'],
            ],
            'estoque' => [
                ['label' => 'Estoque disponível', 'valor' => $this->numero($estoque['quantidade']), 'variacao' => 'Todas as unidades'],
                ['label' => 'Valor em estoque', 'valor' => $this->moeda($estoque['valor']), 'variacao' => 'Custo médio'],
                ['label' => 'Abaixo do mínimo', 'valor' => $this->numero($estoque['abaixo_minimo']), 'variacao' => 'Atenção'],
                ['label' => 'Produtos ativos', 'valor' => $this->numero($produtos['ativos']), 'variacao' => 'Catálogo'],
            ],
            'clientes', 'recorrentes', 'carteira' => [
                ['label' => 'Clientes ativos', 'valor' => $this->numero($clientes['ativos']), 'variacao' => 'Banco de dados'],
                ['label' => 'Novos clientes', 'valor' => $this->numero($clientes['novos']), 'variacao' => 'No período'],
                ['label' => 'Clientes compradores', 'valor' => $this->numero($pedidos['clientes']), 'variacao' => 'Com pedidos'],
                ['label' => 'Faturamento', 'valor' => $this->moeda($pedidos['valor']), 'variacao' => 'No período'],
            ],
            'compras', 'previsao', 'semMovimento' => [
                ['label' => 'Estoque disponível', 'valor' => $this->numero($estoque['quantidade']), 'variacao' => 'Saldo atual'],
                ['label' => 'Abaixo do mínimo', 'valor' => $this->numero($estoque['abaixo_minimo']), 'variacao' => 'Reposição'],
                ['label' => 'Sem movimentação', 'valor' => $this->numero($produtos['sem_movimento']), 'variacao' => '90 dias'],
                ['label' => 'Contas a pagar', 'valor' => $this->moeda($financeiro['pagar']), 'variacao' => 'No período'],
            ],
            default => [
                ['label' => 'Faturamento', 'valor' => $this->moeda($pedidos['valor']), 'variacao' => 'Dados reais'],
                ['label' => 'Pedidos', 'valor' => $this->numero($pedidos['total']), 'variacao' => 'No período'],
                ['label' => 'Ticket médio', 'valor' => $this->moeda($pedidos['ticket_medio']), 'variacao' => 'Pedidos válidos'],
                ['label' => 'Clientes ativos', 'valor' => $this->numero($clientes['ativos']), 'variacao' => 'Cadastro'],
            ],
        ];

        return $map[$key] ?? $map['default'];
    }

    private function moeda(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function numero(float|int $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function compacto(float $value): string
    {
        if ($value >= 1000000) {
            return number_format($value / 1000000, 1, ',', '.') . ' mi';
        }

        if ($value >= 1000) {
            return number_format($value / 1000, 1, ',', '.') . ' mil';
        }

        return number_format($value, 0, ',', '.');
    }
}
