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

          <RouterLink
            v-for="item in grupo.items"
            :key="item.name"
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
                  class="top-menu-dropdown top-menu-mega"
                  :class="`menu-${menu.id}`"
                  @click.stop
                >
                  <div
                    v-for="section in menu.sections"
                    :key="section.title"
                    class="top-menu-section"
                  >
                    <div class="top-menu-title">{{ section.title }}</div>

                    <template v-for="item in section.items" :key="`${section.title}-${item.label}`">
                      <RouterLink
                        v-if="!item.planned"
                        :to="item.to"
                        class="top-menu-item"
                        @click="topMenuAberto = null"
                      >
                        <component :is="item.icon" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                        <div class="top-menu-copy">
                          <strong>{{ item.label }}</strong>
                          <span v-if="item.description">{{ item.description }}</span>
                        </div>
                      </RouterLink>

                      <button
                        v-else
                        type="button"
                        class="top-menu-item top-menu-item-planned"
                        :title="`${item.label}: módulo ainda não implantado`"
                      >
                        <component :is="item.icon" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                        <div class="top-menu-copy">
                          <strong>{{ item.label }}</strong>
                          <span v-if="item.description">{{ item.description }}</span>
                        </div>
                        <span class="planned-badge">Pendente</span>
                      </button>
                    </template>

                    <RouterLink
                      v-if="section.report"
                      :to="section.report.to"
                      class="top-menu-report"
                      @click="topMenuAberto = null"
                    >
                      {{ section.report.label }}
                    </RouterLink>
                  </div>
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
} from '@heroicons/vue/24/outline'

const auth = useAuthStore()
const dashboard = useDashboardStore()

