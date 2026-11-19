<template>
  <div class="contador-page">
    <header class="page-header">
      <div>
        <div class="contador-breadcrumb">
          <DocumentTextIcon class="w-4 h-4"/>
          <span>Fiscal</span>
          <ChevronRightIcon class="w-3 h-3"/>
          <strong>Contador</strong>
        </div>
        <h1 class="page-title">Espaço do Contador</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">
          Centralize arquivos fiscais, dados do escritório e envios automáticos.
        </p>
      </div>

      <div class="header-actions">
        <div class="search-box">
          <MagnifyingGlassIcon class="w-4 h-4"/>
          <input v-model="busca" placeholder="Pesquisar arquivos e relatórios..."/>
        </div>
        <button class="btn-secondary" @click="carregar">
          <ArrowPathIcon class="w-4 h-4" :class="carregando && 'animate-spin'"/>
          Atualizar
        </button>
      </div>
    </header>

    <div class="contador-layout">
      <aside class="contador-sidebar card">
        <button :class="{active:aba==='arquivos'}" @click="aba='arquivos'">
          <FolderOpenIcon class="w-4 h-4"/>
          <span>Arquivos</span>
          <ChevronRightIcon class="w-3 h-3 ml-auto"/>
        </button>
        <button :class="{active:aba==='dados'}" @click="aba='dados'">
          <UserCircleIcon class="w-4 h-4"/>
          <span>Dados do contador</span>
          <ChevronRightIcon class="w-3 h-3 ml-auto"/>
        </button>
        <button :class="{active:aba==='agendamento'}" @click="aba='agendamento'">
          <CalendarDaysIcon class="w-4 h-4"/>
          <span>Data e agendamento</span>
          <ChevronRightIcon class="w-3 h-3 ml-auto"/>
        </button>
        <button :class="{active:aba==='historico'}" @click="aba='historico'">
          <ClockIcon class="w-4 h-4"/>
          <span>Histórico</span>
          <ChevronRightIcon class="w-3 h-3 ml-auto"/>
        </button>
      </aside>

      <main class="contador-content">
        <section v-if="aba==='arquivos'" class="card content-card">
          <header class="section-header">
            <div>
              <h2>Arquivos</h2>
              <p>Gere pacotes fiscais e relatórios para o escritório contábil.</p>
            </div>
            <div class="period-filter">
              <input v-model="periodo.inicio" type="date" class="input"/>
              <span>até</span>
              <input v-model="periodo.fim" type="date" class="input"/>
            </div>
          </header>

          <div v-for="grupo in gruposArquivos" :key="grupo.titulo" class="file-group">
            <h3>{{ grupo.titulo }}</h3>
            <div class="file-grid">
              <button
                v-for="item in grupo.items.filter(filtroArquivo)"
                :key="item.id"
                type="button"
                class="file-option"
                :disabled="gerando===item.id"
                @click="gerar(item)"
              >
                <component :is="item.icon" class="w-5 h-5"/>
                <span>{{ item.label }}</span>
                <ArrowPathIcon v-if="gerando===item.id" class="w-4 h-4 animate-spin ml-auto"/>
                <ArrowDownTrayIcon v-else class="w-4 h-4 ml-auto"/>
              </button>
            </div>
          </div>

          <div class="save-row">
            <button class="btn-primary" @click="aba='agendamento'">Configurar envio automático</button>
            <RouterLink to="/fiscal/nfe" class="btn-secondary">Voltar ao Fiscal</RouterLink>
          </div>
        </section>

        <section v-else-if="aba==='dados'" class="card content-card">
          <header class="section-header">
            <div>
              <h2>Dados do contador</h2>
              <p>Cadastre o profissional ou escritório responsável.</p>
            </div>
          </header>

          <div class="form-grid">
            <label><span>Nome do contador</span><input v-model="dados.nome_contador" class="input" placeholder="Nome completo"/></label>
            <label><span>CPF / CNPJ</span><input v-model="dados.cpf_cnpj" class="input" placeholder="000.000.000-00"/></label>
            <label><span>CRC</span><input v-model="dados.crc" class="input" placeholder="UF-000000/O-0"/></label>
            <label><span>Escritório contábil</span><input v-model="dados.escritorio" class="input" placeholder="Nome do escritório"/></label>
            <label><span>E-mail</span><input v-model="dados.email" class="input" type="email" placeholder="contador@email.com"/></label>
            <label><span>Telefone</span><input v-model="dados.telefone" class="input" placeholder="(00) 00000-0000"/></label>
            <label class="wide"><span>Responsável pelo atendimento</span><input v-model="dados.responsavel" class="input" placeholder="Nome do contato"/></label>
          </div>

          <label class="switch-row">
            <button type="button" :class="['switch',dados.possui_escritorio&&'on']" @click="dados.possui_escritorio=!dados.possui_escritorio"><i></i></button>
            <div><strong>O contador possui escritório contábil</strong><span>Ative quando o atendimento for realizado por uma empresa contábil.</span></div>
          </label>

          <div class="save-row">
            <button class="btn-primary" :disabled="salvando" @click="salvarDados">
              <CheckIcon class="w-4 h-4"/>Salvar
            </button>
            <button class="btn-secondary" @click="aba='arquivos'">Voltar</button>
          </div>
        </section>

        <section v-else-if="aba==='agendamento'" class="card content-card">
          <header class="section-header">
            <div>
              <h2>Data e agendamento</h2>
              <p>Defina a frequência dos pacotes enviados ao contador.</p>
            </div>
          </header>

          <div class="form-grid schedule-grid">
            <label>
              <span>Frequência</span>
              <select v-model="agenda.frequencia" class="select">
                <option value="semanal">Semanal</option>
                <option value="quinzenal">Quinzenal</option>
                <option value="mensal">Mensal</option>
              </select>
            </label>
            <label><span>Dia</span><input v-model.number="agenda.dia_envio" type="number" min="1" max="31" class="input"/></label>
            <label><span>Horário</span><input v-model="agenda.hora_envio" type="time" class="input"/></label>
          </div>

          <label class="switch-row">
            <button type="button" :class="['switch',agenda.envio_automatico&&'on']" @click="agenda.envio_automatico=!agenda.envio_automatico"><i></i></button>
            <div><strong>Enviar arquivos automaticamente</strong><span>O ERP preparará e enviará os arquivos conforme a agenda.</span></div>
          </label>

          <div class="schedule-types">
            <h3>Arquivos incluídos no envio</h3>
            <div class="checkbox-grid">
              <label v-for="tipo in tipos" :key="tipo.id">
                <input v-model="agenda.tipos_arquivo" type="checkbox" :value="tipo.id"/>
                <span>{{ tipo.label }}</span>
              </label>
            </div>
          </div>

          <div class="save-row">
            <button class="btn-primary" :disabled="salvando" @click="salvarAgendamento">
              <CheckIcon class="w-4 h-4"/>Salvar
            </button>
            <button class="btn-secondary" @click="aba='arquivos'">Voltar</button>
          </div>
        </section>

        <section v-else class="card content-card">
          <header class="section-header">
            <div>
              <h2>Histórico de arquivos</h2>
              <p>Pacotes já processados para o contador.</p>
            </div>
          </header>

          <div v-if="!historico.length" class="empty-state">
            <ClockIcon class="w-9 h-9"/>
            <strong>Nenhum arquivo gerado</strong>
            <span>Use a aba Arquivos para criar o primeiro pacote.</span>
          </div>

          <div v-else class="history-list">
            <article v-for="item in historico" :key="item.id">
              <div class="history-icon"><ArchiveBoxIcon class="w-5 h-5"/></div>
              <div class="history-copy">
                <strong>{{ labelTipo(item.tipo) }}</strong>
                <span>{{ item.nome_arquivo }}</span>
                <small>{{ dataHora(item.gerado_em || item.created_at) }} · {{ tamanho(item.tamanho_bytes) }}</small>
              </div>
              <span :class="['status-pill',item.status==='processado'?'success':'warning']">{{ item.status }}</span>
              <button class="btn-secondary btn-sm" @click="baixar(item)"><ArrowDownTrayIcon class="w-4 h-4"/>Baixar</button>
              <button class="icon-danger" @click="excluir(item)"><TrashIcon class="w-4 h-4"/></button>
            </article>
          </div>
        </section>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, markRaw } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import {
  DocumentTextIcon, ChevronRightIcon, MagnifyingGlassIcon, ArrowPathIcon,
  FolderOpenIcon, UserCircleIcon, CalendarDaysIcon, ClockIcon,
  ArrowDownTrayIcon, CheckIcon, ArchiveBoxIcon, TrashIcon,
  TableCellsIcon, BanknotesIcon, ReceiptPercentIcon, DocumentArrowDownIcon
} from '@heroicons/vue/24/outline'

