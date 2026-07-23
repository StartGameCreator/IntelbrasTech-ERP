<template>
  <div class="marketplace-tecnicos-page">
    <header class="page-header">
      <div>
        <div class="breadcrumb">
          <WrenchScrewdriverIcon class="w-4 h-4" />
          <span>Serviços</span>
          <ChevronRightIcon class="w-3 h-3" />
          <strong>Marketplace de Técnicos</strong>
        </div>
        <h1 class="page-title">Marketplace de Técnicos</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">
          Incorporação do portal de profissionais e clientes ao IntelbrasTech ERP.
        </p>
      </div>

      <RouterLink to="/cadastre-se" class="btn-primary">
        <UserPlusIcon class="w-4 h-4" />
        Cadastre-se
      </RouterLink>
    </header>

    <section class="summary-grid">
      <article class="summary-card">
        <UsersIcon class="summary-icon" />
        <div><span>Técnicos</span><strong>Cadastro e aprovação</strong></div>
      </article>
      <article class="summary-card">
        <BuildingOffice2Icon class="summary-icon" />
        <div><span>Clientes</span><strong>Contratantes</strong></div>
      </article>
      <article class="summary-card">
        <TicketIcon class="summary-icon" />
        <div><span>Chamados</span><strong>Atendimentos</strong></div>
      </article>
      <article class="summary-card">
        <MapPinIcon class="summary-icon" />
        <div><span>Mapa</span><strong>Proximidade</strong></div>
      </article>
    </section>

    <section class="module-grid">
      <article v-for="module in modules" :key="module.title" class="card module-card">
        <component :is="module.icon" class="w-7 h-7" />
        <div>
          <strong>{{ module.title }}</strong>
          <p>{{ module.description }}</p>
          <span>{{ module.status }}</span>
        </div>
      </article>
    </section>

    <section class="card workspace-card">
      <div>
        <h2>Workspace de incorporação</h2>
        <p>
          O código do projeto original está sendo reorganizado por domínio, mantendo o login atual
          do ERP como autenticação única.
        </p>
      </div>
      <div class="status-badge">Fase 12C.1 ativa</div>
    </section>
  </div>
</template>

<script setup>
import { markRaw } from 'vue'
import { RouterLink } from 'vue-router'
import {
  WrenchScrewdriverIcon,
  ChevronRightIcon,
  UserPlusIcon,
  UsersIcon,
  BuildingOffice2Icon,
  TicketIcon,
  MapPinIcon,
  CalendarDaysIcon,
  ChatBubbleLeftRightIcon,
  DocumentTextIcon,
  StarIcon,
  BanknotesIcon,
  Cog6ToothIcon,
} from '@heroicons/vue/24/outline'

const modules = [
  { title: 'Técnicos', description: 'Perfis, documentos, especialidades e aprovação.', status: 'Em incorporação', icon: markRaw(UsersIcon) },
  { title: 'Clientes', description: 'Pessoas e empresas que solicitam atendimento.', status: 'Em incorporação', icon: markRaw(BuildingOffice2Icon) },
  { title: 'Chamados', description: 'Abertura, distribuição e acompanhamento.', status: 'Base preparada', icon: markRaw(TicketIcon) },
  { title: 'Agenda', description: 'Disponibilidade e visitas técnicas.', status: 'Próxima etapa', icon: markRaw(CalendarDaysIcon) },
  { title: 'Chat', description: 'Comunicação entre cliente, técnico e operação.', status: 'Próxima etapa', icon: markRaw(ChatBubbleLeftRightIcon) },
  { title: 'RAT', description: 'Relatório, evidências e assinatura do cliente.', status: 'Próxima etapa', icon: markRaw(DocumentTextIcon) },
  { title: 'Avaliações', description: 'Reputação e qualidade dos atendimentos.', status: 'Próxima etapa', icon: markRaw(StarIcon) },
  { title: 'Financeiro', description: 'Repasse, comissão e pagamentos.', status: 'Próxima etapa', icon: markRaw(BanknotesIcon) },
  { title: 'Configurações', description: 'Regras, categorias, SLA e cobertura.', status: 'Próxima etapa', icon: markRaw(Cog6ToothIcon) },
]
</script>

<style scoped>
.marketplace-tecnicos-page{display:flex;flex-direction:column;gap:14px}.breadcrumb{display:flex;align-items:center;gap:6px;font-size:.62rem;color:var(--text-muted);margin-bottom:4px}.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.summary-card{display:flex;align-items:center;gap:11px;border:1px solid var(--border);border-radius:10px;background:var(--bg-card);padding:14px}.summary-icon{width:27px;height:27px;color:#60a5fa}.summary-card span,.summary-card strong{display:block}.summary-card span{font-size:.56rem;color:var(--text-muted)}.summary-card strong{font-size:.68rem;margin-top:2px}.module-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.module-card{display:flex;align-items:flex-start;gap:12px;padding:15px}.module-card>svg{color:#60a5fa;flex-shrink:0}.module-card strong,.module-card p,.module-card span{display:block}.module-card strong{font-size:.7rem}.module-card p{font-size:.57rem;color:var(--text-muted);line-height:1.5;margin-top:4px}.module-card span{font-size:.51rem;color:#fbbf24;margin-top:7px}.workspace-card{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:16px}.workspace-card h2{font-size:.72rem;font-weight:800}.workspace-card p{font-size:.58rem;color:var(--text-muted);margin-top:4px}.status-badge{border-radius:999px;padding:5px 9px;background:rgba(34,197,94,.12);color:#4ade80;font-size:.55rem}@media(max-width:1000px){.summary-grid{grid-template-columns:repeat(2,1fr)}.module-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:650px){.summary-grid,.module-grid{grid-template-columns:1fr}.workspace-card{align-items:flex-start;flex-direction:column}}
</style>
