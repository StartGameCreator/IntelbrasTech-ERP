<template>
  <main class="gestao-equipe">
    <header class="page-header">
      <div>
        <p class="eyebrow">Operação</p>
        <h1 class="page-title">Gestão de equipe</h1>
        <p class="page-subtitle">Acompanhe técnicos e vendedores e distribua cada demanda para a pessoa certa.</p>
      </div>
      <button class="btn-secondary" :disabled="carregando" @click="carregar">{{ carregando ? 'Atualizando…' : 'Atualizar' }}</button>
    </header>

    <section class="resumo" aria-label="Resumo da equipe">
      <article><span>Equipe disponível</span><strong>{{ resumo.membros_ativos || 0 }}</strong></article>
      <article><span>Técnicos</span><strong>{{ resumo.tecnicos || 0 }}</strong></article>
      <article><span>Vendedores</span><strong>{{ resumo.vendedores || 0 }}</strong></article>
      <article class="attention"><span>Sem responsável</span><strong>{{ resumo.sem_responsavel || 0 }}</strong></article>
    </section>

    <section class="toolbar card">
      <div class="tabs" role="tablist" aria-label="Gestão de equipe">
        <button :class="{ active: aba === 'demandas' }" role="tab" @click="aba = 'demandas'">Demandas <span>{{ demandasFiltradas.length }}</span></button>
        <button :class="{ active: aba === 'equipe' }" role="tab" @click="aba = 'equipe'">Colaboradores <span>{{ equipeFiltrada.length }}</span></button>
      </div>
      <input v-model.trim="busca" class="input" type="search" placeholder="Buscar pessoa, cliente ou demanda">
    </section>

    <section v-if="aba === 'demandas'" class="card painel">
      <div class="section-head">
        <div><h2>Distribuição de demandas</h2><p>Leads vão para vendedores; visitas e ordens de serviço vão para técnicos.</p></div>
        <select v-model="filtroTipo" class="select"><option value="">Todas as filas</option><option value="comercial">Comercial</option><option value="tecnica">Técnica</option></select>
      </div>

      <div v-if="carregando" class="empty">Carregando dados da operação…</div>
      <div v-else-if="!demandasFiltradas.length" class="empty">Nenhuma demanda encontrada com estes filtros.</div>
      <div v-else class="demandas-grid">
        <article v-for="demanda in demandasFiltradas" :key="`${demanda.origem}-${demanda.id}`" class="demanda-card">
          <div class="demand-card-head">
            <span :class="['tipo', demanda.tipo]">{{ demanda.tipo === 'tecnica' ? 'Técnica' : 'Comercial' }}</span>
            <span :class="['prioridade', demanda.prioridade]">{{ demanda.prioridade || 'normal' }}</span>
          </div>
          <h3>{{ demanda.titulo }}</h3>
          <p class="cliente">{{ demanda.cliente || 'Cliente não informado' }}</p>
          <p v-if="demanda.descricao" class="descricao">{{ demanda.descricao }}</p>
          <div class="demand-card-foot">
            <span>{{ demanda.status || 'Em aberto' }}</span>
            <a v-if="demanda.url" :href="demanda.url">Abrir origem</a>
          </div>
          <label>
            <span>Responsável</span>
            <select :value="demanda.responsavel_id || ''" class="select" :disabled="salvando === chave(demanda)" @change="atribuir(demanda, $event.target.value)">
              <option value="">Sem responsável</option>
              <option v-for="membro in candidatos(demanda)" :key="membro.usuario_id" :value="membro.usuario_id">
                {{ membro.nome }} · {{ membro.carga_total }} em andamento
              </option>
            </select>
          </label>
        </article>
      </div>
    </section>

    <section v-else class="card painel">
      <div class="section-head">
        <div><h2>Colaboradores cadastrados</h2><p>Os cadastros aprovados ficam disponíveis para receber demandas.</p></div>
        <select v-model="filtroPerfil" class="select"><option value="">Todos os perfis</option><option value="tecnico">Técnicos</option><option value="vendedor">Vendedores</option><option value="colaborador">Colaboradores</option></select>
      </div>
      <div v-if="carregando" class="empty">Carregando equipe…</div>
      <div v-else-if="!equipeFiltrada.length" class="empty">Nenhum colaborador encontrado.</div>
      <div v-else class="equipe-grid">
        <article v-for="membro in equipeFiltrada" :key="membro.usuario_id" class="membro-card">
          <div class="avatar">{{ iniciais(membro.nome) }}</div>
          <div class="membro-info"><h3>{{ membro.nome }}</h3><p>{{ rotuloPerfil(membro.tipo_perfil) }}</p></div>
          <span :class="['status', membro.disponivel ? 'disponivel' : 'indisponivel']">{{ membro.disponivel ? 'Disponível' : membro.status }}</span>
          <div class="contato"><span>{{ membro.cidade ? `${membro.cidade}/${membro.estado || ''}` : 'Localização não informada' }}</span><span>{{ membro.telefone || membro.email }}</span></div>
          <div class="carga"><span>Comercial <strong>{{ membro.carga_comercial }}</strong></span><span>Técnica <strong>{{ membro.carga_tecnica }}</strong></span><span>Total <strong>{{ membro.carga_total }}</strong></span></div>
        </article>
      </div>
    </section>
  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast = useToast()
