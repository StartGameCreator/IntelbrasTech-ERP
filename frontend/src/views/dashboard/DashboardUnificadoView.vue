<template>
  <div class="dashboard-unificado">
    <section class="dashboard-hub" aria-label="Navegação do dashboard executivo">
      <div class="dashboard-hub-title">
        <div>
          <span>Central de indicadores</span>
          <strong>Dashboard Executivo</strong>
        </div>
        <small>Dados reais do ERP organizados em uma única tela</small>
      </div>

      <div class="dashboard-group-tabs" role="tablist" aria-label="Áreas do dashboard">
        <button
          v-for="grupo in grupos"
          :key="grupo.id"
          type="button"
          role="tab"
          :aria-selected="grupoAtivo.id === grupo.id"
          :class="['dashboard-group-tab', { active: grupoAtivo.id === grupo.id }]"
          @click="selecionar(grupo.secoes[0].key)"
        >
          <component :is="grupo.icon" class="w-4 h-4" />
          <span>{{ grupo.label }}</span>
          <small>{{ grupo.secoes.length }}</small>
        </button>
      </div>

      <div v-if="grupoAtivo.secoes.length > 1" class="dashboard-section-tabs" role="tablist" :aria-label="`Visões de ${grupoAtivo.label}`">
        <button
          v-for="secao in grupoAtivo.secoes"
          :key="secao.key"
          type="button"
          role="tab"
          :aria-selected="secaoAtiva.key === secao.key"
          :class="{ active: secaoAtiva.key === secao.key }"
          @click="selecionar(secao.key)"
        >
          {{ secao.label }}
        </button>
      </div>
    </section>

    <Suspense>
      <component
        :is="componenteAtivo"
        :key="secaoAtiva.key"
        :dashboard-key="secaoAtiva.key"
        :dashboard-periodo="secaoAtiva.periodo"
        embedded
      />
      <template #fallback>
        <div class="dashboard-loading">Carregando indicadores...</div>
      </template>
    </Suspense>
  </div>
</template>

<script setup>
import { computed, defineAsyncComponent, markRaw } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  Squares2X2Icon,
  CurrencyDollarIcon,
  CubeIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()

const DashboardGeral = defineAsyncComponent(() => import('@/views/dashboard/DashboardView.vue'))
const DashboardAnalitico = defineAsyncComponent(() => import('@/views/dashboards/DashboardAnaliticoView.vue'))

const grupos = [
  {
    id: 'geral',
    label: 'Visão geral',
    icon: markRaw(Squares2X2Icon),
    secoes: [{ key: 'geral', label: 'Visão geral', periodo: 'mes' }],
  },
  {
    id: 'comercial',
    label: 'Comercial',
    icon: markRaw(CurrencyDollarIcon),
    secoes: [
      { key: 'vendas', label: 'Vendas', periodo: '12m' },
      { key: 'mensal', label: 'Mensal', periodo: 'mes' },
      { key: 'diario', label: 'Diário', periodo: 'hoje' },
      { key: 'pedidos', label: 'Pedidos', periodo: '12m' },
      { key: 'vendedor', label: 'Por vendedor', periodo: '12m' },
      { key: 'canal', label: 'Por canal', periodo: '24m' },
    ],
  },
  {
    id: 'estoque',
    label: 'Estoque e compras',
    icon: markRaw(CubeIcon),
    secoes: [
      { key: 'estoque', label: 'Estoque', periodo: '12m' },
      { key: 'produtos', label: 'Produtos', periodo: '12m' },
      { key: 'previsao', label: 'Previsão', periodo: '12m' },
      { key: 'compras', label: 'Sugestão de compras', periodo: '12m' },
      { key: 'semMovimento', label: 'Sem movimentação', periodo: '12m' },
    ],
  },
  {
    id: 'clientes',
    label: 'Clientes e carteira',
    icon: markRaw(UserGroupIcon),
    secoes: [
      { key: 'clientes', label: 'Clientes', periodo: '12m' },
      { key: 'recorrentes', label: 'Novos x recorrentes', periodo: '12m' },
      { key: 'carteira', label: 'Atividade da carteira', periodo: '12m' },
    ],
  },
]

const secoes = grupos.flatMap((grupo) => grupo.secoes)
const secaoAtiva = computed(() => {
  const chave = String(route.query.secao || 'geral')
  return secoes.find((item) => item.key === chave) || secoes[0]
})
const grupoAtivo = computed(() => grupos.find((grupo) => grupo.secoes.some((item) => item.key === secaoAtiva.value.key)) || grupos[0])
const componenteAtivo = computed(() => secaoAtiva.value.key === 'geral' ? DashboardGeral : DashboardAnalitico)

function selecionar(key) {
  const query = key === 'geral' ? {} : { secao: key }
  router.replace({ path: '/dashboard', query })
}
</script>

<style scoped>
.dashboard-unificado{display:flex;flex-direction:column;gap:14px;min-width:0}
.dashboard-hub{position:sticky;top:0;z-index:35;border:1px solid var(--border);border-radius:14px;background:color-mix(in srgb,var(--bg-card) 94%,transparent);box-shadow:0 12px 30px rgba(0,0,0,.22);backdrop-filter:blur(16px);padding:12px}
.dashboard-hub-title{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;padding:0 2px 10px}
.dashboard-hub-title div{display:flex;flex-direction:column}.dashboard-hub-title span{font-size:.64rem;letter-spacing:.09em;text-transform:uppercase;color:var(--brand)}.dashboard-hub-title strong{font-size:1rem;color:var(--text-primary)}.dashboard-hub-title small{font-size:.68rem;color:var(--text-muted)}
.dashboard-group-tabs,.dashboard-section-tabs{display:flex;gap:7px;overflow-x:auto;scrollbar-width:thin}.dashboard-group-tabs{padding-bottom:7px}.dashboard-group-tab{display:flex;align-items:center;gap:7px;flex:1;min-width:150px;border:1px solid var(--border);border-radius:10px;padding:9px 12px;background:var(--bg-secondary);color:var(--text-secondary);font-size:.72rem;font-weight:700;white-space:nowrap}.dashboard-group-tab small{margin-left:auto;display:grid;place-items:center;min-width:20px;height:20px;border-radius:999px;background:var(--bg-card);color:var(--text-muted);font-size:.58rem}.dashboard-group-tab.active{border-color:color-mix(in srgb,var(--brand) 65%,var(--border));background:color-mix(in srgb,var(--brand) 16%,var(--bg-secondary));color:var(--brand)}
.dashboard-section-tabs{border-top:1px solid var(--border);padding-top:8px}.dashboard-section-tabs button{flex:none;border:0;border-radius:8px;padding:7px 11px;background:transparent;color:var(--text-muted);font-size:.66rem;font-weight:700;white-space:nowrap}.dashboard-section-tabs button.active{background:var(--brand);color:#fff}
.dashboard-loading{min-height:260px;display:grid;place-items:center;border:1px solid var(--border);border-radius:14px;color:var(--text-muted);background:var(--bg-card)}
@media(max-width:700px){.dashboard-unificado{gap:8px}.dashboard-hub{top:0;border-radius:0;margin:-12px -12px 0;padding:9px}.dashboard-hub-title{align-items:flex-start;padding-bottom:7px}.dashboard-hub-title small{display:none}.dashboard-group-tab{min-width:128px;padding:8px;font-size:.66rem}.dashboard-section-tabs button{padding:6px 9px}}
</style>
