<template>
  <div class="space-y-5">
    <div class="page-header">
      <div><h1 class="page-title">Mercado Livre</h1><p class="mt-1 text-xs text-[var(--text-muted)]">Conexão OAuth da conta.</p></div>
      <RouterLink to="/marketplace-hub" class="btn-secondary">Voltar ao Hub</RouterLink>
    </div>

    <div v-if="loading" class="card p-10 text-center">Carregando...</div>
    <div v-else class="grid gap-4 xl:grid-cols-3">
      <section class="card p-5 xl:col-span-2">
        <div class="header">
          <div class="logo">ML</div>
          <div class="grow"><h2>Conta do Mercado Livre</h2><p>{{ connected ? 'Conta conectada' : 'Nenhuma conta conectada' }}</p></div>
          <span :class="['badge', connected?'badge-success':'badge-gray']">{{ connected?'Conectado':'Desconectado' }}</span>
        </div>

        <div v-if="!configured" class="warning mt-5">
          Preencha MERCADO_LIVRE_CLIENT_ID e MERCADO_LIVRE_CLIENT_SECRET no backend/.env.
        </div>

        <div v-if="account?.erro_conexao" class="error mt-5">{{ account.erro_conexao }}</div>

        <div v-if="connected" class="info-grid mt-5">
          <div><span>Conta</span><strong>{{ account.nickname || account.nome_conta }}</strong></div>
          <div><span>ID</span><strong>{{ account.external_user_id }}</strong></div>
          <div><span>Site</span><strong>{{ account.site_id }}</strong></div>
          <div><span>Token expira</span><strong>{{ dateTime(account.token_expires_at) }}</strong></div>
        </div>

        <div class="mt-5 flex gap-2 flex-wrap">
          <button v-if="!connected" class="btn-primary" :disabled="!configured || connecting" @click="connect">{{ connecting?'Abrindo...':'Conectar Mercado Livre' }}</button>
          <template v-else>
            <button class="btn-primary" @click="test">Testar conexão</button>
            <button class="btn-secondary" @click="refresh">Renovar token</button>
            <button class="btn-danger" @click="disconnect">Desconectar</button>
          </template>
        </div>
      </section>

      <aside class="card p-5">
        <h2 class="text-sm font-semibold">URL de retorno</h2>
        <p class="mt-2 text-xs text-[var(--text-muted)]">Cadastre exatamente esta URL no aplicativo:</p>
        <div class="copy mt-3"><code>{{ redirectUri }}</code><button @click="copy">Copiar</button></div>
      </aside>
    </div>
  </div>
</template>

<script setup>
import {ref,computed,onMounted} from 'vue'
import {RouterLink,useRoute,useRouter} from 'vue-router'
import {useToast} from 'vue-toastification'
import api from '@/services/api'

const toast=useToast(),route=useRoute(),router=useRouter()
const loading=ref(true),connecting=ref(false),configured=ref(false),account=ref(null),redirectUri=ref('')
const connected=computed(()=>account.value?.status==='conectado')
const dateTime=v=>v?new Date(v).toLocaleString('pt-BR'):'—'

async function load(){loading.value=true;try{const {data}=await api.get('/marketplace/mercado-livre/status');configured.value=data.configured;account.value=data.account;redirectUri.value=data.redirect_uri}finally{loading.value=false}}
async function connect(){connecting.value=true;try{const {data}=await api.post('/marketplace/mercado-livre/connect');location.href=data.authorization_url}catch(e){toast.error(e.response?.data?.message||'Falha ao conectar.');connecting.value=false}}
async function test(){try{toast.success((await api.post('/marketplace/mercado-livre/test')).data.message);await load()}catch(e){toast.error(e.response?.data?.message||'Falha no teste.')}}
async function refresh(){try{toast.success((await api.post('/marketplace/mercado-livre/refresh')).data.message);await load()}catch(e){toast.error(e.response?.data?.message||'Falha ao renovar.')}}
async function disconnect(){if(!confirm('Desconectar a conta?'))return;toast.success((await api.post('/marketplace/mercado-livre/disconnect')).data.message);await load()}
async function copy(){await navigator.clipboard.writeText(redirectUri.value);toast.success('URL copiada.')}
onMounted(async()=>{if(route.query.connected==='1')toast.success('Conta conectada.');if(route.query.error)toast.error(String(route.query.error));if(route.query.connected||route.query.error)await router.replace({query:{}});await load()})
</script>

<style scoped>
.header{display:flex;align-items:center;gap:14px}.header h2{font-size:.95rem;font-weight:800}.header p{font-size:.7rem;color:var(--text-muted)}
.logo{width:54px;height:54px;border-radius:14px;background:#ffe600;color:#222;display:flex;align-items:center;justify-content:center;font-weight:900}
.warning,.error{padding:12px;border-radius:9px;font-size:.72rem}.warning{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.35)}.error{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.35);color:#f87171}
.info-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.info-grid>div{border:1px solid var(--border);border-radius:9px;padding:11px}.info-grid span{display:block;font-size:.64rem;color:var(--text-muted)}.info-grid strong{display:block;margin-top:4px;font-size:.75rem}
.copy{border:1px solid var(--border);border-radius:9px;padding:10px;background:var(--bg-input)}.copy code{display:block;font-size:.65rem;word-break:break-all}.copy button{margin-top:8px;color:var(--primary);font-size:.68rem}
</style>
