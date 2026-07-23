<template>
  <div class="crm-monday-shell animate-fade-in">
    <section class="crm-hero card">
      <div class="crm-hero__left">
        <span class="crm-eyebrow">IntelbrasTech CRM</span>
        <h1 class="page-title">Central de relacionamento e oportunidades</h1>
        <p>
          Fluxo comercial inspirado em quadros modernos: pipeline visual, tarefas, responsáveis,
          prazos, automações e visão de performance no mesmo ambiente.
        </p>
      </div>
      <div class="crm-hero__actions">
        <button class="btn-secondary btn-sm" @click="carregar" :disabled="loading">
          <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" /> Atualizar
        </button>
        <button class="btn-secondary btn-sm" @click="modalAutomacao = true">
          <BoltIcon class="w-4 h-4" /> Automação
        </button>
        <CrmMeetingLauncher />
        <button class="btn-primary btn-sm" @click="abrirNovo">
          <PlusIcon class="w-4 h-4" /> Novo lead
        </button>
      </div>
    </section>

    <section class="crm-kpis">
      <article v-for="kpi in kpis" :key="kpi.label" class="stat-card crm-kpi">
        <div class="stat-icon" :class="kpi.bg">
          <component :is="kpi.icon" class="w-5 h-5" />
        </div>
        <div>
          <p class="kpi-label">{{ kpi.label }}</p>
          <p class="kpi-value">{{ kpi.value }}</p>
          <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ kpi.caption }}</p>
        </div>
      </article>
    </section>

    <section class="card crm-workspace">
      <div class="crm-board-header">
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h2>{{ pipelineNome }}</h2>
            <button class="crm-title-arrow" title="Configurar quadro">
              <ChevronDownIcon class="w-4 h-4" />
            </button>
            <span class="badge-brand">CRM</span>
          </div>
          <p>{{ leads.length }} registros • {{ moeda(valorTotal) }} em oportunidades</p>
        </div>
        <div class="crm-toolbar-actions">
          <button class="btn-secondary btn-sm" @click="modalAgente = true">
            <SparklesIcon class="w-4 h-4" /> Agente IA
          </button>
          <button class="btn-secondary btn-sm" @click="modalIntegracao = true">
            <LinkIcon class="w-4 h-4" /> Integrar
          </button>
        </div>
      </div>

      <div class="crm-tabs">
        <button
          v-for="aba in abas"
          :key="aba.id"
          :class="['crm-tab', abaAtiva === aba.id && 'active']"
          @click="abaAtiva = aba.id"
        >
          <component :is="aba.icon" class="w-4 h-4" /> {{ aba.label }}
        </button>
        <button class="crm-tab muted" @click="modalView = true"><PlusIcon class="w-4 h-4" /></button>
      </div>

      <div class="crm-tools">
        <CrmMeetingLauncher />
        <button class="btn-primary btn-sm" @click="abrirNovo"><PlusIcon class="w-4 h-4" /> Criar lead</button>
        <div class="crm-search">
          <MagnifyingGlassIcon class="w-4 h-4" />
          <input v-model="busca" placeholder="Pesquise leads, empresas ou responsáveis..." />
        </div>
        <select v-model="filtroResponsavel" class="select input-sm max-w-48">
          <option value="">Todos responsáveis</option>
          <option v-for="nome in responsaveis" :key="nome" :value="nome">{{ nome }}</option>
        </select>
        <button class="btn-ghost btn-sm"><FunnelIcon class="w-4 h-4" /> Filtro</button>
        <button class="btn-ghost btn-sm"><ArrowsUpDownIcon class="w-4 h-4" /> Ordenar</button>
        <button class="btn-ghost btn-sm"><EyeSlashIcon class="w-4 h-4" /> Ocultar</button>
      </div>

      <div v-if="loading" class="skeleton h-72 rounded-xl" />

      <div v-else-if="abaAtiva === 'quadro'" class="crm-table-board">
        <div v-for="grupo in gruposTabela" :key="grupo.nome" class="crm-group">
          <div class="crm-group-title" :style="{ color: grupo.cor }">
            <ChevronDownIcon class="w-4 h-4" /> {{ grupo.nome }}
            <span>{{ grupo.items.length }}</span>
          </div>
          <div class="crm-grid crm-grid-head">
            <span></span><span>Lead</span><span>Responsável</span><span>Status</span><span>Valor</span><span>Prazo</span><span>Origem</span><span>Próxima ação</span><span>+</span>
          </div>
          <div
            v-for="lead in grupo.items"
            :key="lead.id"
            class="crm-grid crm-grid-row"
            @click="abrirDetalhe(lead)"
          >
            <span><input type="checkbox" @click.stop /></span>
            <span class="crm-lead-cell">
              <strong>{{ lead.titulo || lead.nome }}</strong>
              <small>{{ lead.empresa_nome || lead.nome }}</small>
              <small v-if="lead.cnpj">CNPJ {{ formatarCnpj(lead.cnpj) }}</small>
            </span>
            <span><span class="crm-avatar">{{ iniciais(lead.responsavel_nome || lead.responsavel?.nome || 'IT') }}</span></span>
            <span><span class="crm-status" :style="{ background: lead.cor || grupo.cor }">{{ lead.etapa_nome || grupo.nome }}</span></span>
            <span>{{ moeda(lead.valor_estimado) }}</span>
            <span>{{ formatarData(lead.data_previsao_fechamento) }}</span>
            <span>{{ lead.origem || 'Comercial' }}</span>
            <span>{{ lead.proxima_acao || 'Acompanhar' }}</span>
            <span class="text-[var(--text-muted)]">•••</span>
          </div>
          <button class="crm-add-row" @click="abrirNovoComEtapa(grupo)">+ Adicionar lead</button>
        </div>
      </div>

      <div v-else-if="abaAtiva === 'kanban'" class="crm-kanban">
        <div
          v-for="coluna in colunasView"
          :key="coluna.etapa.id"
          class="crm-kanban-column"
          @dragover.prevent
          @drop="mover(coluna.etapa.id)"
        >
          <div class="crm-kanban-title" :style="{ '--accent': coluna.etapa.cor || '#6366f1' }">
            <span>{{ coluna.etapa.nome }}</span><small>{{ coluna.leads.length }} • {{ moeda(coluna.valor_total) }}</small>
          </div>
          <div class="crm-kanban-list">
            <article
              v-for="lead in coluna.leads"
              :key="lead.id"
              draggable="true"
              @dragstart="leadArrastado = lead.id"
              @click="abrirDetalhe(lead)"
              class="crm-card-lead"
            >
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <strong>{{ lead.titulo || lead.nome }}</strong>
                  <p>{{ lead.empresa_nome || lead.nome }}</p>
                </div>
                <span class="crm-avatar small">{{ iniciais(lead.responsavel_nome || 'IT') }}</span>
              </div>
              <div class="crm-card-meta">
                <span>{{ moeda(lead.valor_estimado) }}</span>
                <span>{{ formatarData(lead.data_previsao_fechamento) }}</span>
              </div>
            </article>
            <button class="crm-kanban-add" @click="abrirNovoComEtapa(coluna.etapa)">+ Novo lead</button>
          </div>
        </div>
      </div>

      <div v-else-if="abaAtiva === 'dashboard'" class="crm-dashboard">
        <article class="crm-chart-card">
          <h3>Oportunidades por status</h3>
          <div class="crm-bars">
            <div v-for="coluna in colunasView" :key="coluna.etapa.id" class="crm-bar-line">
              <span>{{ coluna.etapa.nome }}</span>
              <div><i :style="{ width: larguraBarra(coluna.valor_total), background: coluna.etapa.cor }"></i></div>
              <strong>{{ moeda(coluna.valor_total) }}</strong>
            </div>
          </div>
        </article>
        <article class="crm-chart-card">
          <h3>Resumo do funil</h3>
          <div class="crm-funnel">
            <div v-for="coluna in colunasView" :key="coluna.etapa.id" :style="{ background: coluna.etapa.cor }">
              {{ coluna.etapa.nome }} <strong>{{ coluna.leads.length }}</strong>
            </div>
          </div>
        </article>
        <article class="crm-chart-card crm-ai-card">
          <SparklesIcon class="w-8 h-8" />
          <h3>Assistente comercial</h3>
          <p>Próximas ações sugeridas para manter o funil ativo e reduzir oportunidades paradas.</p>
          <button class="btn-secondary btn-sm" @click="modalAgente = true">Abrir agente</button>
        </article>
      </div>

      <div v-else-if="abaAtiva === 'calendario'" class="crm-calendar">
        <div class="crm-calendar-head"><strong>Agenda comercial</strong><span>Próximos prazos e follow-ups</span></div>
        <div class="crm-calendar-grid">
          <div v-for="dia in calendario" :key="dia.label" class="crm-day">
            <strong>{{ dia.label }}</strong>
            <article v-for="lead in dia.items" :key="lead.id" :style="{ borderColor: corLead(lead) }" @click="abrirDetalhe(lead)">
              {{ lead.titulo || lead.nome }}
            </article>
          </div>
        </div>
      </div>

      <div v-else-if="abaAtiva === 'gantt'" class="crm-gantt">
        <div class="crm-gantt-head">
          <span>Lead</span><span>Jul 9</span><span>Jul 10</span><span>Jul 11</span><span>Jul 12</span><span>Jul 13</span><span>Jul 14</span>
        </div>
        <div v-for="(lead, index) in leadsFiltrados" :key="lead.id" class="crm-gantt-row">
          <span>{{ lead.titulo || lead.nome }}</span>
          <i :style="{ gridColumn: `${2 + (index % 4)} / span 2`, background: corLead(lead) }">{{ lead.etapa_nome }}</i>
        </div>
      </div>

      <div v-else class="crm-automation-grid">
        <article v-for="auto in automacoes" :key="auto.titulo" class="crm-automation-card">
          <component :is="auto.icon" class="w-5 h-5" />
          <h3>{{ auto.titulo }}</h3>
          <p>{{ auto.texto }}</p>
          <button class="btn-secondary btn-sm">Configurar</button>
        </article>
      </div>
    </section>

    <button class="crm-help" @click="modalAgente = true">
      <SparklesIcon class="w-5 h-5" /> Como posso ajudar?
    </button>

    <Teleport to="body" v-if="modalLead">
      <div class="modal-overlay" @click.self="modalLead = false">
        <div class="modal-lg animate-slide-up">
          <div class="modal-header">
            <div>
              <h3 class="text-base font-semibold text-[var(--text-primary)]">{{ leadAtual.id ? 'Editar lead' : 'Novo lead' }}</h3>
              <p class="text-xs text-[var(--text-muted)] mt-1">Cadastro rápido no fluxo comercial.</p>
            </div>
            <button @click="modalLead = false" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div class="modal-body space-y-3">
            <div class="form-row-2">
              <div class="form-group"><label class="label">Nome do contato *</label><input v-model="leadAtual.nome" class="input" /></div>
              <div class="form-group"><label class="label">Título da oportunidade</label><input v-model="leadAtual.titulo" class="input" /></div>
            </div>
            <div class="form-row-2">
              <div class="form-group"><label class="label">Empresa</label><input v-model="leadAtual.empresa_nome" class="input" /></div>
              <div class="form-group"><label class="label">Origem</label><input v-model="leadAtual.origem" class="input" placeholder="Marketplace, indicação, telefone..." /></div>
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label class="label">CNPJ</label>
                <input
                  :value="formatarCnpj(leadAtual.cnpj)"
                  class="input"
                  maxlength="18"
                  placeholder="00.000.000/0000-00"
                  @input="leadAtual.cnpj = somenteDigitos($event.target.value).slice(0, 14)"
                />
              </div>
              <div class="form-group">
                <label class="label">Situação cadastral</label>
                <input
                  :value="leadAtual.cnpj ? 'Aguardando enriquecimento cadastral' : 'CNPJ não informado'"
                  class="input"
                  disabled
                />
              </div>
            </div>
            <div class="form-row-2">
              <div class="form-group"><label class="label">E-mail</label><input v-model="leadAtual.email" type="email" class="input" /></div>
              <div class="form-group"><label class="label">Telefone</label><input v-model="leadAtual.telefone" class="input" /></div>
            </div>
            <div class="form-row-3">
              <div class="form-group"><label class="label">Valor estimado</label><input v-model.number="leadAtual.valor_estimado" type="number" step="0.01" min="0" class="input" /></div>
              <div class="form-group"><label class="label">Previsão</label><input v-model="leadAtual.data_previsao_fechamento" type="date" class="input" /></div>
              <div class="form-group"><label class="label">Próxima ação</label><input v-model="leadAtual.proxima_acao" class="input" /></div>
            </div>
          </div>
          <div class="modal-footer justify-between">
            <div v-if="leadAtual.id" class="flex gap-2">
              <button @click="ganho" class="btn-secondary btn-sm text-success" :disabled="processando">Ganho</button>
              <button @click="perdido" class="btn-secondary btn-sm text-danger" :disabled="processando">Perdido</button>
            </div>
            <div class="flex gap-2 ml-auto">
              <button @click="modalLead = false" class="btn-secondary">Cancelar</button>
              <button @click="salvar" class="btn-primary" :disabled="processando">Salvar</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <Teleport to="body" v-if="modalAgente || modalAutomacao || modalIntegracao || modalView">
      <div class="modal-overlay" @click.self="fecharModais">
        <div class="modal-md animate-slide-up">
          <div class="modal-header">
            <h3 class="text-base font-semibold text-[var(--text-primary)]">{{ modalTitulo }}</h3>
            <button @click="fecharModais" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div class="modal-body">
            <p class="text-sm text-[var(--text-secondary)] leading-relaxed">{{ modalTexto }}</p>
            <div class="crm-prompt-box mt-4">
              <SparklesIcon class="w-5 h-5" />
              <span>{{ modalSugestao }}</span>
            </div>
          </div>
          <div class="modal-footer"><button class="btn-primary" @click="fecharModais">Entendi</button></div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import CrmMeetingLauncher from '@/components/crm/CrmMeetingLauncher.vue'
