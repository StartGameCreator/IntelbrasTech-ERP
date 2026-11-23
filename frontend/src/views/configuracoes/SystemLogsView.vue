<template>
  <section class="system-logs-page" aria-live="polite">
    <header class="page-heading">
      <div>
        <div class="heading-kicker">
          <span :class="['live-dot', autoRefresh && 'is-live']"></span>
          Diagnóstico administrativo
        </div>
        <h1>Logs do Sistema</h1>
        <p>Saúde dos serviços e ocorrências reais do ERP, com informações sensíveis protegidas.</p>
      </div>

      <div class="heading-actions">
        <button type="button" class="btn-secondary" @click="toggleAutoRefresh">
          {{ autoRefresh ? 'Pausar tempo real' : 'Ativar tempo real' }}
        </button>
        <button type="button" class="btn-primary" :disabled="refreshing" @click="refreshAll">
          {{ refreshing ? 'Atualizando...' : 'Atualizar agora' }}
        </button>
      </div>
    </header>

    <div v-if="initialLoading" class="card loading-state">Consultando os serviços do ERP...</div>

    <template v-else>
      <div class="overview-strip">
        <div class="overall-status">
          <span :class="['status-orb', `status-${diagnostics.status || 'warning'}`]"></span>
          <div>
            <span>Estado geral</span>
            <strong>{{ statusLabel(diagnostics.status) }}</strong>
          </div>
        </div>
        <div class="overview-number severity-critical">
          <span>Críticos (1h)</span>
          <strong>{{ diagnostics.recent?.critical || 0 }}</strong>
        </div>
        <div class="overview-number severity-error">
          <span>Erros (1h)</span>
          <strong>{{ diagnostics.recent?.error || 0 }}</strong>
        </div>
        <div class="overview-number severity-warning">
          <span>Avisos (1h)</span>
          <strong>{{ diagnostics.recent?.warning || 0 }}</strong>
        </div>
        <div class="server-clock">
          <span>Servidor</span>
          <strong>{{ formatDate(diagnostics.server_time) }}</strong>
        </div>
      </div>

      <div class="health-grid">
        <article v-for="check in diagnostics.checks || []" :key="check.key" class="health-card card">
          <div class="health-title">
            <span :class="['health-indicator', `status-${check.status}`]"></span>
            <strong>{{ check.label }}</strong>
            <span :class="['health-badge', `health-${check.status}`]">{{ statusLabel(check.status) }}</span>
          </div>
          <p>{{ check.message }}</p>
          <dl>
            <template v-if="check.latency_ms !== undefined">
              <dt>Latência</dt><dd>{{ check.latency_ms }} ms</dd>
            </template>
            <template v-if="check.pending !== undefined">
              <dt>Pendentes</dt><dd>{{ check.pending }}</dd>
            </template>
            <template v-if="check.failed !== undefined">
              <dt>Falhas</dt><dd>{{ check.failed }}</dd>
            </template>
            <template v-if="check.free_percent !== undefined && check.free_percent !== null">
              <dt>Espaço livre</dt><dd>{{ check.free_percent }}%</dd>
            </template>
            <template v-if="check.size_bytes !== undefined">
              <dt>Tamanho</dt><dd>{{ formatBytes(check.size_bytes) }}</dd>
            </template>
          </dl>
        </article>
      </div>

      <section class="log-console card">
        <div class="console-heading">
          <div>
            <h2>Ocorrências do Laravel</h2>
            <p>
              {{ logs.meta?.returned_entries || 0 }} exibidas de {{ logs.meta?.scanned_entries || 0 }} analisadas
              <span v-if="logs.meta?.truncated"> · leitura otimizada do trecho mais recente</span>
            </p>
          </div>
          <div class="console-file">
            <span>{{ logs.meta?.path || 'storage/logs/laravel.log' }}</span>
            <small>{{ formatBytes(logs.meta?.size_bytes || 0) }}</small>
          </div>
        </div>

        <div class="summary-row">
          <button
            v-for="item in levelSummary"
            :key="item.level"
            type="button"
            :class="['summary-chip', `level-${item.level}`, filters.level === item.level && 'active']"
            @click="setLevel(item.level)"
          >
            <span>{{ item.label }}</span>
            <strong>{{ item.count }}</strong>
          </button>
        </div>

        <form class="filters" @submit.prevent="refreshLogs">
          <label>
            <span>Nível</span>
            <select v-model="filters.level" class="input" @change="refreshLogs">
              <option value="all">Todos os níveis</option>
              <option value="critical">Crítico</option>
              <option value="error">Erro</option>
              <option value="warning">Aviso</option>
              <option value="notice">Notificação</option>
              <option value="info">Informação</option>
              <option value="debug">Depuração</option>
            </select>
          </label>
          <label>
            <span>Período</span>
            <select v-model.number="filters.hours" class="input" @change="refreshLogs">
              <option :value="1">Última hora</option>
              <option :value="6">Últimas 6 horas</option>
              <option :value="24">Últimas 24 horas</option>
              <option :value="72">Últimos 3 dias</option>
              <option :value="168">Últimos 7 dias</option>
            </select>
          </label>
          <label class="search-filter">
            <span>Buscar ocorrência</span>
            <input v-model.trim="filters.search" class="input" maxlength="100" placeholder="Mensagem, arquivo ou código" />
          </label>
          <button type="submit" class="btn-secondary" :disabled="logsLoading">Filtrar</button>
        </form>

        <div v-if="logs.error" class="empty-logs error-box">{{ logs.error }}</div>
        <div v-else-if="logsLoading && !logs.entries?.length" class="empty-logs">Lendo registros...</div>
        <div v-else-if="!logs.entries?.length" class="empty-logs">
          Nenhuma ocorrência encontrada com os filtros atuais.
        </div>

        <div v-else class="log-list">
          <details v-for="entry in logs.entries" :key="entry.id" class="log-entry">
            <summary>
              <span :class="['level-pill', `level-${entry.level}`]">{{ levelLabel(entry.level) }}</span>
              <time :datetime="entry.timestamp">{{ formatDate(entry.timestamp) }}</time>
              <span class="log-message">{{ entry.message }}</span>
              <code v-if="entry.source">{{ entry.source }}</code>
            </summary>
            <div class="log-detail">
              <div class="detail-meta">
                <span>Ambiente: <strong>{{ entry.environment }}</strong></span>
                <span>Identificador: <strong>{{ entry.fingerprint }}</strong></span>
              </div>
              <pre v-if="entry.context">{{ entry.context }}</pre>
              <p v-else>Esta ocorrência não possui detalhes adicionais.</p>
            </div>
          </details>
        </div>

        <footer class="console-footer">
          <span>Última sincronização: {{ lastUpdated ? formatDate(lastUpdated) : 'aguardando' }}</span>
          <span v-if="autoRefresh" class="live-copy">Atualização automática a cada 5 segundos</span>
        </footer>
      </section>

      <p class="security-note">
        Senhas, tokens, e-mails, documentos e telefones são mascarados antes de chegar ao navegador. O arquivo original não pode ser apagado por este painel.
      </p>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import systemLogsService from '@/services/systemLogs'

