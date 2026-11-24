<template>
  <section class="system-monitor" aria-live="polite">
    <header class="page-heading">
      <div>
        <div class="heading-kicker"><span :class="['live-dot', autoRefresh && 'is-live']"></span>Monitoramento administrativo</div>
        <h1>Saúde, infraestrutura e logs</h1>
        <p>Visão real da VPS, máquina virtual, containers, banco de dados e aplicação em produção.</p>
      </div>
      <div class="heading-actions">
        <button type="button" class="btn-secondary" @click="toggleAutoRefresh">{{ autoRefresh ? 'Pausar tempo real' : 'Ativar tempo real' }}</button>
        <button type="button" class="btn-primary" :disabled="refreshing" @click="refreshAll">{{ refreshing ? 'Atualizando...' : 'Atualizar agora' }}</button>
      </div>
    </header>

    <div v-if="initialLoading" class="card loading-state">Coletando métricas da infraestrutura...</div>

    <template v-else>
      <div class="overview-strip">
        <div class="overall-status">
          <span :class="['status-orb', `status-${overallStatus}`]"></span>
          <div><span>Estado geral</span><strong>{{ statusLabel(overallStatus) }}</strong></div>
        </div>
        <div class="overview-number"><span>CPU da VPS</span><strong>{{ percent(host.cpu?.usage_percent, host.cpu?.warming) }}</strong></div>
        <div class="overview-number"><span>Memória</span><strong>{{ percent(host.memory?.used_percent) }}</strong></div>
        <div class="overview-number"><span>Disco</span><strong>{{ percent(host.filesystem?.used_percent) }}</strong></div>
        <div class="overview-number"><span>Containers</span><strong>{{ docker.summary?.running || 0 }}/{{ docker.summary?.total || 0 }}</strong></div>
        <div class="server-clock"><span>Coleta</span><strong>{{ formatDate(infrastructure.collected_at || diagnostics.server_time) }}</strong></div>
      </div>

      <nav class="monitor-tabs" aria-label="Seções do monitoramento">
        <button v-for="tab in tabs" :key="tab.id" type="button" :class="{ active: activeTab === tab.id }" @click="activeTab = tab.id">
          <span>{{ tab.label }}</span><small v-if="tab.badge !== undefined">{{ tab.badge }}</small>
        </button>
      </nav>

      <section v-if="activeTab === 'overview'" class="tab-panel">
        <div class="resource-grid">
          <article v-for="item in overviewResources" :key="item.title" class="resource-card card"><div class="resource-title"><span>{{ item.title }}</span><i :class="`status-${item.status}`"></i></div><strong>{{ item.value }}</strong><small>{{ item.detail }}</small><div class="progress-track"><span :class="`progress-${item.status}`" :style="{ width: `${Math.max(0, Math.min(100, Number(item.progress) || 0))}%` }"></span></div></article>
        </div>

        <div class="health-grid">
          <article v-for="check in diagnostics.checks || []" :key="check.key" class="health-card card">
            <div class="health-title"><span :class="['health-indicator', `status-${check.status}`]"></span><strong>{{ check.label }}</strong><span :class="['health-badge', `health-${check.status}`]">{{ statusLabel(check.status) }}</span></div>
            <p>{{ check.message }}</p>
            <dl>
              <template v-if="check.latency_ms !== undefined"><dt>Latência</dt><dd>{{ check.latency_ms }} ms</dd></template>
              <template v-if="check.pending !== undefined"><dt>Pendentes</dt><dd>{{ check.pending }}</dd></template>
              <template v-if="check.failed !== undefined"><dt>Falhas</dt><dd>{{ check.failed }}</dd></template>
              <template v-if="check.free_percent != null"><dt>Espaço livre</dt><dd>{{ check.free_percent }}%</dd></template>
              <template v-if="check.size_bytes !== undefined"><dt>Tamanho</dt><dd>{{ formatBytes(check.size_bytes) }}</dd></template>
            </dl>
          </article>
        </div>

        <div class="overview-details">
          <article class="card compact-panel">
            <header><div><h2>VPS e máquina virtual</h2><p>{{ host.identity?.hostname || 'Coletor indisponível' }}</p></div><button type="button" @click="activeTab = 'vps'">Ver detalhes</button></header>
            <dl class="detail-list">
              <dt>Sistema</dt><dd>{{ host.identity?.operating_system || '—' }}</dd>
              <dt>Virtualização</dt><dd>{{ host.identity?.virtualization || '—' }}</dd>
              <dt>Tempo ligada</dt><dd>{{ formatUptime(host.uptime_seconds) }}</dd>
              <dt>Rede agora</dt><dd>↓ {{ formatRate(host.network?.receive_bytes_per_second) }} · ↑ {{ formatRate(host.network?.transmit_bytes_per_second) }}</dd>
            </dl>
          </article>
          <article class="card compact-panel">
            <header><div><h2>Projeto em produção</h2><p>{{ project.application?.name || 'IntelbrasTech ERP' }}</p></div><button type="button" @click="activeTab = 'project'">Ver detalhes</button></header>
            <dl class="detail-list">
              <dt>Laravel / PHP</dt><dd>{{ project.application?.laravel || '—' }} / {{ project.application?.php || '—' }}</dd>
              <dt>Banco</dt><dd>{{ project.database?.driver || '—' }} · {{ formatBytes(project.database?.size_bytes) }}</dd>
              <dt>Migrações</dt><dd>{{ project.migrations?.applied ?? '—' }} aplicadas · {{ project.migrations?.pending ?? '—' }} pendentes</dd>
              <dt>Filas / cache</dt><dd>{{ project.runtime?.queue_connection || '—' }} / {{ project.runtime?.cache_store || '—' }}</dd>
            </dl>
          </article>
        </div>
      </section>

      <section v-else-if="activeTab === 'vps'" class="tab-panel">
        <div v-if="!host.available" class="card unavailable">{{ host.message || 'Métricas da VPS indisponíveis.' }}</div>
        <template v-else>
          <div class="identity-grid">
            <article class="card identity-card"><span>Servidor</span><strong>{{ host.identity?.hostname }}</strong><small>{{ host.identity?.operating_system }}</small></article>
            <article class="card identity-card"><span>Máquina virtual</span><strong>{{ host.identity?.virtualization }}</strong><small>{{ host.identity?.vendor }} · {{ host.identity?.model }}</small></article>
            <article class="card identity-card"><span>Kernel</span><strong>{{ host.identity?.kernel }}</strong><small>{{ host.identity?.architecture }}</small></article>
            <article class="card identity-card"><span>Disponibilidade</span><strong>{{ formatUptime(host.uptime_seconds) }}</strong><small>{{ host.processes?.running }} processos executando</small></article>
          </div>
          <div class="resource-grid three">
            <article v-for="item in vpsResources" :key="item.title" class="resource-card card"><div class="resource-title"><span>{{ item.title }}</span><i :class="`status-${item.status}`"></i></div><strong>{{ item.value }}</strong><small>{{ item.detail }}</small><div class="progress-track"><span :class="`progress-${item.status}`" :style="{ width: `${Math.max(0, Math.min(100, Number(item.progress) || 0))}%` }"></span></div></article>
          </div>
          <div class="metric-panels">
            <article class="card metric-panel"><h2>Rede da VPS</h2><div class="metric-pairs"><div><span>Recebendo agora</span><strong>↓ {{ formatRate(host.network?.receive_bytes_per_second) }}</strong></div><div><span>Enviando agora</span><strong>↑ {{ formatRate(host.network?.transmit_bytes_per_second) }}</strong></div><div><span>Total recebido</span><strong>{{ formatBytes(host.network?.received_bytes) }}</strong></div><div><span>Total enviado</span><strong>{{ formatBytes(host.network?.transmitted_bytes) }}</strong></div></div></article>
            <article class="card metric-panel"><h2>Entrada e saída do disco</h2><div class="metric-pairs"><div><span>Leitura atual</span><strong>{{ formatRate(host.disk_io?.read_bytes_per_second) }}</strong></div><div><span>Gravação atual</span><strong>{{ formatRate(host.disk_io?.write_bytes_per_second) }}</strong></div><div><span>Total lido</span><strong>{{ formatBytes(host.disk_io?.read_bytes) }}</strong></div><div><span>Total gravado</span><strong>{{ formatBytes(host.disk_io?.written_bytes) }}</strong></div></div></article>
            <article class="card metric-panel"><h2>Limites do sistema</h2><div class="metric-pairs"><div><span>Swap</span><strong>{{ host.memory?.swap_total_bytes ? formatBytes(host.memory?.swap_used_bytes) + ' / ' + formatBytes(host.memory?.swap_total_bytes) : 'Não configurado' }}</strong></div><div><span>Processos bloqueados</span><strong>{{ host.processes?.blocked }}</strong></div><div><span>Descritores abertos</span><strong>{{ formatInteger(host.processes?.file_descriptors_open) }}</strong></div><div><span>Limite de descritores</span><strong>{{ formatInteger(host.processes?.file_descriptors_max) }}</strong></div></div></article>
          </div>
        </template>
      </section>

      <section v-else-if="activeTab === 'containers'" class="tab-panel">
        <div class="container-summary">
          <div><span>Total</span><strong>{{ docker.summary?.total || 0 }}</strong></div><div class="good"><span>Executando</span><strong>{{ docker.summary?.running || 0 }}</strong></div><div :class="{ danger: docker.summary?.unhealthy }"><span>Não saudáveis</span><strong>{{ docker.summary?.unhealthy || 0 }}</strong></div><div><span>RAM consumida</span><strong>{{ formatBytes(docker.summary?.memory_bytes) }}</strong></div><div><span>CPU total</span><strong>{{ percent(docker.summary?.cpu_percent) }}</strong></div>
        </div>
        <div v-if="!docker.available" class="card unavailable">{{ docker.message || 'Monitor Docker indisponível.' }}</div>
        <div v-else class="container-table card">
          <header class="container-table-heading"><div><h2>Todos os containers</h2><p>Dados reais da API Docker, em modo estritamente somente leitura.</p></div><span>{{ docker.engine?.server_version }} · API {{ docker.engine?.api_version }}</span></header>
          <div class="container-row table-head"><span>Container</span><span>Estado</span><span>CPU</span><span>Memória</span><span>Rede</span><span>I/O disco</span><span>PIDs</span></div>
          <article v-for="container in docker.containers || []" :key="container.id" class="container-row">
            <div class="container-name"><span :class="['health-indicator', `status-${container.status}`]"></span><div><strong>{{ container.name }}</strong><small>{{ container.image }}</small></div></div>
            <div data-label="Estado"><span :class="['state-pill', `state-${container.status}`]">{{ container.health || container.state }}</span><small>{{ container.status_text }}</small></div>
            <div data-label="CPU"><strong>{{ percent(container.cpu_percent) }}</strong><small>{{ container.pids }} processos</small></div>
            <div data-label="Memória"><strong>{{ formatBytes(container.memory_usage_bytes) }}</strong><small>{{ percent(container.memory_percent) }}</small></div>
            <div data-label="Rede"><strong>↓ {{ formatBytes(container.network_rx_bytes) }}</strong><small>↑ {{ formatBytes(container.network_tx_bytes) }}</small></div>
            <div data-label="I/O disco"><strong>L {{ formatBytes(container.block_read_bytes) }}</strong><small>G {{ formatBytes(container.block_write_bytes) }}</small></div>
            <div data-label="PIDs"><strong>{{ container.pids }}</strong><small>{{ container.restart_count }} reinícios</small></div>
          </article>
        </div>
        <article v-if="docker.available" class="card engine-panel"><h2>Docker Engine</h2><dl class="detail-list columns"><dt>Host</dt><dd>{{ docker.engine?.name }}</dd><dt>Versão</dt><dd>{{ docker.engine?.server_version }}</dd><dt>Armazenamento</dt><dd>{{ docker.engine?.storage_driver }}</dd><dt>Logs</dt><dd>{{ docker.engine?.logging_driver }}</dd><dt>Cgroups</dt><dd>{{ docker.engine?.cgroup_driver }} v{{ docker.engine?.cgroup_version }}</dd><dt>Imagens locais</dt><dd>{{ docker.engine?.images }}</dd><dt>Diretório</dt><dd>{{ docker.engine?.docker_root }}</dd><dt>Capacidade</dt><dd>{{ docker.engine?.cpu_cores }} vCPU · {{ formatBytes(docker.engine?.memory_bytes) }}</dd></dl></article>
      </section>

      <section v-else-if="activeTab === 'project'" class="tab-panel">
        <div v-if="!project.available" class="card unavailable">{{ project.message || 'Dados do projeto indisponíveis.' }}</div>
        <template v-else>
          <div class="project-grid">
            <article class="card project-card"><h2>Aplicação</h2><dl class="detail-list"><dt>Nome</dt><dd>{{ project.application?.name }}</dd><dt>Ambiente</dt><dd>{{ project.application?.environment }}</dd><dt>Laravel</dt><dd>{{ project.application?.laravel }}</dd><dt>PHP</dt><dd>{{ project.application?.php }} · {{ project.application?.server_api }}</dd><dt>Rotas registradas</dt><dd>{{ formatInteger(project.application?.routes) }}</dd><dt>Pacotes Composer</dt><dd>{{ project.application?.composer_packages }} ({{ project.application?.direct_dependencies }} diretos)</dd><dt>Imagem</dt><dd class="breakable">{{ project.application?.image }}</dd><dt>Container iniciado</dt><dd>{{ formatDate(project.application?.container_started_at) }}</dd></dl></article>
            <article class="card project-card"><h2>Banco de dados</h2><dl class="detail-list"><dt>Driver</dt><dd>{{ project.database?.driver }}</dd><dt>Base</dt><dd>{{ project.database?.database }}</dd><dt>Tamanho</dt><dd>{{ formatBytes(project.database?.size_bytes) }}</dd><dt>Tabelas</dt><dd>{{ project.database?.tables }}</dd><dt>Conexões</dt><dd>{{ project.database?.connections }} / {{ project.database?.max_connections }}</dd><dt>Versão</dt><dd class="breakable">{{ shortDatabaseVersion(project.database?.version) }}</dd><dt>Migrações</dt><dd>{{ project.migrations?.applied }} aplicadas</dd><dt>Pendentes</dt><dd :class="{ dangerText: project.migrations?.pending }">{{ project.migrations?.pending }}</dd></dl></article>
            <article class="card project-card"><h2>Runtime e persistência</h2><dl class="detail-list"><dt>Cache</dt><dd>{{ project.runtime?.cache_store }}</dd><dt>Filas</dt><dd>{{ project.runtime?.queue_connection }}</dd><dt>Sessões</dt><dd>{{ project.runtime?.session_driver }}</dd><dt>Arquivos</dt><dd>{{ project.runtime?.filesystem_disk }}</dd><dt>Limite PHP</dt><dd>{{ project.runtime?.memory_limit }}</dd><dt>Upload máximo</dt><dd>{{ project.runtime?.upload_max_filesize }}</dd><dt>OPcache</dt><dd>{{ project.runtime?.opcache_enabled ? 'Ativo' : 'Inativo' }}</dd><dt>OPcache usado</dt><dd>{{ formatBytes(project.runtime?.opcache_used_bytes) }}</dd></dl></article>
          </div>
          <article class="card stack-panel"><h2>Serviços da stack</h2><div class="stack-list"><span v-for="service in project.stack || []" :key="service">{{ service }}</span></div></article>
        </template>
      </section>

      <section v-else class="tab-panel">
        <section class="log-console card">
          <div class="console-heading"><div><h2>Ocorrências do Laravel</h2><p>{{ logs.meta?.returned_entries || 0 }} exibidas de {{ logs.meta?.scanned_entries || 0 }} analisadas<span v-if="logs.meta?.truncated"> · trecho mais recente</span></p></div><div class="console-file"><span>{{ logs.meta?.path || 'storage/logs/laravel.log' }}</span><small>{{ formatBytes(logs.meta?.size_bytes || 0) }}</small></div></div>
          <div class="summary-row"><button v-for="item in levelSummary" :key="item.level" type="button" :class="['summary-chip', `level-${item.level}`, filters.level === item.level && 'active']" @click="setLevel(item.level)"><span>{{ item.label }}</span><strong>{{ item.count }}</strong></button></div>
          <form class="filters" @submit.prevent="refreshLogs"><label><span>Nível</span><select v-model="filters.level" class="input" @change="refreshLogs"><option value="all">Todos os níveis</option><option value="critical">Crítico</option><option value="error">Erro</option><option value="warning">Aviso</option><option value="notice">Notificação</option><option value="info">Informação</option><option value="debug">Depuração</option></select></label><label><span>Período</span><select v-model.number="filters.hours" class="input" @change="refreshLogs"><option :value="1">Última hora</option><option :value="6">Últimas 6 horas</option><option :value="24">Últimas 24 horas</option><option :value="72">Últimos 3 dias</option><option :value="168">Últimos 7 dias</option></select></label><label class="search-filter"><span>Buscar ocorrência</span><input v-model.trim="filters.search" class="input" maxlength="100" placeholder="Mensagem, arquivo ou código" /></label><button type="submit" class="btn-secondary" :disabled="logsLoading">Filtrar</button></form>
          <div v-if="logs.error" class="empty-logs error-box">{{ logs.error }}</div><div v-else-if="logsLoading && !logs.entries?.length" class="empty-logs">Lendo registros...</div><div v-else-if="!logs.entries?.length" class="empty-logs">Nenhuma ocorrência encontrada com os filtros atuais.</div>
          <div v-else class="log-list"><details v-for="entry in logs.entries" :key="entry.id" class="log-entry"><summary><span :class="['level-pill', `level-${entry.level}`]">{{ levelLabel(entry.level) }}</span><time :datetime="entry.timestamp">{{ formatDate(entry.timestamp) }}</time><span class="log-message">{{ entry.message }}</span><code v-if="entry.source">{{ entry.source }}</code></summary><div class="log-detail"><div class="detail-meta"><span>Ambiente: <strong>{{ entry.environment }}</strong></span><span>Identificador: <strong>{{ entry.fingerprint }}</strong></span></div><pre v-if="entry.context">{{ entry.context }}</pre><p v-else>Esta ocorrência não possui detalhes adicionais.</p></div></details></div>
          <footer class="console-footer"><span>Última sincronização: {{ lastUpdated ? formatDate(lastUpdated) : 'aguardando' }}</span><span v-if="autoRefresh" class="live-copy">Atualização automática a cada 5 segundos</span></footer>
        </section>
      </section>

      <p class="security-note">Painel administrativo e somente leitura. Senhas, tokens, e-mails, documentos e telefones são mascarados antes de chegar ao navegador.</p>
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
const activeTab = ref('overview')
const diagnostics = ref({ status: 'warning', checks: [], recent: {} })
const infrastructure = ref({ status: 'warning', host: {}, docker: { containers: [] }, project: {} })
const logs = ref({ entries: [], summary: {}, meta: {} })
const lastUpdated = ref(null)
const filters = reactive({ level: 'all', hours: 24, search: '', limit: 100 })
let timer = null