const sidebarAberta = ref(true)
const notifAberta = ref(false)
const topMenuAberto = ref(null)
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
    id: 'negocio',
    label: 'Meu Negócio',
    sections: [
      {
        title: 'Dashboards',
        items: [
          { to: '/dashboard', label: 'Dashboard geral', description: 'Visão consolidada do ERP', icon: ChartBarIcon },
          { to: '/comercial', label: 'Dashboard de vendas', description: 'Pedidos, metas e conversão', icon: PresentationChartLineIcon },
          { to: '/financeiro/dre', label: 'Dashboard margem por pedido', description: 'Margem e rentabilidade', icon: BanknotesIcon },
          { to: '/produtos', label: 'Dashboard produtos', description: 'Catálogo, preços e estoque', icon: CubeIcon },
          { to: '/clientes', label: 'Dashboard clientes', description: 'Carteira e relacionamento', icon: UsersIcon },
          { to: '/crm', label: 'Curva ABC', description: 'Classificação comercial', icon: ChartBarIcon, planned: true },
          { to: '/dashboard/metas', label: 'Metas', description: 'Metas comerciais e operacionais', icon: PresentationChartLineIcon, planned: true },
          { to: '/dashboard/social-commerce', label: 'Social commerce', description: 'Vendas por redes sociais', icon: ShoppingBagIcon, planned: true },
        ],
      },
    ],
  },
  {
    id: 'cadastros',
    label: 'Cadastros',
    sections: [
      {
        title: 'Cadastros',
        items: [
          { to: '/produtos', label: 'Produtos', description: 'Catálogo completo', icon: CubeIcon },
          { to: '/clientes', label: 'Clientes e fornecedores', description: 'Pessoas e empresas', icon: UsersIcon },
          { to: '/configuracoes/vendedores', label: 'Vendedores', description: 'Equipe comercial', icon: UserGroupIcon, planned: true },
          { to: '/configuracoes/funcionarios', label: 'Funcionários', description: 'Usuários e colaboradores', icon: UsersIcon, planned: true },
          { to: '/financeiro/contas', label: 'Contas financeiras', description: 'Bancos e carteiras', icon: BanknotesIcon, planned: true },
          { to: '/financeiro/categorias', label: 'Categorias financeiras', description: 'Plano gerencial', icon: ArchiveBoxIcon, planned: true },
          { to: '/financeiro/formas-pagamento', label: 'Formas de pagamento', description: 'Meios e condições', icon: ReceiptPercentIcon, planned: true },
        ],
        report: { to: '/clientes', label: 'Ver relatórios de cadastros' },
      },
      {
        title: 'Ferramentas',
        items: [
          { to: '/produtos/categorias', label: 'Categorias de produtos', description: 'Organização do catálogo', icon: ArchiveBoxIcon, planned: true },
          { to: '/produtos/listas-preco', label: 'Listas de preços', description: 'Preços por canal e cliente', icon: BanknotesIcon, planned: true },
        ],
      },
    ],
  },
  {
    id: 'vendas',
    label: 'Vendas',
    sections: [
      {
        title: 'Gestão',
        items: [
          { to: '/marketplaces', label: 'Gestão de anúncios', description: 'Marketplaces e canais', icon: BuildingStorefrontIcon },
          { to: '/pedidos', label: 'Pedidos de venda', description: 'Lista e acompanhamento', icon: ShoppingBagIcon },
          { to: '/fiscal/nfe', label: 'Notas fiscais de saída', description: 'NF-e emitidas', icon: DocumentTextIcon },
          { to: '/pdv/fiscal', label: 'NFC-e', description: 'Documentos do caixa', icon: ReceiptPercentIcon },
          { to: '/pdv', label: 'Frente de caixa', description: 'Venda rápida', icon: CalculatorIcon },
          { to: '/pedidos/novo', label: 'Importar pedidos manualmente', description: 'Entrada assistida', icon: ArrowPathIcon },
          { to: '/comercial/propostas', label: 'Propostas comerciais', description: 'Orçamentos e propostas', icon: DocumentTextIcon, planned: true },
        ],
        report: { to: '/comercial', label: 'Ver relatórios de vendas' },
      },
      {
        title: 'Logística',
        items: [
          { to: '/expedicao', label: 'Objetos de postagem', description: 'Volumes e etiquetas', icon: ArchiveBoxIcon },
          { to: '/logistica/reversa', label: 'Logística reversa', description: 'Trocas e devoluções', icon: ArrowPathIcon, planned: true },
          { to: '/pedidos/checkout', label: 'Checkout de pedidos', description: 'Conferência final', icon: ShoppingCartIcon, planned: true },
          { to: '/logistica/etiquetas', label: 'Impressão automática de etiquetas', description: 'Etiquetas em lote', icon: DocumentTextIcon, planned: true },
          { to: '/logistica/transportadoras', label: 'Envios por transportadora', description: 'Frete e rastreio', icon: TruckIcon },
        ],
      },
      {
        title: 'Serviços',
        items: [
          { to: '/contratos', label: 'Contratos', description: 'Contratos comerciais', icon: DocumentTextIcon, planned: true },
          { to: '/os', label: 'Ordens de serviço', description: 'Assistência técnica', icon: WrenchScrewdriverIcon },
          { to: '/fiscal/cte', label: 'CT-e', description: 'Conhecimento de transporte', icon: TruckIcon, planned: true },
          { to: '/servicos/notas', label: 'Notas de serviço', description: 'NFS-e', icon: DocumentTextIcon, planned: true },
          { to: '/financeiro/cobrancas', label: 'Cobranças', description: 'Boletos e lembretes', icon: BanknotesIcon, planned: true },
          { to: '/servicos/relatorios', label: 'Relatórios de serviço', description: 'Indicadores de atendimento', icon: ChartBarIcon, planned: true },
        ],
      },
    ],
  },
  {
    id: 'estoque',
    label: 'Estoque',
    sections: [
      {
        title: 'Compras',
        items: [
          { to: '/compras', label: 'Pedidos de compra', description: 'Compras e recebimentos', icon: ShoppingCartIcon },
          { to: '/fiscal/notas-entrada', label: 'Notas fiscais de entrada', description: 'Documentos de fornecedores', icon: DocumentTextIcon, planned: true },
          { to: '/fornecedores', label: 'Fornecedores', description: 'Cadastro e relacionamento', icon: TruckIcon },
          { to: '/compras/sugestao', label: 'Sugestão de compras', description: 'Reposição automática', icon: PresentationChartLineIcon, planned: true },
          { to: '/compras/checkin', label: 'Check-in de recebimentos', description: 'Conferência de entrada', icon: ShieldCheckIcon, planned: true },
        ],
        report: { to: '/compras', label: 'Ver relatórios de compras e estoque' },
      },
      {
        title: 'Estoque',
        items: [
          { to: '/estoque/movimentacoes', label: 'Lançamentos de estoque', description: 'Entradas, saídas e ajustes', icon: ArrowPathIcon },
          { to: '/estoque/inventario', label: 'Conferência de estoque', description: 'Inventário e contagem', icon: CubeIcon },
          { to: '/producao/ordens', label: 'Ordens de produção', description: 'Produção e consumo', icon: WrenchScrewdriverIcon, planned: true },
          { to: '/estoque', label: 'Depósitos', description: 'Saldos por depósito', icon: ArchiveBoxIcon },
          { to: '/estoque/multiempresa', label: 'Multiempresa', description: 'Estoque entre empresas', icon: BuildingStorefrontIcon, planned: true },
        ],
      },
    ],
  },
  {
    id: 'financeiro',
    label: 'Financeiro',
    sections: [
      {
        title: 'Gestão financeira',
        items: [
          { to: '/financeiro/contas-receber', label: 'Contas a receber', description: 'Recebimentos e vencimentos', icon: BanknotesIcon },
          { to: '/financeiro/contas-pagar', label: 'Contas a pagar', description: 'Despesas e pagamentos', icon: BanknotesIcon },
          { to: '/financeiro/remessas', label: 'Remessas e retornos', description: 'Arquivos bancários', icon: ArrowPathIcon, planned: true },
          { to: '/financeiro/ficha', label: 'Ficha financeira', description: 'Histórico por cliente', icon: DocumentTextIcon, planned: true },
          { to: '/financeiro/comissoes', label: 'Comissões', description: 'Comissões de vendedores', icon: ReceiptPercentIcon, planned: true },
          { to: '/financeiro/fluxo-caixa', label: 'Controle de caixa', description: 'Fluxo e movimentações', icon: CalculatorIcon },
          { to: '/financeiro/faturamento-agrupado', label: 'Faturamento agrupado', description: 'Consolidação de cobranças', icon: ArchiveBoxIcon, planned: true },
        ],
        report: { to: '/financeiro/dre', label: 'Ver relatórios financeiros' },
      },
      {
        title: 'Tributos e contabilidade',
        items: [
          { to: '/fiscal/das-mei', label: 'DAS MEI', description: 'Guia mensal do MEI', icon: DocumentTextIcon, planned: true },
          { to: '/fiscal/gnre-dare', label: 'GNRE e DARE-SP', description: 'Guias tributárias', icon: DocumentTextIcon, planned: true },
          { to: '/fiscal/contador', label: 'Espaço meu contador', description: 'Compartilhamento contábil', icon: UsersIcon, planned: true },
        ],
      },
      {
        title: 'Soluções financeiras',
        items: [
          { to: '/financeiro/conta', label: 'Conta IntelbrasTech', description: 'Serviços financeiros integrados', icon: BanknotesIcon, planned: true },
        ],
      },
    ],
  },
  {
    id: 'fiscal',
    label: 'Fiscal',
    sections: [
      {
        title: 'Documentos fiscais',
        items: [
          { to: '/fiscal/nfe', label: 'NF-e', description: 'Notas fiscais eletrônicas', icon: DocumentTextIcon },
          { to: '/pdv/fiscal', label: 'NFC-e', description: 'Documentos do PDV', icon: ReceiptPercentIcon },
          { to: '/fiscal/cte', label: 'CT-e', description: 'Transporte eletrônico', icon: TruckIcon, planned: true },
          { to: '/fiscal/nfse', label: 'NFS-e', description: 'Notas de serviço', icon: DocumentTextIcon, planned: true },
          { to: '/fiscal/certificados', label: 'Certificado digital', description: 'Certificados A1', icon: ShieldCheckIcon },
          { to: '/fiscal/nfe/configuracoes', label: 'Configurações fiscais', description: 'Série, ambiente e CSC', icon: Cog6ToothIcon },
        ],
      },
    ],
  },
  {
    id: 'crm',
    label: 'CRM',
    sections: [
      {
        title: 'Relacionamento',
        items: [
          { to: '/crm', label: 'Painel CRM', description: 'Leads e oportunidades', icon: UserGroupIcon },
          { to: '/crm/busca-cnpj', label: 'Busca CNPJ', description: 'Prospecção empresarial', icon: MagnifyingGlassIcon },
          { to: '/clientes', label: 'Clientes', description: 'Carteira comercial', icon: UsersIcon },
          { to: '/crm/campanhas', label: 'Campanhas', description: 'Ações comerciais', icon: PresentationChartLineIcon, planned: true },
        ],
      },
    ],
  },
  {
    id: 'servicos',
    label: 'Serviços',
    sections: [
      {
        title: 'Operação',
        items: [
          { to: '/os', label: 'Ordens de serviço', description: 'Assistência técnica', icon: WrenchScrewdriverIcon },
          { to: '/marketplaces', label: 'Hub Omnichannel', description: 'Marketplaces e integrações', icon: BuildingStorefrontIcon },
          { to: '/expedicao', label: 'Expedição', description: 'Picking, packing e volumes', icon: TruckIcon },
          { to: '/logistica/transportadoras', label: 'Transportadoras', description: 'Cotações, etiquetas e rastreio', icon: TruckIcon },
          { to: '/configuracoes/apis', label: 'APIs', description: 'Integrações externas', icon: Cog6ToothIcon },
        ],
      },
    ],
  },
])
const menuGrupos = computed(() => [
  {
    label: 'Principal',
    items: [
      { to: '/dashboard', label: 'Dashboard', icon: ChartBarIcon, name: 'dashboard' },
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
  left: 0;
  margin-top: 0;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--bg-card);
  box-shadow: 0 18px 50px rgba(0, 0, 0, .38);
}
.top-menu-mega {
  display: grid;
  grid-template-columns: repeat(var(--menu-columns, 1), minmax(220px, 1fr));
  width: max-content;
  min-width: 310px;
  max-width: min(920px, calc(100vw - 280px));
  padding: 12px;
}
.top-menu-mega.menu-negocio,
.top-menu-mega.menu-fiscal,
.top-menu-mega.menu-crm,
.top-menu-mega.menu-servicos {
  --menu-columns: 1;
}
.top-menu-mega.menu-cadastros,
.top-menu-mega.menu-estoque {
  --menu-columns: 2;
}
.top-menu-mega.menu-vendas,
.top-menu-mega.menu-financeiro {
  --menu-columns: 3;
}
.top-menu-section {
  min-width: 0;
  padding: 0 10px 6px;
  border-right: 1px solid var(--border-subtle);
}
.top-menu-section:last-child {
  border-right: 0;
}
.top-menu-title {
  padding: 5px 8px 8px;
  font-size: .66rem;
  font-weight: 800;
  color: var(--text-primary);
}
.top-menu-item {
  width: 100%;
  display: flex;
  align-items: flex-start;
  gap: 9px;
  border-radius: 7px;
  padding: 7px 8px;
  text-align: left;
  color: var(--text-secondary);
}
.top-menu-item:hover {
  background: var(--bg-elevated);
  color: var(--text-primary);
}
.top-menu-item-planned {
  cursor: default;
  opacity: .72;
}
.top-menu-item-planned:hover {
  background: transparent;
  color: var(--text-secondary);
}
.top-menu-copy {
  min-width: 0;
  flex: 1;
}
.top-menu-item strong {
  display: block;
  font-size: .7rem;
  line-height: 1.2;
}
.top-menu-item .top-menu-copy > span {
  display: block;
  margin-top: 2px;
  font-size: .58rem;
  line-height: 1.25;
  color: var(--text-muted);
}
.planned-badge {
  flex-shrink: 0;
  border: 1px solid rgba(245, 158, 11, .25);
  border-radius: 999px;
  background: rgba(245, 158, 11, .08);
  padding: 2px 5px;
  font-size: .5rem !important;
  color: #fbbf24 !important;
}
.top-menu-report {
  display: block;
  margin: 7px 8px 0;
  padding-top: 8px;
  border-top: 1px solid var(--border-subtle);
  font-size: .61rem;
  font-weight: 700;
  color: #4ade80;
}
.top-menu-report:hover {
  color: #86efac;
}
@media (max-width: 1100px) {
  .top-menu-mega {
    grid-template-columns: 1fr !important;
    width: 340px;
    max-width: calc(100vw - 40px);
    max-height: 75vh;
    overflow-y: auto;
  }
  .top-menu-section {
    border-right: 0;
    border-bottom: 1px solid var(--border-subtle);
    padding-bottom: 10px;
    margin-bottom: 8px;
  }
}
</style>