import { useFormato } from '@/composables/useFormato'
import {
  ArrowPathIcon, PlusIcon, XMarkIcon, ChevronDownIcon, MagnifyingGlassIcon,
  FunnelIcon, ArrowsUpDownIcon, EyeSlashIcon, ViewColumnsIcon, Squares2X2Icon,
  ChartPieIcon, CalendarDaysIcon, Bars3BottomLeftIcon, BoltIcon, SparklesIcon,
  LinkIcon, BanknotesIcon, UserGroupIcon, ClockIcon, CheckCircleIcon,
  ChatBubbleLeftRightIcon, BellAlertIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()
const { moeda } = useFormato()

const abas = [
  { id: 'quadro', label: 'Quadro principal', icon: ViewColumnsIcon },
  { id: 'kanban', label: 'Kanban', icon: Squares2X2Icon },
  { id: 'dashboard', label: 'Dashboard', icon: ChartPieIcon },
  { id: 'calendario', label: 'Calendário', icon: CalendarDaysIcon },
  { id: 'gantt', label: 'Gantt', icon: Bars3BottomLeftIcon },
  { id: 'automacoes', label: 'Automações', icon: BoltIcon },
]

const etapasBase = [
  { id: 1, nome: 'Novo lead', cor: '#3b82f6' },
  { id: 2, nome: 'Qualificação', cor: '#8b5cf6' },
  { id: 3, nome: 'Proposta enviada', cor: '#f59e0b' },
  { id: 4, nome: 'Negociação', cor: '#06b6d4' },
  { id: 5, nome: 'Ganho', cor: '#10b981' },
]

const leadsDemo = [
  { id: 'demo-1', nome: 'Ana Martins', empresa_nome: 'Solar Prime', titulo: 'Energia solar residencial', email: 'ana@solarprime.com', telefone: '(61) 99999-0001', valor_estimado: 18500, etapa_id: 1, etapa_nome: 'Novo lead', responsavel_nome: 'Elvis', data_previsao_fechamento: '2026-07-09', origem: 'Indicação', proxima_acao: 'Ligar hoje', cor: '#3b82f6' },
  { id: 'demo-2', nome: 'Carlos Lima', empresa_nome: 'Lima Construções', titulo: 'Projeto fotovoltaico comercial', email: 'carlos@lima.com', telefone: '(61) 99999-0002', valor_estimado: 43000, etapa_id: 2, etapa_nome: 'Qualificação', responsavel_nome: 'Elvis', data_previsao_fechamento: '2026-07-10', origem: 'Site', proxima_acao: 'Enviar diagnóstico', cor: '#8b5cf6' },
  { id: 'demo-3', nome: 'Bianca Rocha', empresa_nome: 'Mercado BR', titulo: 'Integração marketplace', email: 'bianca@mercadobr.com', telefone: '(61) 99999-0003', valor_estimado: 12600, etapa_id: 3, etapa_nome: 'Proposta enviada', responsavel_nome: 'Comercial', data_previsao_fechamento: '2026-07-11', origem: 'Marketplace', proxima_acao: 'Follow-up proposta', cor: '#f59e0b' },
  { id: 'demo-4', nome: 'João Pedro', empresa_nome: 'JP Distribuidora', titulo: 'Contrato de suporte ERP', email: 'joao@jp.com', telefone: '(61) 99999-0004', valor_estimado: 8900, etapa_id: 4, etapa_nome: 'Negociação', responsavel_nome: 'Suporte', data_previsao_fechamento: '2026-07-12', origem: 'Telefone', proxima_acao: 'Revisar contrato', cor: '#06b6d4' },
  { id: 'demo-5', nome: 'Marina Alves', empresa_nome: 'Pet Center', titulo: 'Venda consultiva PetLove', email: 'marina@petcenter.com', telefone: '(61) 99999-0005', valor_estimado: 5200, etapa_id: 5, etapa_nome: 'Ganho', responsavel_nome: 'Elvis', data_previsao_fechamento: '2026-07-13', origem: 'PetLove', proxima_acao: 'Implantação', cor: '#10b981' },
]

const automacoes = [
  { titulo: 'Lead novo', texto: 'Quando um lead entrar, criar tarefa de primeiro contato e avisar o responsável.', icon: BellAlertIcon },
  { titulo: 'Proposta parada', texto: 'Se uma proposta ficar parada por 3 dias, enviar alerta para follow-up.', icon: ClockIcon },
  { titulo: 'Ganho confirmado', texto: 'Ao marcar como ganho, criar pedido ou ordem de serviço automaticamente.', icon: CheckCircleIcon },
  { titulo: 'Resumo diário', texto: 'Gerar um resumo com oportunidades novas, vencidas e próximas ações.', icon: ChatBubbleLeftRightIcon },
]

const colunas = ref([])
const resumo = ref({})
const pipelineNome = ref('Pipeline Comercial')
const pipelineId = ref(null)
const loading = ref(false)
const processando = ref(false)
const modalLead = ref(false)
const modalAgente = ref(false)
const modalAutomacao = ref(false)
const modalIntegracao = ref(false)
const modalView = ref(false)
const leadArrastado = ref(null)
const leadAtual = ref({})
const abaAtiva = ref('quadro')
const busca = ref('')
const filtroResponsavel = ref('')

const baseColunas = computed(() => colunas.value.length ? colunas.value : etapasBase.map(etapa => ({ etapa, leads: leadsDemo.filter(l => l.etapa_id === etapa.id) })))
const leads = computed(() => baseColunas.value.flatMap(c => c.leads || []))
const valorTotal = computed(() => leads.value.reduce((s, l) => s + Number(l.valor_estimado || 0), 0))
const leadsFiltrados = computed(() => {
  const termo = busca.value.trim().toLowerCase()
  return leads.value.filter(lead => {
    const texto = `${lead.nome || ''} ${lead.titulo || ''} ${lead.empresa_nome || ''} ${lead.cnpj || ''} ${lead.responsavel_nome || lead.responsavel?.nome || ''}`.toLowerCase()
    const okBusca = !termo || texto.includes(termo)
    const resp = lead.responsavel_nome || lead.responsavel?.nome || 'Sem responsável'
    const okResp = !filtroResponsavel.value || resp === filtroResponsavel.value
    return okBusca && okResp
  })
})
const responsaveis = computed(() => [...new Set(leads.value.map(l => l.responsavel_nome || l.responsavel?.nome || 'Sem responsável'))])
const colunasView = computed(() => {
  const idsFiltrados = new Set(leadsFiltrados.value.map(l => l.id))
  return baseColunas.value.map(col => {
    const etapa = col.etapa || col
    const leadsCol = (col.leads || []).filter(lead => idsFiltrados.has(lead.id))
    return { etapa, leads: leadsCol, valor_total: leadsCol.reduce((s, l) => s + Number(l.valor_estimado || 0), 0) }
  })
})
const gruposTabela = computed(() => colunasView.value.map(c => ({ nome: c.etapa.nome, cor: c.etapa.cor || '#6366f1', etapa_id: c.etapa.id, items: c.leads })))
const maiorValorColuna = computed(() => Math.max(...colunasView.value.map(c => c.valor_total), 1))
const kpis = computed(() => [
  { label: 'Leads abertos', value: leads.value.filter(l => (l.etapa_nome || '').toLowerCase() !== 'ganho').length, caption: 'Em andamento no funil', icon: UserGroupIcon, bg: 'bg-brand-500/15 text-brand-400' },
  { label: 'Valor em negociação', value: moeda(valorTotal.value), caption: 'Soma estimada do pipeline', icon: BanknotesIcon, bg: 'bg-success/15 text-success' },
  { label: 'Próximas ações', value: leads.value.filter(l => l.proxima_acao).length, caption: 'Follow-ups cadastrados', icon: ClockIcon, bg: 'bg-warning/15 text-warning' },
  { label: 'Conversão', value: `${Math.round((leads.value.filter(l => (l.etapa_nome || '').toLowerCase() === 'ganho').length / Math.max(leads.value.length, 1)) * 100)}%`, caption: 'Base atual do quadro', icon: CheckCircleIcon, bg: 'bg-info/15 text-info' },
])
const calendario = computed(() => ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'].map((label, i) => ({ label, items: leadsFiltrados.value.filter((_, idx) => idx % 7 === i) })))
const modalTitulo = computed(() => modalAgente.value ? 'Agente comercial IA' : modalAutomacao.value ? 'Automações do CRM' : modalIntegracao.value ? 'Integrações' : 'Nova visualização')
const modalTexto = computed(() => modalAgente.value
  ? 'Aqui ficará o assistente para resumir leads, sugerir follow-ups e preparar contatos. A tela já está pronta para integrar com uma API de IA depois.'
  : modalAutomacao.value
    ? 'Automatize tarefas repetitivas: avisos, mudança de etapa, criação de pedidos, OS e lembretes comerciais.'
    : modalIntegracao.value
      ? 'Área preparada para conectar marketplace, e-mail, WhatsApp, logística e outras fontes de leads.'
      : 'Você poderá adicionar novas visões sem perder o quadro principal: calendário, kanban, relatórios e cartões.')
const modalSugestao = computed(() => modalAgente.value ? 'Sugestão: “Liste os leads parados e diga o próximo passo.”' : 'Fluxo preparado para implantação gradual sem quebrar o que já funciona.')

function leadVazio() { return { nome: '', cnpj: '', empresa_nome: '', titulo: '', email: '', telefone: '', valor_estimado: null, data_previsao_fechamento: '', origem: '', proxima_acao: '' } }
function normalizarColunas(payload) {
  const recebidas = payload?.colunas || []
  return recebidas.map(col => {
    const etapa = col.etapa || col
    return {
      etapa: { id: etapa.id, nome: etapa.nome, cor: etapa.cor || '#6366f1' },
      leads: (col.leads || []).map(l => ({ ...l, etapa_id: etapa.id, etapa_nome: etapa.nome, cor: etapa.cor || '#6366f1', responsavel_nome: l.responsavel_nome || l.responsavel?.nome }))
    }
  })
}
async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get('/leads/kanban')
    const normalizadas = normalizarColunas(data)
    colunas.value = normalizadas.length ? normalizadas : []
    resumo.value = data.resumo || {}
    pipelineNome.value = data.pipeline?.nome || 'Pipeline Comercial'
    pipelineId.value = data.pipeline?.id || null
  } catch {
    colunas.value = []
  } finally { loading.value = false }
}
function abrirNovo() { leadAtual.value = leadVazio(); modalLead.value = true }
function abrirNovoComEtapa(grupo) { leadAtual.value = { ...leadVazio(), etapa_id: grupo.etapa_id || grupo.id }; modalLead.value = true }
function abrirDetalhe(lead) { leadAtual.value = { ...lead }; modalLead.value = true }
async function salvar() {
  if (!leadAtual.value.nome?.trim()) { toast.warning('Informe o nome do contato.'); return }
  processando.value = true
  try {
    if (String(leadAtual.value.id || '').startsWith('demo-')) {
      toast.success('Lead salvo no modo visual. Conecte a API para persistir este registro.')
      modalLead.value = false
      return
    }
    if (leadAtual.value.id) {
      const { data } = await api.put(`/leads/${leadAtual.value.id}`, leadAtual.value)
      toast.success(data.message || 'Lead atualizado.')
    } else {
      const { data } = await api.post('/leads', { ...leadAtual.value, pipeline_id: pipelineId.value })
      toast.success(data.message || 'Lead criado.')
    }
    modalLead.value = false
    await carregar()
  } catch (e) { toast.error(e.response?.data?.message || 'Não foi possível salvar o lead.') }
  finally { processando.value = false }
}
async function ganho() { await acaoLead('ganho', 'Lead marcado como ganho.') }
async function perdido() { await acaoLead('perdido', 'Lead marcado como perdido.') }
async function acaoLead(acao, msg) {
  if (String(leadAtual.value.id || '').startsWith('demo-')) { toast.success(msg); modalLead.value = false; return }
  processando.value = true
  try { const { data } = await api.post(`/leads/${leadAtual.value.id}/${acao}`); toast.success(data.message || msg); modalLead.value = false; await carregar() }
  catch (e) { toast.error(e.response?.data?.message || 'Não foi possível atualizar o lead.') }
  finally { processando.value = false }
}
async function mover(etapaId) {
  if (!leadArrastado.value) return
  const id = leadArrastado.value
  leadArrastado.value = null
  if (String(id).startsWith('demo-')) { toast.info('Arraste real disponível para leads salvos na API.'); return }
  try { await api.post(`/leads/${id}/mover-etapa`, { etapa_id: etapaId }); await carregar() }
  catch (e) { toast.error(e.response?.data?.message || 'Não foi possível mover o lead.') }
}
function fecharModais() { modalAgente.value = false; modalAutomacao.value = false; modalIntegracao.value = false; modalView.value = false }