const host = computed(() => infrastructure.value.host || {})
const docker = computed(() => infrastructure.value.docker || { containers: [] })
const project = computed(() => infrastructure.value.project || {})
const overallStatus = computed(() => [diagnostics.value.status, infrastructure.value.status].includes('error') ? 'error' : ([diagnostics.value.status, infrastructure.value.status].includes('warning') ? 'warning' : 'ok'))
const containerProgress = computed(() => docker.value.summary?.total ? (docker.value.summary.running / docker.value.summary.total) * 100 : 0)
const overviewResources = computed(() => [
  { title: 'Processador', value: percent(host.value.cpu?.usage_percent, host.value.cpu?.warming), progress: host.value.cpu?.usage_percent, status: resourceStatus(host.value.cpu?.usage_percent), detail: `${host.value.cpu?.cores || '—'} vCPU · carga ${host.value.cpu?.load_1 ?? '—'}` },
  { title: 'Memória RAM', value: formatBytes(host.value.memory?.used_bytes), progress: host.value.memory?.used_percent, status: resourceStatus(host.value.memory?.used_percent), detail: `de ${formatBytes(host.value.memory?.total_bytes)} · ${percent(host.value.memory?.used_percent)}` },
  { title: 'Disco da VPS', value: formatBytes(host.value.filesystem?.used_bytes), progress: host.value.filesystem?.used_percent, status: resourceStatus(host.value.filesystem?.used_percent), detail: `de ${formatBytes(host.value.filesystem?.total_bytes)} · ${percent(host.value.filesystem?.used_percent)}` },
  { title: 'Containers', value: `${docker.value.summary?.running || 0} ativos`, progress: containerProgress.value, status: docker.value.status || 'warning', detail: `${docker.value.summary?.unhealthy || 0} não saudáveis · ${formatBytes(docker.value.summary?.memory_bytes)}` },
])
const vpsResources = computed(() => [
  { title: 'CPU', value: percent(host.value.cpu?.usage_percent, host.value.cpu?.warming), progress: host.value.cpu?.usage_percent, status: resourceStatus(host.value.cpu?.usage_percent), detail: `${host.value.cpu?.cores || '—'} vCPU · carga ${host.value.cpu?.load_1 ?? '—'} / ${host.value.cpu?.load_5 ?? '—'} / ${host.value.cpu?.load_15 ?? '—'}` },
  { title: 'RAM', value: `${formatBytes(host.value.memory?.used_bytes)} usados`, progress: host.value.memory?.used_percent, status: resourceStatus(host.value.memory?.used_percent), detail: `${formatBytes(host.value.memory?.available_bytes)} disponíveis de ${formatBytes(host.value.memory?.total_bytes)}` },
  { title: 'Disco raiz', value: `${formatBytes(host.value.filesystem?.used_bytes)} usados`, progress: host.value.filesystem?.used_percent, status: resourceStatus(host.value.filesystem?.used_percent), detail: `${formatBytes(host.value.filesystem?.available_bytes)} livres · ${host.value.filesystem?.type || ''}` },
])
const tabs = computed(() => [
  { id: 'overview', label: 'Visão geral' }, { id: 'vps', label: 'VPS e VM' },
  { id: 'containers', label: 'Containers', badge: docker.value.summary?.total || 0 },
  { id: 'project', label: 'Projeto' }, { id: 'logs', label: 'Logs', badge: (diagnostics.value.recent?.error || 0) + (diagnostics.value.recent?.critical || 0) },
])
const levelSummary = computed(() => [
  { level: 'critical', label: 'Críticos', count: logs.value.summary?.critical || 0 }, { level: 'error', label: 'Erros', count: logs.value.summary?.error || 0 },
  { level: 'warning', label: 'Avisos', count: logs.value.summary?.warning || 0 }, { level: 'info', label: 'Informações', count: logs.value.summary?.info || 0 },
])