const toast=useToast()
const aba=ref('arquivos'),busca=ref(''),carregando=ref(false),salvando=ref(false),gerando=ref(null)
const tipos=ref([]),historico=ref([])
const hoje=new Date(),primeiro=new Date(hoje.getFullYear(),hoje.getMonth(),1)
const iso=d=>d.toISOString().slice(0,10)
const periodo=ref({inicio:iso(primeiro),fim:iso(hoje)})
const dados=ref({nome_contador:'',cpf_cnpj:'',crc:'',escritorio:'',email:'',telefone:'',responsavel:'',possui_escritorio:false})
const agenda=ref({frequencia:'mensal',dia_envio:10,hora_envio:'08:00',envio_automatico:false,tipos_arquivo:[]})

const gruposArquivos=[
  {titulo:'XML',items:[
    {id:'xml-nfce-saida',label:'NFC-e de saída',icon:markRaw(DocumentTextIcon)},
    {id:'xml-nfe-saida',label:'NF-e de saída',icon:markRaw(DocumentTextIcon)},
    {id:'xml-nfe-entrada',label:'NF-e de entrada',icon:markRaw(DocumentTextIcon)},
  ]},
  {titulo:'Relatórios',items:[
    {id:'estoque',label:'Estoque',icon:markRaw(TableCellsIcon)},
    {id:'contas-pagar',label:'Contas a pagar',icon:markRaw(BanknotesIcon)},
    {id:'contas-receber',label:'Contas a receber',icon:markRaw(BanknotesIcon)},
    {id:'pagamentos',label:'Pagamentos',icon:markRaw(ReceiptPercentIcon)},
    {id:'recebimentos',label:'Recebimentos',icon:markRaw(ReceiptPercentIcon)},
    {id:'nfse',label:'NFS-e',icon:markRaw(DocumentTextIcon)},
    {id:'nfe-entrada',label:'NF-e Entrada',icon:markRaw(DocumentTextIcon)},
    {id:'nfe-saida',label:'NF-e Saída',icon:markRaw(DocumentTextIcon)},
    {id:'nfce',label:'NFC-e',icon:markRaw(DocumentTextIcon)},
  ]},
  {titulo:'Outros',items:[
    {id:'sped-fiscal',label:'Arquivo auxiliar do SPED Fiscal',icon:markRaw(DocumentArrowDownIcon)},
    {id:'sintegra',label:'Arquivo Sintegra',icon:markRaw(DocumentArrowDownIcon)},
  ]},
]

