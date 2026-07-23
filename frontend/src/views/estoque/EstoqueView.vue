<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Estoque</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ totalItens }} produtos • {{ moeda(valorTotal) }} em estoque</p>
      </div>
      <div class="flex items-center gap-2">
        <router-link to="/estoque/movimentacoes" class="btn-secondary btn-sm">Movimentações</router-link>
        <router-link to="/estoque/inventario" class="btn-secondary btn-sm">Inventário</router-link>
        <button @click="carregar" class="btn-secondary btn-sm" :disabled="loading">
          <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
        </button>
        <button @click="modalTransferencia = true" class="btn-primary btn-sm">
          <ArrowsRightLeftIcon class="w-4 h-4" /> Transferir
        </button>
        <button @click="xmlInput?.click()" class="btn-primary btn-sm">
          <DocumentArrowUpIcon class="w-4 h-4" /> Entrada por XML
        </button>
        <input ref="xmlInput" type="file" accept=".xml,application/xml,text/xml" class="hidden" @change="selecionarXml" />
      </div>
    </div>

    <div v-if="alertas.length" class="card p-4 border border-warning/30 bg-warning/5">
      <p class="text-xs font-medium text-warning mb-1 flex items-center gap-1">
        <ExclamationTriangleIcon class="w-4 h-4" /> {{ alertas.length }} produto(s) abaixo do estoque mínimo
      </p>
    </div>

    <div class="card p-4">
      <div class="flex flex-wrap gap-3">
        <div class="flex-1 min-w-[220px] relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-muted)]" />
          <input v-model="busca" type="text" placeholder="Buscar por nome, código ou SKU..." class="input pl-9" @input="debounceBusca" />
        </div>
        <select v-model="filtroDeposito" class="input w-52" @change="carregar">
          <option value="">Todos os depósitos</option>
          <option v-for="d in depositos" :key="d.id" :value="d.id">{{ d.nome }}</option>
        </select>
      </div>
    </div>

    <div class="table-wrapper">
      <table class="table">
        <thead><tr>
          <th>Produto</th><th>Categoria</th><th>Depósito</th>
          <th class="text-right">Quantidade</th><th class="text-right">Reservado</th>
          <th class="text-right">Disponível</th><th class="text-right">Valor Total</th>
        </tr></thead>
        <tbody>
          <tr v-if="loading"><td colspan="7"><div class="skeleton h-8 rounded m-2" /></td></tr>
          <tr v-else-if="!itensFiltrados.length"><td colspan="7">
            <div class="empty-state"><p class="text-sm text-[var(--text-muted)]">Nenhum item de estoque encontrado</p></div>
          </td></tr>
          <tr v-else v-for="(item, i) in itensFiltrados" :key="i" :class="item.abaixo_minimo && 'bg-warning/5'">
            <td class="text-sm font-medium text-[var(--text-primary)]">{{ item.nome }}</td>
            <td class="text-xs text-[var(--text-muted)]">{{ item.categoria || '—' }}</td>
            <td class="text-xs">{{ item.deposito || '—' }}</td>
            <td class="text-sm text-right">{{ decimal(item.quantidade, 3) }}</td>
            <td class="text-sm text-right text-[var(--text-muted)]">{{ decimal(item.quantidade_reservada, 3) }}</td>
            <td class="text-sm text-right font-medium" :class="item.abaixo_minimo && 'text-warning'">{{ decimal(item.quantidade_disponivel, 3) }}</td>
            <td class="text-sm text-right">{{ moeda(item.valor_total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Transferência -->
    <Teleport to="body" v-if="modalTransferencia">
      <div class="modal-overlay" @click.self="modalTransferencia = false">
        <div class="modal-sm animate-slide-up">
          <div class="modal-header">
            <h3 class="text-base font-semibold text-[var(--text-primary)]">Transferir entre Depósitos</h3>
            <button @click="modalTransferencia = false" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div class="modal-body space-y-3">
            <div class="form-group">
              <label class="label">Produto</label>
              <input v-model.number="transf.produto_id" type="number" class="input" placeholder="ID do produto" />
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label class="label">Depósito de Origem</label>
                <select v-model.number="transf.deposito_origem_id" class="select">
                  <option :value="null">Selecione</option>
                  <option v-for="d in depositos" :key="d.id" :value="d.id">{{ d.nome }}</option>
                </select>
              </div>
              <div class="form-group">
                <label class="label">Depósito de Destino</label>
                <select v-model.number="transf.deposito_destino_id" class="select">
                  <option :value="null">Selecione</option>
                  <option v-for="d in depositos" :key="d.id" :value="d.id">{{ d.nome }}</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="label">Quantidade</label>
              <input v-model.number="transf.quantidade" type="number" step="0.001" min="0" class="input" />
            </div>
          </div>
          <div class="modal-footer">
            <button @click="modalTransferencia = false" class="btn-secondary">Cancelar</button>
            <button @click="transferir" class="btn-primary" :disabled="processando">Transferir</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal de entrada por XML da NF-e de compra -->
    <Teleport to="body" v-if="modalXml">
      <div class="modal-overlay" @click.self="fecharXml">
        <div class="modal-lg animate-slide-up max-h-[92vh] overflow-y-auto">
          <div class="modal-header">
            <div><h3 class="text-base font-semibold">Entrada de estoque pela NF-e</h3><p class="text-xs text-[var(--text-muted)]">Confira as associações antes de atualizar.</p></div>
            <button @click="fecharXml" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div v-if="lendoXml" class="modal-body"><div class="notice">Validando e lendo o XML autorizado...</div></div>
          <template v-else-if="notaXml">
            <div class="modal-body space-y-4">
              <div class="grid gap-3 rounded-lg border border-[var(--border)] p-4 md:grid-cols-4 text-xs">
                <div class="md:col-span-2"><span class="label">Fornecedor</span><strong>{{ notaXml.emitente_nome }}</strong></div>
                <div><span class="label">NF-e</span><strong>{{ notaXml.numero }}</strong></div>
                <div><span class="label">Valor</span><strong>{{ moeda(notaXml.valor_total) }}</strong></div>
                <div class="md:col-span-4"><span class="label">Chave de acesso</span><span class="break-all">{{ notaXml.chave_acesso }}</span></div>
              </div>
              <div><label class="label">Depósito de entrada *</label><select v-model.number="depositoXml" class="select max-w-md"><option :value="null">Selecione</option><option v-for="d in depositos" :key="d.id" :value="d.id">{{ d.nome }}{{ d.principal ? ' — Principal' : '' }}</option></select></div>
              <div class="table-wrapper">
                <table class="table">
                  <thead><tr><th>Item do XML</th><th>Código/GTIN</th><th class="text-right">Quantidade</th><th class="text-right">Custo unit.</th><th>Produto no ERP *</th></tr></thead>
                  <tbody><tr v-for="item in notaXml.itens" :key="item.numero">
                    <td><p class="text-sm font-medium">{{ item.descricao }}</p><p class="text-xs text-[var(--text-muted)]">Item {{ item.numero }} · {{ item.unidade }}</p></td>
                    <td class="text-xs">{{ item.codigo }}<br/>{{ item.gtin || 'Sem GTIN' }}</td>
                    <td class="text-right">{{ decimal(item.quantidade, 3) }}</td>
                    <td class="text-right">{{ moeda(item.valor_unitario) }}</td>
                    <td><select v-model.number="item.produto_id" class="select min-w-[280px]" :class="!item.produto_id && 'border-warning'"><option :value="null">Associar produto...</option><option v-for="p in catalogoXml" :key="p.id" :value="p.id">{{ p.nome }} — {{ p.codigo || p.sku || p.ean || p.id }}</option></select></td>
                  </tr></tbody>
                </table>
              </div>
              <div v-if="itensSemAssociacao" class="notice border-warning/40 text-warning">Associe todos os itens do XML a produtos cadastrados no ERP.</div>
            </div>
            <div class="modal-footer"><button @click="fecharXml" class="btn-secondary">Cancelar</button><button @click="importarXml" class="btn-primary" :disabled="processandoXml || !depositoXml || itensSemAssociacao">{{ processandoXml ? 'Atualizando...' : 'Confirmar entrada no estoque' }}</button></div>
          </template>
        </div>
      </div>
    </Teleport>
  </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowPathIcon, MagnifyingGlassIcon, ArrowsRightLeftIcon, DocumentArrowUpIcon, ExclamationTriangleIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const toast = useToast()
const { moeda, decimal } = useFormato()

const itens = ref([])
const alertas = ref([])
const depositos = ref([])
const totalItens = ref(0)
const valorTotal = ref(0)
const loading = ref(false)
const processando = ref(false)
const busca = ref('')
const filtroDeposito = ref('')
const modalTransferencia = ref(false)
const modalXml = ref(false), lendoXml = ref(false), processandoXml = ref(false)
const xmlInput = ref(null), arquivoXml = ref(null), notaXml = ref(null), catalogoXml = ref([]), depositoXml = ref(null)
const transf = ref({ produto_id: null, deposito_origem_id: null, deposito_destino_id: null, quantidade: null })
let timer = null

const itensFiltrados = computed(() => {
  if (!filtroDeposito.value) return itens.value
  const d = depositos.value.find(d => d.id === filtroDeposito.value)
  return itens.value.filter(i => i.deposito === d?.nome)
})
const itensSemAssociacao = computed(() => notaXml.value?.itens?.some(item => !item.produto_id) ?? true)

async function selecionarXml(event) {
  const arquivo = event.target.files?.[0]
  event.target.value = ''
  if (!arquivo) return
  arquivoXml.value = arquivo
  notaXml.value = null
  modalXml.value = true
  lendoXml.value = true
  try {
    const fd = new FormData(); fd.append('xml', arquivo)
    const {data} = await api.post('/estoque/xml-entrada/preview', fd, { headers: {'Content-Type':'multipart/form-data'} })
    notaXml.value = data.nota
    catalogoXml.value = data.catalogo || []
    depositoXml.value = depositos.value.find(d => d.principal)?.id || depositos.value[0]?.id || null
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível ler o XML da NF-e.')
    fecharXml()
  } finally { lendoXml.value = false }
}

async function importarXml() {
  if (!arquivoXml.value || !notaXml.value || itensSemAssociacao.value || !depositoXml.value) return
  processandoXml.value = true
  try {
    const fd = new FormData()
    fd.append('xml', arquivoXml.value)
    fd.append('deposito_id', depositoXml.value)
    notaXml.value.itens.forEach((item, index) => {
      fd.append(`itens[${index}][numero]`, item.numero)
      fd.append(`itens[${index}][produto_id]`, item.produto_id)
    })
    const {data} = await api.post('/estoque/xml-entrada/importar', fd, { headers: {'Content-Type':'multipart/form-data'} })
    toast.success(data.message)
    fecharXml()
    await Promise.all([carregar(), carregarAlertas()])
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.error(errors ? Object.values(errors).flat()[0] : (e.response?.data?.message || 'Não foi possível importar a NF-e.'))
  } finally { processandoXml.value = false }
}

function fecharXml() {
  modalXml.value = false; arquivoXml.value = null; notaXml.value = null; catalogoXml.value = []; depositoXml.value = null
}

async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get('/estoque', { params: { busca: busca.value } })
    itens.value = data.dados ?? []
    totalItens.value = data.total_itens ?? 0
    valorTotal.value = data.valor_total ?? 0
  } catch {
    // erros já tratados globalmente pelo interceptor do api.js
  } finally {
    loading.value = false
  }
}

async function carregarAlertas() {
  try {
    const { data } = await api.get('/estoque/alertas')
    alertas.value = data.dados ?? []
  } catch { /* opcional */ }
}

async function carregarDepositos() {
  try {
    const { data } = await api.get('/depositos')
    depositos.value = data
  } catch { /* opcional */ }
}

async function transferir() {
  processando.value = true
  try {
    const { data } = await api.post('/estoque/transferencia', transf.value)
    toast.success(data.message)
    modalTransferencia.value = false
    transf.value = { produto_id: null, deposito_origem_id: null, deposito_destino_id: null, quantidade: null }
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível transferir.')
  } finally {
    processando.value = false
  }
}

function debounceBusca() { clearTimeout(timer); timer = setTimeout(carregar, 400) }

onMounted(() => {
  carregar()
  carregarAlertas()
  carregarDepositos()
})
</script>
