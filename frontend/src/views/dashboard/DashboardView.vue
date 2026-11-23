<template>
  <div class="dashboard-page space-y-6 animate-fade-in">

    <!-- ── Header ──────────────────────────────────────────── -->
    <div class="page-header dashboard-page-header">
      <div>
        <h1 class="page-title">Dashboard Geral</h1>
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
    <div class="dashboard-kpi-row grid grid-cols-2 lg:grid-cols-4 gap-4">
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
    <div class="dashboard-kpi-row grid grid-cols-2 lg:grid-cols-4 gap-4">
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

    <!-- ── Mapa de origem das vendas ─────────────────────────── -->
    <div class="card p-5 dashboard-map-section">
      <div class="mb-4 dashboard-map-title">
        <div>
          <h3 class="text-sm font-semibold text-[var(--text-primary)]">Origem geográfica das vendas</h3>
          <p class="text-xs text-[var(--text-muted)] mt-1">
            Clientes localizados pelo CEP, com pedidos agrupados por região.
          </p>
        </div>

      </div>

      <div class="dashboard-map-grid">
        <div class="dashboard-map-fixed-zone">
        <div v-show="mapaMobileVisivel" class="dashboard-map-wrapper">
          <button class="dashboard-map-close" type="button" aria-label="Recolher mapa" @click="mapaMobileVisivel = false">
            <XMarkIcon class="w-5 h-5"/>
          </button>
          <div ref="mapContainer" class="dashboard-sales-map"></div>
          <div v-if="mapError" class="dashboard-map-error">
            <ExclamationTriangleIcon class="w-8 h-8"/>
            <strong>Não foi possível carregar o mapa</strong>
            <span>{{ mapError }}</span>
            <button class="btn-primary btn-sm" @click="reiniciarMapa">Tentar novamente</button>
          </div>
        </div>

        <div class="dashboard-map-filters">
          <div class="map-filter-heading">
            <strong>Filtragem e resumo</strong>
            <span>Selecione um resultado abaixo para localizar no mapa.</span>
          </div>
          <div class="map-kpi"><strong>{{ mapaResumo.clientes || 0 }}</strong><span>clientes</span></div>
          <div class="map-kpi"><strong>{{ mapaResumo.pedidos || 0 }}</strong><span>pedidos</span></div>
          <div class="map-kpi"><strong>{{ fmt.moeda(mapaResumo.valor || 0) }}</strong><span>em vendas</span></div>
          <button v-if="!mapaMobileVisivel" class="btn-secondary btn-sm dashboard-map-show" @click="mostrarMapaMobile">
            <MapIcon class="w-4 h-4"/> Exibir mapa
          </button>
          <button
            class="btn-secondary btn-sm dashboard-map-refresh"
            type="button"
            title="Atualizar CEPs"
            aria-label="Atualizar CEPs"
            :disabled="sincronizandoMapa"
            @click="sincronizarCeps"
          >
            <MapPinIcon class="w-4 h-4"/>
            {{ sincronizandoMapa ? 'Localizando...' : 'Atualizar CEPs' }}
          </button>
        </div>
        </div>

        <div class="dashboard-map-ranking">
          <div class="flex items-center justify-between mb-3">
            <div>
              <h4 class="text-xs font-semibold">Principais origens</h4>
              <p class="text-[10px] text-[var(--text-muted)]">{{ mapaPontos.length }} de {{ mapaTotalResultados }} clientes carregados</p>
            </div>
            <span class="badge badge-brand">{{ mapaResumo.estados || 0 }} estados</span>
          </div>

          <div v-if="loadingMapa" class="space-y-2">
            <div v-for="i in 6" :key="i" class="skeleton h-12 rounded-lg"></div>
          </div>

          <div v-else class="dashboard-ranking-list space-y-2" @scroll.passive="rolarRankingMapa">
            <button
              v-for="(ponto,index) in mapaPontos"
              :key="ponto.id"
              class="map-ranking-item"
              @click="focarPonto(ponto)"
            >
              <span class="map-ranking-position">{{ index+1 }}</span>
              <div class="min-w-0 flex-1 text-left">
                <strong class="block text-xs truncate">{{ ponto.nome }}</strong>
                <span class="block text-[10px] text-[var(--text-muted)] truncate">
                  {{ ponto.cidade }} / {{ ponto.estado }} · CEP {{ ponto.cep || 'não informado' }}
                </span>
              </div>
              <div class="text-right">
                <strong class="block text-xs">{{ fmt.moeda(ponto.valor) }}</strong>
                <span class="block text-[10px] text-[var(--text-muted)]">{{ ponto.pedidos }} pedidos</span>
              </div>
            </button>

            <button
              v-if="mapaTemMais"
              type="button"
              class="btn-secondary btn-sm w-full justify-center"
              :disabled="carregandoMaisMapa"
              @click="carregarMaisMapa"
            >
              <ArrowPathIcon class="w-4 h-4" :class="carregandoMaisMapa && 'animate-spin'" />
              {{ carregandoMaisMapa ? 'Carregando...' : `Carregar mais (${mapaPontos.length} de ${mapaTotalResultados})` }}
            </button>

            <p v-else-if="mapaPontos.length" class="py-2 text-center text-[10px] text-[var(--text-muted)]">
              Todos os {{ mapaTotalResultados }} resultados foram carregados.
            </p>

            <div v-if="!mapaPontos.length" class="empty-state py-10">
              <MapPinIcon class="w-8 h-8 text-[var(--text-muted)]"/>
              <p class="text-xs text-[var(--text-muted)] mt-2">
                Nenhum cliente com venda e coordenadas disponíveis.
              </p>
              <button class="btn-primary btn-sm mt-3" @click="sincronizarCeps">Localizar clientes pelos CEPs</button>
            </div>
          </div>
        </div>
      </div>

      <p class="text-[10px] text-[var(--text-muted)] mt-3">
        Mapa: OpenStreetMap. As coordenadas são obtidas a partir dos CEPs cadastrados dos clientes.
      </p>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { RouterLink } from 'vue-router'