const initialLoading = ref(true)
const refreshing = ref(false)
const logsLoading = ref(false)
const autoRefresh = ref(true)
const diagnostics = ref({ status: 'warning', checks: [], recent: {} })
const logs = ref({ entries: [], summary: {}, meta: {} })
const lastUpdated = ref(null)
const filters = reactive({ level: 'all', hours: 24, search: '', limit: 100 })
let timer = null

const levelSummary = computed(() => [
  { level: 'critical', label: 'Críticos', count: logs.value.summary?.critical || 0 },
  { level: 'error', label: 'Erros', count: logs.value.summary?.error || 0 },
  { level: 'warning', label: 'Avisos', count: logs.value.summary?.warning || 0 },
  { level: 'info', label: 'Informações', count: logs.value.summary?.info || 0 },
])

async function refreshAll() {
  if (refreshing.value) return
  refreshing.value = true
  try {
    const [diagnosticData, logData] = await Promise.all([
      systemLogsService.diagnostics(),
      systemLogsService.logs({ ...filters }),
    ])
    diagnostics.value = diagnosticData
    logs.value = logData
    lastUpdated.value = new Date().toISOString()
  } finally {
    refreshing.value = false
    initialLoading.value = false
  }
}

async function refreshLogs() {
  if (logsLoading.value || refreshing.value) return
  logsLoading.value = true
  try {
    logs.value = await systemLogsService.logs({ ...filters })
    lastUpdated.value = new Date().toISOString()
  } finally {
    logsLoading.value = false
  }
}

