<template>
  <div class="flex h-screen overflow-hidden" :class="temaClasse">
    <aside
      :class="[
        'flex flex-col flex-shrink-0 transition-all duration-300 border-r border-[var(--border)] bg-dark-800',
        sidebarAberta ? 'w-60' : 'w-16',
      ]"
    >
      <div class="flex items-center h-14 px-4 border-b border-[var(--border)] flex-shrink-0">
        <div class="flex items-center gap-3 overflow-hidden">
          <div class="w-8 h-8 rounded-lg bg-brand-500 flex items-center justify-center flex-shrink-0 shadow-glow-sm">
            <span class="text-white font-bold text-sm">IT</span>
          </div>
          <Transition name="fade">
            <div v-if="sidebarAberta" class="overflow-hidden">
              <p class="text-xs font-bold text-[var(--text-primary)] leading-none">IntelbrasTech</p>
              <p class="text-[10px] text-[var(--text-muted)] leading-none mt-0.5">ERP v1.0</p>
            </div>
          </Transition>
        </div>
      </div>

      <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
        <template v-for="grupo in menuGrupos" :key="grupo.label">
          <div v-if="sidebarAberta" class="px-2 pt-3 pb-1">
            <span class="text-[10px] font-semibold text-[var(--text-muted)] uppercase tracking-widest">
              {{ grupo.label }}
            </span>
          </div>
          <div v-else class="border-t border-[var(--border-subtle)] my-2" />

          <div v-for="item in grupo.items" :key="item.name">
            <button
              v-if="item.children && item.children.length"
              type="button"
              :class="[
                'sidebar-item w-full',
                ($route.path === item.to || $route.path.startsWith(item.to + '/')) && 'active',
              ]"
              :title="!sidebarAberta ? item.label : undefined"
              @click="sidebarAberta ? (menuLateralAberto = menuLateralAberto === item.name ? null : item.name) : $router.push(item.to)"
            >
              <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
              <Transition name="fade">
                <span v-if="sidebarAberta" class="truncate">{{ item.label }}</span>
              </Transition>
              <ChevronDownIcon
                v-if="sidebarAberta"
                :class="[
                  'w-4 h-4 ml-auto transition-transform',
                  menuLateralAberto === item.name && 'rotate-180',
                ]"
              />
            </button>

            <Transition name="slide-down">
              <div
                v-if="sidebarAberta && item.children && menuLateralAberto === item.name"
                class="sidebar-submenu"
              >
                <RouterLink
                  v-for="child in item.children"
                  :key="child.name"
                  :to="child.to"
                  class="sidebar-submenu-item"
                  active-class="active"
                >
                  <span class="sidebar-submenu-dot"></span>
                  <span>{{ child.label }}</span>
                </RouterLink>
              </div>
            </Transition>

            <RouterLink
              v-if="!item.children || !item.children.length"
              :to="item.to"
              v-slot="{ isActive }"
              custom
            >
              <a
                :href="item.to"
                @click.prevent="$router.push(item.to)"
                :class="['sidebar-item', isActive && 'active']"
                :title="!sidebarAberta ? item.label : undefined"
              >
                <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                <Transition name="fade">
                  <span v-if="sidebarAberta" class="truncate">{{ item.label }}</span>
                </Transition>
                <Transition name="fade">
                  <span
                    v-if="sidebarAberta && item.badge"
                    class="ml-auto badge badge-danger text-[10px] px-1.5 py-0.5"
                  >{{ item.badge }}</span>
                </Transition>
              </a>
            </RouterLink>
          </div>
        </template>
      </nav>

      <div class="border-t border-[var(--border)] p-2 space-y-1">
        <button @click="sidebarAberta = !sidebarAberta" class="sidebar-item w-full">
          <ChevronLeftIcon v-if="sidebarAberta" class="w-5 h-5 flex-shrink-0" />
          <ChevronRightIcon v-else class="w-5 h-5 flex-shrink-0" />
          <Transition name="fade">
            <span v-if="sidebarAberta">Recolher</span>
          </Transition>
        </button>

        <div
          :class="[
            'flex items-center gap-3 px-3 py-2 rounded-lg overflow-hidden cursor-pointer',
            'hover:bg-[var(--bg-elevated)] transition-colors',
          ]"
          @click="$router.push('/configuracoes/empresa')"
        >
          <div class="w-7 h-7 rounded-full bg-brand-500/20 flex items-center justify-center flex-shrink-0">
            <span class="text-brand-400 font-semibold text-xs">
              {{ auth.usuario?.nome?.charAt(0)?.toUpperCase() }}
            </span>
          </div>
          <Transition name="fade">
            <div v-if="sidebarAberta" class="min-w-0">
              <p class="text-xs font-medium text-[var(--text-primary)] truncate">{{ auth.usuario?.nome }}</p>
              <p class="text-[10px] text-[var(--text-muted)] truncate">{{ auth.empresa?.nome_fantasia }}</p>
            </div>
          </Transition>
        </div>
      </div>
    </aside>

    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">
      <header class="border-b border-[var(--border)] bg-[var(--bg-surface)] flex-shrink-0">
        <div class="min-h-12 flex items-center gap-3 px-5 py-1.5">
          <div class="flex items-center gap-1.5 flex-1 min-w-0 flex-wrap">
            <div v-for="menu in topMenus" :key="menu.id" class="relative flex-shrink-0">
              <button
                type="button"
                :class="['top-menu-button', topMenuAberto === menu.id && 'active']"
                @click.stop="alternarMenuSuperior(menu.id)"
              >
                {{ menu.label }}
                <ChevronDownIcon class="w-3.5 h-3.5" />
              </button>

              <Transition name="slide-down">
                <div
                  v-if="topMenuAberto === menu.id"
                  class="top-menu-dropdown"
                  @click.stop
                >
                  <div class="top-menu-title">{{ menu.label }}</div>
                  <RouterLink
                    v-for="item in menu.items"
                    :key="item.to"
                    :to="item.to"
                    class="top-menu-item"
                    @click="topMenuAberto = null"
                  >
                    <component :is="item.icon" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                    <div>
                      <strong>{{ item.label }}</strong>
                      <span>{{ item.description }}</span>
                    </div>
                  </RouterLink>
                </div>
              </Transition>
            </div>
          </div>

          <div class="flex items-center gap-2 flex-shrink-0">
            <button class="btn-ghost btn-icon" title="Busca global">
              <MagnifyingGlassIcon class="w-4 h-4" />
            </button>

            <div class="relative notif-area">
              <button class="btn-ghost btn-icon" @click.stop="notifAberta = !notifAberta">
                <BellIcon class="w-4 h-4" />
                <span
                  v-if="totalAlertas > 0"
                  class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full bg-danger text-white text-[9px] flex items-center justify-center font-bold"
                >{{ totalAlertas }}</span>
              </button>

              <Transition name="slide-down">
                <div
                  v-if="notifAberta"
                  class="absolute right-0 top-10 w-80 card shadow-xl z-50 max-h-96 overflow-y-auto"
                  @click.stop
                >
                  <div class="p-3 border-b border-[var(--border)] flex items-center justify-between">
                    <span class="text-sm font-medium">Alertas</span>
                  </div>
                  <div
                    v-for="alerta in alertas"
                    :key="alerta.tipo"
                    class="flex gap-3 p-3 hover:bg-[var(--bg-elevated)] border-b border-[var(--border-subtle)] last:border-0"
                  >
                    <div
                      :class="[
                        'w-2 h-2 rounded-full mt-1.5 flex-shrink-0',
                        alerta.nivel === 'danger' ? 'bg-danger' : 'bg-warning',
                      ]"
                    />
                    <p class="text-xs text-[var(--text-secondary)]">{{ alerta.mensagem }}</p>
                  </div>
                  <div v-if="!alertas?.length" class="p-6 text-center text-xs text-[var(--text-muted)]">
                    Nenhum alerta no momento
                  </div>
                </div>
              </Transition>
            </div>

            <button class="btn-ghost btn-icon" @click="alternarTema" :title="tema === 'dark' ? 'Modo claro' : 'Modo escuro'">
              <SunIcon v-if="tema === 'dark'" class="w-4 h-4" />
              <MoonIcon v-else class="w-4 h-4" />
            </button>

            <button class="btn-ghost btn-icon" @click="auth.logout()" title="Sair">
              <ArrowRightOnRectangleIcon class="w-4 h-4" />
            </button>
          </div>
        </div>

        <div class="h-9 flex items-center px-6 border-t border-[var(--border-subtle)]">
          <span class="text-xs text-[var(--text-muted)] truncate">{{ $route.meta.title }}</span>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto bg-[var(--bg-base)] p-6">
        <RouterView v-slot="{ Component, route }">
          <Transition name="page" mode="out-in">
            <component :is="Component" :key="route.path" />
          </Transition>
        </RouterView>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { RouterView, RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import {
  ChartBarIcon,
  UsersIcon,
  ShoppingBagIcon,
  CubeIcon,
  BanknotesIcon,
  DocumentTextIcon,
  WrenchScrewdriverIcon,
  MapIcon,
  Squares2X2Icon,
  BuildingStorefrontIcon,
  UserGroupIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  BellIcon,
  MagnifyingGlassIcon,
  SunIcon,
  MoonIcon,
  ArrowRightOnRectangleIcon,
  Cog6ToothIcon,
  ArchiveBoxIcon,
  ShoppingCartIcon,
  TruckIcon,
  ShieldCheckIcon,
  CalculatorIcon,
  ReceiptPercentIcon,
  PresentationChartLineIcon,
  ArrowPathIcon,
  UserCircleIcon,
} from '@heroicons/vue/24/outline'

const auth = useAuthStore()
const dashboard = useDashboardStore()

const sidebarAberta = ref(true)
const notifAberta = ref(false)
const topMenuAberto = ref(null)
const menuLateralAberto = ref('dashboards')
const tema = ref(auth.usuario?.tema || 'dark')

const temaClasse = computed(() => (tema.value === 'dark' ? 'dark' : 'light'))
const alertas = computed(() => dashboard.alertas)
const totalAlertas = computed(() => alertas.value?.length ?? 0)

function alternarTema() {
  tema.value = tema.value === 'dark' ? 'light' : 'dark'
  document.documentElement.className = tema.value === 'dark' ? '' : 'light'
}

function alternarMenuSuperior(id) {
  topMenuAberto.value = topMenuAberto.value === id ? null : id
  notifAberta.value = false
}

const topMenus = computed(() => [
  {
    id: 'principal',
    label: 'Início',
    items: [
      { to: '/dashboard', label: 'Dashboards', description: 'Visão geral e análises do ERP', icon: ChartBarIcon },
      { to: '/comercial', label: 'Central Comercial', description: 'Pedidos e desempenho comercial', icon: ShoppingBagIcon },
      { to: '/mapa', label: 'Mapa ONS', description: 'Mapa operacional', icon: MapIcon },
    ],
  },
  {
    id: 'cadastros',
    label: 'Cadastros',
    items: [
      { to: '/configuracoes/empresa', label: 'Empresa', description: 'Dados da empresa e filiais', icon: BuildingStorefrontIcon },
      { to: '/clientes', label: 'Clientes', description: 'Cadastro e relacionamento', icon: UsersIcon },
      { to: '/fornecedores', label: 'Fornecedores', description: 'Parceiros e compras', icon: TruckIcon },
      { to: '/produtos', label: 'Produtos', description: 'Catálogo completo', icon: CubeIcon },
    ],
  },
  {
    id: 'vendas',
    label: 'Vendas',
    items: [
      { to: '/pedidos', label: 'Pedidos de venda', description: 'Lista e acompanhamento', icon: ShoppingBagIcon },
      { to: '/pedidos/novo', label: 'Novo pedido', description: 'Iniciar uma venda', icon: ShoppingCartIcon },
      { to: '/pedidos/kanban', label: 'Kanban de pedidos', description: 'Fluxo visual das vendas', icon: ArchiveBoxIcon },
      { to: '/comercial', label: 'Central Comercial', description: 'Indicadores e atalhos', icon: ChartBarIcon },
    ],
  },
  {
    id: 'pdv',
    label: 'PDV',
    items: [
      { to: '/pdv', label: 'Frente de Caixa', description: 'Venda rápida e recebimento', icon: CalculatorIcon },
      { to: '/pdv/vendas', label: 'Vendas do PDV', description: 'Histórico e comprovantes', icon: DocumentTextIcon },
      { to: '/pdv/fiscal', label: 'NFC-e do PDV', description: 'Validação e XML fiscal', icon: ReceiptPercentIcon },
    ],
  },
  {
    id: 'estoque',
    label: 'Estoque',
    items: [
      { to: '/estoque', label: 'Posição de estoque', description: 'Saldos por depósito', icon: ArchiveBoxIcon },
      { to: '/estoque/movimentacoes', label: 'Movimentações', description: 'Entradas, saídas e transferências', icon: ShoppingCartIcon },
      { to: '/estoque/inventario', label: 'Inventário', description: 'Contagem e ajuste', icon: CubeIcon },
      { to: '/produtos', label: 'Produtos', description: 'Cadastro e preços', icon: CubeIcon },
    ],
  },
  {
    id: 'compras',
    label: 'Compras',
    items: [
      { to: '/compras', label: 'Pedidos de compra', description: 'Compras e recebimentos', icon: TruckIcon },
      { to: '/fornecedores', label: 'Fornecedores', description: 'Cadastro de fornecedores', icon: BuildingStorefrontIcon },
      { to: '/estoque', label: 'Conferir estoque', description: 'Necessidades de reposição', icon: ArchiveBoxIcon },
    ],
  },
  {
    id: 'financeiro',
    label: 'Financeiro',
    items: [
      { to: '/financeiro/contas-receber', label: 'Contas a receber', description: 'Recebimentos e vencimentos', icon: BanknotesIcon },
      { to: '/financeiro/contas-pagar', label: 'Contas a pagar', description: 'Despesas e pagamentos', icon: BanknotesIcon },
      { to: '/financeiro/fluxo-caixa', label: 'Fluxo de caixa', description: 'Entradas e saídas', icon: ChartBarIcon },
      { to: '/financeiro/dre', label: 'DRE', description: 'Resultado gerencial', icon: PresentationChartLineIcon },
    ],
  },
  {
    id: 'fiscal',
    label: 'Fiscal',
    items: [
      { to: '/fiscal/nfe', label: 'NF-e', description: 'Documentos fiscais', icon: DocumentTextIcon },
      { to: '/pdv/fiscal', label: 'NFC-e do PDV', description: 'Validação fiscal das vendas', icon: ReceiptPercentIcon },
      { to: '/fiscal/nfe/configuracoes', label: 'Configurações fiscais', description: 'CSC, série e ambiente', icon: Cog6ToothIcon },
      { to: '/fiscal/certificados', label: 'Certificado digital', description: 'Certificados A1', icon: ShieldCheckIcon },
    ],
  },
  {
    id: 'crm',
    label: 'CRM',
    items: [
      { to: '/crm', label: 'Painel CRM', description: 'Leads e oportunidades', icon: UserGroupIcon },
      { to: '/crm/busca-cnpj', label: 'Busca CNPJ', description: 'Prospecção empresarial', icon: MagnifyingGlassIcon },
      { to: '/clientes', label: 'Clientes', description: 'Carteira comercial', icon: UsersIcon },
    ],
  },
  {
    id: 'servicos',
    label: 'Serviços',
    items: [
      { to: '/os', label: 'Ordens de serviço', description: 'Assistência técnica', icon: WrenchScrewdriverIcon },
      { to: '/marketplace-hub', label: 'Central de Extensões', description: 'Todos os conectores e marketplaces', icon: BuildingStorefrontIcon },
      { to: '/marketplaces', label: 'Marketplaces conectados', description: 'Contas e canais ativos', icon: BuildingStorefrontIcon },
      { to: '/expedicao', label: 'Expedição', description: 'Picking, packing e volumes', icon: TruckIcon },
      { to: '/logistica/transportadoras', label: 'Transportadoras', description: 'Cotações, etiquetas e rastreio', icon: TruckIcon },
      { to: '/marketplaces/mercado-livre', label: 'Mercado Livre', description: 'Conta e autenticação', icon: BuildingStorefrontIcon },
      { to: '/marketplaces/mercado-livre/catalogo', label: 'Catálogo Mercado Livre', description: 'Preço, estoque e anúncios', icon: CubeIcon },
      { to: '/marketplaces/mercado-livre/pedidos', label: 'Pedidos Mercado Livre', description: 'Importação e acompanhamento', icon: ShoppingBagIcon },
      { to: '/marketplaces/automacao', label: 'Automação Omnichannel', description: 'Pedido interno e baixa de estoque', icon: ArrowPathIcon },
      { to: '/marketplaces/financeiro-fiscal', label: 'Financeiro e Fiscal', description: 'Conciliação e fila fiscal', icon: BanknotesIcon },
      { to: '/marketplaces/emissao-fiscal', label: 'Emissão Fiscal', description: 'XML e fila de assinatura', icon: DocumentTextIcon },
      { to: '/configuracoes/apis', label: 'APIs', description: 'Integrações externas', icon: Cog6ToothIcon },
    ],
  },
])
const menuGrupos = computed(() => [
  {
    label: 'Principal',
    items: [
            {
        to: '/dashboards',
        label: 'Dashboards',
        icon: ChartBarIcon,
        name: 'dashboards',
        children: [
          { to: '/dashboards', label: 'Dashboard', name: 'dashboards.geral' },
          { to: '/dashboards/mensal', label: 'Mensal', name: 'dashboards.mensal' },
          { to: '/dashboards/diario', label: 'Diário', name: 'dashboards.diario' },
          { to: '/dashboards/vendas', label: 'Vendas', name: 'dashboards.vendas' },
          { to: '/dashboards/pedidos-venda', label: 'Pedidos de Venda', name: 'dashboards.pedidos' },
          { to: '/dashboards/venda-vendedor', label: 'Venda por Vendedor', name: 'dashboards.vendedor' },
          { to: '/dashboards/venda-canal-24-meses', label: 'Venda Canal (24 meses)', name: 'dashboards.canal' },
          { to: '/dashboards/previsao-estoque', label: 'Previsão de Estoque', name: 'dashboards.previsao' },
          { to: '/dashboards/produtos', label: 'Produtos', name: 'dashboards.produtos' },
          { to: '/dashboards/estoque-produtos', label: 'Estoque de Produtos', name: 'dashboards.estoque' },
          { to: '/dashboards/sugestao-compras', label: 'Sugestão de Compras', name: 'dashboards.compras' },
          { to: '/dashboards/sem-movimentacao', label: 'Sem Movimentação', name: 'dashboards.sem-movimento' },
          { to: '/dashboards/clientes', label: 'Clientes', name: 'dashboards.clientes' },
          { to: '/dashboards/novos-recorrentes', label: 'Novo x Recorrentes', name: 'dashboards.recorrentes' },
          { to: '/dashboards/atividade-carteira', label: 'Atividade da Carteira', name: 'dashboards.carteira' },
        ],
      },
      { to: '/mapa', label: 'Mapa ONS', icon: MapIcon, name: 'mapa' },
      { to: '/comercial', label: 'Central Comercial', icon: ShoppingBagIcon, name: 'comercial' },
    ],
  },
  {
    label: 'PDV',
    items: [
      { to: '/pdv', label: 'Frente de Caixa', icon: CalculatorIcon, name: 'pdv' },
      { to: '/pdv/vendas', label: 'Vendas do PDV', icon: DocumentTextIcon, name: 'pdv.vendas' },
      { to: '/pdv/fiscal', label: 'NFC-e do PDV', icon: ReceiptPercentIcon, name: 'pdv.fiscal' },
    ],
  },
  {
    label: 'Comercial',
    items: [
      { to: '/clientes', label: 'Clientes', icon: UsersIcon, name: 'clientes' },
      { to: '/fornecedores', label: 'Fornecedores', icon: TruckIcon, name: 'fornecedores' },
      { to: '/pedidos', label: 'Pedidos', icon: ShoppingBagIcon, name: 'pedidos' },
      { to: '/crm', label: 'CRM', icon: UserGroupIcon, name: 'crm' },
      { to: '/crm/busca-cnpj', label: 'Busca CNPJ', icon: MagnifyingGlassIcon, name: 'crm.cnpj' },
    ],
  },
  {
    label: 'Estoque',
    items: [
      { to: '/produtos', label: 'Produtos', icon: CubeIcon, name: 'produtos' },
      { to: '/estoque', label: 'Estoque', icon: ArchiveBoxIcon, name: 'estoque' },
      { to: '/compras', label: 'Compras', icon: ShoppingCartIcon, name: 'compras' },
    ],
  },
  {
    label: 'Financeiro',
    items: [
      {
        to: '/financeiro/contas-receber',
        label: 'A Receber',
        icon: BanknotesIcon,
        name: 'receber',
        badge: dashboard.totalContasVencidas > 0 ? dashboard.totalContasVencidas : null,
      },
      { to: '/financeiro/contas-pagar', label: 'A Pagar', icon: BanknotesIcon, name: 'pagar' },
      { to: '/financeiro/fluxo-caixa', label: 'Fluxo Caixa', icon: ChartBarIcon, name: 'fluxo' },
    ],
  },
  {
    label: 'Fiscal',
    items: [
      { to: '/fiscal/nfe', label: 'NF-e', icon: DocumentTextIcon, name: 'nfe' },
      { to: '/fiscal/nfe/configuracoes', label: 'Config. NF-e / CSC', icon: Cog6ToothIcon, name: 'nfe-config' },
      { to: '/fiscal/certificados', label: 'Certificado Digital', icon: ShieldCheckIcon, name: 'fiscal.certificados' },
      { to: '/fiscal/contador', label: 'Contador', icon: UserCircleIcon, name: 'fiscal.contador' },
    ],
  },
  {
    label: 'Configurações',
    items: [
      { to: '/configuracoes/apis', label: 'APIs', icon: Cog6ToothIcon, name: 'configuracoes.apis' },
    ],
  },
  {
    label: 'Serviços',
    items: [
      { to: '/os', label: 'Assistência Técnica', icon: WrenchScrewdriverIcon, name: 'os' },
      { to: '/configuracoes/empresa', label: 'Empresa', icon: BuildingStorefrontIcon, name: 'configuracoes.empresa' },
      { to: '/marketplaces', label: 'Marketplaces', icon: BuildingStorefrontIcon, name: 'marketplaces' },
      { to: '/expedicao', label: 'Expedição', icon: TruckIcon, name: 'expedicao' },
      { to: '/logistica/transportadoras', label: 'Transportadoras', icon: TruckIcon, name: 'transportadoras' },
      { to: '/extensoes', label: 'Central de Extensões', icon: Squares2X2Icon, name: 'extensoes-servicos' },
    ],
  },
])

function handleClickFora(event) {
  if (!event.target.closest('.notif-area')) {
    notifAberta.value = false
  }

  if (!event.target.closest('.top-menu-button') && !event.target.closest('.top-menu-dropdown')) {
    topMenuAberto.value = null
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickFora)
  dashboard.carregarAlertas()
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickFora)
})
</script>