import VueApexCharts from 'vue3-apexcharts'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import { useFormato } from '@/composables/useFormato'
import StatCard from '@/components/ui/StatCard.vue'
import BadgeStatus from '@/components/ui/BadgeStatus.vue'
import SkeletonChart from '@/components/ui/SkeletonChart.vue'
import {
  ShoppingBagIcon, ChartBarIcon, BanknotesIcon, BuildingLibraryIcon,
  UsersIcon, CubeIcon, WrenchScrewdriverIcon, ReceiptPercentIcon,
  ArrowPathIcon, ExclamationTriangleIcon, MapPinIcon, MapIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const auth      = useAuthStore()
const dashboard = useDashboardStore()
const fmt       = useFormato()

const loading           = ref(false)
const periodoSelecionado = ref('mes')
const anoAtual          = new Date().getFullYear()

const mapContainer = ref(null)
const loadingMapa = ref(false)
const sincronizandoMapa = ref(false)
const mapError = ref('')
const mapaMobileVisivel = ref(true)
const mapaPontos = ref([])
const mapaResumo = ref({ clientes: 0, pedidos: 0, valor: 0, estados: 0 })
const mapaPagina = ref(1)
const mapaTemMais = ref(false)
const mapaTotalResultados = ref(0)
const carregandoMaisMapa = ref(false)

async function mostrarMapaMobile() {
  mapaMobileVisivel.value = true
  await nextTick()
  salesMap?.invalidateSize()
}
let salesMap = null
let salesLayer = null
let salesResizeObserver = null
const salesMarkers = new Map()

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


function inicializarMapaVendas() {
  if (!mapContainer.value || salesMap) return

  try {
    mapError.value = ''
    salesMap = L.map(mapContainer.value, {
      center: [-14.235, -51.9253],
      zoom: 4,
      minZoom: 3,
      maxZoom: 18,
      zoomControl: true,
      preferCanvas: true,
    })

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors',
      crossOrigin: true,
    }).addTo(salesMap)

    salesLayer = L.layerGroup().addTo(salesMap)
    salesResizeObserver = new ResizeObserver(() => {
      requestAnimationFrame(() => salesMap?.invalidateSize(false))
    })
    salesResizeObserver.observe(mapContainer.value)

    setTimeout(() => {
      salesMap?.invalidateSize(true)
      desenharMapaVendas()
    }, 250)
  } catch (error) {
    console.error(error)
    mapError.value = error?.message || 'Falha ao inicializar o mapa.'
  }
}