async function poll() {
  if (!autoRefresh.value || document.hidden || refreshing.value || logsLoading.value) return
  try {
    const [diagnosticData, logData] = await Promise.all([
      systemLogsService.diagnostics(),
      systemLogsService.logs({ ...filters }),
    ])
    diagnostics.value = diagnosticData
    logs.value = logData
    lastUpdated.value = new Date().toISOString()
  } catch {
    // O interceptor global apresenta a falha; a próxima rodada tenta novamente.
  }
}

function toggleAutoRefresh() {
  autoRefresh.value = !autoRefresh.value
  if (autoRefresh.value) poll()
}

function setLevel(level) {
  filters.level = filters.level === level ? 'all' : level
  refreshLogs()
}

function statusLabel(status) {
  return { ok: 'Operacional', warning: 'Atenção', error: 'Indisponível' }[status] || 'Verificando'
}

function levelLabel(level) {
  return {
    emergency: 'Emergência', alert: 'Alerta', critical: 'Crítico', error: 'Erro',
    warning: 'Aviso', notice: 'Notificação', info: 'Informação', debug: 'Depuração',
  }[level] || level
}

function formatDate(value) {
  if (!value) return '—'
  const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value) ? value.replace(' ', 'T') : value
  const date = new Date(normalized)
  return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short', timeStyle: 'medium',
  }).format(date)
}

function formatBytes(bytes) {
  const value = Number(bytes || 0)
  if (value < 1024) return `${value} B`
  const units = ['KB', 'MB', 'GB', 'TB']
  let size = value / 1024
  let unit = 0
  while (size >= 1024 && unit < units.length - 1) {
    size /= 1024
    unit++
  }
  return `${size.toFixed(size >= 10 ? 1 : 2)} ${units[unit]}`
}

onMounted(async () => {
  await refreshAll()
  timer = window.setInterval(poll, 5000)
})

onUnmounted(() => {
  if (timer) window.clearInterval(timer)
})
</script>

