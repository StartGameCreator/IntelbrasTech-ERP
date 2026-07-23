<template>
  <div class="platform-admin">
    <header class="page-header">
      <div>
        <h1 class="page-title">Marketplace de Técnicos</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">Gestão dos três pilares da IntelbrasTech Platform.</p>
      </div>
      <div class="flex gap-2">
        <RouterLink to="/cadastre-se" target="_blank" class="btn-secondary">Abrir cadastro</RouterLink>
        <button class="btn-primary" @click="carregar">Atualizar</button>
      </div>
    </header>

    <section class="cards">
      <article><strong>{{ resumo.total||0 }}</strong><span>Total</span></article>
      <article><strong>{{ resumo.tecnicos||0 }}</strong><span>Técnicos</span></article>
      <article><strong>{{ resumo.clientes||0 }}</strong><span>Clientes</span></article>
      <article><strong>{{ resumo.colaboradores||0 }}</strong><span>Colaboradores</span></article>
      <article><strong>{{ resumo.pendentes||0 }}</strong><span>Pendentes</span></article>
    </section>

    <section class="card p-4">
      <div class="filters">
        <input v-model="busca" class="input" placeholder="Pesquisar...">
        <select v-model="tipo" class="select"><option value="">Todos os perfis</option><option value="tecnico">Técnicos</option><option value="cliente">Clientes</option><option value="colaborador">Colaboradores</option></select>
        <select v-model="status" class="select"><option value="">Todos os status</option><option value="pendente">Pendente</option><option value="aprovado">Aprovado</option><option value="rejeitado">Rejeitado</option><option value="suspenso">Suspenso</option></select>
      </div>

      <div class="overflow-auto">
        <table class="table">
          <thead><tr><th>Perfil</th><th>Nome</th><th>Contato</th><th>Localização</th><th>Status</th><th>Ação</th></tr></thead>
          <tbody>
            <tr v-for="i in filtrados" :key="i.id">
              <td>{{ label(i.tipo_perfil) }}</td>
              <td><strong>{{ i.nome }}</strong><small>{{ i.empresa||i.cargo||'' }}</small></td>
              <td>{{ i.email }}<small>{{ i.whatsapp||i.telefone||'' }}</small></td>
              <td>{{ i.cidade||'—' }}/{{ i.estado||'—' }}</td>
              <td>{{ i.status }}</td>
              <td><select v-model="i.status" class="select" @change="analisar(i)"><option value="pendente">Pendente</option><option value="aprovado">Aprovado</option><option value="rejeitado">Rejeitado</option><option value="suspenso">Suspenso</option></select></td>
            </tr>
            <tr v-if="!filtrados.length"><td colspan="6" class="text-center py-10 text-[var(--text-muted)]">Nenhum cadastro.</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import {computed,onMounted,ref} from 'vue'
import {RouterLink} from 'vue-router'
import {useToast} from 'vue-toastification'
import api from '@/services/api'
const toast=useToast(),resumo=ref({}),cadastros=ref([]),busca=ref(''),tipo=ref(''),status=ref('')
const filtrados=computed(()=>cadastros.value.filter(i=>{const q=busca.value.toLowerCase();return(!tipo.value||i.tipo_perfil===tipo.value)&&(!status.value||i.status===status.value)&&(!q||`${i.nome} ${i.email} ${i.cidade}`.toLowerCase().includes(q))}))
const label=p=>({tecnico:'Técnico',cliente:'Cliente',colaborador:'Colaborador'}[p]||p)
async function carregar(){try{const {data}=await api.get('/platform/cadastros');resumo.value=data.resumo||{};cadastros.value=data.cadastros||[]}catch(e){toast.error(e.response?.data?.message||'Falha ao carregar.')}}
async function analisar(i){try{await api.patch(`/platform/cadastros/${i.id}/analisar`,{status:i.status});toast.success('Status atualizado.')}catch(e){toast.error(e.response?.data?.message||'Falha ao atualizar.')}}
onMounted(carregar)
</script>

<style scoped>
.platform-admin{display:flex;flex-direction:column;gap:14px}.cards{display:grid;grid-template-columns:repeat(5,1fr);gap:9px}.cards article{background:var(--bg-card);border:1px solid var(--border);border-radius:9px;padding:14px}.cards strong,.cards span{display:block}.cards strong{font-size:1.2rem}.cards span{font-size:.55rem;color:var(--text-muted)}.filters{display:grid;grid-template-columns:1fr 210px 180px;gap:8px;margin-bottom:12px}.table td strong,.table td small{display:block}.table td small{font-size:.52rem;color:var(--text-muted)}@media(max-width:800px){.cards{grid-template-columns:repeat(2,1fr)}.filters{grid-template-columns:1fr}}
</style>