function destruirMapaVendas() {
  salesResizeObserver?.disconnect()
  salesResizeObserver = null
  salesMarkers.clear()

  if (salesMap) {
    salesMap.remove()
    salesMap = null
  }

  salesLayer = null
}

function reiniciarMapa() {
  destruirMapaVendas()
  nextTick(() => inicializarMapaVendas())
}

function coordenadasValidas(ponto) {
  const lat = Number(ponto?.latitude)
  const lng = Number(ponto?.longitude)
  return Number.isFinite(lat) && Number.isFinite(lng)
    && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180
}

function corPonto(ponto) {
  const valor = Number(ponto.valor || 0)
  if (valor >= 50000) return '#22c55e'
  if (valor >= 10000) return '#3b82f6'
  if (valor >= 3000) return '#f59e0b'
  return '#a855f7'
}

function desenharMapaVendas(ajustarEnquadramento = true) {
  if (!salesMap || !salesLayer) return

  salesLayer.clearLayers()
  salesMarkers.clear()
  const limites = []

  for (const ponto of mapaPontos.value) {
    if (!coordenadasValidas(ponto)) continue

    const lat = Number(ponto.latitude)
    const lng = Number(ponto.longitude)
    const raio = Math.max(5, Math.min(16, 5 + Math.log10(Number(ponto.valor || 1)) * 2))
    const cor = corPonto(ponto)

    const marker = L.circleMarker([lat, lng], {
      radius: raio,
      color: cor,
      fillColor: cor,
      fillOpacity: .78,
      weight: 1.5,
    })

    marker.bindPopup(`
      <div style="min-width:190px">
        <strong>${escapeHtmlMapa(ponto.nome || 'Cliente')}</strong><br>
        <span>${escapeHtmlMapa(ponto.cidade || '')} / ${escapeHtmlMapa(ponto.estado || '')}</span><br>
        <span>CEP: ${escapeHtmlMapa(ponto.cep || 'não informado')}</span><br>
        <strong>${fmt.moeda(Number(ponto.valor || 0))}</strong>
        <span> · ${Number(ponto.pedidos || 0)} pedidos</span>
      </div>
    `)

    marker.addTo(salesLayer)
    salesMarkers.set(ponto.id, marker)
    limites.push([lat, lng])
  }

  if (limites.length && ajustarEnquadramento) {
    salesMap.fitBounds(L.latLngBounds(limites), {
      padding: [25, 25],
      maxZoom: 8,
      animate: false,
    })
  }

  setTimeout(() => salesMap?.invalidateSize(false), 50)
}

function focarPonto(ponto) {
  if (!salesMap || !coordenadasValidas(ponto)) return

  salesMap.flyTo([Number(ponto.latitude), Number(ponto.longitude)], 11, {
    animate: true,
    duration: .8,
  })

  setTimeout(() => salesMarkers.get(ponto.id)?.openPopup(), 400)
}

function escapeHtmlMapa(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')
}

