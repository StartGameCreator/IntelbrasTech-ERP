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
        <div v-if="!gruposTabela.length" class="crm-empty-state">
          <UserGroupIcon class="w-10 h-10" />
          <h3>Nenhum lead no pipeline</h3>
          <p>Os leads enviados pelo portal, simulador, assistência ou cadastrados pela equipe aparecerão aqui.</p>
          <button class="btn-primary btn-sm" @click="abrirNovo"><PlusIcon class="w-4 h-4" /> Criar primeiro lead</button>
        </div>
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
        <div v-if="!colunasView.length" class="crm-empty-state w-full">
          <Squares2X2Icon class="w-10 h-10" /><h3>Pipeline ainda não configurado</h3>
          <p>Configure um pipeline ativo para iniciar o quadro Kanban.</p>
        </div>
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
        <article class="crm-chart-card crm-forecast"><h3>Forecast comercial</h3><div><span>Pipeline aberto<strong>{{ moeda(gestao.forecast?.pipeline) }}</strong></span><span>Previsão ponderada<strong>{{ moeda(gestao.forecast?.ponderado) }}</strong></span><span>Receita ganha no mês<strong>{{ moeda(gestao.forecast?.ganho_mes) }}</strong></span><span>Negócios ganhos<strong>{{ gestao.forecast?.negocios_ganhos || 0 }}</strong></span></div></article>
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
        <article class="crm-chart-card crm-productivity"><h3>Produtividade por vendedor</h3><div class="crm-productivity-row head"><span>Responsável</span><span>Leads</span><span>Ganhos</span><span>Receita</span></div><div v-for="u in gestao.produtividade" :key="u.id" class="crm-productivity-row"><span>{{ u.nome }}</span><span>{{ u.leads }}</span><span>{{ u.ganhos }}</span><span>{{ moeda(u.valor_ganho) }}</span></div></article>
      </div>

      <div v-else-if="abaAtiva === 'calendario'" class="crm-calendar">
        <div class="crm-calendar-head"><strong>Agenda comercial</strong><span>Próximos prazos e follow-ups</span></div>
        <div class="crm-calendar-grid">
          <div v-for="dia in calendario" :key="dia.data" class="crm-day">
            <strong>{{ dia.label }} <small>{{ dia.dataLabel }}</small></strong>
            <article v-for="lead in dia.items" :key="lead.id" :style="{ borderColor: corLead(lead) }" @click="abrirDetalhe(lead)">
              {{ lead.titulo || lead.nome }}
            </article>
          </div>
        </div>
      </div>

      <div v-else-if="abaAtiva === 'gantt'" class="crm-gantt">
        <div class="crm-gantt-head">
          <span>Lead</span><span v-for="dia in ganttDias" :key="dia.data">{{ dia.curto }}</span>
        </div>
        <div v-for="lead in leadsComPrazo" :key="lead.id" class="crm-gantt-row">
          <span>{{ lead.titulo || lead.nome }}</span>
          <i :style="{ gridColumn: `${colunaGantt(lead)} / span 1`, background: corLead(lead) }">{{ lead.etapa_nome }}</i>
        </div>
        <div v-if="!leadsComPrazo.length" class="crm-empty-state"><ClockIcon class="w-9 h-9" /><h3>Nenhum prazo nos próximos dias</h3><p>Cadastre a próxima ação ou a previsão de fechamento nos leads.</p></div>
      </div>

      <div v-else class="crm-management">
        <form class="crm-config-card" @submit.prevent="salvarAutomacao"><h3>Nova automação</h3><p>Quando um evento ocorrer, crie automaticamente o próximo passo.</p><input v-model="automacaoForm.nome" required class="input" placeholder="Nome da automação"><select v-model="automacaoForm.gatilho" class="input"><option value="lead_criado">Lead criado</option><option value="etapa_alterada">Etapa alterada</option><option value="lead_ganho">Lead ganho</option><option value="lead_perdido">Lead perdido</option></select><input v-model="automacaoForm.titulo_tarefa" required class="input" placeholder="Título da tarefa"><input v-model.number="automacaoForm.dias" type="number" min="0" class="input" placeholder="Executar em quantos dias"><button class="btn-primary btn-sm">Salvar automação</button></form>
        <section class="crm-config-card"><h3>Automações ativas</h3><article v-for="auto in gestao.automacoes" :key="auto.id" class="crm-rule"><div><b>{{ auto.nome }}</b><small>{{ auto.gatilho }} → {{ auto.acao }} · {{ auto.execucoes }} execuções</small></div><span :class="auto.ativo ? 'badge-success':'badge-gray'">{{ auto.ativo ? 'Ativa':'Pausada' }}</span></article><p v-if="!gestao.automacoes?.length" class="text-xs text-[var(--text-muted)]">Nenhuma automação configurada.</p></section>
        <form class="crm-config-card" @submit.prevent="salvarMeta"><h3>Meta comercial</h3><p>Defina valor e quantidade para o período.</p><input v-model="metaForm.periodo_inicio" type="date" required class="input"><input v-model="metaForm.periodo_fim" type="date" required class="input"><input v-model.number="metaForm.meta_valor" type="number" min="0" class="input" placeholder="Meta em R$"><input v-model.number="metaForm.meta_quantidade" type="number" min="0" class="input" placeholder="Quantidade de negócios"><button class="btn-primary btn-sm">Criar meta</button></form>
        <form class="crm-config-card" @submit.prevent="salvarMotivo"><h3>Motivos de perda</h3><p>Padronize os motivos para analisar onde as vendas são perdidas.</p><div class="flex gap-2"><input v-model="motivoForm.nome" required class="input" placeholder="Novo motivo"><button class="btn-primary btn-sm">Adicionar</button></div><div class="crm-tags"><span v-for="m in gestao.motivos_perda" :key="m.id">{{ m.nome }}</span></div></form>
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
            <RfbEnrichmentPanel
              v-if="leadAtual?.id && leadAtual?.cnpj"
              :lead-id="leadAtual.id"
              :cnpj="leadAtual.cnpj"
              @updated="leadAtual = { ...leadAtual, ...$event }"
            />
            <div class="form-row-2">
              <div class="form-group"><label class="label">E-mail</label><input v-model="leadAtual.email" type="email" class="input" /></div>
              <div class="form-group"><label class="label">Telefone</label><input v-model="leadAtual.telefone" class="input" /></div>
            </div>
            <div class="form-row-3">
              <div class="form-group"><label class="label">Valor estimado</label><input v-model.number="leadAtual.valor_estimado" type="number" step="0.01" min="0" class="input" /></div>
              <div class="form-group"><label class="label">Previsão</label><input v-model="leadAtual.data_previsao_fechamento" type="date" class="input" /></div>
              <div class="form-group"><label class="label">Próxima ação</label><input v-model="leadAtual.proxima_acao" class="input" /></div>
            </div>
            <div class="form-row-3">
              <div class="form-group"><label class="label">Data da próxima ação</label><input v-model="leadAtual.proxima_acao_em" type="datetime-local" class="input" /></div>
              <div class="form-group"><label class="label">Prioridade</label><select v-model="leadAtual.prioridade" class="input"><option value="baixa">Baixa</option><option value="media">Média</option><option value="alta">Alta</option><option value="urgente">Urgente</option></select></div>
              <div class="form-group"><label class="label">Score de qualificação</label><input :value="leadAtual.score ?? 0" class="input" disabled /></div>
            </div>
            <details class="crm-qualification" open>
              <summary>Qualificação comercial</summary>
              <div class="form-row-2 mt-3"><div class="form-group"><label class="label">Necessidade / dor</label><input v-model="leadAtual.necessidade" class="input" /></div><div class="form-group"><label class="label">Orçamento disponível</label><input v-model="leadAtual.orcamento" class="input" /></div></div>
              <div class="form-row-2"><div class="form-group"><label class="label">Autoridade de decisão</label><input v-model="leadAtual.autoridade" class="input" /></div><div class="form-group"><label class="label">Prazo de compra</label><input v-model="leadAtual.prazo_compra" class="input" /></div></div>
            </details>
            <section v-if="leadAtual.id" class="crm-timeline">
              <div class="crm-timeline-head"><div><h4>Atividades e histórico</h4><p>Registre cada contato e mantenha sempre um próximo passo.</p></div></div>
              <form class="crm-activity-form" @submit.prevent="registrarAtividade"><select v-model="novaAtividade.tipo" class="input"><option value="tarefa">Tarefa</option><option value="ligacao">Ligação</option><option value="email">E-mail</option><option value="reuniao">Reunião</option><option value="whatsapp">WhatsApp</option><option value="visita">Visita</option><option value="nota">Nota</option></select><input v-model="novaAtividade.titulo" required class="input" placeholder="Próxima ação ou contato realizado"><input v-model="novaAtividade.agendada_para" type="datetime-local" class="input"><select v-model="novaAtividade.status" class="input"><option value="pendente">Agendar</option><option value="concluida">Concluída</option></select><button class="btn-primary btn-sm" :disabled="processando">Registrar</button></form>
              <div class="crm-timeline-list"><article v-for="atividade in atividades" :key="atividade.id"><i :class="atividade.status"></i><div><b>{{ atividade.titulo }}</b><small>{{ atividade.tipo }} · {{ atividade.usuario?.nome || 'Sistema' }} · {{ formatarDataHora(atividade.agendada_para || atividade.created_at) }}</small><p v-if="atividade.descricao">{{ atividade.descricao }}</p></div><button v-if="atividade.status==='pendente'" class="btn-ghost btn-sm" @click="concluirAtividade(atividade)">Concluir</button></article><p v-if="!atividades.length" class="text-xs text-[var(--text-muted)]">Nenhuma atividade registrada.</p></div>
            </section>
            <section v-if="leadAtual.id" class="crm-products"><h4>Produtos e serviços da oportunidade</h4><form @submit.prevent="adicionarProduto"><input v-model="produtoForm.descricao" required class="input" placeholder="Produto ou serviço"><input v-model.number="produtoForm.quantidade" required min="0.001" step="0.001" type="number" class="input"><input v-model.number="produtoForm.valor_unitario" required min="0" step="0.01" type="number" class="input" placeholder="Valor unitário"><button class="btn-primary btn-sm">Adicionar</button></form><div v-for="item in produtosLead" :key="item.id" class="crm-product-row"><span>{{ item.descricao }} × {{ item.quantidade }}</span><b>{{ moeda(item.total) }}</b></div></section>
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
import RfbEnrichmentPanel from '@/components/crm/RfbEnrichmentPanel.vue'

