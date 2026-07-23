<template>
  <div class="mt-page">
    <header class="page-header">
      <div>
        <div class="crumb"><WrenchScrewdriverIcon class="w-4 h-4"/><span>Serviços</span><ChevronRightIcon class="w-3 h-3"/><strong>Marketplace de Técnicos</strong></div>
        <h1 class="page-title">Marketplace de Técnicos</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">Profissionais, clientes e chamados técnicos em uma única área do ERP.</p>
      </div>
      <div class="flex gap-2">
        <RouterLink to="/cadastre-se" class="btn-secondary"><UserPlusIcon class="w-4 h-4"/>Cadastre-se</RouterLink>
        <button class="btn-primary" @click="carregar"><ArrowPathIcon class="w-4 h-4" :class="carregando&&'animate-spin'"/>Atualizar</button>
      </div>
    </header>

    <section class="summary-grid">
      <article><UsersIcon/><div><span>Técnicos</span><strong>{{ resumo.tecnicos||0 }}</strong></div></article>
      <article><ShieldCheckIcon/><div><span>Aprovados</span><strong>{{ resumo.aprovados||0 }}</strong></div></article>
      <article><SignalIcon/><div><span>Online</span><strong>{{ resumo.online||0 }}</strong></div></article>
      <article><BuildingOffice2Icon/><div><span>Clientes</span><strong>{{ resumo.clientes||0 }}</strong></div></article>
      <article><TicketIcon/><div><span>Chamados abertos</span><strong>{{ resumo.chamados_abertos||0 }}</strong></div></article>
    </section>

    <nav class="tabs">
      <button :class="{active:aba==='tecnicos'}" @click="aba='tecnicos'">Técnicos</button>
      <button :class="{active:aba==='chamados'}" @click="aba='chamados'">Chamados</button>
      <button :class="{active:aba==='estrutura'}" @click="aba='estrutura'">Estrutura importada</button>
    </nav>

    <section v-if="aba==='tecnicos'" class="card p-4">
      <div class="toolbar">
        <div class="search"><MagnifyingGlassIcon class="w-4 h-4"/><input v-model="busca" placeholder="Nome, especialidade, cidade ou UF"/></div>
        <select v-model="status" class="select"><option value="">Todos os status</option><option>pendente</option><option>aprovado</option><option>suspenso</option><option>rejeitado</option></select>
      </div>
      <div class="tech-grid">
        <article v-for="t in tecnicosFiltrados" :key="t.id" class="tech-card">
          <div class="avatar">{{ iniciais(t.nome) }}</div>
          <div class="tech-main">
            <div class="flex items-center gap-2"><strong>{{ t.nome }}</strong><span :class="['status',t.status]">{{ t.status }}</span><span v-if="t.online" class="online">online</span></div>
            <span>{{ t.empresa || 'Profissional independente' }}</span>
            <p>{{ t.especialidades || 'Especialidades ainda não informadas' }}</p>
            <small>{{ t.cidade || 'Cidade não informada' }} / {{ t.estado || '--' }} · raio {{ t.raio_km||50 }} km</small>
          </div>
          <div class="rating"><StarIcon class="w-4 h-4"/><strong>{{ Number(t.media_avaliacao||0).toFixed(1) }}</strong><span>{{ t.total_atendimentos||0 }} atend.</span></div>
          <select v-model="t.status" class="select select-sm" @change="salvarStatus(t)"><option>pendente</option><option>aprovado</option><option>suspenso</option><option>rejeitado</option></select>
        </article>
        <div v-if="!tecnicosFiltrados.length" class="empty">Nenhum técnico cadastrado.</div>
      </div>
    </section>

    <section v-else-if="aba==='chamados'" class="card p-4">
      <div class="overflow-auto">
        <table class="table">
          <thead><tr><th>Número</th><th>Título</th><th>Cliente</th><th>Técnico</th><th>Local</th><th>Prioridade</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="c in chamados" :key="c.id"><td>{{ c.numero }}</td><td>{{ c.titulo }}</td><td>{{ c.cliente_nome }}</td><td>{{ c.tecnico_nome||'Não distribuído' }}</td><td>{{ c.cidade }}/{{ c.estado }}</td><td>{{ c.prioridade }}</td><td>{{ c.status }}</td></tr>
            <tr v-if="!chamados.length"><td colspan="7" class="text-center text-[var(--text-muted)] py-8">Nenhum chamado importado nesta primeira fase.</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-else class="card p-5">
      <h2 class="font-semibold">Estrutura preservada para evolução gradual</h2>
      <p class="text-xs text-[var(--text-muted)] mt-2">O login isolado do projeto original foi descartado. O módulo agora utiliza o acesso atual do ERP e mantém a área pública de cadastro.</p>
      <div class="structure-grid">
        <article v-for="item in estrutura" :key="item.nome"><component :is="item.icon" class="w-6 h-6"/><strong>{{ item.nome }}</strong><span>{{ item.status }}</span></article>
      </div>
    </section>
  </div>
</template>

<script setup>
import {ref,computed,onMounted,markRaw} from 'vue'
import {RouterLink} from 'vue-router'
import {useToast} from 'vue-toastification'
import api from '@/services/api'
import {WrenchScrewdriverIcon,ChevronRightIcon,UserPlusIcon,ArrowPathIcon,UsersIcon,ShieldCheckIcon,SignalIcon,BuildingOffice2Icon,TicketIcon,MagnifyingGlassIcon,StarIcon,ChatBubbleLeftRightIcon,DocumentTextIcon,MapIcon,VideoCameraIcon,CurrencyDollarIcon} from '@heroicons/vue/24/outline'

