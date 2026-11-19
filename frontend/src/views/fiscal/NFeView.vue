<template>
  <div class="nfe-page animate-fade-in">
    <div class="nfe-main">
      <div class="page-header mb-4">
        <div>
          <h1 class="page-title">Notas Fiscais de Saída</h1>
          <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ total }} registros</p>
        </div>
        <div class="flex items-center gap-2">
          <button @click="carregar" class="btn-secondary btn-icon" :disabled="loading" title="Atualizar">
            <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
          </button>
          <router-link to="/pedidos" class="btn-primary btn-sm">
            <PlusIcon class="w-4 h-4" /> Incluir nota fiscal
          </router-link>
        </div>
      </div>

      <div class="card p-3 mb-3">
        <div class="flex flex-wrap items-center gap-2">
          <button class="btn-secondary btn-icon" @click="mostrarFiltros = !mostrarFiltros" title="Filtros">
            <FunnelIcon class="w-4 h-4" />
          </button>
          <div class="flex-1 min-w-[260px] relative">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-muted)]" />
            <input v-model="busca" type="text" placeholder="Pesquisar por nome, CPF/CNPJ ou nº da nota" class="input pl-9 pr-9" @input="debounceBusca" />
            <button v-if="busca" @click="busca=''; carregar()" class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--text-muted)]">×</button>
          </div>
          <div class="relative min-w-[230px]">
            <CalendarDaysIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-muted)]" />
            <input v-model="periodo" type="text" class="input pl-9" placeholder="dd/mm/aaaa até dd/mm/aaaa" @change="aplicarPeriodo" />
          </div>
          <select v-model="filtroTipo" class="input w-40" @change="carregar">
            <option value="">Todos os tipos</option>
            <option value="NFe">NF-e</option>
            <option value="NFCe">NFC-e</option>
            <option value="NFSe">NFS-e</option>
          </select>
          <select v-model="filtroStatus" class="input w-44" @change="carregar">
            <option value="">Todos os status</option>
            <option value="rascunho">Rascunho</option>
            <option value="processando">Processando</option>
            <option value="autorizada">Autorizada</option>
            <option value="cancelada">Cancelada</option>
            <option value="rejeitada">Rejeitada</option>
          </select>
        </div>

        <div v-if="mostrarFiltros" class="mt-3 pt-3 border-t border-[var(--border)] grid grid-cols-1 md:grid-cols-4 gap-3">
          <select v-model="filtroAmbiente" class="input" @change="carregar">
            <option value="">Todos os ambientes</option>
            <option value="1">Produção</option>
            <option value="2">Homologação</option>
          </select>
          <input v-model="numero" class="input" placeholder="Número da nota" @input="debounceBusca" />
          <input v-model="chaveAcesso" class="input" placeholder="Chave de acesso" @input="debounceBusca" />
          <button class="btn-secondary" @click="limparFiltros">Limpar filtros</button>
        </div>
      </div>

      <div v-if="selecionados.length" class="selected-bar">
        <span>{{ selecionados.length }} nota(s) selecionada(s)</span>
        <button class="text-brand-400 hover:underline" @click="selecionados=[]">Limpar seleção</button>
      </div>

      <div class="table-wrapper min-h-[500px]">
        <table class="table">
          <thead>
            <tr>
              <th class="w-10"><input type="checkbox" :checked="todosSelecionados" @change="alternarTodos" /></th>
              <th>Número / Série</th>
              <th>Destinatário</th>
              <th>Ambiente</th>
              <th class="text-right">Valor total</th>
              <th>Status</th>
              <th>Emissão</th>
              <th class="text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading"><td colspan="8"><div class="skeleton h-8 rounded m-2" /></td></tr>
            <tr v-else-if="!items.length"><td colspan="8">
              <div class="empty-state py-16">
                <DocumentMagnifyingGlassIcon class="w-14 h-14 mx-auto mb-3 text-brand-500/60" />
                <p class="text-base font-semibold text-[var(--text-primary)]">Nenhum resultado encontrado.</p>
                <p class="text-xs text-[var(--text-muted)] mt-1">Confira os filtros ou inclua uma nova nota fiscal.</p>
              </div>
            </td></tr>
            <tr v-else v-for="item in items" :key="item.id" class="cursor-pointer" :class="selecionados.includes(item.id) && 'row-selected'" @click="verItem(item.id)">
              <td @click.stop><input type="checkbox" :value="item.id" v-model="selecionados" /></td>
              <td class="text-sm font-medium text-[var(--text-primary)]">
                {{ item.tipo }} {{ item.numero ?? '—' }}<span v-if="item.serie" class="text-[var(--text-muted)]"> / {{ item.serie }}</span>
              </td>
              <td class="text-sm">{{ destinatario(item) }}</td>
              <td class="text-xs text-[var(--text-muted)]">{{ item.ambiente === 1 ? 'Produção' : 'Homologação' }}</td>
              <td class="text-sm text-right font-medium">{{ moeda(item.valor_total) }}</td>
              <td><BadgeStatus :status="item.status" /></td>
              <td class="text-xs text-[var(--text-muted)]">{{ data(item.data_emissao) }}</td>
              <td class="text-right" @click.stop>
                <button @click="verItem(item.id)" class="btn-ghost btn-icon"><EyeIcon class="w-4 h-4" /></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <aside class="nfe-actions-panel">
      <button class="btn-primary w-full justify-center mb-3" @click="$router.push('/pedidos')">
        <PlusIcon class="w-4 h-4" /> Incluir nota fiscal
      </button>

      <div class="action-list">
        <button :disabled="!selecionados.length || processando" @click="enviarSelecionadas">
          <PaperAirplaneIcon /> <span>Enviar NF-es selecionadas</span>
        </button>
        <button :disabled="!selecionados.length || processando" @click="imprimirSelecionadas">
          <PrinterIcon /> <span>Imprimir NF-es selecionadas</span>
        </button>
        <button :disabled="!selecionados.length" @click="enviarLojaVirtual">
          <ShoppingCartIcon /> <span>Enviar dados para loja virtual</span>
        </button>
        <button :disabled="processando" @click="enviarPendentes">
          <ClipboardDocumentCheckIcon /> <span>Enviar notas pendentes</span>
        </button>
        <button :disabled="!selecionados.length || processando" @click="gerarDanfe">
          <DocumentArrowDownIcon /> <span>Gerar PDF DANFE</span>
        </button>
        <button :disabled="!selecionados.length || processando" @click="exportarXml">
          <DocumentIcon /> <span>Exportar XML</span>
        </button>
        <button :disabled="!selecionados.length || processando" @click="imprimirSimplificado">
          <PrinterIcon /> <span>Imprimir DANFE simplificado em lote</span>
        </button>
        <button @click="maisAcoesAberto = !maisAcoesAberto">
          <PlusCircleIcon /> <span>Mais ações</span>
          <ChevronDownIcon class="ml-auto" />
        </button>
      </div>

      <div v-if="maisAcoesAberto" class="more-actions">
        <button @click="carregar">Atualizar listagem</button>
        <button @click="selecionarAutorizadas">Selecionar autorizadas</button>
        <button @click="selecionarPendentes">Selecionar pendentes</button>
        <button @click="exportarCsv">Exportar listagem CSV</button>
      </div>

      <div class="info-card">
        <h3>Informações</h3>
        <div><span>Quantidade de notas</span><strong>{{ total }}</strong></div>
        <div><span>Valor total</span><strong>{{ moeda(valorTotal) }}</strong></div>
        <div><span>Selecionadas</span><strong>{{ selecionados.length }}</strong></div>
      </div>

      <div v-if="mensagem" class="status-message" :class="mensagemTipo">{{ mensagem }}</div>
    </aside>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import BadgeStatus from '@/components/ui/BadgeStatus.vue'
