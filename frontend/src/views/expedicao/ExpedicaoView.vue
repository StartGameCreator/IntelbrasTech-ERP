<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Expedição Inteligente</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">
          Picking, packing, volumes, etiquetas e romaneio.
        </p>
      </div>
      <button class="btn-primary" @click="abrirCriacao">Criar expedição</button>
    </div>

    <div class="summary-grid">
      <Summary label="Aguardando separação" :value="resumo.aguardando_separacao"/>
      <Summary label="Separando" :value="resumo.separando"/>
      <Summary label="Aguardando conferência" :value="resumo.aguardando_conferencia"/>
      <Summary label="Conferindo" :value="resumo.conferindo"/>
      <Summary label="Embalagem" :value="resumo.aguardando_embalagem"/>
      <Summary label="Prontas" :value="resumo.pronto_expedicao"/>
      <Summary label="Expedidas hoje" :value="resumo.expedidas_hoje"/>
    </div>

    <div class="card p-4">
      <div class="grid gap-3 md:grid-cols-4">
        <input v-model="filtros.busca" class="input md:col-span-2" placeholder="Código da expedição ou pedido"/>
        <select v-model="filtros.status" class="select">
          <option value="">Todos os status</option>
          <option value="aguardando_separacao">Aguardando separação</option>
          <option value="separando">Separando</option>
          <option value="aguardando_conferencia">Aguardando conferência</option>
          <option value="conferindo">Conferindo</option>
          <option value="aguardando_embalagem">Aguardando embalagem</option>
          <option value="pronto_expedicao">Pronto para expedição</option>
          <option value="expedida">Expedida</option>
        </select>
        <button class="btn-secondary" @click="carregar(1)">Pesquisar</button>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div v-if="loading" class="p-10 text-center text-sm text-[var(--text-muted)]">Carregando...</div>
      <div v-else-if="!expedicoes.length" class="p-10 text-center text-sm text-[var(--text-muted)]">Nenhuma expedição encontrada.</div>
      <div v-else class="overflow-auto">
        <table class="table">
          <thead>
            <tr>
              <th>Expedição</th>
              <th>Pedido</th>
              <th>Canal</th>
              <th>Status</th>
              <th>Picking</th>
              <th>Packing</th>
              <th>Volumes</th>
              <th class="text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in expedicoes" :key="item.id">
              <td><strong>{{ item.codigo }}</strong></td>
              <td>#{{ item.pedido_numero }}</td>
              <td>{{ item.canal || 'ERP' }}</td>
              <td><span :class="['badge', badge(item.status)]">{{ labelStatus(item.status) }}</span></td>
              <td>{{ item.itens_separados }}/{{ item.total_itens }}</td>
              <td>{{ item.itens_conferidos }}/{{ item.total_itens }}</td>
              <td>{{ item.total_volumes }}</td>
              <td class="text-right">
                <button class="btn-secondary btn-sm" @click="abrir(item.id)">Abrir</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="modalCriar" class="modal-backdrop">
      <div class="card modal-small">
        <div class="flex justify-between">
          <h2 class="font-bold">Criar expedição</h2>
          <button @click="modalCriar=false">×</button>
        </div>
        <label class="label mt-4">ID do pedido</label>
        <input v-model="pedidoId" class="input" placeholder="Ex.: 125"/>
        <button class="btn-primary w-full mt-4" @click="criar">Criar</button>
      </div>
    </div>

    <div v-if="modalDetalhe" class="modal-backdrop">
      <div class="card modal-large">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-base font-bold">{{ detalhe.expedicao.codigo }}</h2>
            <p class="text-xs text-[var(--text-muted)]">Pedido #{{ detalhe.expedicao.pedido_numero }}</p>
          </div>
          <button class="text-xl" @click="modalDetalhe=false">×</button>
        </div>

        <div class="workflow mt-4">
          <button class="btn-secondary" @click="acao('iniciar-separacao')">Iniciar picking</button>
          <button class="btn-secondary" @click="acao('concluir-separacao')">Concluir picking</button>
          <button class="btn-secondary" @click="acao('iniciar-conferencia')">Iniciar packing</button>
          <button class="btn-secondary" @click="acao('concluir-conferencia')">Concluir packing</button>
          <button class="btn-primary" @click="abrirVolume">Criar volume</button>
          <button class="btn-primary" @click="acao('expedir')">Expedir</button>
          <a :href="romaneioUrl" target="_blank" class="btn-secondary">Romaneio</a>
          <button class="btn-secondary" @click="abrirEtiquetas">Etiquetas</button>
        </div>

        <div class="mt-5">
          <h3 class="text-sm font-semibold">Itens</h3>
          <div class="mt-2 space-y-2">
            <div v-for="item in detalhe.itens" :key="item.id" class="item-row">
              <div class="grow">
                <strong>{{ item.descricao }}</strong>
                <span>{{ item.codigo || 'Sem código' }} · Qtd. {{ numero(item.quantidade) }}</span>
              </div>
              <input v-model.number="item.qtd_operacao" type="number" min="0" :max="item.quantidade" class="input qty"/>
              <button class="btn-secondary btn-sm" @click="separar(item)">Separar</button>
              <button class="btn-secondary btn-sm" @click="conferir(item)">Conferir</button>
            </div>
          </div>
        </div>

        <div class="mt-5">
          <h3 class="text-sm font-semibold">Volumes</h3>
          <div class="mt-2 volumes">
            <div v-for="volume in detalhe.volumes" :key="volume.id" class="volume-card">
              <strong>Volume {{ volume.numero }}</strong>
              <code>{{ volume.codigo_barras }}</code>
              <small>{{ volume.peso || '—' }} kg</small>
            </div>
          </div>
        </div>

        <div class="mt-5">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Etiquetas de envio</h3>
            <button class="btn-secondary btn-sm" @click="abrirEtiquetas">Gerenciar etiquetas</button>
          </div>
          <div v-if="etiquetas.length" class="mt-2 volumes">
            <div v-for="etiqueta in etiquetas" :key="etiqueta.id" class="volume-card">
              <strong>{{ String(etiqueta.marketplace || etiqueta.origem).toUpperCase() }}</strong>
              <small>{{ etiqueta.formato.toUpperCase() }} · {{ etiqueta.tamanho_papel }}</small>
              <small>{{ etiqueta.codigo_rastreio || 'Sem rastreio' }}</small>
              <small>Impressões: {{ etiqueta.quantidade_impressoes }}</small>
              <div class="flex gap-1 mt-2">
                <button class="btn-secondary btn-sm" @click="baixarEtiqueta(etiqueta)">Abrir</button>
                <button class="btn-secondary btn-sm" @click="registrarImpressao(etiqueta)">Imprimi</button>
              </div>
            </div>
          </div>
          <p v-else class="text-xs text-[var(--text-muted)] mt-2">Nenhuma etiqueta vinculada.</p>
        </div>
      </div>
    </div>

    <div v-if="modalEtiquetas" class="modal-backdrop">
      <div class="card modal-small">
        <div class="flex justify-between">
          <div>
            <h2 class="font-bold">Etiquetas de envio</h2>
            <p class="text-xs text-[var(--text-muted)]">PDF/ZPL oficial ou etiqueta própria 10 × 15 cm.</p>
          </div>
          <button @click="modalEtiquetas=false">×</button>
        </div>

        <div class="mt-4 grid gap-3">
          <label class="label">Marketplace/canal</label>
          <input v-model="etiquetaForm.marketplace" class="input" placeholder="Mercado Livre, Shopee, Nuvemshop...">
          <label class="label">Transportadora</label>
          <input v-model="etiquetaForm.transportadora" class="input" placeholder="Correios, Jadlog...">
          <label class="label">Serviço</label>
          <input v-model="etiquetaForm.servico" class="input" placeholder="PAC, SEDEX...">
          <label class="label">Código de rastreamento</label>
          <input v-model="etiquetaForm.codigo_rastreio" class="input" placeholder="Código de rastreio">
          <label class="label">Etiqueta oficial do marketplace</label>
          <input ref="arquivoEtiqueta" type="file" accept=".pdf,.png,.zpl,.txt" class="input" @change="selecionarArquivo">
        </div>

        <div class="grid grid-cols-2 gap-2 mt-4">
          <button class="btn-secondary" @click="enviarEtiqueta" :disabled="enviandoEtiqueta">Enviar oficial</button>
          <button class="btn-primary" @click="gerarEtiqueta" :disabled="enviandoEtiqueta">Gerar 10 × 15</button>
        </div>
        <p class="text-xs text-[var(--text-muted)] mt-3">Arquivos oficiais são armazenados sem redimensionamento ou alteração.</p>
      </div>
    </div>

    <div v-if="modalVolume" class="modal-backdrop">
      <div class="card modal-small">
        <div class="flex justify-between">
          <h2 class="font-bold">Novo volume</h2>
          <button @click="modalVolume=false">×</button>
        </div>
        <div class="grid gap-3 mt-4 grid-cols-2">
          <input v-model.number="volume.peso" type="number" class="input" placeholder="Peso kg"/>
          <input v-model.number="volume.altura" type="number" class="input" placeholder="Altura cm"/>
          <input v-model.number="volume.largura" type="number" class="input" placeholder="Largura cm"/>
          <input v-model.number="volume.comprimento" type="number" class="input" placeholder="Comprimento cm"/>
        </div>
        <textarea v-model="volume.observacoes" class="input mt-3" placeholder="Observações"></textarea>
        <button class="btn-primary w-full mt-4" @click="criarVolume">Salvar volume</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, defineComponent, h, computed, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast = useToast()
