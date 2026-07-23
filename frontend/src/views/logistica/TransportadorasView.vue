<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Central de Transportadoras</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">
          Cotação, contas, envios, etiquetas e rastreamento.
        </p>
      </div>
      <RouterLink to="/expedicao" class="btn-secondary">Expedição</RouterLink>
    </div>

    <div class="summary-grid">
      <Summary label="Transportadoras" :value="resumo.transportadoras"/>
      <Summary label="Contas conectadas" :value="resumo.contas_conectadas"/>
      <Summary label="Aguardando postagem" :value="resumo.aguardando_postagem"/>
      <Summary label="Postados" :value="resumo.postados"/>
      <Summary label="Entregues" :value="resumo.entregues"/>
    </div>

    <div class="tabs">
      <button :class="{active:tab==='cotacao'}" @click="tab='cotacao'">Cotação</button>
      <button :class="{active:tab==='contas'}" @click="tab='contas'; carregarContas()">Contas</button>
      <button :class="{active:tab==='envios'}" @click="tab='envios'; carregarEnvios()">Envios</button>
    </div>

    <section v-if="tab==='cotacao'" class="card p-5">
      <h2 class="text-sm font-semibold">Cotação simultânea</h2>
      <div class="mt-4 grid gap-3 md:grid-cols-4">
        <input v-model="cotacao.cep_origem" class="input" placeholder="CEP origem"/>
        <input v-model="cotacao.cep_destino" class="input" placeholder="CEP destino"/>
        <input v-model.number="cotacao.peso" type="number" step="0.001" class="input" placeholder="Peso kg"/>
        <input v-model.number="cotacao.valor_declarado" type="number" step="0.01" class="input" placeholder="Valor declarado"/>
        <input v-model.number="cotacao.altura" type="number" class="input" placeholder="Altura cm"/>
        <input v-model.number="cotacao.largura" type="number" class="input" placeholder="Largura cm"/>
        <input v-model.number="cotacao.comprimento" type="number" class="input" placeholder="Comprimento cm"/>
        <button class="btn-primary" @click="cotar">Calcular frete</button>
      </div>

      <div v-if="cotacoes.length" class="quotes mt-5">
        <article v-for="q in cotacoes" :key="q.transportadora_id" class="quote-card">
          <div>
            <strong>{{ q.transportadora }}</strong>
            <span>{{ q.servico }}</span>
          </div>
          <div class="text-right">
            <strong>{{ moeda(q.valor) }}</strong>
            <span>{{ q.prazo_dias }} dia(s)</span>
          </div>
          <button class="btn-secondary btn-sm" @click="selecionarCotacao(q)">Criar envio</button>
        </article>
      </div>
    </section>

    <section v-if="tab==='contas'" class="connectors-grid">
      <article v-for="c in contas" :key="c.id" class="card connector-card">
        <div class="flex items-center justify-between">
          <div class="logo">{{ iniciais(c.nome) }}</div>
          <span :class="['badge', c.status==='configurado'||c.status==='conectado'?'badge-success':'badge-gray']">
            {{ c.status || 'desconectado' }}
          </span>
        </div>
        <h3>{{ c.nome }}</h3>
        <p>{{ labelTipo(c.tipo) }}</p>
        <button class="btn-primary btn-sm mt-4" @click="abrirConta(c)">Configurar</button>
      </article>
    </section>

    <section v-if="tab==='envios'" class="card overflow-hidden">
      <div v-if="!envios.length" class="empty">Nenhum envio criado.</div>
      <div v-else class="overflow-auto">
        <table class="table">
          <thead><tr><th>Pedido</th><th>Transportadora</th><th>Serviço</th><th>Rastreio</th><th>Status</th><th class="text-right">Frete</th><th class="text-right">Ações</th></tr></thead>
          <tbody>
            <tr v-for="e in envios" :key="e.id">
              <td>{{ e.pedido_numero || `#${e.pedido_id || '—'}` }}</td>
              <td>{{ e.transportadora_nome }}</td>
              <td>{{ e.servico || '—' }}</td>
              <td>{{ e.codigo_rastreio || '—' }}</td>
              <td><span :class="['badge', e.status==='postado'?'badge-success':'badge-warning']">{{ e.status }}</span></td>
              <td class="text-right">{{ moeda(e.valor_frete) }}</td>
              <td class="text-right"><button class="btn-secondary btn-sm" @click="abrirPostagem(e)">Postar</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="modalConta" class="modal-backdrop">
      <div class="card modal-card small">
        <div class="modal-title"><h2>{{ contaAtual.nome }}</h2><button @click="modalConta=false">×</button></div>
        <input v-model="contaForm.nome_conta" class="input w-full mt-4" placeholder="Nome da conta"/>
        <input v-model="contaForm.token" class="input w-full mt-3" placeholder="Token / API Key"/>
        <input v-model="contaForm.secret" class="input w-full mt-3" placeholder="Secret"/>
        <button class="btn-primary w-full mt-4" @click="salvarConta">Salvar configuração</button>
      </div>
    </div>

    <div v-if="modalEnvio" class="modal-backdrop">
      <div class="card modal-card small">
        <div class="modal-title"><h2>Novo envio</h2><button @click="modalEnvio=false">×</button></div>
        <input v-model="envioForm.pedido_id" class="input w-full mt-4" placeholder="ID do pedido"/>
        <input v-model="envioForm.expedicao_id" class="input w-full mt-3" placeholder="ID da expedição"/>
        <input v-model="envioForm.servico" class="input w-full mt-3" placeholder="Serviço"/>
        <button class="btn-primary w-full mt-4" @click="criarEnvio">Criar envio</button>
      </div>
    </div>

    <div v-if="modalPostagem" class="modal-backdrop">
      <div class="card modal-card small">
        <div class="modal-title"><h2>Registrar postagem</h2><button @click="modalPostagem=false">×</button></div>
        <input v-model="postagem.codigo_rastreio" class="input w-full mt-4" placeholder="Código de rastreio"/>
        <input v-model="postagem.local" class="input w-full mt-3" placeholder="Local de postagem"/>
        <button class="btn-primary w-full mt-4" @click="postar">Confirmar postagem</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import {ref,defineComponent,h,onMounted} from 'vue'
