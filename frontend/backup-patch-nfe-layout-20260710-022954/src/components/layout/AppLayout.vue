<template>
  <div class="flex h-screen overflow-hidden" :class="temaClasse">

    <!-- ── Sidebar ──────────────────────────────────────────── -->
    <aside
      :class="['flex flex-col flex-shrink-0 transition-all duration-300 border-r border-[var(--border)]',
               'bg-dark-800',
               sidebarAberta ? 'w-60' : 'w-16']"
    >
      <!-- Logo -->
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

      <!-- Navigation -->
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

      <!-- Bottom: toggle + user -->
      <div class="border-t border-[var(--border)] p-2 space-y-1">
        <button @click="sidebarAberta = !sidebarAberta" class="sidebar-item w-full">
          <ChevronLeftIcon v-if="sidebarAberta" class="w-5 h-5 flex-shrink-0" />
          <ChevronRightIcon v-else class="w-5 h-5 flex-shrink-0" />
          <Transition name="fade">
            <span v-if="sidebarAberta">Recolher</span>
          </Transition>
        </button>

        <div :class="['flex items-center gap-3 px-3 py-2 rounded-lg overflow-hidden cursor-pointer',
                      'hover:bg-[var(--bg-elevated)] transition-colors']"
             @click="$router.push('/configuracoes')">
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

    <!-- ── Main Content ──────────────────────────────────────── -->
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">

      <!-- Topbar -->
      <header class="h-14 flex items-center gap-4 px-6 border-b border-[var(--border)] bg-[var(--bg-surface)] flex-shrink-0">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm flex-1 min-w-0">
          <span class="text-[var(--text-muted)] truncate">{{ $route.meta.title }}</span>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
          <!-- Search -->
          <button class="btn-ghost btn-icon" title="Busca global (Ctrl+K)">
            <MagnifyingGlassIcon class="w-4 h-4" />
          </button>

          <!-- Notificações -->
          <div class="relative">
            <button class="btn-ghost btn-icon" @click="notifAberta = !notifAberta">
              <BellIcon class="w-4 h-4" />
              <span v-if="totalAlertas > 0"
                class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full bg-danger
                       text-white text-[9px] flex items-center justify-center font-bold">
                {{ totalAlertas }}
              </span>
            </button>

            <!-- Notif dropdown -->
            <Transition name="slide-down">
              <div v-if="notifAberta"
                class="absolute right-0 top-10 w-80 card shadow-xl z-50 max-h-96 overflow-y-auto">
                <div class="p-3 border-b border-[var(--border)] flex items-center justify-between">
                  <span class="text-sm font-medium">Alertas</span>
                  <button class="text-xs text-brand-400">Marcar todos lidos</button>
                </div>
                <div v-for="alerta in alertas" :key="alerta.tipo"
                  class="flex gap-3 p-3 hover:bg-[var(--bg-elevated)] border-b border-[var(--border-subtle)] last:border-0 cursor-pointer">
                  <div :class="['w-2 h-2 rounded-full mt-1.5 flex-shrink-0',
                    alerta.nivel === 'danger' ? 'bg-danger' : 'bg-warning']" />
                  <p class="text-xs text-[var(--text-secondary)]">{{ alerta.mensagem }}</p>
                </div>
                <div v-if="!alertas?.length" class="p-6 text-center text-xs text-[var(--text-muted)]">
                  Nenhum alerta no momento
                </div>
              </div>
            </Transition>
          </div>

          <!-- Tema toggle -->
          <button class="btn-ghost btn-icon" @click="alternarTema" :title="tema === 'dark' ? 'Modo Claro' : 'Modo Escuro'">
            <SunIcon v-if="tema === 'dark'" class="w-4 h-4" />
            <MoonIcon v-else class="w-4 h-4" />
          </button>

          <!-- Logout -->
          <button class="btn-ghost btn-icon" @click="auth.logout()" title="Sair">
            <ArrowRightOnRectangleIcon class="w-4 h-4" />
          </button>
        </div>
      </header>

      <!-- Page content -->
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
  ChartBarIcon, UsersIcon, ShoppingBagIcon, CubeIcon,
  BanknotesIcon, DocumentTextIcon, WrenchScrewdriverIcon,
  MapIcon, BuildingStorefrontIcon, UserGroupIcon,
  ChevronLeftIcon, ChevronRightIcon, BellIcon,
  MagnifyingGlassIcon, SunIcon, MoonIcon,
  ArrowRightOnRectangleIcon, Cog6ToothIcon,
  ArchiveBoxIcon, ShoppingCartIcon, TruckIcon,
} from '@heroicons/vue/24/outline'