const carregando = ref(false)
const salvando = ref('')
const aba = ref('demandas')
const busca = ref('')
const filtroTipo = ref('')
const filtroPerfil = ref('')
const resumo = ref({})
const equipe = ref([])
const demandas = ref([])

const normalizar = (valor) => String(valor || '').toLocaleLowerCase('pt-BR')
const chave = (demanda) => `${demanda.origem}-${demanda.id}`
const iniciais = (nome) => String(nome || 'IT').split(' ').filter(Boolean).slice(0, 2).map((parte) => parte[0]).join('').toUpperCase()
const rotuloPerfil = (perfil) => ({ tecnico: 'Técnico', vendedor: 'Vendedor', colaborador: 'Colaborador' }[perfil] || perfil)

const demandasFiltradas = computed(() => demandas.value.filter((demanda) => {
  const texto = normalizar(`${demanda.titulo} ${demanda.cliente} ${demanda.descricao} ${demanda.responsavel_nome}`)
  return (!filtroTipo.value || demanda.tipo === filtroTipo.value) && (!busca.value || texto.includes(normalizar(busca.value)))
}))

const equipeFiltrada = computed(() => equipe.value.filter((membro) => {
  const texto = normalizar(`${membro.nome} ${membro.email} ${membro.cidade} ${membro.estado}`)
  return (!filtroPerfil.value || membro.tipo_perfil === filtroPerfil.value) && (!busca.value || texto.includes(normalizar(busca.value)))
}))

function candidatos(demanda) {
  const perfis = demanda.tipo === 'tecnica' ? ['tecnico', 'colaborador'] : ['vendedor', 'colaborador']
  return equipe.value.filter((membro) => membro.disponivel && perfis.includes(membro.tipo_perfil)).sort((a, b) => a.carga_total - b.carga_total || a.nome.localeCompare(b.nome))
}

async function carregar() {
  carregando.value = true
  try {
    const { data } = await api.get('/gestao-equipe')
    resumo.value = data.resumo || {}
    equipe.value = data.equipe || []
    demandas.value = data.demandas || []
  } catch (error) {
    toast.error(error.response?.data?.message || 'Não foi possível carregar a gestão de equipe.')
  } finally {
    carregando.value = false
  }
}

async function atribuir(demanda, usuarioId) {
  const id = usuarioId ? Number(usuarioId) : null
  salvando.value = chave(demanda)
  try {
    await api.patch(`/gestao-equipe/demandas/${demanda.origem}/${demanda.id}/atribuir`, { usuario_id: id })
    toast.success(id ? 'Responsável atualizado.' : 'Responsável removido da demanda.')
    await carregar()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Não foi possível distribuir esta demanda.')
    await carregar()
  } finally {
    salvando.value = ''
  }
}

onMounted(carregar)
</script>