import { ref, reactive, computed, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import CrmMeetingLauncher from '@/components/crm/CrmMeetingLauncher.vue'
import { useFormato } from '@/composables/useFormato'
import {
  ArrowPathIcon, PlusIcon, XMarkIcon, ChevronDownIcon, MagnifyingGlassIcon,
  FunnelIcon, ArrowsUpDownIcon, EyeSlashIcon, ViewColumnsIcon, Squares2X2Icon,
  ChartPieIcon, CalendarDaysIcon, Bars3BottomLeftIcon, BoltIcon, SparklesIcon,
  LinkIcon, BanknotesIcon, UserGroupIcon, ClockIcon, CheckCircleIcon
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
const atividades = ref([])
const novaAtividade = reactive({ tipo:'tarefa', titulo:'', agendada_para:'', status:'pendente' })
const gestao = reactive({ forecast:{}, produtividade:[], automacoes:[], motivos_perda:[], metas:[] })
const automacaoForm=reactive({nome:'Primeiro contato',gatilho:'lead_criado',acao:'criar_tarefa',titulo_tarefa:'Realizar primeiro contato',dias:1,ativo:true})
const metaForm=reactive({periodo_inicio:new Date().toISOString().slice(0,10),periodo_fim:new Date(new Date().getFullYear(),new Date().getMonth()+1,0).toISOString().slice(0,10),meta_valor:0,meta_quantidade:0,usuario_id:null})
const motivoForm=reactive({nome:'',ativo:true})
const produtosLead=ref([]),produtoForm=reactive({descricao:'',quantidade:1,valor_unitario:0,desconto:0,produto_id:null})

const baseColunas = computed(() => colunas.value)
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
  { label: 'Leads abertos', value: resumo.value.total_leads ?? leads.value.length, caption: 'Em andamento no funil', icon: UserGroupIcon, bg: 'bg-brand-500/15 text-brand-400' },
  { label: 'Valor em negociação', value: moeda(valorTotal.value), caption: 'Soma estimada do pipeline', icon: BanknotesIcon, bg: 'bg-success/15 text-success' },
  { label: 'Próximas ações', value: leads.value.filter(l => l.proxima_acao).length, caption: 'Follow-ups cadastrados', icon: ClockIcon, bg: 'bg-warning/15 text-warning' },
  { label: 'Conversão', value: `${Math.round((Number(gestao.forecast?.negocios_ganhos || 0) / Math.max(leads.value.length + Number(gestao.forecast?.negocios_ganhos || 0), 1)) * 100)}%`, caption: 'Ganhos no mês sobre a base atual', icon: CheckCircleIcon, bg: 'bg-info/15 text-info' },
])
const inicioAgenda = computed(() => { const d = new Date(); d.setHours(0, 0, 0, 0); return d })
const calendario = computed(() => Array.from({ length: 7 }, (_, i) => {
  const dia = new Date(inicioAgenda.value); dia.setDate(dia.getDate() + i)
  const data = dia.toISOString().slice(0, 10)
  return {
    data,
    label: new Intl.DateTimeFormat('pt-BR', { weekday: 'short' }).format(dia).replace('.', ''),
    dataLabel: new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit' }).format(dia),
    items: leadsFiltrados.value.filter(lead => dataLead(lead) === data),
  }
}))
const ganttDias = computed(() => calendario.value.map(dia => ({ ...dia, curto: `${dia.label} ${dia.dataLabel}` })))
const leadsComPrazo = computed(() => leadsFiltrados.value.filter(lead => ganttDias.value.some(dia => dia.data === dataLead(lead))))
const modalTitulo = computed(() => modalAgente.value ? 'Agente comercial IA' : modalAutomacao.value ? 'Automações do CRM' : modalIntegracao.value ? 'Integrações' : 'Nova visualização')
const modalTexto = computed(() => modalAgente.value
  ? 'Aqui ficará o assistente para resumir leads, sugerir follow-ups e preparar contatos. A tela já está pronta para integrar com uma API de IA depois.'
  : modalAutomacao.value
    ? 'Automatize tarefas repetitivas: avisos, mudança de etapa, criação de pedidos, OS e lembretes comerciais.'
    : modalIntegracao.value
      ? 'Área preparada para conectar marketplace, e-mail, WhatsApp, logística e outras fontes de leads.'
      : 'Você poderá adicionar novas visões sem perder o quadro principal: calendário, kanban, relatórios e cartões.')
