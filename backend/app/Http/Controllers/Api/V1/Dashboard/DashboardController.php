<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\{Pedido, Cliente, Produto, ContaReceber, ContaPagar, OrdemServico};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Cache, Schema};

class DashboardController extends Controller
{
    // ── GET /dashboard ────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $cacheKey  = "dashboard.{$empresaId}." . now()->format('YmdHi'); // Cache 1 minuto

        $data = Cache::remember($cacheKey, 60, function () use ($empresaId) {
            return [
                'vendas'      => $this->kpiVendas($empresaId),
                'financeiro'  => $this->kpiFinanceiro($empresaId),
                'estoque'     => $this->kpiEstoque($empresaId),
                'clientes'    => $this->kpiClientes($empresaId),
                'os'          => $this->kpiOS($empresaId),
                'grafico_vendas' => $this->graficoVendasMensais($empresaId),
                'top_produtos'   => $this->topProdutos($empresaId),
                'ultimos_pedidos'=> $this->ultimosPedidos($empresaId),
                'alertas'        => $this->alertas($empresaId),
            ];
        });

        return response()->json($data);
    }

    // ── GET /dashboard/mapa ───────────────────────────────────

    public function mapa(Request $request): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;

        $clientes = DB::table('clientes')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'razao_social as nome', 'cidade', 'estado', 'latitude', 'longitude',
                DB::raw("'cliente' as tipo"), 'ativo')
            ->get();

        $tecnicos = collect();
        if (Schema::hasTable('funcionarios')) {
            $tecnicos = DB::table('usuarios as u')
                ->join('funcionarios as f', 'f.usuario_id', '=', 'u.id')
                ->where('u.empresa_id', $empresaId)
                ->whereNotNull('f.latitude')
                ->whereNotNull('f.longitude')
                ->select('u.id', 'u.nome', DB::raw("'' as cidade"), DB::raw("'' as estado"),
                    'f.latitude', 'f.longitude', DB::raw("'tecnico' as tipo"))
                ->get();
        } elseif (Schema::hasTable('platform_cadastros')) {
            $tecnicos = DB::table('platform_cadastros')
                ->where('empresa_id', $empresaId)
                ->whereIn('tipo_perfil', ['tecnico', 'colaborador'])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->whereNull('deleted_at')
                ->select('id', 'nome', 'cidade', 'estado', 'latitude', 'longitude',
                    DB::raw("tipo_perfil as tipo"))
                ->get();
        }

        $pedidosEntrega = DB::table('pedidos as p')
            ->join('clientes as c', 'c.id', '=', 'p.cliente_id')
            ->where('p.empresa_id', $empresaId)
            ->where('p.status_entrega', 'em_transito')
            ->whereNotNull('c.latitude')
            ->select('p.id', 'p.numero',
                'c.razao_social as nome', 'c.cidade', 'c.estado',
                'c.latitude', 'c.longitude',
                DB::raw("'entrega' as tipo"))
            ->limit(200)
            ->get();

        return response()->json([
            'clientes'  => $clientes,
            'tecnicos'  => $tecnicos,
            'entregas'  => $pedidosEntrega,
        ]);
    }

    public function resumo(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    // ── GET /dashboard/vendas ─────────────────────────────────

    public function vendas(Request $request): JsonResponse
    {
        $empresaId = auth()->user()->empresa_id;
        $periodo   = $request->get('periodo', 'mensal');
        $ano       = $request->get('ano', now()->year);

        $query = DB::table('pedidos')
            ->where('empresa_id', $empresaId)
            ->whereNotIn('status', ['cancelado', 'cancelada'])
            ->whereYear('data_pedido', $ano);

        $dados = match ($periodo) {
            'diario'  => $query->select(
                    DB::raw('DATE(data_pedido) as label'),
                    DB::raw('count(*) as pedidos'),
                    DB::raw('sum(total) as valor')
                )->whereMonth('data_pedido', now()->month)
                ->groupBy('label')->orderBy('label')->get(),

            // Agrupamento por mês/ano feito em PHP (em vez de TO_CHAR/EXTRACT do
            // Postgres) para funcionar igual em SQLite — o volume de pedidos de
            // um ano é pequeno o suficiente para isso não pesar.
            'mensal'  => $query->select('data_pedido', 'total')->get()
                ->groupBy(fn ($p) => \Carbon\Carbon::parse($p->data_pedido)->format('m/Y'))
                ->map(fn ($grupo, $label) => [
                    'label'   => $label,
                    'mes'     => \Carbon\Carbon::parse($grupo->first()->data_pedido)->month,
                    'pedidos' => $grupo->count(),
                    'valor'   => round((float) $grupo->sum('total'), 2),
                ])
                ->sortBy('mes')->values(),

            'anual'   => DB::table('pedidos')
                ->where('empresa_id', $empresaId)
                ->whereNotIn('status', ['cancelado', 'cancelada'])
                ->select('data_pedido', 'total')->get()
                ->groupBy(fn ($p) => \Carbon\Carbon::parse($p->data_pedido)->year)
                ->map(fn ($grupo, $ano) => [
                    'label'   => (int) $ano,
                    'pedidos' => $grupo->count(),
                    'valor'   => round((float) $grupo->sum('total'), 2),
                ])
                ->sortBy('label')->values(),

            default => collect(),
        };

        return response()->json($dados);
    }

    // ──────────────────────────────────────────────────────────
    // KPI Helpers
    // ──────────────────────────────────────────────────────────

    private function kpiVendas(int $empresaId): array
    {
        $hoje = DB::table('pedidos')
            ->where('empresa_id', $empresaId)
            ->whereDate('data_pedido', today())
            ->whereNotIn('status', ['cancelado', 'cancelada']);

        $mes = DB::table('pedidos')
            ->where('empresa_id', $empresaId)
            ->whereMonth('data_pedido', now()->month)
            ->whereYear('data_pedido', now()->year)
            ->whereNotIn('status', ['cancelado', 'cancelada']);

        $mesAnterior = DB::table('pedidos')
            ->where('empresa_id', $empresaId)
            ->whereMonth('data_pedido', now()->subMonth()->month)
            ->whereYear('data_pedido', now()->subMonth()->year)
            ->whereNotIn('status', ['cancelado', 'cancelada'])
            ->sum('total');

        $valorMes = (clone $mes)->sum('total');
        $crescimento = $mesAnterior > 0
            ? round((($valorMes - $mesAnterior) / $mesAnterior) * 100, 1)
            : 0;

        return [
            'hoje_pedidos'     => (clone $hoje)->count(),
            'hoje_valor'       => (clone $hoje)->sum('total'),
            'mes_pedidos'      => (clone $mes)->count(),
            'mes_valor'        => $valorMes,
            'mes_ticket_medio' => (clone $mes)->avg('total') ?? 0,
            'crescimento_pct'  => $crescimento,
            'por_canal'        => DB::table('pedidos')
                ->where('empresa_id', $empresaId)
                ->whereMonth('data_pedido', now()->month)
                ->whereYear('data_pedido', now()->year)
                ->whereNotIn('status', ['cancelado', 'cancelada'])
                ->select('canal', DB::raw('count(*) as qtde'), DB::raw('sum(total) as valor'))
                ->groupBy('canal')
                ->get(),
        ];
    }

    private function kpiFinanceiro(int $empresaId): array
    {
        $receber = DB::table('contas_receber')
            ->where('empresa_id', $empresaId)
            ->where('status', 'aberto');

        $pagar = DB::table('contas_pagar')
            ->where('empresa_id', $empresaId)
            ->where('status', 'aberto');

        return [
            'a_receber_total'    => (clone $receber)->sum('valor'),
            'a_receber_vencido'  => (clone $receber)->where('data_vencimento', '<', today())->sum('valor'),
            'a_receber_hoje'     => (clone $receber)->whereDate('data_vencimento', today())->sum('valor'),
            'a_pagar_total'      => (clone $pagar)->sum('valor'),
            'a_pagar_vencido'    => (clone $pagar)->where('data_vencimento', '<', today())->sum('valor'),
            'a_pagar_hoje'       => (clone $pagar)->whereDate('data_vencimento', today())->sum('valor'),
            'saldo_bancos'       => DB::table('contas_bancarias')
                ->where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->sum('saldo_atual'),
        ];
    }

    private function kpiEstoque(int $empresaId): array
    {
        return [
            'total_produtos'  => DB::table('produtos')
                ->where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->whereNull('deleted_at')
                ->count(),
            'abaixo_minimo'   => DB::table('estoque as e')
                ->join('produtos as p', 'p.id', '=', 'e.produto_id')
                ->where('p.empresa_id', $empresaId)
                ->where('p.controla_estoque', true)
                ->whereNull('p.deleted_at')
                ->whereRaw('e.quantidade_disponivel <= p.estoque_minimo')
                ->count(),
            'valor_total'     => DB::table('estoque as e')
                ->join('produtos as p', 'p.id', '=', 'e.produto_id')
                ->where('p.empresa_id', $empresaId)
                ->whereNull('p.deleted_at')
                ->sum(DB::raw('e.quantidade_disponivel * e.custo_medio')),
            'sem_estoque'     => DB::table('estoque as e')
                ->join('produtos as p', 'p.id', '=', 'e.produto_id')
                ->where('p.empresa_id', $empresaId)
                ->where('p.controla_estoque', true)
                ->whereNull('p.deleted_at')
                ->where('e.quantidade_disponivel', '<=', 0)
                ->count(),
        ];
    }

    private function kpiClientes(int $empresaId): array
    {
        return [
            'total'        => DB::table('clientes')->where('empresa_id', $empresaId)->where('ativo', true)->count(),
            'novos_mes'    => DB::table('clientes')
                ->where('empresa_id', $empresaId)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'inadimplentes'=> DB::table('contas_receber')
                ->where('empresa_id', $empresaId)
                ->where('status', 'aberto')
                ->where('data_vencimento', '<', today())
                ->distinct('cliente_id')
                ->count('cliente_id'),
        ];
    }

    private function kpiOS(int $empresaId): array
    {
        $os = DB::table('ordens_servico as os')
            ->join('os_status as st', 'st.id', '=', 'os.status_id')
            ->where('os.empresa_id', $empresaId);

        return [
            'abertas'   => (clone $os)->where('st.tipo', 'andamento')->count(),
            'hoje'      => (clone $os)->whereDate('os.created_at', today())->count(),
            'vencidas'  => (clone $os)->where('os.data_previsao', '<', today())
                ->where('st.tipo', 'andamento')->count(),
            'concluidas_mes' => (clone $os)->whereIn('st.tipo', ['concluido', 'entregue'])
                ->whereMonth('os.data_conclusao', now()->month)
                ->whereYear('os.data_conclusao', now()->year)
                ->count(),
        ];
    }

    private function graficoVendasMensais(int $empresaId): array
    {
        // Agrupamento em PHP (Carbon) em vez de EXTRACT/TO_CHAR do Postgres,
        // para funcionar da mesma forma em SQLite.
        $meses = [];
        foreach (range(1, 12) as $m) {
            $meses[$m] = [
                'mes'     => $m,
                'label'   => \Carbon\Carbon::create()->month($m)->translatedFormat('M'),
                'valor'   => 0.0,
                'pedidos' => 0,
            ];
        }

        DB::table('pedidos')
            ->where('empresa_id', $empresaId)
            ->whereYear('data_pedido', now()->year)
            ->whereNotIn('status', ['cancelado', 'cancelada'])
            ->select('data_pedido', 'total')
            ->get()
            ->groupBy(fn ($p) => \Carbon\Carbon::parse($p->data_pedido)->month)
            ->each(function ($grupo, $mes) use (&$meses) {
                $meses[(int) $mes]['valor']   = round((float) $grupo->sum('total'), 2);
                $meses[(int) $mes]['pedidos'] = $grupo->count();
            });

        return array_values($meses);
    }

    private function topProdutos(int $empresaId): array
    {
        return DB::table('pedido_itens as pi')
            ->join('pedidos as p', 'p.id', '=', 'pi.pedido_id')
            ->join('produtos as pr', 'pr.id', '=', 'pi.produto_id')
            ->where('p.empresa_id', $empresaId)
            ->whereMonth('p.data_pedido', now()->month)
            ->whereYear('p.data_pedido', now()->year)
            ->whereNotIn('p.status', ['cancelado', 'cancelada'])
            ->select(
                'pr.id', 'pr.nome', 'pr.codigo',
                DB::raw('sum(pi.quantidade) as qtde_vendida'),
                DB::raw('sum(pi.total) as receita')
            )
            ->groupBy('pr.id', 'pr.nome', 'pr.codigo')
            ->orderByDesc('receita')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function ultimosPedidos(int $empresaId): array
    {
        return DB::table('pedidos as p')
            ->join('clientes as c', 'c.id', '=', 'p.cliente_id')
            ->where('p.empresa_id', $empresaId)
            ->select('p.id', 'p.numero', 'p.status', 'p.total', 'p.data_pedido', 'p.canal',
                'c.razao_social as cliente_nome')
            ->orderByDesc('p.data_pedido')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function alertas(int $empresaId): array
    {
        $alertas = [];

        // Estoque baixo
        $baixo = DB::table('estoque as e')
            ->join('produtos as p', 'p.id', '=', 'e.produto_id')
            ->where('p.empresa_id', $empresaId)
            ->where('p.controla_estoque', true)
            ->whereNull('p.deleted_at')
            ->whereRaw('e.quantidade_disponivel <= p.estoque_minimo AND e.quantidade_disponivel > 0')
            ->count();

        if ($baixo > 0) {
            $alertas[] = ['tipo' => 'estoque', 'nivel' => 'warning',
                'mensagem' => "{$baixo} produto(s) abaixo do estoque mínimo"];
        }

        // Contas vencidas
        $vencidas = DB::table('contas_receber')
            ->where('empresa_id', $empresaId)
            ->where('status', 'aberto')
            ->where('data_vencimento', '<', today())
            ->count();

        if ($vencidas > 0) {
            $alertas[] = ['tipo' => 'financeiro', 'nivel' => 'danger',
                'mensagem' => "{$vencidas} conta(s) a receber vencida(s)"];
        }

        // OS atrasadas
        $osAtrasadas = DB::table('ordens_servico as os')
            ->join('os_status as st', 'st.id', '=', 'os.status_id')
            ->where('os.empresa_id', $empresaId)
            ->where('st.tipo', 'andamento')
            ->where('os.data_previsao', '<', today())
            ->count();

        if ($osAtrasadas > 0) {
            $alertas[] = ['tipo' => 'os', 'nivel' => 'warning',
                'mensagem' => "{$osAtrasadas} ordem(s) de serviço atrasada(s)"];
        }

        return $alertas;
    }
}