function somenteDigitos(value) {
  return String(value || '').replace(/\D/g, '')
}

function formatarCnpj(value) {
  const digits = somenteDigitos(value).slice(0, 14)
  if (!digits) return ''
  if (digits.length < 14) return digits

  return digits.replace(
    /^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/,
    '$1.$2.$3/$4-$5',
  )
}
function iniciais(nome) { return String(nome || 'IT').split(' ').map(p => p[0]).join('').slice(0, 2).toUpperCase() }
function formatarData(data) { if (!data) return 'Sem prazo'; const [a, m, d] = String(data).split('-'); return d && m ? `${d}/${m}` : data }
function corLead(lead) { return lead.cor || etapasBase.find(e => e.id === lead.etapa_id)?.cor || '#6366f1' }
function larguraBarra(valor) { return `${Math.max(8, Math.round((Number(valor || 0) / maiorValorColuna.value) * 100))}%` }

onMounted(carregar)
</script>

<style scoped>
.crm-monday-shell { position: relative; display: flex; flex-direction: column; gap: 1.25rem; }
.crm-hero { padding: 1.25rem; display: flex; justify-content: space-between; gap: 1rem; align-items: center; overflow: hidden; position: relative; }
.crm-hero:before { content: ''; position: absolute; inset: -40% auto auto 50%; width: 420px; height: 220px; background: radial-gradient(circle, rgba(99,102,241,.24), transparent 65%); pointer-events: none; }
.crm-hero__left { max-width: 720px; position: relative; z-index: 1; }
.crm-eyebrow { display: inline-flex; color: var(--brand); font-size: .72rem; text-transform: uppercase; letter-spacing: .12em; font-weight: 700; margin-bottom: .4rem; }
.crm-hero p, .crm-board-header p { color: var(--text-secondary); font-size: .85rem; margin-top: .35rem; }
.crm-hero__actions, .crm-toolbar-actions { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; position: relative; z-index: 1; }
.crm-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
.crm-kpi { padding: 1rem; }
.crm-workspace { padding: 1.25rem; min-height: 560px; overflow: hidden; }
.crm-board-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
.crm-board-header h2 { font-size: 1.35rem; font-weight: 700; color: var(--text-primary); }
.crm-title-arrow { color: var(--text-secondary); display: inline-flex; }
.crm-tabs { display: flex; align-items: center; gap: .25rem; border-bottom: 1px solid var(--border); overflow-x: auto; }
.crm-tab { display: inline-flex; align-items: center; gap: .45rem; padding: .75rem .85rem; color: var(--text-secondary); font-size: .85rem; border-bottom: 2px solid transparent; white-space: nowrap; transition: .15s; }
.crm-tab:hover { color: var(--text-primary); background: var(--bg-elevated); }
.crm-tab.active { color: var(--brand); border-color: var(--brand); }
.crm-tab.muted { color: var(--text-muted); }
.crm-tools { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; padding: 1rem 0; }
.crm-search { display: flex; align-items: center; gap: .5rem; min-width: 280px; flex: 1; max-width: 520px; height: 34px; padding: 0 .75rem; border: 1px solid var(--border); border-radius: .65rem; background: var(--bg-input); color: var(--text-muted); }
.crm-search input { background: transparent; outline: none; width: 100%; color: var(--text-primary); font-size: .85rem; }
.crm-table-board { display: flex; flex-direction: column; gap: 1.7rem; overflow-x: auto; padding-bottom: .5rem; }
.crm-group { min-width: 980px; }
.crm-group-title { display: flex; align-items: center; gap: .45rem; font-size: 1rem; font-weight: 700; margin-bottom: .5rem; }
.crm-group-title span { font-size: .75rem; color: var(--text-muted); font-weight: 600; }
.crm-grid { display: grid; grid-template-columns: 42px minmax(190px, 1.5fr) 110px 150px 120px 110px 120px 170px 50px; align-items: center; }
.crm-grid-head { background: var(--bg-elevated); border: 1px solid var(--border); border-radius: .75rem .75rem 0 0; color: var(--text-secondary); font-size: .75rem; font-weight: 700; }
.crm-grid-head span, .crm-grid-row span { padding: .65rem .75rem; border-right: 1px solid var(--border-subtle); min-height: 42px; display: flex; align-items: center; }
.crm-grid-head span:last-child, .crm-grid-row span:last-child { border-right: none; }
.crm-grid-row { border-left: 1px solid var(--border); border-right: 1px solid var(--border); border-bottom: 1px solid var(--border-subtle); cursor: pointer; color: var(--text-secondary); font-size: .82rem; background: var(--bg-surface); transition: .12s; }
.crm-grid-row:hover { background: var(--bg-elevated); }
.crm-lead-cell { flex-direction: column; align-items: flex-start !important; gap: .15rem; color: var(--text-primary); }
.crm-lead-cell small { color: var(--text-muted); font-size: .72rem; }
.crm-avatar { width: 28px; height: 28px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #6366f1, #ec4899); color: white; font-weight: 800; font-size: .68rem; }
.crm-avatar.small { width: 24px; height: 24px; font-size: .6rem; }
.crm-status { color: white; font-weight: 700; justify-content: center !important; border-radius: .45rem; min-height: 28px !important; padding: .35rem .7rem !important; width: 100%; }
.crm-add-row { width: 100%; min-height: 40px; padding-left: 64px; text-align: left; color: var(--text-muted); background: var(--bg-surface); border: 1px dashed var(--border); border-radius: 0 0 .75rem .75rem; font-size: .82rem; }
.crm-kanban { display: flex; gap: 1rem; overflow-x: auto; padding-bottom: .5rem; }
.crm-kanban-column { min-width: 285px; max-width: 285px; background: var(--bg-elevated); border: 1px solid var(--border); border-radius: 1rem; overflow: hidden; }
.crm-kanban-title { padding: .85rem 1rem; border-top: 4px solid var(--accent); display: flex; align-items: center; justify-content: space-between; gap: .5rem; font-weight: 700; }
.crm-kanban-title small { color: var(--text-muted); font-weight: 600; }
.crm-kanban-list { padding: .75rem; display: flex; flex-direction: column; gap: .65rem; min-height: 360px; }
.crm-card-lead { padding: .9rem; border: 1px solid var(--border); border-radius: .85rem; background: var(--bg-surface); cursor: grab; transition: .15s; }
.crm-card-lead:hover { transform: translateY(-1px); border-color: var(--brand); }
.crm-card-lead strong { color: var(--text-primary); font-size: .88rem; }
.crm-card-lead p { color: var(--text-muted); font-size: .76rem; margin-top: .2rem; }
.crm-card-meta { margin-top: .85rem; display: flex; justify-content: space-between; color: var(--text-secondary); font-size: .76rem; }
.crm-kanban-add { padding: .75rem; border: 1px dashed var(--border); border-radius: .75rem; color: var(--text-muted); font-size: .82rem; }
.crm-dashboard { display: grid; grid-template-columns: 1.4fr 1fr; gap: 1rem; }
.crm-chart-card { border: 1px solid var(--border); background: var(--bg-elevated); border-radius: 1rem; padding: 1rem; }
.crm-chart-card h3 { font-weight: 700; margin-bottom: 1rem; }
.crm-bars { display: flex; flex-direction: column; gap: .85rem; }
.crm-bar-line { display: grid; grid-template-columns: 140px 1fr 100px; gap: .75rem; align-items: center; color: var(--text-secondary); font-size: .82rem; }
.crm-bar-line div { height: 10px; background: var(--bg-input); border-radius: 999px; overflow: hidden; }
.crm-bar-line i { display: block; height: 100%; border-radius: 999px; }
.crm-funnel { display: flex; flex-direction: column; align-items: center; gap: .55rem; }
.crm-funnel div { width: 100%; color: white; padding: .7rem 1rem; border-radius: .75rem; display: flex; justify-content: space-between; font-weight: 700; }
.crm-ai-card { grid-column: 1 / -1; display: flex; align-items: center; gap: 1rem; }
.crm-ai-card p { color: var(--text-secondary); flex: 1; }
.crm-calendar-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: .9rem; color: var(--text-secondary); }
.crm-calendar-head strong { color: var(--text-primary); font-size: 1rem; }
.crm-calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(130px, 1fr)); border: 1px solid var(--border); border-radius: 1rem; overflow: hidden; }
.crm-day { min-height: 190px; border-right: 1px solid var(--border); padding: .75rem; background: var(--bg-surface); }
.crm-day:last-child { border-right: 0; }
.crm-day strong { color: var(--text-secondary); font-size: .8rem; }
.crm-day article { margin-top: .55rem; padding: .45rem .55rem; border-left: 3px solid; background: var(--bg-elevated); border-radius: .45rem; font-size: .75rem; cursor: pointer; }
.crm-gantt { min-width: 860px; overflow-x: auto; }
.crm-gantt-head, .crm-gantt-row { display: grid; grid-template-columns: 190px repeat(6, 1fr); border-bottom: 1px solid var(--border-subtle); align-items: center; }
.crm-gantt-head { color: var(--text-secondary); font-weight: 700; font-size: .75rem; border-top: 1px solid var(--border); }
.crm-gantt-head span, .crm-gantt-row span { padding: .75rem; border-right: 1px solid var(--border-subtle); }
.crm-gantt-row { min-height: 56px; position: relative; }
.crm-gantt-row i { color: white; border-radius: 999px; padding: .35rem .75rem; font-size: .75rem; font-style: normal; text-align: center; }
.crm-automation-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
.crm-automation-card { border: 1px solid var(--border); background: var(--bg-elevated); border-radius: 1rem; padding: 1rem; }
.crm-automation-card h3 { font-weight: 700; margin-top: .7rem; }
.crm-automation-card p { color: var(--text-secondary); font-size: .82rem; margin: .45rem 0 1rem; }
.crm-help { position: fixed; right: 1.5rem; bottom: 1.5rem; z-index: 30; display: flex; align-items: center; gap: .6rem; border-radius: 1rem; padding: .85rem 1rem; color: var(--text-primary); background: var(--bg-surface); border: 1px solid var(--border); box-shadow: 0 12px 35px rgba(0,0,0,.35); }
.crm-help:before { content: ''; position: absolute; inset: -2px; border-radius: 1.1rem; background: linear-gradient(90deg, #06b6d4, #8b5cf6, #f59e0b); z-index: -1; }
.crm-prompt-box { display: flex; gap: .75rem; align-items: flex-start; border: 1px solid var(--border); background: var(--bg-elevated); border-radius: .9rem; padding: .9rem; color: var(--text-secondary); }
@media (max-width: 1200px) { .crm-kpis, .crm-automation-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .crm-dashboard { grid-template-columns: 1fr; } }
@media (max-width: 760px) { .crm-hero, .crm-board-header { flex-direction: column; align-items: stretch; } .crm-kpis, .crm-automation-grid { grid-template-columns: 1fr; } .crm-help { right: 1rem; bottom: 1rem; } }
</style>