const loading = ref(false)
const expedicoes = ref([])
const resumo = ref({})
const filtros = ref({ busca:'', status:'' })
const modalCriar = ref(false)
const modalDetalhe = ref(false)
const modalVolume = ref(false)
const modalEtiquetas = ref(false)
const etiquetas = ref([])
const arquivoSelecionado = ref(null)
const enviandoEtiqueta = ref(false)
const etiquetaForm = ref({ marketplace:'', transportadora:'', servico:'', codigo_rastreio:'' })
const pedidoId = ref('')
const detalhe = ref({ expedicao:{}, itens:[], volumes:[], eventos:[] })
const volume = ref({ peso:null, altura:null, largura:null, comprimento:null, observacoes:'' })

const Summary = defineComponent({
  props:{label:String,value:[String,Number]},
  setup(props){ return()=>h('div',{class:'summary-card'},[h('span',props.label),h('strong',props.value??0)]) }
})

const romaneioUrl = computed(() => {
  const base = String(api.defaults.baseURL || '').replace(/\/$/, '')
  return `${base}/expedicao/${detalhe.value.expedicao.id}/romaneio`
})

const numero=v=>new Intl.NumberFormat('pt-BR',{maximumFractionDigits:3}).format(Number(v||0))

function labelStatus(v){
  return {
    aguardando_separacao:'Aguardando separação',
    separando:'Separando',
    aguardando_conferencia:'Aguardando conferência',
    conferindo:'Conferindo',
    aguardando_embalagem:'Aguardando embalagem',
    pronto_expedicao:'Pronto para expedição',
    expedida:'Expedida'
  }[v]||v
}
function badge(v){
  if(v==='expedida') return 'badge-success'
  if(v==='pronto_expedicao') return 'badge-success'
  if(v==='separando'||v==='conferindo') return 'badge-warning'
  return 'badge-gray'
}