import {RouterLink} from 'vue-router'
import {useToast} from 'vue-toastification'
import api from '@/services/api'

const toast=useToast(),tab=ref('cotacao')
const resumo=ref({transportadoras:0,contas_conectadas:0,aguardando_postagem:0,postados:0,entregues:0})
const contas=ref([]),envios=ref([]),cotacoes=ref([])
const modalConta=ref(false),modalEnvio=ref(false),modalPostagem=ref(false)
const contaAtual=ref({}),envioAtual=ref({})
const contaForm=ref({nome_conta:'',token:'',secret:''})
const envioForm=ref({pedido_id:'',expedicao_id:'',transportadora_id:null,servico:'',valor_frete:0,prazo_dias:null})
const postagem=ref({codigo_rastreio:'',local:''})
const cotacao=ref({cep_origem:'',cep_destino:'',peso:1,altura:10,largura:10,comprimento:10,valor_declarado:0})

const Summary=defineComponent({props:{label:String,value:[String,Number]},setup:p=>()=>h('div',{class:'summary-card'},[h('span',p.label),h('strong',p.value??0)])})
const moeda=v=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(v||0))
const iniciais=n=>String(n||'').split(/\s+/).slice(0,2).map(x=>x[0]).join('').toUpperCase()
const labelTipo=t=>({nacional:'Operador nacional',hub:'Hub logístico',transportadora:'Transportadora',entrega_rapida:'Entrega rápida'})[t]||t

async function carregar(){const {data}=await api.get('/logistica/overview');resumo.value=data.resumo}
async function carregarContas(){const {data}=await api.get('/logistica/contas');contas.value=data.data||[]}
async function carregarEnvios(){const {data}=await api.get('/logistica/envios');envios.value=data.data||[]}
async function cotar(){try{const {data}=await api.post('/logistica/cotar',cotacao.value);cotacoes.value=data.data||[];toast.success('Cotação concluída.')}catch(e){toast.error(e.response?.data?.message||'Falha na cotação.')}}
function abrirConta(c){contaAtual.value=c;contaForm.value={nome_conta:c.nome_conta||'',token:'',secret:''};modalConta.value=true}
async function salvarConta(){try{toast.success((await api.post(`/logistica/contas/${contaAtual.value.id}`,contaForm.value)).data.message);modalConta.value=false;await carregarContas();await carregar()}catch(e){toast.error(e.response?.data?.message||'Falha ao salvar.')}}
function selecionarCotacao(q){envioForm.value={pedido_id:'',expedicao_id:'',transportadora_id:q.transportadora_id,servico:q.servico,valor_frete:q.valor,prazo_dias:q.prazo_dias};modalEnvio.value=true}
async function criarEnvio(){try{toast.success((await api.post('/logistica/envios',envioForm.value)).data.message);modalEnvio.value=false;tab.value='envios';await carregarEnvios();await carregar()}catch(e){toast.error(e.response?.data?.message||'Falha ao criar envio.')}}
function abrirPostagem(e){envioAtual.value=e;postagem.value={codigo_rastreio:e.codigo_rastreio||'',local:''};modalPostagem.value=true}
async function postar(){try{toast.success((await api.post(`/logistica/envios/${envioAtual.value.id}/postar`,postagem.value)).data.message);modalPostagem.value=false;await carregarEnvios();await carregar()}catch(e){toast.error(e.response?.data?.message||'Falha ao postar.')}}

onMounted(async()=>{await carregar();await carregarContas()})
</script>

<style scoped>
.summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.summary-card{border:1px solid var(--border);border-radius:10px;background:var(--bg-card);padding:14px}.summary-card span{display:block;font-size:.65rem;color:var(--text-muted)}.summary-card strong{display:block;margin-top:5px;font-size:1.5rem}
.tabs{display:flex;gap:6px}.tabs button{border:1px solid var(--border);border-radius:999px;padding:7px 12px;font-size:.7rem}.tabs button.active{background:var(--primary);color:white;border-color:var(--primary)}
.quotes{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px}.quote-card{border:1px solid var(--border);border-radius:10px;padding:12px;display:grid;grid-template-columns:1fr auto;gap:10px}.quote-card span{display:block;font-size:.64rem;color:var(--text-muted)}.quote-card button{grid-column:1/-1}
.connectors-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px}.connector-card{padding:14px}.connector-card h3{margin-top:12px;font-size:.82rem;font-weight:800}.connector-card p{font-size:.66rem;color:var(--text-muted)}.logo{width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,var(--primary),#7c3aed);display:flex;align-items:center;justify-content:center;color:white;font-weight:900}
.empty{padding:40px;text-align:center;color:var(--text-muted);font-size:.72rem}.modal-backdrop{position:fixed;inset:0;z-index:140;background:rgba(0,0,0,.72);display:flex;align-items:center;justify-content:center;padding:16px}.modal-card{width:min(650px,100%);padding:18px}.modal-card.small{width:min(520px,100%)}.modal-title{display:flex;justify-content:space-between}.modal-title h2{font-weight:800}.modal-title button{font-size:1.4rem}
@media(max-width:900px){.summary-grid{grid-template-columns:repeat(2,1fr)}}
</style>
