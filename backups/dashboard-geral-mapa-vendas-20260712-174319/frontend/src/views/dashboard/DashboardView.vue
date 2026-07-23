<template>
  <div class="space-y-6 animate-fade-in">

    <!-- ── Header ──────────────────────────────────────────── -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">
          {{ saudacao }}, {{ auth.usuario?.nome?.split(' ')[0] }} · {{ dataHoje }}
        </p>
      </div>
      <div class="flex items-center gap-2">
        <select v-model="periodoSelecionado" class="select w-36 text-xs" @change="recarregar">
          <option value="hoje">Hoje</option>
          <option value="semana">Esta semana</option>
          <option value="mes">Este mês</option>
          <option value="ano">Este ano</option>
        </select>
        <button @click="recarregar" class="btn-secondary btn-sm" :disabled="loading">
          <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
        </button>
      </div>
    </div>

    <!-- ── Alertas ──────────────────────────────────────────── -->
    <div v-if="alertas.length" class="grid gap-2">
      <div
        v-for="alerta in alertas" :key="alerta.tipo"
        :class="['flex items-center gap-3 px-4 py-3 rounded-xl text-sm border',
          alerta.nivel === 'danger'
            ? 'bg-danger/10 border-danger/30 text-danger'
            : 'bg-warning/10 border-warning/30 text-warning']"
      >
        <ExclamationTriangleIcon class="w-4 h-4 flex-shrink-0" />
        <span>{{ alerta.mensagem }}</span>
        <RouterLink :to="linkAlerta(alerta.tipo)"
          class="ml-auto text-xs underline opacity-70 hover:opacity-100">
          Ver
        </RouterLink>
      </div>
    </div>

    <!-- ── KPIs Principais ──────────────────────────────────── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <StatCard
        label="Vendas Hoje"
        :value="fmt.moeda(dash?.vendas?.hoje_valor)"
        :sub="`${dash?.vendas?.hoje_pedidos || 0} pedidos`"
        cor="brand"
        :icon="ShoppingBagIcon"
        :loading="loading"
      />
      <StatCard
        label="Vendas do Mês"
        :value="fmt.moeda(dash?.vendas?.mes_valor)"
        :trend="dash?.vendas?.crescimento_pct"
        cor="green"
        :icon="ChartBarIcon"
        :loading="loading"
      />
      <StatCard
        label="A Receber"
        :value="fmt.moeda(dash?.financeiro?.a_receber_total)"
        :sub="dash?.financeiro?.a_receber_vencido > 0 ? `${fmt.moeda(dash.financeiro.a_receber_vencido)} vencido` : undefined"
        :subCor="dash?.financeiro?.a_receber_vencido > 0 ? 'danger' : undefined"
        cor="blue"
        :icon="BanknotesIcon"
        :loading="loading"
      />
      <StatCard
        label="Saldo em Bancos"
        :value="fmt.moeda(dash?.financeiro?.saldo_bancos)"
        cor="purple"
        :icon="BuildingLibraryIcon"
        :loading="loading"
      />
    </div>

    <!-- ── Segunda linha de KPIs ────────────────────────────── -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <StatCard
        label="Clientes"
        :value="fmt.numero(dash?.clientes?.total)"
        :sub="`+${dash?.clientes?.novos_mes || 0} este mês`"
        cor="teal"
        :icon="UsersIcon"
        :loading="loading"
      />
      <StatCard
        label="Produtos"
        :value="fmt.numero(dash?.estoque?.total_produtos)"
        :sub="dash?.estoque?.abaixo_minimo > 0 ? `${dash.estoque.abaixo_minimo} abaixo do mínimo` : 'Estoque OK'"
        :subCor="dash?.estoque?.abaixo_minimo > 0 ? 'warning' : 'success'"
        cor="orange"
        :icon="CubeIcon"
        :loading="loading"
      />
      <StatCard
        label="OS Abertas"
        :value="fmt.numero(dash?.os?.abertas)"
        :sub="dash?.os?.vencidas > 0 ? `${dash.os.vencidas} atrasadas` : 'Em dia'"
        :subCor="dash?.os?.vencidas > 0 ? 'danger' : 'success'"
        cor="red"
        :icon="WrenchScrewdriverIcon"
        :loading="loading"
      />
      <StatCard
        label="Ticket Médio"
        :value="fmt.moeda(dash?.vendas?.mes_ticket_medio)"
        :sub="`${dash?.vendas?.mes_pedidos || 0} pedidos no mês`"
        cor="pink"
        :icon="ReceiptPercentIcon"
        :loading="loading"
      />
    </div>

    <!-- ── Gráficos ─────────────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

      <!-- Gráfico Vendas Mensal -->
      <div class="card p-5 lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-[var(--text-primary)]">Vendas por Mês</h3>
          <span class="text-xs text-[var(--text-muted)]">{{ anoAtual }}</span>
        </div>
        <div class="h-52">
          <VueApexCharts
            v-if="dadosGraficoVendas.length"
            type="area"
            :height="208"
            :options="opcoesGrafico"
            :series="seriesGrafico"
          />
          <SkeletonChart v-else-if="loading" />
          <div v-else class="empty-state h-full">
            <p class="text-xs text-[var(--text-muted)]">Sem dados de vendas</p>
          </div>
        </div>
      </div>

      <!-- Top Produtos -->
      <div class="card p-5">
        <h3 class="text-sm font-semibold text-[var(--text-primary)] mb-4">Top Produtos do Mês</h3>
        <div class="space-y-3">
          <template v-if="loading">
            <div v-for="i in 5" :key="i" class="skeleton h-9 rounded-lg" />
          </template>
          <template v-else>
            <div
              v-for="(prod, i) in topProdutos.slice(0, 6)"
              :key="prod.id"
              class="flex items-center gap-3"
            >
              <span class="text-xs font-bold text-[var(--text-muted)] w-4">{{ i + 1 }}</span>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-[var(--text-primary)] truncate">{{ prod.nome }}</p>
                <div class="flex items-center gap-2 mt-0.5">
                  <div class="h-1.5 rounded-full bg-[var(--border)] flex-1">
                    <div
                      class="h-1.5 rounded-full bg-brand-500"
                      :style="{ width: `${(prod.receita / topProdutos[0]?.receita) * 100}%` }"
                    />
                  </div>
                  <span class="text-[10px] text-[var(--text-muted)] w-16 text-right">
                    {{ fmt.moeda(prod.receita) }}
                  </span>
                </div>
              </div>
            </div>
            <div v-if="!topProdutos.length" class="text-center text-xs text-[var(--text-muted)] py-4">
              Sem dados
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- ── Linha 3: Canais + Últimos Pedidos ─────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

      <!-- Vendas por Canal -->
      <div class="card p-5">
        <h3 class="text-sm font-semibold text-[var(--text-primary)] mb-4">Vendas por Canal</h3>
        <div class="space-y-3">
          <template v-if="loading">
            <div v-for="i in 4" :key="i" class="skeleton h-8 rounded-lg" />
          </template>
          <div
            v-else
            v-for="canal in vendaCanal"
            :key="canal.canal"
            class="flex items-center justify-between gap-3"
          >
            <div class="flex items-center gap-2">
              <div :class="['w-2 h-2 rounded-full', corCanal(canal.canal)]" />
              <span class="text-xs text-[var(--text-secondary)] capitalize">{{ canal.canal || 'Interno' }}</span>
            </div>
            <div class="text-right">
              <p class="text-xs font-semibold text-[var(--text-primary)]">{{ fmt.moeda(canal.valor) }}</p>
              <p class="text-[10px] text-[var(--text-muted)]">{{ canal.qtde }} pedidos</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Últimos Pedidos -->
      <div class="card lg:col-span-2 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-[var(--border)]">
          <h3 class="text-sm font-semibold text-[var(--text-primary)]">Últimos Pedidos</h3>
          <RouterLink to="/pedidos" class="text-xs text-brand-400 hover:text-brand-300">
            Ver todos →
          </RouterLink>
        </div>
        <div class="overflow-x-auto">
          <table class="table">
            <thead>
              <tr>
                <th>Número</th>
                <th>Cliente</th>
                <th>Canal</th>
                <th>Status</th>
                <th class="text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="loading">
                <tr v-for="i in 6" :key="i">
                  <td colspan="5"><div class="skeleton h-6 rounded" /></td>
                </tr>
              </template>
              <tr v-else v-for="pedido in ultimosPedidos" :key="pedido.id"
                @click="$router.push(`/pedidos/${pedido.id}`)" class="cursor-pointer">
                <td class="font-mono text-xs font-semibold text-brand-400">#{{ pedido.numero }}</td>
                <td class="max-w-[140px]">
                  <p class="truncate text-xs">{{ pedido.cliente_nome }}</p>
                </td>
                <td>
                  <span :class="['badge', badgeCanal(pedido.canal)]">{{ pedido.canal }}</span>
                </td>
                <td><BadgeStatus :status="pedido.status" /></td>
                <td class="text-right font-semibold text-xs">{{ fmt.moeda(pedido.total) }}</td>
              </tr>
              <tr v-if="!loading && !ultimosPedidos.length">
                <td colspan="5" class="text-center text-xs text-[var(--text-muted)] py-8">
                  Nenhum pedido encontrado
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import VueApexCharts from 'vue3-apexcharts'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import { useFormato } from '@/composables/useFormato'
import StatCard from '@/components/ui/StatCard.vue'
import BadgeStatus from '@/components/ui/BadgeStatus.vue'
import SkeletonChart from '@/components/ui/SkeletonChart.vue'
import {
  ShoppingBagIcon, ChartBarIcon, BanknotesIcon, BuildingLibraryIcon,
  UsersIcon, CubeIcon, WrenchScrewdriverIcon, ReceiptPercentIcon,
  ArrowPathIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const auth      = useAuthStore()
const dashboard = useDashboardStore()
const fmt       = useFormato()

const loading           = ref(false)
const periodoSelecionado = ref('mes')
const anoAtual          = new Date().getFullYear()

const dash          = computed(() => dashboard.dados)
const alertas       = computed(() => dashboard.alertas ?? [])
const topProdutos   = computed(() => dashboard.dados?.top_produtos ?? [])
const ultimosPedidos = computed(() => dashboard.dados?.ultimos_pedidos ?? [])
const vendaCanal    = computed(() => dashboard.dados?.vendas?.por_canal ?? [])
const dadosGraficoVendas = computed(() => dashboard.dados?.grafico_vendas ?? [])

const saudacao = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Bom dia'
  if (h < 18) return 'Boa tarde'
  return 'Boa noite'
})
const dataHoje = computed(() =>
  new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' })
)