async function carregar(){
  loading.value=true
  try{
    const dash=(await api.get('/expedicao/dashboard')).data
    resumo.value=dash.resumo||{}
    const {data}=await api.get('/expedicao',{params:filtros.value})
    expedicoes.value=data.data||[]
  }catch(e){toast.error(e.response?.data?.message||'Falha ao carregar expedição.')}
  finally{loading.value=false}
}

function abrirCriacao(){modalCriar.value=true}

async function criar(){
  try{
    const {data}=await api.post('/expedicao',{pedido_id:pedidoId.value})
    toast.success(data.message)
    modalCriar.value=false
    pedidoId.value=''
    await carregar()
    await abrir(data.expedicao.id)
  }catch(e){toast.error(e.response?.data?.message||'Falha ao criar expedição.')}
}

async function abrir(id){
  const {data}=await api.get(`/expedicao/${id}`)
  data.itens=(data.itens||[]).map(i=>({...i,qtd_operacao:Number(i.quantidade)}))
  detalhe.value=data
  modalDetalhe.value=true
  await carregarEtiquetas(id)
}

async function acao(nome){
  try{
    const {data}=await api.post(`/expedicao/${detalhe.value.expedicao.id}/${nome}`)
    toast.success(data.message)
    await abrir(detalhe.value.expedicao.id)
    await carregar()
  }catch(e){toast.error(e.response?.data?.message||'Operação não concluída.')}
}

async function separar(item){
  try{
    const {data}=await api.post(`/expedicao/${detalhe.value.expedicao.id}/itens/${item.id}/separar`,{quantidade:item.qtd_operacao})
    toast.success(data.message)
    await abrir(detalhe.value.expedicao.id)
  }catch(e){toast.error(e.response?.data?.message||'Falha ao separar item.')}
}

async function conferir(item){
  try{
    const {data}=await api.post(`/expedicao/${detalhe.value.expedicao.id}/itens/${item.id}/conferir`,{quantidade:item.qtd_operacao})
    toast.success(data.message)
    await abrir(detalhe.value.expedicao.id)
  }catch(e){toast.error(e.response?.data?.message||'Falha ao conferir item.')}
}

function abrirVolume(){modalVolume.value=true}

async function criarVolume(){
  try{
    const {data}=await api.post(`/expedicao/${detalhe.value.expedicao.id}/volumes`,volume.value)
    toast.success(data.message)
    modalVolume.value=false
    volume.value={peso:null,altura:null,largura:null,comprimento:null,observacoes:''}
    await abrir(detalhe.value.expedicao.id)
    await carregar()
  }catch(e){toast.error(e.response?.data?.message||'Falha ao criar volume.')}
}


