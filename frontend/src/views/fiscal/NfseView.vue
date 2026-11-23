<template>
  <div class="nfse-page animate-fade-in">
    <div class="page-header">
      <div class="nfse-heading">
        <h1 class="page-title">PDV de Serviços</h1>
        <p class="text-xs text-[var(--text-muted)] mt-1">Prestação de serviços e emissão de Nota Fiscal de Serviço eletrônica.</p>
      </div>

      <div class="nfse-mode-switch" role="group" aria-label="Selecionar o tipo de operação do PDV">
        <RouterLink to="/pdv" @click="selecionarPdvProdutos">
          <span>Produtos<small>NF-e / NFC-e</small></span>
        </RouterLink>
        <span class="active" aria-current="page">
          <span>Serviços<small>NFS-e</small></span>
        </span>
      </div>
    </div>

    <div class="nfse-grid">
      <form class="card p-5 space-y-4" @submit.prevent="criarRascunho">
        <div class="nfse-note">
          <strong>Emissão de serviços</strong>
          <span>Este fluxo cria a DPS/NFS-e como rascunho. A transmissão só é liberada após a configuração e o credenciamento da NFS-e Nacional.</span>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <label><span class="label">Tomador do serviço *</span><input v-model.trim="form.destinatario_nome" class="input" required placeholder="Nome ou razão social"></label>
          <label><span class="label">CPF/CNPJ</span><input v-model.trim="form.destinatario_cpf_cnpj" class="input" placeholder="Opcional nesta etapa"></label>
          <label><span class="label">E-mail</span><input v-model.trim="form.destinatario_email" type="email" class="input" placeholder="Para envio futuro"></label>
          <label><span class="label">Código do serviço municipal</span><input v-model.trim="form.codigo_servico" class="input" placeholder="Ex.: 1.07"></label>
        </div>

        <label><span class="label">Descrição do serviço *</span><textarea v-model.trim="form.descricao_servico" class="input min-h-28" required placeholder="Descreva o serviço prestado"></textarea></label>
        <label><span class="label">Valor do serviço *</span><input v-model.number="form.valor" class="input" type="number" min="0.01" step="0.01" required></label>
        <label><span class="label">Observações</span><textarea v-model.trim="form.observacoes" class="input min-h-20" placeholder="Informações adicionais"></textarea></label>
        <button class="btn-primary" :disabled="salvando">{{ salvando ? 'Criando...' : 'Criar rascunho de NFS-e' }}</button>
      </form>

      <aside class="card p-5">
        <h2 class="text-sm font-semibold text-[var(--text-primary)]">Últimas NFS-e</h2>
        <p class="text-xs text-[var(--text-muted)] mt-1">Documentos de prestação de serviços preparados no ERP.</p>
        <div class="nfse-list">
          <div v-if="loading" class="text-xs text-[var(--text-muted)]">Carregando...</div>
          <div v-else-if="!notas.length" class="text-xs text-[var(--text-muted)]">Nenhum rascunho de NFS-e criado.</div>
          <article v-for="nota in notas" :key="nota.id">
            <div><strong>{{ nota.destinatario_nome }}</strong><span>{{ moeda(nota.valor_total) }} · {{ nota.status }}</span></div>
            <small>{{ formatarData(nota.created_at) }}</small>
          </article>
        </div>
        <RouterLink to="/configuracoes/apis" class="btn-secondary w-full justify-center mt-4">Configurar API NFS-e</RouterLink>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast = useToast()
const PDV_OPERATION_MODE_KEY = 'intelbrastech:pdv:operation-mode'
const loading = ref(false)
const salvando = ref(false)
const notas = ref([])
const form = ref({ destinatario_nome:'', destinatario_cpf_cnpj:'', destinatario_email:'', codigo_servico:'', descricao_servico:'', valor:null, observacoes:'' })

function salvarModoOperacao(modo){
  try { window.localStorage.setItem(PDV_OPERATION_MODE_KEY, modo) } catch {}
}

function selecionarPdvProdutos(){
  salvarModoOperacao('produtos')
}
const moeda = value => new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(value || 0))
const formatarData = value => value ? new Intl.DateTimeFormat('pt-BR',{dateStyle:'short',timeStyle:'short'}).format(new Date(value)) : '—'

async function carregar(){
  loading.value = true
  try { const { data } = await api.get('/nfse'); notas.value = data.data || [] }
  catch (error) { toast.error(error.response?.data?.message || 'Não foi possível carregar as NFS-e.') }
  finally { loading.value = false }
}
async function criarRascunho(){
  salvando.value = true
  try {
    const { data } = await api.post('/nfse', form.value)
    toast.success(data.message)
    form.value = { destinatario_nome:'', destinatario_cpf_cnpj:'', destinatario_email:'', codigo_servico:'', descricao_servico:'', valor:null, observacoes:'' }
    await carregar()
  } catch (error) { toast.error(error.response?.data?.message || 'Não foi possível criar a NFS-e.') }
  finally { salvando.value = false }
}
onMounted(()=>{
  salvarModoOperacao('servicos')
  carregar()
})
</script>

<style scoped>
.page-header{position:relative;display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:52px}.nfse-heading{min-width:0}.nfse-mode-switch{display:grid;grid-template-columns:repeat(2,minmax(140px,1fr));gap:4px;padding:4px;border:1px solid var(--border);border-radius:11px;background:var(--bg-elevated)}.nfse-mode-switch>a,.nfse-mode-switch>span{display:flex;align-items:center;justify-content:center;min-height:42px;padding:7px 13px;border:1px solid transparent;border-radius:8px;color:var(--text-secondary);transition:background-color .16s ease,border-color .16s ease,color .16s ease}.nfse-mode-switch>a:hover{border-color:var(--border);background:var(--bg-input);color:var(--text-primary)}.nfse-mode-switch .active{border-color:rgba(34,169,90,.48);background:#22a95a;color:#fff;box-shadow:0 5px 16px rgba(34,169,90,.2)}.nfse-mode-switch span span,.nfse-mode-switch>a span{display:flex;flex-direction:column;align-items:flex-start;font-size:.75rem;font-weight:850;line-height:1.05}.nfse-mode-switch small{margin-top:4px;font-size:.6rem;font-weight:700;opacity:.72}.nfse-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.42fr);gap:16px}.nfse-note{display:flex;flex-direction:column;gap:5px;padding:12px;border:1px solid rgba(34,169,90,.35);border-radius:9px;background:rgba(34,169,90,.08);font-size:.78rem}.nfse-note span{color:var(--text-muted);line-height:1.45}.nfse-list{display:flex;flex-direction:column;gap:8px;margin-top:16px}.nfse-list article{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)}.nfse-list strong,.nfse-list span{display:block}.nfse-list strong{font-size:.78rem}.nfse-list span,.nfse-list small{font-size:.68rem;color:var(--text-muted);margin-top:3px}@media(min-width:1001px){.nfse-mode-switch{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%)}}@media(max-width:800px){.page-header{align-items:stretch;flex-direction:column}.nfse-mode-switch{grid-template-columns:1fr 1fr;width:100%}.nfse-mode-switch>a,.nfse-mode-switch>span{min-height:46px}.nfse-grid{grid-template-columns:1fr}}
</style>
