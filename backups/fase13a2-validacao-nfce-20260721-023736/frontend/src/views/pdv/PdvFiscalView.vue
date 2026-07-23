<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">NFC-e do PDV</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">Fluxo integrado ao módulo Fiscal, certificado, CSC e SEFAZ.</p>
      </div>
      <RouterLink to="/pdv/vendas" class="btn-secondary">Voltar</RouterLink>
    </div>

    <div class="card p-5 space-y-4">
      <div class="grid gap-3 md:grid-cols-2">
        <div>
          <label class="label">Venda do PDV</label>
          <select v-model="vendaId" class="select">
            <option value="">Selecione uma venda</option>
            <option v-for="v in vendas" :key="v.id" :value="v.id">
              #{{ v.numero }} · {{ cliente(v) }} · {{ moeda(v.total) }} · {{ labelStatus(v.nfce_status) }}
            </option>
          </select>
        </div>

        <div class="config-box">
          <span>Configuração automática</span>
          <strong>{{ validacao?.configuracao?.ambiente_nome || 'Aguardando validação' }}</strong>
          <small>Série {{ validacao?.configuracao?.serie || '—' }} · UF {{ validacao?.configuracao?.uf || '—' }}</small>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <button class="btn-secondary" :disabled="!vendaId || processando" @click="validar">Validar</button>
        <button class="btn-secondary" :disabled="!validacao?.valido || processando" @click="preparar">Preparar no Fiscal</button>
        <button class="btn-primary" :disabled="!validacao?.valido || processando" @click="emitir">
          {{ processando ? 'Processando...' : 'Emitir na SEFAZ' }}
        </button>
        <button v-if="documento" class="btn-secondary" @click="baixarDanfe">Baixar DANFE</button>
      </div>
    </div>

    <div v-if="validacao" class="card p-5">
      <div v-if="validacao.valido" class="ok">Venda apta para o fluxo fiscal da NFC-e.</div>

      <div v-if="validacao.erros?.length" class="mt-3">
        <h3 class="text-xs font-semibold text-danger">Pendências</h3>
        <p v-for="erro in validacao.erros" :key="erro" class="mt-1 text-xs">• {{ erro }}</p>
      </div>

      <div v-if="validacao.avisos?.length" class="mt-3">
        <h3 class="text-xs font-semibold text-warning">Avisos</h3>
        <p v-for="aviso in validacao.avisos" :key="aviso" class="mt-1 text-xs">• {{ aviso }}</p>
      </div>
    </div>

    <div v-if="documento" class="card p-5">
      <h2 class="text-sm font-semibold">Documento fiscal vinculado à venda</h2>

      <div class="mt-3 grid gap-3 md:grid-cols-4 text-xs">
        <div>Status: <strong>{{ labelStatus(documento.status) }}</strong></div>
        <div>Ambiente: <strong>{{ documento.ambiente_nome || documento.ambiente }}</strong></div>
        <div>Série: <strong>{{ documento.serie }}</strong></div>
        <div>Número: <strong>{{ documento.numero }}</strong></div>
      </div>

      <div v-if="documento.chave_acesso" class="mt-3 text-xs">
        Chave: <strong class="break-all">{{ documento.chave_acesso }}</strong>
      </div>

      <div v-if="documento.protocolo" class="mt-2 text-xs">
        Protocolo: <strong>{{ documento.protocolo }}</strong>
      </div>

      <div v-if="documento.informacoes_fisco" class="mt-3 error-box">
        {{ documento.informacoes_fisco }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast = useToast()
const route = useRoute()
const vendaId = ref(route.query.venda || '')
const validacao = ref(null)
const documento = ref(null)
const vendas = ref([])
const processando = ref(false)

const moeda = v => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v || 0))
const cliente = v => v.cliente?.nome_fantasia || v.cliente?.razao_social || 'Consumidor final'
const labelStatus = v => ({
  nao_emitida: 'Não emitida',
  preparada: 'Preparada',
  processando: 'Processando',
  rascunho: 'Preparada',
  autorizada: 'Autorizada',
  emitida: 'Autorizada',
  rejeitada: 'Rejeitada',
  cancelada: 'Cancelada',
}[v] || v || '—')

async function carregarVendas() {
  const { data } = await api.get('/pdv/vendas', { params: { per_page: 100 } })
  vendas.value = data.data || []
}

async function validar() {
  try {
    validacao.value = (await api.get(`/pdv/nfce/${vendaId.value}/validar`)).data
    documento.value = (await api.get(`/pdv/nfce/${vendaId.value}/documento`)).data.data
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha na validação.')
  }
}

async function preparar() {
  processando.value = true

  try {
    const { data } = await api.post(`/pdv/nfce/${vendaId.value}/gerar`)
    documento.value = data.documento
    toast.success(data.message)
    await validar()
  } catch (e) {
    validacao.value = e.response?.data?.validacoes || validacao.value
    toast.error(e.response?.data?.message || 'Falha ao preparar NFC-e.')
  } finally {
    processando.value = false
  }
}

async function emitir() {
  if (!confirm('Confirmar transmissão da NFC-e para a SEFAZ no ambiente configurado?')) return

  processando.value = true

  try {
    const { data } = await api.post(`/pdv/nfce/${vendaId.value}/emitir`)
    documento.value = data.documento
    toast.success(data.message)
    await carregarVendas()
  } catch (e) {
    validacao.value = e.response?.data?.validacoes || validacao.value
    toast.error(e.response?.data?.message || 'Falha na emissão da NFC-e.')
  } finally {
    processando.value = false
  }
}

function baixarDanfe() {
  const base = (api.defaults.baseURL || '').replace(/\/$/, '')
  window.open(`${base}/pdv/nfce/${vendaId.value}/pdf`, '_blank')
}

onMounted(async () => {
  await carregarVendas()
  if (vendaId.value) await validar()
})
</script>

<style scoped>
.ok{padding:12px;border:1px solid rgba(34,197,94,.35);border-radius:9px;background:rgba(34,197,94,.08);font-size:.75rem;color:#4ade80}
.config-box{display:flex;flex-direction:column;justify-content:center;border:1px solid var(--border);border-radius:9px;background:var(--bg-input);padding:10px}
.config-box span,.config-box small{font-size:.56rem;color:var(--text-muted)}
.config-box strong{font-size:.72rem;margin:2px 0}
.error-box{border:1px solid rgba(239,68,68,.35);border-radius:8px;background:rgba(239,68,68,.08);padding:10px;color:#f87171}
</style>