async function refreshAll() {
  if (refreshing.value) return
  refreshing.value = true
  try {
    const [diagnosticData, infrastructureData, logData] = await Promise.all([systemLogsService.diagnostics(), systemLogsService.infrastructure(), systemLogsService.logs({ ...filters })])
    diagnostics.value = diagnosticData; infrastructure.value = infrastructureData; logs.value = logData; lastUpdated.value = new Date().toISOString()
  } finally { refreshing.value = false; initialLoading.value = false }
}
async function refreshLogs() { if (logsLoading.value || refreshing.value) return; logsLoading.value = true; try { logs.value = await systemLogsService.logs({ ...filters }); lastUpdated.value = new Date().toISOString() } finally { logsLoading.value = false } }
async function poll() { if (!autoRefresh.value || document.hidden || refreshing.value || logsLoading.value) return; try { const [d, i, l] = await Promise.all([systemLogsService.diagnostics(), systemLogsService.infrastructure(), systemLogsService.logs({ ...filters })]); diagnostics.value = d; infrastructure.value = i; logs.value = l; lastUpdated.value = new Date().toISOString() } catch { /* nova tentativa no próximo ciclo */ } }
function toggleAutoRefresh() { autoRefresh.value = !autoRefresh.value; if (autoRefresh.value) poll() }
function setLevel(level) { filters.level = filters.level === level ? 'all' : level; refreshLogs() }
function statusLabel(status) { return { ok: 'Operacional', warning: 'Atenção', error: 'Indisponível' }[status] || 'Verificando' }
function resourceStatus(value) { const n = Number(value || 0); return n >= 90 ? 'error' : (n >= 80 ? 'warning' : 'ok') }
function levelLabel(level) { return { emergency: 'Emergência', alert: 'Alerta', critical: 'Crítico', error: 'Erro', warning: 'Aviso', notice: 'Notificação', info: 'Informação', debug: 'Depuração' }[level] || level }
function formatDate(value) { if (!value) return '—'; const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value) ? value.replace(' ', 'T') : value; const date = new Date(normalized); return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'medium' }).format(date) }
function formatBytes(bytes) { const value = Number(bytes || 0); if (!value) return '0 B'; const units = ['B', 'KB', 'MB', 'GB', 'TB']; let size = value; let unit = 0; while (size >= 1024 && unit < units.length - 1) { size /= 1024; unit++ } return `${size.toFixed(size >= 10 || unit === 0 ? 1 : 2)} ${units[unit]}` }
function formatRate(bytes) { return `${formatBytes(bytes)}/s` }
function formatInteger(value) { return new Intl.NumberFormat('pt-BR').format(Number(value || 0)) }
function percent(value, warming = false) { return warming ? 'aferindo…' : `${Number(value || 0).toFixed(1).replace('.', ',')}%` }
function formatUptime(seconds) { const value = Number(seconds || 0); if (!value) return '—'; const days = Math.floor(value / 86400); const hours = Math.floor((value % 86400) / 3600); const minutes = Math.floor((value % 3600) / 60); return `${days ? `${days}d ` : ''}${hours}h ${minutes}min` }
function shortDatabaseVersion(value) { if (!value) return '—'; const match = String(value).match(/PostgreSQL\s+[\d.]+/i); return match?.[0] || String(value).slice(0, 90) }

