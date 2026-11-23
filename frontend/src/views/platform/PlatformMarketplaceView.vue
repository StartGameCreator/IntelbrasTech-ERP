<template>
  <div class="platform-admin">
    <header class="page-header">
      <div>
        <div class="crumb">IntelbrasTech Platform <span>/</span> Arquitetura Modular</div>
        <h1 class="page-title">Marketplace Nacional de Serviços</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">Gestão dos três pilares de cadastro da plataforma.</p>
      </div>
      <div class="flex gap-2">
        <RouterLink to="/cadastre-se" target="_blank" class="btn-secondary">Visualizar cadastro</RouterLink>
        <button class="btn-primary" @click="carregar">Atualizar</button>
      </div>
    </header>

    <section class="cards">
      <article><strong>{{ resumo.total||0 }}</strong><span>Total</span></article>
      <article><strong>{{ resumo.tecnicos||0 }}</strong><span>Técnicos</span></article>
      <article><strong>{{ resumo.clientes||0 }}</strong><span>Clientes</span></article>
      <article><strong>{{ resumo.colaboradores||0 }}</strong><span>Colaboradores</span></article>
      <article class="attention"><strong>{{ resumo.pendentes||0 }}</strong><span>Pendentes</span></article>
    </section>

    <section class="card p-4">
      <div class="filters results-toolbar">
        <input v-model="busca" class="input" placeholder="Pesquisar nome, e-mail, cidade...">
        <select v-model="tipo" class="select"><option value="">Todos os pilares</option><option value="tecnico">Técnicos</option><option value="cliente">Clientes</option><option value="colaborador">Colaboradores</option></select>
        <select v-model="status" class="select"><option value="">Todos os status</option><option value="pendente">Pendente</option><option value="aprovado">Aprovado</option><option value="rejeitado">Rejeitado</option><option value="suspenso">Suspenso</option></select>
      </div>
      <div class="results-scroll">
        <table class="table">
          <thead><tr><th>Perfil</th><th>Nome</th><th>Contato</th><th>Localização</th><th>Solicitado em</th><th>Status</th><th>Ações</th></tr></thead>
          <tbody>
            <tr v-for="item in filtrados" :key="item.id">
              <td><span :class="['profile-tag',item.tipo_perfil]">{{ labelPerfil(item.tipo_perfil) }}</span></td>
              <td><strong>{{ item.nome }}</strong><small>{{ item.empresa||item.cargo||'' }}</small></td>
              <td>{{ item.email }}<small>{{ item.whatsapp||item.telefone||'' }}</small></td>
              <td>{{ item.cidade||'—' }} / {{ item.estado||'—' }}</td>
              <td>{{ data(item.created_at) }}</td>
              <td><span :class="['status-tag',item.status]">{{ item.status }}</span></td>
              <td><select v-model="item.status" class="select select-sm" @change="analisar(item)"><option value="pendente">Pendente</option><option value="aprovado">Aprovado</option><option value="rejeitado">Rejeitado</option><option value="suspenso">Suspenso</option></select></td>
            </tr>
            <tr v-if="!filtrados.length"><td colspan="7" class="text-center py-10 text-[var(--text-muted)]">Nenhum cadastro encontrado.</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="architecture">
      <h2>Fundação modular criada</h2>
      <div class="architecture-grid">
        <article v-for="modulo in modulos" :key="modulo.nome"><component :is="modulo.icon"/><strong>{{ modulo.nome }}</strong><span>{{ modulo.status }}</span></article>
      </div>
    </section>
  </div>
</template>

