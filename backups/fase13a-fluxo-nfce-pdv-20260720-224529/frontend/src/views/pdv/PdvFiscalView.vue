<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">NFC-e do PDV</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">Validação fiscal e XML de rascunho.</p>
      </div>
      <RouterLink to="/pdv/vendas" class="btn-secondary">Voltar</RouterLink>
    </div>

    <div class="card p-5 space-y-4">
      <div class="grid gap-3 md:grid-cols-3">
        <div><label class="label">ID da venda</label><input v-model="vendaId" class="input"/></div>
        <div><label class="label">Ambiente</label><select v-model="ambiente" class="select"><option value="homologacao">Homologação</option><option value="producao">Produção</option></select></div>
        <div><label class="label">Série</label><input v-model.number="serie" type="number" min="1" max="999" class="input"/></div>
      </div>
      <div class="flex gap-2">
        <button class="btn-secondary" :disabled="!vendaId" @click="validar">Validar</button>
        <button class="btn-primary" :disabled="!validacao?.valido" @click="gerar">Gerar XML</button>
      </div>
    </div>

    <div v-if="validacao" class="card p-5">
      <div v-if="validacao.valido" class="ok">Venda apta para gerar o XML de rascunho.</div>
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
      <h2 class="text-sm font-semibold">Documento</h2>
      <div class="mt-3 grid gap-3 md:grid-cols-4 text-xs">
        <div>Status: <strong>{{ documento.status }}</strong></div>
        <div>Ambiente: <strong>{{ documento.ambiente }}</strong></div>
        <div>Série: <strong>{{ documento.serie }}</strong></div>
        <div>Número: <strong>{{ documento.numero }}</strong></div>
      </div>
    </div>

    <div v-if="xml" class="card p-5">
      <div class="flex justify-between"><div><h2 class="text-sm font-semibold">XML de rascunho</h2><p class="text-xs text-warning">Não assinado e não transmitido.</p></div><button class="btn-secondary btn-sm" @click="copiar">Copiar</button></div>
      <pre class="xml">{{ xml }}</pre>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast=useToast(), vendaId=ref(''), ambiente=ref('homologacao'), serie=ref(1)
const validacao=ref(null), documento=ref(null), xml=ref('')

async function validar(){
  try{
    validacao.value=(await api.get(`/pdv/nfce/${vendaId.value}/validar`)).data
    documento.value=(await api.get(`/pdv/nfce/${vendaId.value}/documento`)).data.data
  }catch(e){toast.error(e.response?.data?.message||'Falha na validação.')}
}
async function gerar(){
  try{
    const {data}=await api.post(`/pdv/nfce/${vendaId.value}/gerar`,{ambiente:ambiente.value,serie:serie.value})
    documento.value=data.documento;xml.value=data.xml;toast.success(data.message)
  }catch(e){validacao.value=e.response?.data?.validacoes||validacao.value;toast.error(e.response?.data?.message||'Falha ao gerar XML.')}
}
async function copiar(){await navigator.clipboard.writeText(xml.value);toast.success('XML copiado.')}
</script>

<style scoped>
.ok{padding:12px;border:1px solid rgba(34,197,94,.35);border-radius:9px;background:rgba(34,197,94,.08);font-size:.75rem;color:#4ade80}
.xml{margin-top:14px;max-height:520px;overflow:auto;border:1px solid var(--border);border-radius:9px;background:var(--bg-input);padding:14px;white-space:pre-wrap;word-break:break-word;font-size:.68rem}
</style>
