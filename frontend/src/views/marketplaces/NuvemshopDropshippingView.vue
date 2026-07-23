<template>
  <div class="nuvemshop-page">
    <div class="page-header">
      <div>
        <h1 class="page-title">Nuvemshop e Dropshipping</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">Conecte a loja, sincronize catálogo e pedidos e distribua vendas entre fornecedores.</p>
      </div>
      <button class="btn-secondary" @click="carregar">Atualizar</button>
    </div>

    <div class="summary-grid">
      <div class="summary-card"><span>Conexão</span><strong>{ conectado ? 'Ativa' : 'Pendente' }</strong><small>{ conta?.nome_conta || 'Nenhuma loja conectada' }</small></div>
      <div class="summary-card"><span>Produtos dropship</span><strong>{ resumo.produtos_dropship || 0 }</strong><small>vinculados a fornecedores</small></div>
      <div class="summary-card"><span>Ordens pendentes</span><strong>{ resumo.ordens_pendentes || 0 }</strong><small>aguardando fornecedor</small></div>
      <div class="summary-card"><span>Custo pendente</span><strong>{ moeda(resumo.custo_pendente) }</strong><small>compromisso estimado</small></div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
      <section class="card p-5">
        <h2 class="section-title">1. Conexão da loja</h2>
        <p class="section-help">O aplicativo usa OAuth. A senha da sua Nuvemshop não é armazenada no ERP.</p>
        <div class="notice" v-if="!conectado">Cadastre as chaves no arquivo <code>backend/.env</code> antes de conectar.</div>
        <div class="actions">
          <button v-if="!conectado" class="btn-primary" :disabled="busy" @click="conectar">Conectar Nuvemshop</button>
          <button v-else class="btn-secondary" :disabled="busy" @click="testar">Testar conexão</button>
        </div>
      </section>

      <section class="card p-5">
        <h2 class="section-title">2. Sincronização</h2>
        <p class="section-help">Importa produtos, pedidos e gera ordens separadas por fornecedor.</p>
        <div class="actions">
          <button class="btn-secondary" :disabled="!conectado || busy" @click="sincronizarProdutos">Sincronizar produtos</button>
          <button class="btn-primary" :disabled="!conectado || busy" @click="sincronizarPedidos">Sincronizar pedidos</button>
        </div>
      </section>
    </div>

    <section class="card p-5">
      <div class="flex items-center justify-between gap-3">
        <div><h2 class="section-title">Ordens de fornecedores</h2><p class="section-help">Pedidos roteados automaticamente pelos vínculos de produto e fornecedor.</p></div>
        <span class="badge badge-gray">{ ordens.length } ordens</span>
      </div>
      <div class="table-wrap mt-4">
        <table>
          <thead><tr><th>Número</th><th>Fornecedor</th><th>Status</th><th>Custo</th><th>Rastreio</th></tr></thead>
          <tbody>
            <tr v-for="ordem in ordens" :key="ordem.id">
              <td>{ ordem.numero }</td><td>{ ordem.fornecedor_nome }</td><td><span class="badge badge-gray">{ ordem.status }</span></td><td>{ moeda(ordem.total_custo) }</td><td>{ ordem.codigo_rastreio || '—' }</td>
            </tr>
            <tr v-if="!ordens.length"><td colspan="5" class="empty">Nenhuma ordem de fornecedor gerada.</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="mensagem" :class="['toast', erro && 'error']">{ mensagem }</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route = useRoute(); const router = useRouter()
const busy = ref(false), conectado = ref(false), conta = ref(null), resumo = ref({}), ordens = ref([]), mensagem = ref(''), erro = ref(false)
function moeda(v){ return Number(v || 0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'}) }
function avisar(texto, falha=false){ mensagem.value=texto; erro.value=falha; setTimeout(()=>mensagem.value='',5000) }
async function carregar(){
  try {
    const [status,dropship] = await Promise.all([api.get('/nuvemshop/status'), api.get('/nuvemshop/dropshipping')])
    conectado.value=!!status.data.conectado; conta.value=status.data.account; resumo.value=dropship.data.resumo||{}; ordens.value=dropship.data.ordens||[]
  } catch(e){ avisar(e.response?.data?.message || 'Falha ao carregar módulo Nuvemshop.',true) }
}
async function conectar(){ busy.value=true; try { const {data}=await api.post('/nuvemshop/oauth/redirect'); window.location.href=data.url } catch(e){avisar(e.response?.data?.message||'Não foi possível iniciar o OAuth.',true)} finally{busy.value=false} }
async function concluirCallback(){
  if(!route.query.code || !route.query.state) return
  busy.value=true
  try { const {data}=await api.post('/nuvemshop/oauth/callback',{code:route.query.code,state:route.query.state}); avisar(data.message); await router.replace({name:'nuvemshop-dropshipping'}); await carregar() }
  catch(e){ avisar(e.response?.data?.message||'Falha ao concluir conexão.',true) } finally{busy.value=false}
}
async function acao(url){ busy.value=true; try{const {data}=await api.post(url); avisar(data.message||'Operação concluída.'); await carregar()}catch(e){avisar(e.response?.data?.message||'Operação falhou.',true)}finally{busy.value=false} }
const testar=()=>acao('/nuvemshop/testar'), sincronizarProdutos=()=>acao('/nuvemshop/sincronizar-produtos'), sincronizarPedidos=()=>acao('/nuvemshop/sincronizar-pedidos')
onMounted(async()=>{await concluirCallback(); await carregar()})
</script>

<style scoped>
.nuvemshop-page{display:flex;flex-direction:column;gap:16px}.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.summary-card{border:1px solid var(--border);border-radius:10px;background:var(--bg-card);padding:14px}.summary-card span,.summary-card small{display:block;font-size:.65rem;color:var(--text-muted)}.summary-card strong{display:block;font-size:1.25rem;color:var(--text-primary);margin:5px 0}.section-title{font-size:.85rem;font-weight:800;color:var(--text-primary)}.section-help{font-size:.7rem;color:var(--text-muted);margin-top:4px}.notice{margin-top:16px;border:1px solid rgba(245,158,11,.35);background:rgba(245,158,11,.08);border-radius:9px;padding:12px;font-size:.72rem;color:var(--text-secondary)}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:9px}table{width:100%;border-collapse:collapse;font-size:.72rem}th,td{text-align:left;padding:10px 12px;border-bottom:1px solid var(--border);white-space:nowrap}th{font-size:.64rem;text-transform:uppercase;color:var(--text-muted)}td{color:var(--text-secondary)}.empty{text-align:center;padding:30px;color:var(--text-muted)}.toast{position:fixed;right:18px;bottom:18px;z-index:130;padding:12px 16px;border-radius:9px;background:#065f46;color:#fff;font-size:.75rem;box-shadow:0 10px 30px rgba(0,0,0,.3)}.toast.error{background:#991b1b}@media(max-width:900px){.summary-grid{grid-template-columns:repeat(2,1fr)}}
</style>