<style scoped>
.system-logs-page { display: grid; gap: 1.1rem; min-width: 0; }
.page-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.page-heading h1 { margin: .2rem 0 0; color: var(--text-primary); font-size: 1.4rem; font-weight: 800; }
.page-heading p, .console-heading p { margin: .25rem 0 0; color: var(--text-muted); font-size: .78rem; }
.heading-kicker { display: flex; align-items: center; gap: .45rem; color: #34d399; font-size: .68rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.live-dot { width: .5rem; height: .5rem; border-radius: 999px; background: #64748b; }
.live-dot.is-live { background: #22c55e; box-shadow: 0 0 0 .3rem rgb(34 197 94 / 12%); }
.heading-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .55rem; }
.loading-state, .empty-logs { padding: 2.5rem 1rem; text-align: center; color: var(--text-muted); }
.overview-strip { display: grid; grid-template-columns: minmax(180px, 1.3fr) repeat(3, minmax(100px, .65fr)) minmax(180px, 1fr); gap: .65rem; }
.overview-strip > div { min-height: 4.2rem; border: 1px solid var(--border); border-radius: .8rem; background: var(--bg-card); padding: .8rem; }
.overall-status { display: flex; align-items: center; gap: .75rem; }
.overall-status span:not(.status-orb), .overview-number span, .server-clock span { display: block; color: var(--text-muted); font-size: .66rem; text-transform: uppercase; letter-spacing: .08em; }
.overall-status strong, .overview-number strong, .server-clock strong { display: block; margin-top: .18rem; color: var(--text-primary); font-size: 1rem; }
.status-orb { width: 1rem; height: 1rem; border-radius: 999px; flex: 0 0 auto; }
.overview-number { display: flex; flex-direction: column; justify-content: center; }
.overview-number strong { font-size: 1.25rem; }
.severity-critical strong, .severity-error strong { color: #fb7185; }
.severity-warning strong { color: #fbbf24; }
.server-clock { display: flex; flex-direction: column; justify-content: center; }
.health-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .65rem; }
.health-card { padding: .85rem; min-width: 0; }
.health-title { display: flex; align-items: center; gap: .45rem; }
.health-title > strong { color: var(--text-primary); font-size: .78rem; }
.health-indicator { width: .55rem; height: .55rem; border-radius: 999px; flex: 0 0 auto; }
.status-ok { background: #22c55e; }.status-warning { background: #f59e0b; }.status-error { background: #ef4444; }
.health-badge { margin-left: auto; border-radius: 999px; padding: .15rem .4rem; font-size: .58rem; font-weight: 800; text-transform: uppercase; }
.health-ok { color: #34d399; background: rgb(16 185 129 / 12%); }.health-warning { color: #fbbf24; background: rgb(245 158 11 / 12%); }.health-error { color: #fb7185; background: rgb(239 68 68 / 12%); }
.health-card p { min-height: 2.2rem; margin: .7rem 0; color: var(--text-secondary); font-size: .7rem; line-height: 1.45; }
.health-card dl { display: grid; grid-template-columns: 1fr auto; gap: .25rem .5rem; margin: 0; font-size: .66rem; }
.health-card dt { color: var(--text-muted); }.health-card dd { margin: 0; color: var(--text-primary); font-weight: 700; }
.log-console { min-width: 0; overflow: hidden; }
.console-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem; border-bottom: 1px solid var(--border); }
.console-heading h2 { margin: 0; color: var(--text-primary); font-size: 1rem; font-weight: 800; }
.console-file { display: flex; flex-direction: column; align-items: flex-end; gap: .2rem; color: var(--text-secondary); font-family: ui-monospace, monospace; font-size: .68rem; }
.console-file small { color: var(--text-muted); }
.summary-row { display: flex; gap: .45rem; overflow-x: auto; padding: .75rem 1rem 0; }
.summary-chip { display: flex; align-items: center; gap: .5rem; min-width: max-content; border: 1px solid var(--border); border-radius: 999px; padding: .38rem .65rem; color: var(--text-secondary); background: var(--bg-input); font-size: .68rem; }
.summary-chip strong { font-size: .76rem; }.summary-chip.active { border-color: currentColor; box-shadow: 0 0 0 2px rgb(99 102 241 / 12%); }
.filters { display: grid; grid-template-columns: 170px 170px minmax(220px, 1fr) auto; align-items: end; gap: .65rem; padding: .8rem 1rem; }
.filters label { min-width: 0; }.filters label > span { display: block; margin: 0 0 .3rem; color: var(--text-muted); font-size: .65rem; font-weight: 700; }
.filters .input { height: 2.35rem; font-size: .72rem; }
.log-list { max-height: min(55vh, 620px); overflow: auto; border-top: 1px solid var(--border); scrollbar-gutter: stable; }
.log-entry { border-bottom: 1px solid var(--border-subtle); background: var(--bg-card); }
.log-entry[open] { background: var(--bg-input); }
.log-entry summary { display: grid; grid-template-columns: 88px 138px minmax(220px, 1fr) minmax(0, 230px); align-items: center; gap: .65rem; padding: .72rem 1rem; cursor: pointer; list-style: none; }
.log-entry summary::-webkit-details-marker { display: none; }
.level-pill { display: inline-flex; justify-content: center; border-radius: .35rem; padding: .22rem .4rem; font-size: .61rem; font-weight: 800; text-transform: uppercase; }
.level-critical, .level-error, .level-emergency, .level-alert { color: #fb7185; background: rgb(244 63 94 / 11%); }
.level-warning { color: #fbbf24; background: rgb(245 158 11 / 11%); }
.level-info, .level-notice { color: #60a5fa; background: rgb(59 130 246 / 11%); }
.level-debug { color: #a78bfa; background: rgb(139 92 246 / 11%); }
.log-entry time { color: var(--text-muted); font: .64rem ui-monospace, monospace; }
.log-message { min-width: 0; overflow: hidden; color: var(--text-primary); font-size: .72rem; text-overflow: ellipsis; white-space: nowrap; }
.log-entry summary code { min-width: 0; overflow: hidden; color: var(--text-muted); font-size: .62rem; text-overflow: ellipsis; white-space: nowrap; }
.log-detail { padding: 0 1rem 1rem calc(88px + 1.65rem); }
.detail-meta { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: .65rem; color: var(--text-muted); font-size: .65rem; }
.detail-meta strong { color: var(--text-secondary); }
.log-detail pre { max-height: 15rem; overflow: auto; margin: 0; border: 1px solid var(--border); border-radius: .5rem; background: rgb(2 6 23 / 42%); padding: .75rem; color: var(--text-secondary); font: .66rem/1.55 ui-monospace, monospace; white-space: pre-wrap; word-break: break-word; }
.log-detail p { color: var(--text-muted); font-size: .7rem; }
.console-footer { display: flex; justify-content: space-between; gap: 1rem; padding: .65rem 1rem; color: var(--text-muted); font-size: .62rem; }
.live-copy { color: #34d399; }.error-box { color: #fb7185; }.security-note { margin: 0; color: var(--text-muted); font-size: .66rem; text-align: center; }

@media (max-width: 1100px) {
  .health-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .overview-strip { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .overall-status, .server-clock { grid-column: span 2; }
  .filters { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .search-filter { grid-column: span 2; }
  .log-entry summary { grid-template-columns: 84px 130px minmax(180px, 1fr); }
  .log-entry summary code { display: none; }
}

@media (max-width: 640px) {
  .system-logs-page { gap: .75rem; }
  .page-heading { display: block; }
  .page-heading h1 { font-size: 1.15rem; }
  .heading-actions { justify-content: stretch; margin-top: .75rem; }
  .heading-actions button { flex: 1; }
  .overview-strip { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .4rem; }
  .overview-strip > div { min-height: 3.6rem; padding: .55rem; }
  .overall-status { grid-column: span 3; }.server-clock { grid-column: span 3; }
  .overall-status strong, .server-clock strong { font-size: .8rem; }
  .overview-number strong { font-size: 1rem; }
  .health-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .4rem; }
  .health-card { padding: .65rem; }.health-card:last-child { grid-column: span 2; }
  .health-card p { min-height: 0; margin: .5rem 0; }
  .console-heading { display: block; padding: .8rem; }
  .console-file { align-items: flex-start; margin-top: .55rem; }
  .summary-row { padding: .65rem .8rem 0; }
  .filters { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: .7rem .8rem; gap: .5rem; }
  .filters .search-filter, .filters button { grid-column: span 2; }
  .log-list { max-height: 56vh; }
  .log-entry summary { grid-template-columns: 74px 1fr; gap: .35rem .5rem; padding: .65rem .8rem; }
  .log-entry time { text-align: right; }.log-message { grid-column: span 2; white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
  .log-detail { padding: 0 .8rem .8rem; }.detail-meta { gap: .4rem 1rem; }
  .console-footer { display: block; padding: .6rem .8rem; }.console-footer span { display: block; margin-top: .15rem; }
}
</style>