async function carregarEtiquetas(expedicaoId){
  try{
    const {data}=await api.get('/expedicao/etiquetas',{params:{expedicao_id:expedicaoId,per_page:100}})
    etiquetas.value=data.data||[]
  }catch(e){etiquetas.value=[]}
}

function abrirEtiquetas(){
  etiquetaForm.value.marketplace=detalhe.value.expedicao.canal||''
  modalEtiquetas.value=true
}

function selecionarArquivo(event){
  arquivoSelecionado.value=event.target.files?.[0]||null
}

async function enviarEtiqueta(){
  if(!arquivoSelecionado.value){toast.error('Selecione o arquivo oficial da etiqueta.');return}
  enviandoEtiqueta.value=true
  try{
    const formData=new FormData()
    formData.append('arquivo',arquivoSelecionado.value)
    Object.entries(etiquetaForm.value).forEach(([k,v])=>{if(v)formData.append(k,v)})
    const {data}=await api.post(`/expedicao/${detalhe.value.expedicao.id}/etiquetas/upload`,formData,{headers:{'Content-Type':'multipart/form-data'}})
    toast.success(data.message)
    arquivoSelecionado.value=null
    modalEtiquetas.value=false
    await carregarEtiquetas(detalhe.value.expedicao.id)
  }catch(e){toast.error(e.response?.data?.message||'Falha ao armazenar etiqueta.')}
  finally{enviandoEtiqueta.value=false}
}

async function gerarEtiqueta(){
  enviandoEtiqueta.value=true
  try{
    const {data}=await api.post(`/expedicao/${detalhe.value.expedicao.id}/etiquetas/gerar`,etiquetaForm.value)
    toast.success(data.message)
    modalEtiquetas.value=false
    await carregarEtiquetas(detalhe.value.expedicao.id)
  }catch(e){toast.error(e.response?.data?.message||'Falha ao gerar etiqueta.')}
  finally{enviandoEtiqueta.value=false}
}

async function baixarEtiqueta(etiqueta){
  try{
    const response=await api.get(`/expedicao/etiquetas/${etiqueta.id}/download`,{responseType:'blob'})
    const blob=new Blob([response.data],{type:etiqueta.mime_type||'application/octet-stream'})
    const url=URL.createObjectURL(blob)
    const a=document.createElement('a')
    a.href=url
    a.download=etiqueta.nome_original||`etiqueta-${etiqueta.id}.${etiqueta.formato}`
    document.body.appendChild(a);a.click();a.remove()
    setTimeout(()=>URL.revokeObjectURL(url),1000)
  }catch(e){toast.error('Não foi possível baixar a etiqueta.')}
}

async function registrarImpressao(etiqueta){
  try{
    const {data}=await api.post(`/expedicao/etiquetas/${etiqueta.id}/imprimir`,{copias:1,modo:'navegador'})
    toast.success(data.message)
    await carregarEtiquetas(detalhe.value.expedicao.id)
  }catch(e){toast.error(e.response?.data?.message||'Falha ao registrar impressão.')}
}


onMounted(carregar)
</script>

<style scoped>
.summary-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:9px}
.summary-card{border:1px solid var(--border);border-radius:10px;background:var(--bg-card);padding:12px}
.summary-card span{display:block;font-size:.62rem;color:var(--text-muted)}
.summary-card strong{display:block;margin-top:5px;font-size:1.35rem}
.modal-backdrop{position:fixed;inset:0;z-index:150;background:rgba(0,0,0,.72);display:flex;align-items:center;justify-content:center;padding:16px}
.modal-small{width:min(480px,100%);padding:18px}
.modal-large{width:min(1100px,100%);max-height:94vh;overflow:auto;padding:18px}
.workflow{display:flex;gap:7px;flex-wrap:wrap}
.item-row{display:flex;align-items:center;gap:8px;border:1px solid var(--border);border-radius:9px;padding:10px}
.item-row strong{display:block;font-size:.74rem}.item-row span{display:block;font-size:.64rem;color:var(--text-muted);margin-top:2px}.qty{width:90px}
.volumes{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:9px}
.volume-card{border:1px solid var(--border);border-radius:9px;padding:11px}.volume-card strong,.volume-card code,.volume-card small{display:block}.volume-card code{margin-top:5px;font-size:.7rem}.volume-card small{margin-top:4px;color:var(--text-muted)}
@media(max-width:1100px){.summary-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:700px){.summary-grid{grid-template-columns:1fr 1fr}.item-row{align-items:flex-start;flex-wrap:wrap}}
</style>