const seriesGrafico = computed(() => [{
  name: 'Vendas',
  data: dadosGraficoVendas.value.map(d => parseFloat(d.valor) || 0),
}])

const opcoesGrafico = computed(() => ({
  chart: { type: 'area', toolbar: { show: false }, background: 'transparent' },
  theme: { mode: 'dark' },
  colors: ['#6366f1'],
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0, stops: [0, 100] } },
  stroke: { width: 2, curve: 'smooth' },
  xaxis: {
    categories: dadosGraficoVendas.value.map(d => d.label),
    labels: { style: { colors: '#64748b', fontSize: '11px' } },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: { labels: { formatter: v => fmt.moedaCompacto(v), style: { colors: '#64748b', fontSize: '11px' } } },
  grid: { borderColor: '#1e1e35', strokeDashArray: 3 },
  tooltip: { theme: 'dark', y: { formatter: v => fmt.moeda(v) } },
  dataLabels: { enabled: false },
}))

function corCanal(canal) {
  return { interno: 'bg-brand-500', marketplace: 'bg-green-500', ecommerce: 'bg-orange-500', whatsapp: 'bg-teal-500' }[canal] || 'bg-gray-500'
}
function badgeCanal(canal) {
  return { interno: 'badge-brand', marketplace: 'badge-success', ecommerce: 'badge-warning', whatsapp: 'badge-info' }[canal] || 'badge-gray'
}
function linkAlerta(tipo) {
  return { estoque: '/estoque', financeiro: '/financeiro/contas-receber', os: '/os' }[tipo] || '/dashboard'
}

async function recarregar() {
  loading.value = true
  try { await dashboard.carregar() }
  finally { loading.value = false }
}

onMounted(recarregar)
</script>