async function carregarMapaVendas(opcoesCarregamento = {}) {
  const append = Boolean(opcoesCarregamento?.append)
  if (append) {
    if (loadingMapa.value || carregandoMaisMapa.value || !mapaTemMais.value) return
    carregandoMaisMapa.value = true
  } else {
    loadingMapa.value = true
  }

  const paginaSolicitada = append ? mapaPagina.value + 1 : 1
  try {
    const { data } = await api.get('/dashboard/mapa-vendas', {
      params: { page: paginaSolicitada, per_page: 50 },
    })
    const novosPontos = data.pontos || []
    if (append) {
      const idsExistentes = new Set(mapaPontos.value.map(ponto => String(ponto.id)))
      mapaPontos.value = [
        ...mapaPontos.value,
        ...novosPontos.filter(ponto => !idsExistentes.has(String(ponto.id))),
      ]
    } else {
      mapaPontos.value = novosPontos
    }

    mapaPagina.value = Number(data.page || paginaSolicitada)
    mapaTotalResultados.value = Number(data.total ?? data.resumo?.clientes ?? mapaPontos.value.length)
    mapaTemMais.value = Boolean(data.has_more ?? (mapaPontos.value.length < mapaTotalResultados.value))
    mapaResumo.value = data.resumo || { clientes: 0, pedidos: 0, valor: 0, estados: 0 }

    await nextTick()
    if (!salesMap) inicializarMapaVendas()
    desenharMapaVendas(!append)
  } catch (error) {
    mapError.value = error.response?.data?.message || 'Não foi possível carregar a origem das vendas.'
  } finally {
    if (append) carregandoMaisMapa.value = false
    else loadingMapa.value = false
  }
}

async function carregarMaisMapa() {
  await carregarMapaVendas({ append: true })
}

function rolarRankingMapa(event) {
  const lista = event.currentTarget
  if (!lista || lista.scrollHeight - lista.scrollTop - lista.clientHeight > 160) return
  carregarMaisMapa()
}

async function sincronizarCeps() {
  sincronizandoMapa.value = true

  try {
    const { data } = await api.post('/dashboard/mapa-vendas/sincronizar', { limite: 150 })
    const resultado = data.data || {}
    alert(`Geolocalização concluída: ${resultado.atualizados || 0} clientes atualizados.`)
    await carregarMapaVendas()
  } catch (error) {
    alert(error.response?.data?.message || 'Não foi possível localizar os clientes pelos CEPs.')
  } finally {
    sincronizandoMapa.value = false
  }
}

async function recarregar() {
  loading.value = true
  try { await dashboard.carregar() }
  finally { loading.value = false }
}

onMounted(async () => {
  await recarregar()
  await nextTick()
  inicializarMapaVendas()
  await carregarMapaVendas()
})

onBeforeUnmount(() => destruirMapaVendas())
</script>


