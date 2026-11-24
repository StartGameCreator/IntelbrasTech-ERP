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
    private const KEYS = [
        'geral', 'mensal', 'diario', 'vendas', 'pedidos', 'vendedor', 'canal',
        'previsao', 'produtos', 'estoque', 'compras', 'semMovimento',
        'clientes', 'recorrentes', 'carteira',
    ];

    public function show(Request $request, string $key): JsonResponse
    {
        abort_unless(in_array($key, self::KEYS, true), 404, 'Dashboard não encontrado.');

        $empresaId = auth()->user()->empresa_id;
        [$inicio, $fim] = $this->periodo((string) $request->get('periodo', '30d'));

        $pedidos = $this->pedidos($empresaId, $inicio, $fim);
        $clientes = $this->clientes($empresaId, $inicio, $fim);
        $produtos = $this->produtos($empresaId);
        $estoque = $this->estoque($empresaId);
        $financeiro = $this->financeiro($empresaId, $inicio, $fim);

        $serie = $this->serieVendas($empresaId, $inicio, $fim);
        $ranking = $this->ranking($empresaId, $inicio, $fim, $key);
        $tabela = $this->tabela($empresaId, $inicio, $fim, $key);

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
            'colunas' => $tabela['colunas'],
            'linhas' => $tabela['linhas'],
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
            'todo' => Carbon::create(2000, 1, 1)->startOfDay(),
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
                ->select('p.id', 'p.estoque_minimo')
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

        $periodoSql = match (DB::connection()->getDriverName()) {
            'pgsql' => "TO_CHAR(data_pedido, 'YYYY-MM')",
            'mysql', 'mariadb' => "DATE_FORMAT(data_pedido, '%Y-%m')",
            default => "strftime('%Y-%m', data_pedido)",
        };

        $rows = DB::table('pedidos')
            ->where('empresa_id', $empresaId)
            ->whereBetween('data_pedido', [$inicio, $fim])
            ->whereNotIn('status', ['cancelado', 'cancelada'])
            ->selectRaw("{$periodoSql} as periodo, SUM(total) as valor")
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

        if ($key === 'vendedor' && Schema::hasTable('usuarios')) {
            $rows = DB::table('pedidos as p')
                ->leftJoin('usuarios as u', 'u.id', '=', 'p.vendedor_id')
                ->where('p.empresa_id', $empresaId)
                ->whereBetween('p.data_pedido', [$inicio, $fim])
                ->whereNotIn('p.status', ['cancelado', 'cancelada'])
                ->selectRaw("COALESCE(NULLIF(u.nome,''), 'Sem vendedor') as nome")
                ->selectRaw('COUNT(*) as quantidade, SUM(p.total) as valor')
                ->groupBy('nome')->orderByDesc('valor')->limit(8)->get();
        } elseif ($key === 'produtos' && Schema::hasTable('pedido_itens') && Schema::hasTable('produtos')) {
            $rows = DB::table('pedido_itens as pi')
                ->join('pedidos as p', 'p.id', '=', 'pi.pedido_id')
                ->join('produtos as pr', 'pr.id', '=', 'pi.produto_id')
                ->where('p.empresa_id', $empresaId)
                ->whereBetween('p.data_pedido', [$inicio, $fim])
                ->whereNotIn('p.status', ['cancelado', 'cancelada'])
                ->select('pr.nome')
                ->selectRaw('SUM(pi.quantidade) as quantidade, SUM(pi.total) as valor')
                ->groupBy('pr.id', 'pr.nome')->orderByDesc('valor')->limit(8)->get();
        } elseif (in_array($key, ['estoque', 'previsao', 'compras', 'semMovimento'], true)
            && Schema::hasTable('estoque') && Schema::hasTable('produtos')) {
            $rows = DB::table('estoque as e')
                ->join('produtos as pr', 'pr.id', '=', 'e.produto_id')
                ->where('e.empresa_id', $empresaId)
                ->select('pr.nome')
                ->selectRaw('SUM(e.quantidade_disponivel) as quantidade')
                ->selectRaw('SUM(e.quantidade_disponivel * e.custo_medio) as valor')
                ->groupBy('pr.id', 'pr.nome')
                ->orderByDesc($key === 'compras' || $key === 'previsao' ? 'quantidade' : 'valor')
                ->limit(8)->get();
        } elseif (in_array($key, ['clientes', 'recorrentes', 'carteira'], true) && Schema::hasTable('clientes')) {
            $rows = DB::table('pedidos as p')
                ->join('clientes as c', 'c.id', '=', 'p.cliente_id')
                ->where('p.empresa_id', $empresaId)
                ->whereBetween('p.data_pedido', [$inicio, $fim])
                ->whereNotIn('p.status', ['cancelado', 'cancelada'])
                ->selectRaw("COALESCE(NULLIF(c.nome_fantasia,''), c.razao_social) as nome")
                ->selectRaw('COUNT(*) as quantidade, SUM(p.total) as valor')
                ->groupBy('c.id', 'c.nome_fantasia', 'c.razao_social')
                ->orderByDesc('valor')->limit(8)->get();
        } else {
            $rows = DB::table('pedidos')
                ->where('empresa_id', $empresaId)
                ->whereBetween('data_pedido', [$inicio, $fim])
                ->whereNotIn('status', ['cancelado', 'cancelada'])
                ->selectRaw("COALESCE(NULLIF(marketplace,''), NULLIF(canal,''), 'Interno') as nome")
                ->selectRaw('COUNT(*) as quantidade, SUM(total) as valor')
                ->groupBy('nome')->orderByDesc('valor')->limit(8)->get();
        }

        $max = max(1, (float) ($rows->max('valor') ?? 1));

        return $rows->map(fn ($row) => [
            'nome' => $row->nome,
            'detalhe' => $this->numero($row->quantidade ?? 0) . ($key === 'produtos' ? ' unidades' : ($key === 'estoque' ? ' em estoque' : ' registros')),
            'valor' => $this->moeda((float) $row->valor),
            'percentual' => round(((float) $row->valor / $max) * 100),
        ])->values()->all();
    }

    private function tabela(int $empresaId, Carbon $inicio, Carbon $fim, string $key): array
    {
        if (!Schema::hasTable('pedidos')) {
            return ['colunas' => [], 'linhas' => []];
        }

        if (in_array($key, ['produtos', 'estoque', 'previsao', 'compras', 'semMovimento'], true)
            && Schema::hasTable('produtos') && Schema::hasTable('estoque')) {
            $linhas = DB::table('produtos as pr')
                ->leftJoin('estoque as e', 'e.produto_id', '=', 'pr.id')
                ->where('pr.empresa_id', $empresaId)
                ->select('pr.codigo', 'pr.nome')
                ->selectRaw('COALESCE(SUM(e.quantidade_disponivel), 0) as disponivel')
                ->selectRaw('COALESCE(SUM(e.quantidade_disponivel * e.custo_medio), 0) as valor')
                ->groupBy('pr.id', 'pr.codigo', 'pr.nome')
                ->orderByDesc($key === 'semMovimento' ? 'disponivel' : 'valor')
                ->limit(20)->get()
                ->map(fn ($row) => [
                    $row->codigo ?: '—', $row->nome, $this->numero((float) $row->disponivel), $this->moeda((float) $row->valor),
                ])->all();

            return ['colunas' => ['Código', 'Produto', 'Disponível', 'Valor em estoque'], 'linhas' => $linhas];
        }

        if (in_array($key, ['clientes', 'recorrentes', 'carteira'], true) && Schema::hasTable('clientes')) {
            $linhas = DB::table('clientes as c')
                ->leftJoin('pedidos as p', function ($join) use ($inicio, $fim) {
                    $join->on('p.cliente_id', '=', 'c.id')
                        ->whereBetween('p.data_pedido', [$inicio, $fim])
                        ->whereNotIn('p.status', ['cancelado', 'cancelada']);
                })
                ->where('c.empresa_id', $empresaId)
                ->select('c.razao_social', 'c.cidade', 'c.estado')
                ->selectRaw('COUNT(p.id) as pedidos, COALESCE(SUM(p.total), 0) as valor')
                ->groupBy('c.id', 'c.razao_social', 'c.cidade', 'c.estado')
                ->orderByDesc('valor')->limit(20)->get()
                ->map(fn ($row) => [
                    $row->razao_social, trim(($row->cidade ?: '—') . ' / ' . ($row->estado ?: '—')), $this->numero($row->pedidos), $this->moeda((float) $row->valor),
                ])->all();

            return ['colunas' => ['Cliente', 'Cidade/UF', 'Pedidos', 'Faturamento'], 'linhas' => $linhas];
        }

        if ($key === 'vendedor' && Schema::hasTable('usuarios')) {
            $linhas = DB::table('usuarios as u')
                ->leftJoin('pedidos as p', function ($join) use ($inicio, $fim) {
                    $join->on('p.vendedor_id', '=', 'u.id')
                        ->whereBetween('p.data_pedido', [$inicio, $fim])
                        ->whereNotIn('p.status', ['cancelado', 'cancelada']);
                })
                ->where('u.empresa_id', $empresaId)
                ->select('u.nome')
                ->selectRaw('COUNT(p.id) as pedidos, COALESCE(SUM(p.total), 0) as valor')
                ->groupBy('u.id', 'u.nome')->orderByDesc('valor')->limit(20)->get()
                ->map(fn ($row) => [
                    $row->nome, $this->numero($row->pedidos), $this->moeda((float) $row->valor),
                    $this->moeda($row->pedidos > 0 ? (float) $row->valor / $row->pedidos : 0),
                ])->all();

            return ['colunas' => ['Vendedor', 'Pedidos', 'Faturamento', 'Ticket médio'], 'linhas' => $linhas];
        }

        $linhas = DB::table('pedidos')
            ->where('empresa_id', $empresaId)
            ->whereBetween('data_pedido', [$inicio, $fim])
            ->orderByDesc('data_pedido')
            ->limit(20)
            ->get(['numero', 'canal', 'total', 'status'])
            ->map(fn ($row) => [
                $row->numero,
                $row->canal ?: 'interno',
                $this->moeda((float) $row->total),
                $row->status,
            ])
            ->all();

        return ['colunas' => ['Pedido', 'Canal', 'Valor', 'Status'], 'linhas' => $linhas];
    }

    private function cards(
        string $key,
        array $pedidos,
        array $clientes,
        array $produtos,
        array $estoque,
        array $financeiro
    ): array {
        $default = [
            ['label' => 'Faturamento', 'valor' => $this->moeda($pedidos['valor']), 'variacao' => 'Dados reais'],
            ['label' => 'Pedidos', 'valor' => $this->numero($pedidos['total']), 'variacao' => 'No período'],
            ['label' => 'Ticket médio', 'valor' => $this->moeda($pedidos['ticket_medio']), 'variacao' => 'Pedidos válidos'],
            ['label' => 'Clientes ativos', 'valor' => $this->numero($clientes['ativos']), 'variacao' => 'Cadastro'],
        ];

        return match ($key) {
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

            default => $default,
        };
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