<style scoped>
.top-menu-button {
  display: flex;
  align-items: center;
  gap: 5px;
  border-radius: 7px;
  padding: 6px 8px;
  font-size: .7rem;
  color: var(--text-secondary);
  white-space: nowrap;
}
.top-menu-button:hover,
.top-menu-button.active {
  background: var(--bg-elevated);
  color: var(--text-primary);
}
.top-menu-dropdown {
  position: absolute;
  z-index: 120;
  top: calc(100% + 4px);
  margin-top: 0;
  min-width: 290px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--bg-card);
  padding: 8px;
  box-shadow: 0 18px 50px rgba(0, 0, 0, .32);
}
.top-menu-title {
  padding: 7px 9px;
  font-size: .66rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--text-muted);
}
.top-menu-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  border-radius: 8px;
  padding: 9px;
  color: var(--text-secondary);
}
.top-menu-item:hover {
  background: var(--bg-elevated);
  color: var(--text-primary);
}
.top-menu-item strong {
  display: block;
  font-size: .74rem;
}
.top-menu-item span {
  display: block;
  margin-top: 2px;
  font-size: .64rem;
  color: var(--text-muted);
}

.sidebar-submenu {
  margin: 2px 0 5px 21px;
  padding-left: 13px;
  border-left: 1px solid var(--border-subtle);
}
.sidebar-submenu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 29px;
  border-radius: 6px;
  padding: 5px 8px;
  font-size: .64rem;
  color: var(--text-muted);
}
.sidebar-submenu-item:hover {
  background: var(--bg-elevated);
  color: var(--text-primary);
}
.sidebar-submenu-item.active {
  background: rgba(59, 130, 246, .12);
  color: #60a5fa;
}
.sidebar-submenu-dot {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}
</style>
