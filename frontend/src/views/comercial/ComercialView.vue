<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Central Comercial</h1>
        <p class="text-xs text-[var(--text-muted)] mt-1">
          Pedidos, propostas, clientes e preparação de vendas em um só lugar.
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <RouterLink to="/pedidos/novo" class="btn-primary">
          <PlusIcon class="w-4 h-4" /> Novo pedido
        </RouterLink>
        <RouterLink to="/crm" class="btn-secondary">
          <UserGroupIcon class="w-4 h-4" /> Abrir CRM
        </RouterLink>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <article v-for="card in indicadores" :key="card.label" class="card p-4">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs text-[var(--text-muted)]">{{ card.label }}</p>
            <p class="mt-2 text-2xl font-bold text-[var(--text-primary)]">{{ card.valor }}</p>
            <p class="mt-1 text-[11px]" :class="card.classe">{{ card.legenda }}</p>
          </div>
          <div class="rounded-lg bg-brand-500/10 p-2 text-brand-400">
            <component :is="card.icon" class="h-5 w-5" />
          </div>
        </div>
      </article>
    </div>

    <div class="grid gap-4 xl:grid-cols-[1.3fr_1fr]">
      <section class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-[var(--border)] p-4">
          <div>
            <h2 class="text-sm font-semibold text-[var(--text-primary)]">Fluxo comercial</h2>
            <p class="text-[11px] text-[var(--text-muted)]">Acesso rápido às etapas de venda.</p>
          </div>
        </div>

        <div class="grid gap-3 p-4 md:grid-cols-2">
          <RouterLink
            v-for="acao in acoes"
            :key="acao.to"
            :to="acao.to"
            class="commercial-action"
          >
            <div class="rounded-lg bg-[var(--bg-elevated)] p-2">
              <component :is="acao.icon" class="h-5 w-5 text-brand-400" />
            </div>
            <div class="min-w-0 flex-1">
              <strong>{{ acao.titulo }}</strong>
              <p>{{ acao.descricao }}</p>
            </div>
            <ChevronRightIcon class="h-4 w-4 text-[var(--text-muted)]" />
          </RouterLink>
        </div>
      </section>

      <section class="card p-4">
        <h2 class="text-sm font-semibold text-[var(--text-primary)]">Atalhos operacionais</h2>
        <p class="mt-1 text-[11px] text-[var(--text-muted)]">
          Os atalhos também aparecem no menu superior.
        </p>

        <div class="mt-4 space-y-2">
          <RouterLink to="/clientes/novo" class="shortcut-row">
            <UsersIcon class="h-4 w-4" />
            <span>Novo cliente</span>
          </RouterLink>
          <RouterLink to="/pedidos/novo" class="shortcut-row">
            <ShoppingBagIcon class="h-4 w-4" />
            <span>Novo pedido</span>
          </RouterLink>
          <RouterLink to="/pedidos/kanban" class="shortcut-row">
            <ViewColumnsIcon class="h-4 w-4" />
            <span>Kanban de pedidos</span>
          </RouterLink>
          <RouterLink to="/produtos" class="shortcut-row">
            <CubeIcon class="h-4 w-4" />
            <span>Consultar catálogo</span>
          </RouterLink>
          <RouterLink to="/estoque" class="shortcut-row">
            <ArchiveBoxIcon class="h-4 w-4" />
            <span>Consultar disponibilidade</span>
          </RouterLink>
        </div>
      </section>
    </div>

    <section class="card p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-sm font-semibold text-[var(--text-primary)]">Próxima etapa</h2>
          <p class="mt-1 text-xs text-[var(--text-muted)]">
            A Frente de Caixa será adicionada na Fase 7, usando esta mesma barra superior.
          </p>
        </div>
        <span class="badge badge-warning">PDV em preparação</span>
      </div>
    </section>
  </div>
</template>

<script setup>
import { RouterLink } from 'vue-router'
import {
  ArchiveBoxIcon,
  BanknotesIcon,
  ChevronRightIcon,
  ClipboardDocumentCheckIcon,
  CubeIcon,
  PlusIcon,
  ShoppingBagIcon,
  UserGroupIcon,
  UsersIcon,
  ViewColumnsIcon,
} from '@heroicons/vue/24/outline'

const indicadores = [
  {
    label: 'Pedidos em aberto',
    valor: '—',
    legenda: 'Consulte a lista de pedidos',
    classe: 'text-brand-400',
    icon: ShoppingBagIcon,
  },
  {
    label: 'Propostas em andamento',
    valor: '—',
    legenda: 'Estrutura pronta para evolução',
    classe: 'text-warning',
    icon: ClipboardDocumentCheckIcon,
  },
  {
    label: 'Clientes ativos',
    valor: '—',
    legenda: 'Acesse o cadastro comercial',
    classe: 'text-success',
    icon: UsersIcon,
  },
  {
    label: 'Faturamento do período',
    valor: '—',
    legenda: 'Integrado ao financeiro',
    classe: 'text-[var(--text-muted)]',
    icon: BanknotesIcon,
  },
]

const acoes = [
  {
    to: '/pedidos',
    titulo: 'Pedidos de venda',
    descricao: 'Acompanhe pedidos, status e detalhes comerciais.',
    icon: ShoppingBagIcon,
  },
  {
    to: '/pedidos/kanban',
    titulo: 'Kanban comercial',
    descricao: 'Visualize pedidos por etapa e prioridade.',
    icon: ViewColumnsIcon,
  },
  {
    to: '/clientes',
    titulo: 'Clientes',
    descricao: 'Cadastros, contatos e relacionamento.',
    icon: UsersIcon,
  },
  {
    to: '/crm',
    titulo: 'CRM',
    descricao: 'Leads, oportunidades e reuniões.',
    icon: UserGroupIcon,
  },
  {
    to: '/produtos',
    titulo: 'Catálogo de produtos',
    descricao: 'Consulte produtos, preços e disponibilidade.',
    icon: CubeIcon,
  },
  {
    to: '/estoque',
    titulo: 'Consulta de estoque',
    descricao: 'Confira saldos antes de concluir a venda.',
    icon: ArchiveBoxIcon,
  },
]
</script>

<style scoped>
.commercial-action {
  display: flex;
  align-items: center;
  gap: 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 13px;
  background: var(--bg-input);
  transition: .18s ease;
}
.commercial-action:hover {
  border-color: var(--primary);
  transform: translateY(-1px);
}
.commercial-action strong {
  display: block;
  font-size: .78rem;
  color: var(--text-primary);
}
.commercial-action p {
  margin-top: 2px;
  font-size: .68rem;
  color: var(--text-muted);
}
.shortcut-row {
  display: flex;
  align-items: center;
  gap: 9px;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 12px;
  font-size: .76rem;
  color: var(--text-secondary);
}
.shortcut-row:hover {
  color: var(--text-primary);
  border-color: var(--primary);
  background: var(--bg-elevated);
}
</style>