const auth      = useAuthStore()
const dashboard = useDashboardStore()

const sidebarAberta = ref(true)
const notifAberta   = ref(false)
const tema          = ref(auth.usuario?.tema || 'dark')

const temaClasse = computed(() => tema.value === 'dark' ? 'dark' : 'light')
const alertas    = computed(() => dashboard.alertas)
const totalAlertas = computed(() => alertas.value?.length ?? 0)

function alternarTema() {
  tema.value = tema.value === 'dark' ? 'light' : 'dark'
  document.documentElement.className = tema.value === 'dark' ? '' : 'light'
}

const menuGrupos = computed(() => [
  {
    label: 'Principal',
    items: [
      { to: '/dashboard', label: 'Dashboard',   icon: ChartBarIcon,        name: 'dashboard' },
      { to: '/mapa',      label: 'Mapa GPS',     icon: MapIcon,             name: 'mapa' },
    ],
  },
  {
    label: 'Comercial',
    items: [
      { to: '/clientes',     label: 'Clientes',     icon: UsersIcon,           name: 'clientes' },
      { to: '/fornecedores', label: 'Fornecedores', icon: TruckIcon,           name: 'fornecedores' },
      { to: '/pedidos',      label: 'Pedidos',       icon: ShoppingBagIcon,     name: 'pedidos' },
      { to: '/crm',          label: 'CRM',           icon: UserGroupIcon,       name: 'crm' },
      { to: '/crm/busca-cnpj', label: 'Busca CNPJ', icon: MagnifyingGlassIcon, name: 'crm.cnpj' },
    ],
  },
  {
    label: 'Estoque',
    items: [
      { to: '/produtos',  label: 'Produtos',      icon: CubeIcon,            name: 'produtos' },
      { to: '/estoque',   label: 'Estoque',       icon: ArchiveBoxIcon,      name: 'estoque' },
      { to: '/compras',   label: 'Compras',       icon: ShoppingCartIcon,    name: 'compras' },
    ],
  },
  {
    label: 'Financeiro',
    items: [
      { to: '/financeiro/contas-receber', label: 'A Receber', icon: BanknotesIcon, name: 'receber',
        badge: dashboard.totalContasVencidas > 0 ? dashboard.totalContasVencidas : null },
      { to: '/financeiro/contas-pagar',   label: 'A Pagar',   icon: BanknotesIcon, name: 'pagar' },
      { to: '/financeiro/fluxo-caixa',    label: 'Fluxo Caixa', icon: ChartBarIcon, name: 'fluxo' },
    ],
  },
  {
    label: 'Fiscal',
    items: [
      { to: '/fiscal/nfe', label: 'NF-e',         icon: DocumentTextIcon,    name: 'nfe' },
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
      { to: '/os',         label: 'Assistência Técnica', icon: WrenchScrewdriverIcon, name: 'os' },
      { to: '/marketplaces', label: 'Marketplaces', icon: BuildingStorefrontIcon, name: 'marketplaces' },
    ],
  },
])

// Fechar notificações ao clicar fora
function handleClickFora(e) {
  if (!e.target.closest('.notif-area')) notifAberta.value = false
}

onMounted(() => {
  document.addEventListener('click', handleClickFora)
  dashboard.carregarAlertas()
})
onUnmounted(() => document.removeEventListener('click', handleClickFora))
</script>