const modalSugestao = computed(() => modalAgente.value ? 'Sugestão: “Liste os leads parados e diga o próximo passo.”' : 'Fluxo preparado para implantação gradual sem quebrar o que já funciona.')

function leadVazio() { return { nome: '', cnpj: '', empresa_nome: '', titulo: '', email: '', telefone: '', valor_estimado: null, data_previsao_fechamento: '', origem: '', proxima_acao: '', proxima_acao_em:'', prioridade:'media', necessidade:'', orcamento:'', autoridade:'', prazo_compra:'' } }
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
    await carregarGestao()
  } catch (error) {
    colunas.value = []
    toast.error(error.response?.data?.message || 'Não foi possível carregar o CRM.')
  } finally { loading.value = false }
}
function abrirNovo() { leadAtual.value = leadVazio(); modalLead.value = true }
function abrirNovoComEtapa(grupo) { leadAtual.value = { ...leadVazio(), etapa_id: grupo.etapa_id || grupo.id }; modalLead.value = true }
async function abrirDetalhe(lead) { leadAtual.value = { ...lead, proxima_acao_em: paraInputData(lead.proxima_acao_em) }; atividades.value=[];produtosLead.value=[]; modalLead.value = true; try{const [{data},{data:produtos}]=await Promise.all([api.get(`/leads/${lead.id}`),api.get(`/leads/${lead.id}/produtos`)]);leadAtual.value={...data,proxima_acao_em:paraInputData(data.proxima_acao_em),data_previsao_fechamento:String(data.data_previsao_fechamento||'').slice(0,10)};atividades.value=data.atividades||[];produtosLead.value=produtos||[]}catch(e){toast.error(e.response?.data?.message||'Não foi possível carregar o lead.')} }
async function salvar() {
  if (!leadAtual.value.nome?.trim()) { toast.warning('Informe o nome do contato.'); return }
  processando.value = true
  try {
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
async function perdido() {const nomes=gestao.motivos_perda.map((m,i)=>`${i+1}. ${m.nome}`).join('\n');const escolha=window.prompt(`Informe o número do motivo da perda:\n${nomes}`);if(escolha===null)return;const motivo=gestao.motivos_perda[Number(escolha)-1];if(!motivo){toast.warning('Selecione um motivo válido.');return}await acaoLead('perdido','Lead marcado como perdido.',{motivo_perda_id:motivo.id,motivo_perda:motivo.nome}) }
async function acaoLead(acao, msg, payload={}) {
  processando.value = true
  try { const { data } = await api.post(`/leads/${leadAtual.value.id}/${acao}`,payload); toast.success(data.message || msg); modalLead.value = false; await carregar() }
  catch (e) { toast.error(e.response?.data?.message || 'Não foi possível atualizar o lead.') }
  finally { processando.value = false }
}
async function mover(etapaId) {
  if (!leadArrastado.value) return
  const id = leadArrastado.value
  leadArrastado.value = null
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
function formatarDataHora(data){if(!data)return 'Sem data';return new Intl.DateTimeFormat('pt-BR',{dateStyle:'short',timeStyle:'short'}).format(new Date(data))}
function dataLead(lead){return String(lead.proxima_acao_em || lead.data_previsao_fechamento || '').slice(0,10)}
function colunaGantt(lead){const indice=ganttDias.value.findIndex(dia=>dia.data===dataLead(lead));return Math.max(2,indice+2)}
function paraInputData(data){if(!data)return '';const d=new Date(data);d.setMinutes(d.getMinutes()-d.getTimezoneOffset());return d.toISOString().slice(0,16)}
async function registrarAtividade(){processando.value=true;try{const payload={...novaAtividade,agendada_para:novaAtividade.agendada_para||null};await api.post(`/leads/${leadAtual.value.id}/atividades`,payload);Object.assign(novaAtividade,{tipo:'tarefa',titulo:'',agendada_para:'',status:'pendente'});const {data}=await api.get(`/leads/${leadAtual.value.id}/timeline`);atividades.value=data.atividades||[];toast.success('Atividade registrada.');await carregar()}catch(e){toast.error(e.response?.data?.message||'Não foi possível registrar a atividade.')}finally{processando.value=false}}
async function concluirAtividade(item){await api.put(`/leads/${leadAtual.value.id}/atividades/${item.id}`,{status:'concluida'});item.status='concluida';toast.success('Atividade concluída.')}
async function carregarGestao(){try{const {data}=await api.get('/crm/gestao');Object.assign(gestao,data)}catch{}}
async function salvarAutomacao(){const {data}=await api.post('/crm/automacoes',automacaoForm);toast.success(data.message);await carregarGestao()}
async function salvarMeta(){const {data}=await api.post('/crm/metas',metaForm);toast.success(data.message);await carregarGestao()}
async function salvarMotivo(){const {data}=await api.post('/crm/motivos-perda',motivoForm);motivoForm.nome='';toast.success(data.message);await carregarGestao()}
async function adicionarProduto(){const {data}=await api.post(`/leads/${leadAtual.value.id}/produtos`,produtoForm);leadAtual.value.valor_estimado=data.total;Object.assign(produtoForm,{descricao:'',quantidade:1,valor_unitario:0,desconto:0,produto_id:null});const response=await api.get(`/leads/${leadAtual.value.id}/produtos`);produtosLead.value=response.data;toast.success(data.message);await carregar()}
function corLead(lead) { return lead.cor || colunas.value.find(c => (c.etapa || c).id === lead.etapa_id)?.etapa?.cor || '#6366f1' }
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
.crm-gantt-head, .crm-gantt-row { display: grid; grid-template-columns: 190px repeat(7, 1fr); border-bottom: 1px solid var(--border-subtle); align-items: center; }
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
.crm-qualification{border:1px solid var(--border);border-radius:.8rem;padding:.85rem}.crm-qualification summary{cursor:pointer;font-size:.82rem;font-weight:800;color:var(--text-primary)}.crm-timeline{border-top:1px solid var(--border);padding-top:1rem}.crm-timeline-head h4{font-weight:800}.crm-timeline-head p{font-size:.75rem;color:var(--text-muted)}.crm-activity-form{display:grid;grid-template-columns:110px 1fr 180px 110px auto;gap:.5rem;margin:1rem 0}.crm-timeline-list{max-height:260px;overflow:auto}.crm-timeline-list article{display:flex;gap:.75rem;align-items:flex-start;border-left:1px solid var(--border);padding:0 0 1rem 1rem;margin-left:.3rem}.crm-timeline-list i{width:9px;height:9px;border-radius:50%;background:#64748b;margin-left:-1.32rem;margin-top:.35rem;box-shadow:0 0 0 4px var(--bg-surface)}.crm-timeline-list i.pendente{background:#f59e0b}.crm-timeline-list i.concluida{background:#10b981}.crm-timeline-list article>div{flex:1}.crm-timeline-list b,.crm-timeline-list small{display:block}.crm-timeline-list small{font-size:.7rem;color:var(--text-muted);margin-top:.2rem}.crm-timeline-list p{font-size:.75rem;color:var(--text-secondary);margin-top:.25rem}.crm-forecast{grid-column:1/-1}.crm-forecast>div{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}.crm-forecast span{padding:1rem;border-radius:.75rem;background:var(--bg-surface);color:var(--text-muted);font-size:.75rem}.crm-forecast strong{display:block;color:var(--text-primary);font-size:1.2rem;margin-top:.35rem}.crm-productivity{grid-column:1/-1}.crm-productivity-row{display:grid;grid-template-columns:2fr repeat(3,1fr);gap:1rem;padding:.65rem;border-bottom:1px solid var(--border-subtle);font-size:.8rem}.crm-productivity-row.head{font-weight:800;color:var(--text-muted)}.crm-management{display:grid;grid-template-columns:repeat(2,1fr);gap:1rem}.crm-config-card{border:1px solid var(--border);background:var(--bg-elevated);border-radius:1rem;padding:1rem;display:flex;flex-direction:column;gap:.7rem}.crm-config-card h3,.crm-products h4{font-weight:800}.crm-config-card>p{font-size:.78rem;color:var(--text-muted)}.crm-rule{display:flex;align-items:center;justify-content:space-between;padding:.65rem;border:1px solid var(--border-subtle);border-radius:.6rem}.crm-rule small{display:block;color:var(--text-muted);font-size:.68rem}.crm-tags{display:flex;gap:.4rem;flex-wrap:wrap}.crm-tags span{padding:.35rem .6rem;border-radius:999px;background:var(--bg-surface);font-size:.72rem}.crm-products{border-top:1px solid var(--border);padding-top:1rem}.crm-products form{display:grid;grid-template-columns:1fr 90px 140px auto;gap:.5rem;margin:.8rem 0}.crm-product-row{display:flex;justify-content:space-between;padding:.55rem;border-bottom:1px solid var(--border-subtle);font-size:.78rem}.crm-empty-state{min-height:240px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.65rem;text-align:center;color:var(--text-muted);border:1px dashed var(--border);border-radius:1rem;padding:2rem}.crm-empty-state h3{color:var(--text-primary);font-weight:800}.crm-empty-state p{max-width:540px;font-size:.82rem}.crm-day strong small{color:var(--text-muted);font-weight:500;margin-left:.25rem}
@media (max-width: 1200px) { .crm-kpis, .crm-automation-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .crm-dashboard { grid-template-columns: 1fr; } }
@media (max-width: 760px) { .crm-hero, .crm-board-header { flex-direction: column; align-items: stretch; } .crm-kpis, .crm-automation-grid,.crm-management { grid-template-columns: 1fr; } .crm-help { right: 1rem; bottom: 1rem; }.crm-activity-form,.crm-products form{grid-template-columns:1fr}.crm-forecast>div{grid-template-columns:1fr 1fr} }
</style>