import { useFormato } from '@/composables/useFormato'
import {
  ArrowPathIcon, MagnifyingGlassIcon, EyeIcon, PlusIcon, FunnelIcon,
  CalendarDaysIcon, DocumentMagnifyingGlassIcon, PaperAirplaneIcon,
  PrinterIcon, ShoppingCartIcon, ClipboardDocumentCheckIcon,
  DocumentArrowDownIcon, DocumentIcon, PlusCircleIcon, ChevronDownIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const { moeda, data } = useFormato()
const items = ref([])
const total = ref(0)
const loading = ref(false)
const processando = ref(false)
const busca = ref('')
const filtroTipo = ref('')
const filtroStatus = ref('')
const filtroAmbiente = ref('')
const numero = ref('')
const chaveAcesso = ref('')
const periodo = ref('')
const dataInicio = ref('')
const dataFim = ref('')
const selecionados = ref([])
const mostrarFiltros = ref(false)
const maisAcoesAberto = ref(false)
const mensagem = ref('')
const mensagemTipo = ref('success')
let timer = null

const todosSelecionados = computed(() => items.value.length > 0 && items.value.every(i => selecionados.value.includes(i.id)))
const valorTotal = computed(() => items.value.reduce((s, i) => s + Number(i.valor_total || 0), 0))

function destinatario(item) {
  return item.destinatario_nome || item.pedido?.cliente?.razao_social || item.pedido?.cliente?.nome_fantasia || '—'
}

async function carregar() {
  loading.value = true
  try {
    const { data: resp } = await api.get('/nfe', { params: {
      busca: busca.value, tipo: filtroTipo.value, status: filtroStatus.value,
      ambiente: filtroAmbiente.value, numero: numero.value, chave_acesso: chaveAcesso.value,
      data_inicio: dataInicio.value, data_fim: dataFim.value, per_page: 100,
    }})
    items.value = resp.data ?? resp
    total.value = resp.total ?? items.value.length
    selecionados.value = selecionados.value.filter(id => items.value.some(i => i.id === id))
  } catch (e) {
    avisar(e?.response?.data?.message || 'Não foi possível carregar as notas.', 'error')
  } finally { loading.value = false }
}

function aplicarPeriodo() {
  const partes = periodo.value.split(/\s+(?:até|a)\s+/i)
  if (partes.length === 2) {
    dataInicio.value = paraIso(partes[0])
    dataFim.value = paraIso(partes[1])
  } else { dataInicio.value = ''; dataFim.value = '' }
  carregar()
}
function paraIso(v) { const m = String(v).trim().match(/(\d{2})\/(\d{2})\/(\d{4})/); return m ? `${m[3]}-${m[2]}-${m[1]}` : '' }
function debounceBusca() { clearTimeout(timer); timer = setTimeout(carregar, 400) }
function verItem(id) { router.push('/fiscal/nfe/' + id) }
function alternarTodos(e) { selecionados.value = e.target.checked ? items.value.map(i => i.id) : [] }
function limparFiltros() { busca.value=''; filtroTipo.value=''; filtroStatus.value=''; filtroAmbiente.value=''; numero.value=''; chaveAcesso.value=''; periodo.value=''; dataInicio.value=''; dataFim.value=''; carregar() }
function avisar(texto, tipo='success') { mensagem.value=texto; mensagemTipo.value=tipo; setTimeout(() => { mensagem.value='' }, 5000) }
function itensSelecionados() { return items.value.filter(i => selecionados.value.includes(i.id)) }

async function executarEmSelecionadas(fn, sucesso) {
  if (!selecionados.value.length) return avisar('Selecione pelo menos uma nota.', 'error')
  processando.value = true
  let ok = 0
  try { for (const item of itensSelecionados()) { await fn(item); ok++ } avisar(`${ok} nota(s): ${sucesso}`); await carregar() }
  catch (e) { avisar(e?.response?.data?.message || 'A operação não pôde ser concluída.', 'error') }
  finally { processando.value = false }
}
async function enviarSelecionadas() { await executarEmSelecionadas(i => api.post(`/nfe/${i.id}/enviar-email`), 'envio solicitado com sucesso.') }
async function imprimirSelecionadas() { await abrirDanfes() }
async function gerarDanfe() { await abrirDanfes() }
async function imprimirSimplificado() { await abrirDanfes() }
async function abrirDanfes() {
  await executarEmSelecionadas(async i => {
    const response = await api.get(`/nfe/${i.id}/danfe-arquivo`, { responseType:'blob' })
    const url = URL.createObjectURL(new Blob([response.data], { type:'application/pdf' }))
    const janela = window.open(url, '_blank')
    if (!janela) {
      URL.revokeObjectURL(url)
      throw new Error('O navegador bloqueou a abertura do DANFE.')
    }
    setTimeout(() => URL.revokeObjectURL(url), 30000)
  }, 'DANFE aberto para impressão.')
}
async function exportarXml() {
  await executarEmSelecionadas(async i => {
    const { data:r } = await api.get(`/nfe/${i.id}/xml`)
    const blob = new Blob([r.xml], { type:'application/xml;charset=utf-8' })
    const a = document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=r.filename || `NFe-${i.id}.xml`; a.click(); URL.revokeObjectURL(a.href)
  }, 'XML exportado.')
}
async function enviarPendentes() {
  const pendentes = items.value.filter(i => ['rascunho','rejeitada'].includes(i.status))
  if (!pendentes.length) return avisar('Não há notas pendentes nesta listagem.', 'error')
  processando.value = true
  let ok=0
  try { for (const i of pendentes) { await api.post(`/nfe/${i.id}/emitir`); ok++ } avisar(`${ok} nota(s) pendente(s) enviada(s).`); await carregar() }
  catch (e) { avisar(e?.response?.data?.message || 'Falha ao enviar notas pendentes.', 'error') }
  finally { processando.value=false }
}
function enviarLojaVirtual() { avisar('Integração com loja virtual será executada pelas APIs de marketplace configuradas.', 'info') }
function selecionarAutorizadas() { selecionados.value = items.value.filter(i => i.status === 'autorizada').map(i => i.id) }
function selecionarPendentes() { selecionados.value = items.value.filter(i => ['rascunho','rejeitada'].includes(i.status)).map(i => i.id) }
function exportarCsv() {
  const rows = [['Número','Série','Destinatário','Ambiente','Valor','Status','Emissão'], ...items.value.map(i => [i.numero||'',i.serie||'',destinatario(i),i.ambiente===1?'Produção':'Homologação',i.valor_total||0,i.status||'',i.data_emissao||''])]
  const csv = rows.map(r => r.map(v => `"${String(v).replaceAll('"','""')}"`).join(';')).join('\n')
  const blob = new Blob(['\ufeff'+csv], {type:'text/csv;charset=utf-8'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='notas-fiscais.csv'; a.click(); URL.revokeObjectURL(a.href)
}

onMounted(carregar)
</script>

<style scoped>
.nfe-page { display:grid; grid-template-columns:minmax(0,1fr) 270px; gap:16px; align-items:start; }
.nfe-main { min-width:0; }
.nfe-actions-panel { position:sticky; top:0; border:1px solid var(--border); background:var(--bg-surface); border-radius:12px; padding:14px; min-height:620px; }
.action-list { display:flex; flex-direction:column; }
.action-list button { display:flex; align-items:center; gap:10px; width:100%; padding:11px 4px; border-bottom:1px solid var(--border-subtle); font-size:12px; color:var(--text-secondary); text-align:left; transition:.15s; }
.action-list button:hover:not(:disabled) { color:var(--text-primary); padding-left:8px; }
.action-list button:disabled { opacity:.35; cursor:not-allowed; }
.action-list svg { width:17px; height:17px; flex:0 0 auto; }
.more-actions { padding:8px 0; border-bottom:1px solid var(--border); display:flex; flex-direction:column; gap:2px; }
.more-actions button { text-align:left; padding:7px 8px; border-radius:6px; font-size:11px; color:var(--text-muted); }
.more-actions button:hover { background:var(--bg-elevated); color:var(--text-primary); }
.info-card { margin-top:18px; padding-top:14px; border-top:1px solid var(--border); }
.info-card h3 { font-size:13px; font-weight:700; margin-bottom:12px; }
.info-card div { display:flex; justify-content:space-between; font-size:11px; color:var(--text-muted); margin-bottom:9px; }
.info-card strong { color:var(--text-primary); }
.selected-bar { display:flex; align-items:center; justify-content:space-between; padding:8px 12px; margin-bottom:8px; border-radius:8px; background:rgb(var(--brand-rgb, 99 102 241) / .12); font-size:12px; }
.row-selected { background:rgb(var(--brand-rgb, 99 102 241) / .08); }
.status-message { margin-top:14px; padding:10px; border-radius:8px; font-size:11px; }
.status-message.success { background:rgba(16,185,129,.12); color:#34d399; }
.status-message.error { background:rgba(239,68,68,.12); color:#f87171; }
.status-message.info { background:rgba(59,130,246,.12); color:#60a5fa; }
@media (max-width:1100px) { .nfe-page { grid-template-columns:1fr; } .nfe-actions-panel { position:static; min-height:auto; } }
</style>