<style scoped>
.dashboard-map-grid {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(280px, .8fr);
  grid-template-areas: "fixed ranking";
  gap: 14px;
}
.dashboard-map-fixed-zone {
  grid-area: fixed;
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 14px;
}
.dashboard-map-wrapper {
  grid-area: map;
  position: relative;
  isolation: isolate;
  z-index: 0;
  min-height: 430px;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: #0b0b12;
}
.dashboard-sales-map {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  min-height: 430px;
}
.dashboard-map-close,
.dashboard-map-show {
  display: none;
}
.dashboard-map-filters {
  grid-area: filters;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px;
  background: var(--bg-surface);
}
.map-filter-heading {
  margin-right: auto;
}
.map-filter-heading strong,
.map-filter-heading span {
  display: block;
}
.map-filter-heading strong {
  font-size: .72rem;
}
.map-filter-heading span {
  margin-top: 2px;
  font-size: .58rem;
  color: var(--text-muted);
}
.dashboard-map-error {
  position: absolute;
  inset: 0;
  z-index: 500;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 20px;
  text-align: center;
  background: var(--bg-card);
  color: var(--text-muted);
}
.dashboard-map-ranking {
  grid-area: ranking;
  min-height: 430px;
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 13px;
  background: var(--bg-surface);
}
.dashboard-ranking-list {
  max-height: 360px;
  overflow-y: auto;
  padding-right: 4px;
  overscroll-behavior: contain;
  -webkit-overflow-scrolling: touch;
}
.map-ranking-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 9px;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 9px;
}
.map-ranking-item:hover {
  border-color: #3b82f6;
  background: var(--bg-elevated);
}
.map-ranking-position {
  width: 22px;
  height: 22px;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  border-radius: 50%;
  background: var(--bg-elevated);
  font-size: .58rem;
  font-weight: 700;
}
.map-kpi {
  min-width: 82px;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 6px 9px;
  background: var(--bg-surface);
}
.map-kpi strong,
.map-kpi span {
  display: block;
}
.map-kpi strong {
  font-size: .7rem;
}
.map-kpi span {
  margin-top: 1px;
  font-size: .5rem;
  color: var(--text-muted);
}
.dashboard-sales-map :deep(.leaflet-control-zoom a) {
  background: #171724 !important;
  color: #f8fafc !important;
  border-color: #2b2b45 !important;
}
.dashboard-sales-map :deep(.leaflet-control-attribution) {
  background: rgba(9, 9, 16, .82) !important;
  color: #94a3b8 !important;
}
.dashboard-sales-map :deep(.leaflet-popup-content-wrapper),
.dashboard-sales-map :deep(.leaflet-popup-tip) {
  background: #171724 !important;
  color: #f8fafc !important;
}
@media (max-width: 1100px) {
  .dashboard-map-grid {
    grid-template-columns: 1fr;
    grid-template-areas: "fixed" "ranking";
  }
  .dashboard-map-ranking {
    min-height: auto;
  }
}
@media (max-width: 767px) {
  .dashboard-page {
    display: flex;
    min-height: 0;
    flex-direction: column;
    gap: 8px;
  }
  .dashboard-page > * {
    order: 2;
    margin-top: 0 !important;
  }
  .dashboard-page > .dashboard-page-header {
    position: sticky;
    top: 0;
    z-index: 35;
    order: 0;
    min-height: 44px;
    margin-bottom: 0 !important;
    padding: 5px 0 7px;
    background: var(--bg-primary);
    box-shadow: 0 8px 18px rgba(0, 0, 0, .24);
  }
  .dashboard-page-header .page-title {
    font-size: 1rem;
    line-height: 1.15;
  }
  .dashboard-page-header > div:first-child p {
    display: none;
  }
  .dashboard-page-header > div:last-child {
    gap: 5px;
  }
  .dashboard-page-header .select {
    width: 106px;
    height: 32px;
    min-height: 32px;
    padding: 4px 8px;
    font-size: .65rem;
  }
  .dashboard-page-header .btn-sm {
    width: 32px;
    height: 32px;
    min-height: 32px;
    padding: 5px;
  }
  .dashboard-page > .dashboard-map-section {
    display: flex;
    order: 1;
    height: auto;
    min-height: 0;
    max-height: none;
    flex-direction: column;
    overflow: visible;
    padding: 8px;
  }
  .dashboard-page > .dashboard-kpi-row {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 4px;
  }
  .dashboard-kpi-row :deep(.stat-card) {
    min-width: 0;
    min-height: 68px;
    flex-direction: column;
    align-items: flex-start;
    gap: 3px;
    padding: 6px;
  }
  .dashboard-kpi-row :deep(.stat-icon) {
    width: 22px;
    height: 22px;
    border-radius: 6px;
  }
  .dashboard-kpi-row :deep(.stat-icon svg) {
    width: 13px;
    height: 13px;
  }
  .dashboard-kpi-row :deep(.stat-card > .flex-1) {
    width: 100%;
  }
  .dashboard-kpi-row :deep(.kpi-label) {
    min-height: 1.55em;
    overflow: hidden;
    font-size: .46rem;
    line-height: .78;
    letter-spacing: .025em;
  }
  .dashboard-kpi-row :deep(.kpi-value) {
    overflow: hidden;
    font-size: .76rem;
    line-height: 1.05;
    white-space: nowrap;
    text-overflow: ellipsis;
  }
  .dashboard-kpi-row :deep(.kpi-value + div) {
    display: none;
  }
  .dashboard-map-title {
    position: sticky;
    top: 44px;
    z-index: 34;
    flex: 0 0 auto;
    margin-bottom: 6px;
    padding: 3px 2px;
    background: var(--bg-card);
  }
  .dashboard-map-title h3 {
    font-size: .75rem;
  }
  .dashboard-map-title p,
  .dashboard-map-section > p {
    display: none;
  }
  .dashboard-map-grid {
    display: flex;
    min-height: 0;
    flex: 0 0 auto;
    flex-direction: column;
    gap: 6px;
    overflow: visible;
  }
  .dashboard-map-fixed-zone {
    position: sticky;
    top: 68px;
    z-index: 33;
    order: 1;
    gap: 6px;
    padding-bottom: 1px;
    background: var(--bg-card);
  }
  .dashboard-map-wrapper {
    position: relative;
    top: auto;
    z-index: 1;
    order: initial;
    width: 100%;
    height: clamp(160px, 22dvh, 190px);
    min-height: 160px;
    flex: 0 0 clamp(160px, 22dvh, 190px);
    border-radius: 10px;
    box-shadow: 0 7px 20px rgba(0, 0, 0, .3);
  }
  .dashboard-sales-map {
    min-height: 160px;
  }
  .dashboard-map-close {
    position: absolute;
    z-index: 1000;
    top: 8px;
    right: 8px;
    display: grid;
    width: 36px;
    height: 36px;
    place-items: center;
    border: 1px solid rgba(255, 255, 255, .28);
    border-radius: 9px;
    background: rgba(12, 14, 24, .92);
    color: #fff;
    box-shadow: 0 5px 15px rgba(0, 0, 0, .3);
  }
  .dashboard-map-show {
    display: inline-flex;
  }
  .dashboard-map-filters {
    position: relative;
    top: auto;
    z-index: 1;
    display: grid;
    order: initial;
    flex: 0 0 auto;
    grid-template-columns: repeat(3, minmax(0, 1fr)) 30px;
    align-items: stretch;
    gap: 4px;
    padding: 4px;
  }
  .map-filter-heading {
    display: none;
  }
  .map-kpi {
    min-width: 0;
    padding: 3px 5px;
  }
  .map-kpi strong {
    overflow: hidden;
    font-size: .58rem;
    white-space: nowrap;
    text-overflow: ellipsis;
  }
  .map-kpi span {
    font-size: .42rem;
  }
  .dashboard-map-refresh {
    width: 30px;
    min-width: 30px;
    min-height: 28px;
    justify-content: center;
    overflow: hidden;
    padding: 4px;
    font-size: 0;
  }
  .dashboard-map-refresh svg {
    width: 14px;
    height: 14px;
  }
  .dashboard-map-filters > .dashboard-map-show {
    grid-column: 1 / -1;
    width: 100%;
    min-height: 28px;
    justify-content: center;
    padding: 4px 8px;
  }
  .dashboard-map-ranking {
    display: block;
    order: 2;
    height: auto;
    min-height: 0;
    max-height: none;
    flex: 0 0 auto;
    overflow: visible;
    padding: 7px;
  }
  .dashboard-map-ranking > div:first-child {
    flex: 0 0 auto;
    margin-bottom: 5px;
  }
  .dashboard-ranking-list {
    min-height: 0;
    max-height: min(38dvh, 360px);
    height: auto;
    flex: 0 0 auto;
    overflow-y: auto;
    padding-right: 0;
    overscroll-behavior-y: contain;
    -webkit-overflow-scrolling: touch;
  }
  .map-ranking-item {
    gap: 7px;
    padding: 7px;
  }
}
</style>