<style scoped>
.gestao-equipe{display:flex;flex-direction:column;gap:16px}.eyebrow{margin:0 0 4px;color:#22a95a;font-size:.67rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.page-subtitle{margin:5px 0 0;color:var(--text-muted);font-size:.75rem}.resumo{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.resumo article{padding:15px;border:1px solid var(--border);border-radius:11px;background:var(--bg-card)}.resumo span,.resumo strong{display:block}.resumo span{font-size:.64rem;color:var(--text-muted)}.resumo strong{margin-top:5px;font-size:1.45rem}.resumo .attention{border-color:rgba(245,158,11,.45)}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px}.toolbar .input{max-width:300px}.tabs{display:flex;gap:5px}.tabs button{border:0;border-radius:7px;background:transparent;color:var(--text-muted);padding:8px 10px;font-size:.7rem;font-weight:800}.tabs button.active{background:rgba(34,169,90,.14);color:#22a95a}.tabs span{padding:2px 6px;border-radius:999px;background:rgba(127,127,127,.15);font-size:.58rem}.painel{padding:16px}.section-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px}.section-head h2{margin:0;font-size:.9rem}.section-head p{margin:4px 0 0;color:var(--text-muted);font-size:.65rem}.section-head .select{max-width:180px}.demandas-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.demanda-card,.membro-card{border:1px solid var(--border);border-radius:10px;background:var(--bg-elevated);padding:13px}.demand-card-head,.demand-card-foot{display:flex;align-items:center;justify-content:space-between;gap:8px}.tipo,.prioridade,.status{border-radius:999px;padding:3px 7px;font-size:.56rem;font-weight:800}.tipo.tecnica{color:#60a5fa;background:rgba(59,130,246,.13)}.tipo.comercial{color:#4ade80;background:rgba(34,197,94,.13)}.prioridade.alta{color:#fca5a5;background:rgba(239,68,68,.13)}.prioridade.normal{color:#cbd5e1;background:rgba(148,163,184,.12)}.demanda-card h3{margin:10px 0 3px;font-size:.75rem;line-height:1.35}.cliente{margin:0;color:var(--text-muted);font-size:.63rem}.descricao{min-height:34px;margin:9px 0;color:var(--text-muted);font-size:.62rem;line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.demand-card-foot{margin:10px 0;color:var(--text-muted);font-size:.58rem}.demand-card-foot a{color:#60a5fa;font-weight:800;text-decoration:none}.demanda-card label{display:block}.demanda-card label span{display:block;margin-bottom:4px;color:var(--text-muted);font-size:.58rem;font-weight:700}.demanda-card .select{width:100%;font-size:.65rem}.equipe-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.membro-card{display:grid;grid-template-columns:38px 1fr auto;align-items:center;gap:9px}.avatar{display:grid;place-items:center;width:38px;height:38px;border-radius:50%;background:rgba(99,102,241,.2);color:#a5b4fc;font-size:.7rem;font-weight:900}.membro-info h3,.membro-info p{margin:0}.membro-info h3{font-size:.75rem}.membro-info p,.contato{font-size:.59rem;color:var(--text-muted)}.status.disponivel{color:#4ade80;background:rgba(34,197,94,.13)}.status.indisponivel{color:#fbbf24;background:rgba(245,158,11,.13)}.contato,.carga{grid-column:1/-1;display:flex;justify-content:space-between;gap:8px;border-top:1px solid var(--border);padding-top:8px}.contato span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.carga span{font-size:.58rem;color:var(--text-muted)}.carga strong{margin-left:3px;color:var(--text-primary)}.empty{padding:30px 15px;text-align:center;color:var(--text-muted);font-size:.72rem}@media(max-width:1050px){.demandas-grid,.equipe-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.resumo{grid-template-columns:repeat(2,minmax(0,1fr))}.toolbar,.section-head{align-items:stretch;flex-direction:column}.toolbar .input,.section-head .select{max-width:none;width:100%}.demandas-grid,.equipe-grid{grid-template-columns:1fr}.painel{padding:12px}.demanda-card,.membro-card{padding:12px}}
</style>