const tipoMap=computed(()=>Object.fromEntries(tipos.value.map(t=>[t.id,t.label])))
const filtroArquivo=item=>!busca.value||item.label.toLowerCase().includes(busca.value.toLowerCase())
const labelTipo=t=>tipoMap.value[t]||t
const dataHora=v=>v?new Intl.DateTimeFormat('pt-BR',{dateStyle:'short',timeStyle:'short'}).format(new Date(v)):'—'
const tamanho=v=>{const n=Number(v||0);return n<1024?`${n} B`:n<1048576?`${(n/1024).toFixed(1)} KB`:`${(n/1048576).toFixed(1)} MB`}

async function carregar(){
  carregando.value=true
  try{
    const {data}=await api.get('/contador')
    tipos.value=data.tipos||[]
    historico.value=data.historico||[]
    const c=data.configuracao||{}
    Object.assign(dados.value,{
      nome_contador:c.nome_contador||'',cpf_cnpj:c.cpf_cnpj||'',crc:c.crc||'',
      escritorio:c.escritorio||'',email:c.email||'',telefone:c.telefone||'',
      responsavel:c.responsavel||'',possui_escritorio:Boolean(c.possui_escritorio),
    })
    Object.assign(agenda.value,{
      frequencia:c.frequencia||'mensal',dia_envio:c.dia_envio||10,
      hora_envio:c.hora_envio||'08:00',envio_automatico:Boolean(c.envio_automatico),
      tipos_arquivo:parseJson(c.tipos_arquivo,[]),
    })
  }catch(e){toast.error(e.response?.data?.message||'Falha ao carregar o Espaço do Contador.')}
  finally{carregando.value=false}
}
function parseJson(value,fallback){if(Array.isArray(value))return value;try{return value?JSON.parse(value):fallback}catch{return fallback}}
async function salvarDados(){salvando.value=true;try{toast.success((await api.post('/contador/dados',dados.value)).data.message)}catch(e){toast.error(e.response?.data?.message||'Falha ao salvar.')}finally{salvando.value=false}}
async function salvarAgendamento(){salvando.value=true;try{toast.success((await api.post('/contador/agendamento',agenda.value)).data.message)}catch(e){toast.error(e.response?.data?.message||'Falha ao salvar.')}finally{salvando.value=false}}
async function gerar(item){
  gerando.value=item.id
  try{
    const {data}=await api.post('/contador/exportacoes',{tipo:item.id,inicio:periodo.value.inicio,fim:periodo.value.fim})
    toast.success(data.message)
    await carregar()
    aba.value='historico'
  }catch(e){toast.error(e.response?.data?.message||'Falha ao gerar arquivo.')}
  finally{gerando.value=null}
}
function baixar(item){
  const base=(api.defaults.baseURL||'').replace(/\/$/,'')
  window.open(`${base}/contador/exportacoes/${item.id}/download`,'_blank')
}
async function excluir(item){
  if(!confirm(`Excluir ${item.nome_arquivo}?`))return
  try{toast.success((await api.delete(`/contador/exportacoes/${item.id}`)).data.message);await carregar()}catch(e){toast.error(e.response?.data?.message||'Falha ao excluir.')}
}
onMounted(carregar)
</script>