<script setup>
import {computed,onMounted,ref,markRaw} from 'vue'
import {RouterLink} from 'vue-router'
import {useToast} from 'vue-toastification'
import api from '@/services/api'
import {TicketIcon,MapIcon,StarIcon,ChatBubbleLeftRightIcon,DocumentTextIcon,VideoCameraIcon,BellAlertIcon,DevicePhoneMobileIcon} from '@heroicons/vue/24/outline'
const toast=useToast(),resumo=ref({}),cadastros=ref([]),busca=ref(''),tipo=ref(''),status=ref('')
const modulos=[
 {nome:'Sistema de Chamados',status:'Próxima fase',icon:markRaw(TicketIcon)},
 {nome:'Geolocalização',status:'Fundação preparada',icon:markRaw(MapIcon)},
 {nome:'Avaliações',status:'Fundação preparada',icon:markRaw(StarIcon)},
 {nome:'Rede Social',status:'Planejado',icon:markRaw(ChatBubbleLeftRightIcon)},
 {nome:'RAT Digital',status:'Planejado',icon:markRaw(DocumentTextIcon)},
 {nome:'Videoconferência',status:'Planejado',icon:markRaw(VideoCameraIcon)},
 {nome:'Notificações Push',status:'Planejado',icon:markRaw(BellAlertIcon)},
 {nome:'PWA',status:'Planejado',icon:markRaw(DevicePhoneMobileIcon)},
]
const filtrados=computed(()=>cadastros.value.filter(i=>{
 const q=busca.value.toLowerCase().trim()
 return (!tipo.value||i.tipo_perfil===tipo.value)&&(!status.value||i.status===status.value)&&(!q||`${i.nome} ${i.email} ${i.cidade} ${i.estado} ${i.empresa}`.toLowerCase().includes(q))
}))
const labelPerfil=p=>({tecnico:'Técnico',cliente:'Cliente',colaborador:'Colaborador'}[p]||p)
const data=v=>v?new Intl.DateTimeFormat('pt-BR',{dateStyle:'short',timeStyle:'short'}).format(new Date(v)):'—'
async function carregar(){try{const {data}=await api.get('/platform/cadastros');resumo.value=data.resumo||{};cadastros.value=data.cadastros||[]}catch(e){toast.error(e.response?.data?.message||'Falha ao carregar cadastros.')}}
async function analisar(item){try{await api.patch(`/platform/cadastros/${item.id}/analisar`,{status:item.status});toast.success('Status atualizado.');await carregar()}catch(e){toast.error(e.response?.data?.message||'Falha ao analisar cadastro.')}}
onMounted(carregar)
</script>

<style scoped>
.platform-admin{display:flex;flex-direction:column;gap:14px}.crumb{font-size:.6rem;color:var(--text-muted)}.crumb span{margin:0 6px}.cards{display:grid;grid-template-columns:repeat(5,1fr);gap:9px}.cards article{border:1px solid var(--border);background:var(--bg-card);border-radius:9px;padding:14px}.cards strong,.cards span{display:block}.cards strong{font-size:1.2rem}.cards span{font-size:.55rem;color:var(--text-muted)}.cards .attention{border-color:rgba(245,158,11,.4)}.filters{display:grid;grid-template-columns:1fr 210px 180px;gap:8px;margin-bottom:12px}.table td strong,.table td small{display:block}.table td small{font-size:.52rem;color:var(--text-muted);margin-top:2px}.profile-tag,.status-tag{border-radius:999px;padding:3px 7px;font-size:.52rem}.profile-tag.tecnico{background:rgba(59,130,246,.12);color:#60a5fa}.profile-tag.cliente{background:rgba(34,197,94,.12);color:#4ade80}.profile-tag.colaborador{background:rgba(168,85,247,.12);color:#c084fc}.status-tag.pendente{background:rgba(245,158,11,.12);color:#fbbf24}.status-tag.aprovado{background:rgba(34,197,94,.12);color:#4ade80}.status-tag.rejeitado,.status-tag.suspenso{background:rgba(239,68,68,.12);color:#f87171}.select-sm{min-width:110px}.architecture{border:1px solid var(--border);border-radius:10px;background:var(--bg-card);padding:15px}.architecture h2{font-size:.75rem;font-weight:800}.architecture-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:12px}.architecture-grid article{border:1px solid var(--border);border-radius:8px;padding:12px}.architecture-grid svg{width:23px;color:#60a5fa}.architecture-grid strong,.architecture-grid span{display:block}.architecture-grid strong{font-size:.62rem;margin-top:8px}.architecture-grid span{font-size:.52rem;color:var(--text-muted);margin-top:3px}@media(max-width:900px){.cards{grid-template-columns:repeat(2,1fr)}.architecture-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:650px){.filters,.cards,.architecture-grid{grid-template-columns:1fr}}
</style>