onMounted(async () => { await refreshAll(); timer = window.setInterval(poll, 5000) })
onUnmounted(() => { if (timer) window.clearInterval(timer) })
</script>

<style scoped>
.system-monitor{display:grid;gap:1rem;min-width:0}.page-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.page-heading h1{margin:.2rem 0 0;color:var(--text-primary);font-size:1.4rem;font-weight:800}.page-heading p,.console-heading p{margin:.25rem 0 0;color:var(--text-muted);font-size:.78rem}.heading-kicker{display:flex;align-items:center;gap:.45rem;color:#34d399;font-size:.68rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.live-dot{width:.5rem;height:.5rem;border-radius:50%;background:#64748b}.live-dot.is-live{background:#22c55e;box-shadow:0 0 0 .3rem rgb(34 197 94/12%)}.heading-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.55rem}.loading-state,.empty-logs,.unavailable{padding:2.5rem 1rem;text-align:center;color:var(--text-muted)}
.overview-strip{display:grid;grid-template-columns:minmax(170px,1.2fr) repeat(4,minmax(95px,.62fr)) minmax(170px,1fr);gap:.55rem}.overview-strip>div{min-height:4rem;border:1px solid var(--border);border-radius:.8rem;background:var(--bg-card);padding:.75rem}.overall-status{display:flex;align-items:center;gap:.7rem}.overall-status span:not(.status-orb),.overview-number span,.server-clock span{display:block;color:var(--text-muted);font-size:.62rem;text-transform:uppercase;letter-spacing:.08em}.overall-status strong,.overview-number strong,.server-clock strong{display:block;margin-top:.18rem;color:var(--text-primary);font-size:.92rem}.overview-number,.server-clock{display:flex;flex-direction:column;justify-content:center}.overview-number strong{font-size:1.1rem}.status-orb{width:1rem;height:1rem;border-radius:50%;flex:none}.status-ok{background:#22c55e}.status-warning{background:#f59e0b}.status-error{background:#ef4444}
.monitor-tabs{position:sticky;top:0;z-index:8;display:flex;gap:.35rem;overflow-x:auto;border:1px solid var(--border);border-radius:.8rem;background:var(--bg-card);padding:.35rem;scrollbar-width:none}.monitor-tabs::-webkit-scrollbar{display:none}.monitor-tabs button{display:flex;align-items:center;gap:.45rem;min-width:max-content;border:0;border-radius:.55rem;padding:.58rem .9rem;color:var(--text-secondary);background:transparent;font-size:.72rem;font-weight:700}.monitor-tabs button.active{color:#fff;background:#6366f1}.monitor-tabs small{min-width:1.1rem;border-radius:999px;padding:.08rem .3rem;background:rgb(255 255 255/14%);font-size:.58rem}.tab-panel{display:grid;gap:.8rem;min-width:0}
.resource-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem}.resource-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.resource-card{padding:.9rem;min-width:0}.resource-title{display:flex;justify-content:space-between;color:var(--text-muted);font-size:.65rem;text-transform:uppercase;letter-spacing:.06em}.resource-title i{width:.55rem;height:.55rem;border-radius:50%}.resource-card>strong{display:block;margin:.5rem 0 .12rem;color:var(--text-primary);font-size:1.15rem}.resource-card>small{display:block;overflow:hidden;color:var(--text-muted);font-size:.64rem;text-overflow:ellipsis;white-space:nowrap}.progress-track{height:.28rem;margin-top:.8rem;overflow:hidden;border-radius:999px;background:var(--bg-input)}.progress-track span{display:block;height:100%;border-radius:inherit;transition:width .35s}.progress-ok{background:#22c55e}.progress-warning{background:#f59e0b}.progress-error{background:#ef4444}
.health-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.65rem}.health-card{padding:.8rem;min-width:0}.health-title{display:flex;align-items:center;gap:.4rem}.health-title>strong{color:var(--text-primary);font-size:.75rem}.health-indicator{width:.5rem;height:.5rem;border-radius:50%;flex:none}.health-badge{margin-left:auto;border-radius:999px;padding:.15rem .38rem;font-size:.54rem;font-weight:800;text-transform:uppercase}.health-ok{color:#34d399;background:rgb(16 185 129/12%)}.health-warning{color:#fbbf24;background:rgb(245 158 11/12%)}.health-error{color:#fb7185;background:rgb(239 68 68/12%)}.health-card p{min-height:2rem;margin:.6rem 0;color:var(--text-secondary);font-size:.68rem;line-height:1.4}.health-card dl,.detail-list{display:grid;grid-template-columns:1fr auto;gap:.28rem .55rem;margin:0;font-size:.65rem}.health-card dt,.detail-list dt{color:var(--text-muted)}.health-card dd,.detail-list dd{margin:0;color:var(--text-primary);font-weight:700;text-align:right}
.overview-details,.metric-panels{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}.compact-panel,.metric-panel,.project-card,.engine-panel,.stack-panel{padding:1rem}.compact-panel header,.container-table-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:.8rem}.compact-panel h2,.metric-panel h2,.project-card h2,.engine-panel h2,.stack-panel h2,.container-table-heading h2{margin:0;color:var(--text-primary);font-size:.88rem}.compact-panel p,.container-table-heading p{margin:.2rem 0 0;color:var(--text-muted);font-size:.64rem}.compact-panel button{border:0;background:transparent;color:#818cf8;font-size:.65rem;font-weight:700}.identity-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem}.identity-card{display:flex;flex-direction:column;gap:.3rem;padding:.9rem;min-width:0}.identity-card span{color:var(--text-muted);font-size:.62rem;text-transform:uppercase}.identity-card strong{overflow:hidden;color:var(--text-primary);font-size:.84rem;text-overflow:ellipsis}.identity-card small{overflow:hidden;color:var(--text-secondary);font-size:.62rem;text-overflow:ellipsis;white-space:nowrap}.metric-panels{grid-template-columns:repeat(3,minmax(0,1fr))}.metric-pairs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.55rem;margin-top:.8rem}.metric-pairs div{border:1px solid var(--border-subtle);border-radius:.55rem;background:var(--bg-input);padding:.65rem}.metric-pairs span,.metric-pairs strong{display:block}.metric-pairs span{color:var(--text-muted);font-size:.59rem}.metric-pairs strong{margin-top:.2rem;color:var(--text-primary);font-size:.75rem}
.container-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:.55rem}.container-summary>div{border:1px solid var(--border);border-radius:.7rem;background:var(--bg-card);padding:.7rem}.container-summary span,.container-summary strong{display:block}.container-summary span{color:var(--text-muted);font-size:.6rem;text-transform:uppercase}.container-summary strong{margin-top:.2rem;color:var(--text-primary);font-size:1rem}.container-summary .good strong{color:#34d399}.container-summary .danger strong,.dangerText{color:#fb7185!important}.container-table{overflow:hidden}.container-table-heading{padding:1rem;margin:0;border-bottom:1px solid var(--border)}.container-table-heading>span{color:var(--text-muted);font-size:.64rem}.container-row{display:grid;grid-template-columns:minmax(190px,1.6fr) minmax(120px,1fr) repeat(5,minmax(85px,.7fr));align-items:center;gap:.55rem;padding:.65rem 1rem;border-bottom:1px solid var(--border-subtle);font-size:.65rem}.container-row:last-child{border-bottom:0}.container-row>div{min-width:0}.container-row strong,.container-row small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.container-row strong{color:var(--text-primary)}.container-row small{margin-top:.17rem;color:var(--text-muted);font-size:.57rem}.table-head{color:var(--text-muted);background:var(--bg-input);font-size:.58rem;font-weight:800;text-transform:uppercase}.container-name{display:flex;align-items:center;gap:.55rem}.container-name>div{min-width:0}.state-pill{display:inline-flex!important;width:max-content;border-radius:999px;padding:.18rem .42rem;font-size:.56rem;font-weight:800;text-transform:uppercase}.state-ok{color:#34d399;background:rgb(34 197 94/10%)}.state-warning{color:#fbbf24;background:rgb(245 158 11/10%)}.state-error{color:#fb7185;background:rgb(239 68 68/10%)}.engine-panel .columns{grid-template-columns:auto 1fr auto 1fr;gap:.45rem 1rem;margin-top:.8rem}.engine-panel .columns dd{text-align:left;overflow-wrap:anywhere}
.project-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem}.project-card .detail-list{margin-top:.9rem}.project-card .detail-list dd{max-width:260px}.breakable{overflow-wrap:anywhere}.stack-list{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.75rem}.stack-list span{border:1px solid var(--border);border-radius:999px;background:var(--bg-input);padding:.3rem .55rem;color:var(--text-secondary);font-size:.64rem}
.log-console{min-width:0;overflow:hidden}.console-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem;border-bottom:1px solid var(--border)}.console-heading h2{margin:0;color:var(--text-primary);font-size:1rem}.console-file{display:flex;flex-direction:column;align-items:flex-end;gap:.2rem;color:var(--text-secondary);font: .68rem ui-monospace,monospace}.console-file small{color:var(--text-muted)}.summary-row{display:flex;gap:.45rem;overflow-x:auto;padding:.75rem 1rem 0}.summary-chip{display:flex;align-items:center;gap:.5rem;min-width:max-content;border:1px solid var(--border);border-radius:999px;padding:.38rem .65rem;color:var(--text-secondary);background:var(--bg-input);font-size:.68rem}.summary-chip.active{border-color:currentColor}.filters{display:grid;grid-template-columns:170px 170px minmax(220px,1fr) auto;align-items:end;gap:.65rem;padding:.8rem 1rem}.filters label>span{display:block;margin-bottom:.3rem;color:var(--text-muted);font-size:.65rem}.filters .input{height:2.35rem;font-size:.72rem}.log-list{max-height:min(55vh,620px);overflow:auto;border-top:1px solid var(--border)}.log-entry{border-bottom:1px solid var(--border-subtle);background:var(--bg-card)}.log-entry[open]{background:var(--bg-input)}.log-entry summary{display:grid;grid-template-columns:88px 138px minmax(220px,1fr) minmax(0,230px);align-items:center;gap:.65rem;padding:.72rem 1rem;cursor:pointer;list-style:none}.level-pill{display:inline-flex;justify-content:center;border-radius:.35rem;padding:.22rem .4rem;font-size:.61rem;font-weight:800;text-transform:uppercase}.level-critical,.level-error,.level-emergency,.level-alert{color:#fb7185;background:rgb(244 63 94/11%)}.level-warning{color:#fbbf24;background:rgb(245 158 11/11%)}.level-info,.level-notice{color:#60a5fa;background:rgb(59 130 246/11%)}.level-debug{color:#a78bfa;background:rgb(139 92 246/11%)}.log-entry time{color:var(--text-muted);font:.64rem ui-monospace,monospace}.log-message,.log-entry summary code{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.log-message{color:var(--text-primary);font-size:.72rem}.log-entry summary code{color:var(--text-muted);font-size:.62rem}.log-detail{padding:0 1rem 1rem calc(88px + 1.65rem)}.detail-meta{display:flex;flex-wrap:wrap;gap:1rem;margin-bottom:.65rem;color:var(--text-muted);font-size:.65rem}.log-detail pre{max-height:15rem;overflow:auto;margin:0;border:1px solid var(--border);border-radius:.5rem;background:rgb(2 6 23/42%);padding:.75rem;color:var(--text-secondary);font:.66rem/1.55 ui-monospace,monospace;white-space:pre-wrap;word-break:break-word}.console-footer{display:flex;justify-content:space-between;gap:1rem;padding:.65rem 1rem;color:var(--text-muted);font-size:.62rem}.live-copy{color:#34d399}.error-box{color:#fb7185}.security-note{margin:0;color:var(--text-muted);font-size:.64rem;text-align:center}
@media(max-width:1150px){.overview-strip{grid-template-columns:repeat(4,minmax(0,1fr))}.overall-status,.server-clock{grid-column:span 2}.health-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.resource-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.identity-grid,.project-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.metric-panels{grid-template-columns:1fr}.container-row{grid-template-columns:minmax(170px,1.4fr) repeat(6,minmax(80px,1fr))}.filters{grid-template-columns:repeat(2,minmax(0,1fr))}.search-filter{grid-column:span 2}.log-entry summary{grid-template-columns:84px 130px minmax(180px,1fr)}.log-entry summary code{display:none}}
@media(max-width:700px){.system-monitor{gap:.7rem}.page-heading{display:block}.page-heading h1{font-size:1.12rem}.page-heading p{font-size:.69rem}.heading-actions{justify-content:stretch;margin-top:.7rem}.heading-actions button{flex:1}.overview-strip{grid-template-columns:repeat(3,minmax(0,1fr));gap:.38rem}.overview-strip>div{min-height:3.35rem;padding:.5rem}.overall-status,.server-clock{grid-column:span 3}.overview-number strong{font-size:.86rem}.monitor-tabs{top:0;margin-inline:-.1rem}.monitor-tabs button{padding:.5rem .7rem}.resource-grid,.resource-grid.three{grid-template-columns:repeat(2,minmax(0,1fr));gap:.4rem}.resource-card{padding:.65rem}.resource-card>strong{font-size:.9rem}.resource-card>small{font-size:.56rem}.health-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:.4rem}.health-card{padding:.65rem}.health-card p{min-height:0}.overview-details,.identity-grid,.project-grid{grid-template-columns:1fr}.metric-pairs{gap:.35rem}.container-summary{grid-template-columns:repeat(3,minmax(0,1fr));gap:.35rem}.container-summary>div{padding:.55rem}.container-summary>div:nth-child(4),.container-summary>div:nth-child(5){grid-column:span 1}.container-table-heading{display:block;padding:.75rem}.container-table-heading>span{display:block;margin-top:.3rem}.table-head{display:none}.container-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.55rem;padding:.75rem}.container-name{grid-column:span 2;border-bottom:1px solid var(--border-subtle);padding-bottom:.5rem}.container-row>div:not(.container-name)::before{content:attr(data-label);display:block;margin-bottom:.15rem;color:var(--text-muted);font-size:.52rem;text-transform:uppercase}.engine-panel .columns{grid-template-columns:auto 1fr}.filters{grid-template-columns:repeat(2,minmax(0,1fr));padding:.7rem .8rem;gap:.5rem}.filters .search-filter,.filters button{grid-column:span 2}.console-heading{display:block;padding:.8rem}.console-file{align-items:flex-start;margin-top:.5rem}.log-list{max-height:55vh}.log-entry summary{grid-template-columns:74px 1fr;gap:.35rem .5rem;padding:.65rem .8rem}.log-entry time{text-align:right}.log-message{grid-column:span 2;white-space:normal}.log-detail{padding:0 .8rem .8rem}.console-footer{display:block}.console-footer span{display:block}.metric-panels{gap:.5rem}}
</style>