<style scoped>
.contador-page{display:flex;flex-direction:column;gap:15px}.contador-breadcrumb{display:flex;align-items:center;gap:6px;font-size:.61rem;color:var(--text-muted);margin-bottom:4px}.header-actions{display:flex;gap:8px}.search-box{width:300px;display:flex;align-items:center;gap:7px;border:1px solid var(--border);border-radius:8px;background:var(--bg-input);padding:0 9px}.search-box input{height:35px;flex:1;background:transparent;outline:none;font-size:.66rem}.contador-layout{display:grid;grid-template-columns:255px 1fr;gap:14px}.contador-sidebar{padding:7px;height:max-content}.contador-sidebar button{width:100%;display:flex;align-items:center;gap:8px;border-left:3px solid transparent;border-radius:6px;padding:10px;text-align:left;font-size:.66rem;color:var(--text-secondary)}.contador-sidebar button:hover{background:var(--bg-elevated)}.contador-sidebar button.active{border-left-color:#22c55e;background:rgba(34,197,94,.09);color:#4ade80}.content-card{padding:18px}.section-header{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;border-bottom:1px solid var(--border);padding-bottom:12px}.section-header h2{font-size:.86rem;font-weight:800}.section-header p{font-size:.61rem;color:var(--text-muted);margin-top:3px}.period-filter{display:flex;align-items:center;gap:6px}.period-filter span{font-size:.58rem;color:var(--text-muted)}.file-group{margin-top:18px}.file-group h3{font-size:.66rem;font-weight:800;margin-bottom:8px}.file-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.file-option{display:flex;align-items:center;gap:9px;border:1px solid var(--border);border-radius:8px;background:var(--bg-surface);padding:13px;font-size:.63rem;font-weight:700;text-align:left}.file-option:hover{border-color:#22c55e;background:var(--bg-elevated)}.file-option:disabled{opacity:.65}.save-row{display:flex;gap:9px;margin-top:22px}.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:18px}.form-grid label>span{display:block;font-size:.59rem;color:var(--text-muted);margin-bottom:5px}.form-grid .wide{grid-column:span 2}.schedule-grid{grid-template-columns:repeat(3,minmax(0,200px))}.switch-row{display:flex;align-items:center;gap:10px;margin-top:18px}.switch{width:34px;height:19px;border-radius:999px;background:#475569;padding:2px;transition:.15s}.switch i{display:block;width:15px;height:15px;border-radius:50%;background:white;transition:.15s}.switch.on{background:#22c55e}.switch.on i{transform:translateX(15px)}.switch-row strong,.switch-row span{display:block}.switch-row strong{font-size:.63rem}.switch-row span{font-size:.54rem;color:var(--text-muted);margin-top:2px}.schedule-types{margin-top:20px}.schedule-types h3{font-size:.67rem;font-weight:800}.checkbox-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:9px}.checkbox-grid label{display:flex;align-items:center;gap:7px;border:1px solid var(--border);border-radius:7px;padding:9px;font-size:.58rem}.empty-state{display:flex;flex-direction:column;align-items:center;gap:7px;padding:55px;color:var(--text-muted)}.empty-state strong{font-size:.7rem}.empty-state span{font-size:.59rem}.history-list{display:flex;flex-direction:column;gap:8px;margin-top:14px}.history-list article{display:flex;align-items:center;gap:10px;border:1px solid var(--border);border-radius:8px;padding:10px}.history-icon{width:35px;height:35px;border-radius:8px;background:var(--bg-elevated);display:grid;place-items:center}.history-copy{flex:1}.history-copy strong,.history-copy span,.history-copy small{display:block}.history-copy strong{font-size:.63rem}.history-copy span{font-size:.56rem;color:var(--text-secondary);margin-top:2px}.history-copy small{font-size:.51rem;color:var(--text-muted);margin-top:2px}.status-pill{border-radius:999px;padding:3px 7px;font-size:.52rem}.status-pill.success{background:rgba(34,197,94,.12);color:#4ade80}.status-pill.warning{background:rgba(245,158,11,.12);color:#fbbf24}.icon-danger{width:31px;height:31px;border-radius:7px;display:grid;place-items:center;color:#f87171}.icon-danger:hover{background:rgba(239,68,68,.1)}@media(max-width:1000px){.contador-layout{grid-template-columns:1fr}.contador-sidebar{display:grid;grid-template-columns:repeat(4,1fr)}.file-grid,.form-grid,.checkbox-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:650px){.page-header,.header-actions{flex-direction:column}.search-box{width:100%}.contador-sidebar{grid-template-columns:1fr}.file-grid,.form-grid,.checkbox-grid{grid-template-columns:1fr}.form-grid .wide{grid-column:auto}.period-filter{flex-wrap:wrap}}
</style>