const toast=useToast(),carregando=ref(false),aba=ref('tecnicos'),busca=ref(''),status=ref('')
const resumo=ref({}),tecnicos=ref([]),chamados=ref([])
const estrutura=[
 {nome:'Cadastro de técnicos e clientes',status:'Integrado',icon:markRaw(UsersIcon)},
 {nome:'Aprovação de profissionais',status:'Integrado',icon:markRaw(ShieldCheckIcon)},
 {nome:'Chamados técnicos',status:'Base implantada',icon:markRaw(TicketIcon)},
 {nome:'Chat por chamado',status:'Próxima etapa',icon:markRaw(ChatBubbleLeftRightIcon)},
 {nome:'RAT e assinatura',status:'Próxima etapa',icon:markRaw(DocumentTextIcon)},
 {nome:'Mapa e proximidade',status:'Próxima etapa',icon:markRaw(MapIcon)},
 {nome:'Reuniões Meet/Teams',status:'Próxima etapa',icon:markRaw(VideoCameraIcon)},
 {nome:'Financeiro do técnico',status:'Próxima etapa',icon:markRaw(CurrencyDollarIcon)},
]
const tecnicosFiltrados=computed(()=>tecnicos.value.filter(t=>{
 const q=busca.value.toLowerCase().trim()
 return (!status.value||t.status===status.value)&&(!q||`${t.nome} ${t.empresa} ${t.especialidades} ${t.cidade} ${t.estado}`.toLowerCase().includes(q))
}))
const iniciais=n=>String(n||'T').split(/\s+/).slice(0,2).map(x=>x[0]).join('').toUpperCase()
async function carregar(){carregando.value=true;try{const {data}=await api.get('/marketplace-tecnicos');resumo.value=data.resumo||{};tecnicos.value=data.tecnicos||[];chamados.value=data.chamados||[]}catch(e){toast.error(e.response?.data?.message||'Falha ao carregar o marketplace.')}finally{carregando.value=false}}
async function salvarStatus(t){try{await api.patch(`/marketplace-tecnicos/tecnicos/${t.id}/status`,{status:t.status});toast.success('Status atualizado.');await carregar()}catch(e){toast.error(e.response?.data?.message||'Falha ao atualizar status.')}}
onMounted(carregar)
</script>

<style scoped>
.mt-page{display:flex;flex-direction:column;gap:14px}.crumb{display:flex;gap:6px;align-items:center;font-size:.62rem;color:var(--text-muted)}.summary-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:9px}.summary-grid article{display:flex;gap:10px;align-items:center;border:1px solid var(--border);border-radius:9px;background:var(--bg-card);padding:13px}.summary-grid svg{width:24px;height:24px;color:#60a5fa}.summary-grid span,.summary-grid strong{display:block}.summary-grid span{font-size:.55rem;color:var(--text-muted)}.summary-grid strong{font-size:1rem}.tabs{display:flex;gap:18px;border-bottom:1px solid var(--border)}.tabs button{padding:8px 2px;border-bottom:2px solid transparent;font-size:.65rem}.tabs button.active{border-color:#3b82f6;color:#60a5fa}.toolbar{display:flex;gap:8px;margin-bottom:12px}.search{flex:1;display:flex;align-items:center;gap:7px;border:1px solid var(--border);border-radius:7px;padding:0 9px}.search input{height:34px;flex:1;background:transparent;outline:none;font-size:.64rem}.tech-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:9px}.tech-card{display:grid;grid-template-columns:auto 1fr auto;gap:10px;border:1px solid var(--border);border-radius:9px;padding:12px}.avatar{width:42px;height:42px;border-radius:11px;display:grid;place-items:center;background:rgba(59,130,246,.15);color:#60a5fa;font-weight:800}.tech-main strong{font-size:.68rem}.tech-main>span,.tech-main p,.tech-main small{display:block}.tech-main>span{font-size:.55rem;color:var(--text-muted)}.tech-main p{font-size:.58rem;margin-top:5px}.tech-main small{font-size:.52rem;color:var(--text-muted);margin-top:5px}.status,.online{border-radius:999px;padding:2px 5px;font-size:.48rem}.status.aprovado{background:rgba(34,197,94,.12);color:#4ade80}.status.pendente{background:rgba(245,158,11,.12);color:#fbbf24}.status.suspenso,.status.rejeitado{background:rgba(239,68,68,.12);color:#f87171}.online{background:rgba(34,197,94,.12);color:#4ade80}.rating{text-align:right}.rating svg{display:inline;color:#fbbf24}.rating strong,.rating span{display:block;font-size:.58rem}.rating span{color:var(--text-muted);font-size:.48rem}.select-sm{grid-column:2/4}.empty{grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted);font-size:.65rem}.structure-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin-top:16px}.structure-grid article{border:1px solid var(--border);border-radius:9px;padding:13px}.structure-grid strong,.structure-grid span{display:block}.structure-grid strong{font-size:.63rem;margin-top:8px}.structure-grid span{font-size:.52rem;color:var(--text-muted);margin-top:3px}@media(max-width:1000px){.summary-grid{grid-template-columns:repeat(2,1fr)}.tech-grid{grid-template-columns:1fr}.structure-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:650px){.summary-grid,.structure-grid{grid-template-columns:1fr}}
</style>
