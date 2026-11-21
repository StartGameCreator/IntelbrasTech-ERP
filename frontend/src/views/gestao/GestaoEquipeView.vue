<template>
  <main class="gestao-equipe">
    <header class="page-header">
      <div>
        <p class="eyebrow">Operação</p>
        <h1 class="page-title">Gestão de equipe</h1>
        <p class="page-subtitle">Aprove vendedores para o CRM e organize apenas a operação técnica de campo.</p>
      </div>
      <button class="btn-secondary" :disabled="carregando" @click="carregar">{{ carregando ? 'Atualizando…' : 'Atualizar' }}</button>
    </header>

    <section class="resumo" aria-label="Resumo da equipe">
      <article><span>Técnicos cadastrados</span><strong>{{ resumo.tecnicos || 0 }}</strong></article>
      <article><span>Disponíveis agora</span><strong>{{ resumo.tecnicos_disponiveis || 0 }}</strong></article>
      <article class="attention"><span>Demandas sem técnico</span><strong>{{ resumo.sem_responsavel || 0 }}</strong></article>
      <article :class="{ attention: resumo.vendedores_pendentes }"><span>Vendedores aguardando aceite</span><strong>{{ resumo.vendedores_pendentes || 0 }}</strong></article>
    </section>

    <section class="toolbar card">
      <div class="tabs" role="tablist" aria-label="Gestão de equipe">
        <button :class="{ active: aba === 'demandas' }" role="tab" @click="aba = 'demandas'">Demandas técnicas <span>{{ demandasFiltradas.length }}</span></button>
        <button :class="{ active: aba === 'tecnicos' }" role="tab" @click="aba = 'tecnicos'">Técnicos <span>{{ equipeFiltrada.length }}</span></button>
        <button :class="{ active: aba === 'cobertura' }" role="tab" @click="aba = 'cobertura'">Mapa e base técnica</button>
        <button :class="{ active: aba === 'vendedores' }" role="tab" @click="aba = 'vendedores'">Aprovar vendedores <span>{{ vendedoresFiltrados.length }}</span></button>
      </div>
      <input v-model.trim="busca" class="input" type="search" placeholder="Buscar técnico, vendedor ou demanda">
    </section>

    <section v-if="aba === 'demandas'" class="card painel">
      <div class="section-head">
        <div><h2>Distribuição técnica</h2><p>Visitas e ordens de serviço são atendidas por técnicos de campo. O comercial é gerido exclusivamente no CRM.</p></div>
      </div>
      <div v-if="carregando" class="empty">Carregando dados da operação…</div>
      <div v-else-if="!demandasFiltradas.length" class="empty">Nenhuma demanda técnica encontrada.</div>
      <div v-else class="demandas-grid">
        <article v-for="demanda in demandasFiltradas" :key="`${demanda.origem}-${demanda.id}`" class="demanda-card">
          <div class="demand-card-head"><span class="tipo">{{ demanda.origem === 'os' ? 'Ordem de serviço' : 'Visita técnica' }}</span><span :class="['prioridade', demanda.prioridade]">{{ demanda.prioridade || 'normal' }}</span></div>
          <h3>{{ demanda.titulo }}</h3>
          <p class="cliente">{{ demanda.cliente || 'Cliente não informado' }}</p>
          <p v-if="demanda.descricao" class="descricao">{{ demanda.descricao }}</p>
          <div class="demand-card-foot"><span>{{ demanda.status || 'Em aberto' }}</span><RouterLink v-if="demanda.url" :to="demanda.url">Abrir OS</RouterLink></div>
          <label><span>Técnico responsável</span><select :value="demanda.responsavel_id || ''" class="select" :disabled="salvando === chave(demanda)" @change="atribuir(demanda, $event.target.value)"><option value="">Sem responsável</option><option v-for="membro in candidatos" :key="membro.usuario_id" :value="membro.usuario_id">{{ membro.nome }} · {{ membro.carga_tecnica }} em andamento{{ membro.disponivel ? '' : ' · indisponível' }}</option></select></label>
        </article>
      </div>
    </section>

    <section v-else-if="aba === 'tecnicos'" class="card painel">
      <div class="section-head"><div><h2>Equipe técnica</h2><p>A disponibilidade e a localização são atualizadas pelo próprio técnico no aplicativo de campo.</p></div></div>
      <div v-if="carregando" class="empty">Carregando técnicos…</div>
      <div v-else-if="!equipeFiltrada.length" class="empty">Nenhum técnico ou colaborador de campo encontrado.</div>
      <div v-else class="equipe-grid">
        <article v-for="membro in equipeFiltrada" :key="membro.usuario_id" class="membro-card">
          <div class="avatar">{{ iniciais(membro.nome) }}</div>
          <div class="membro-info"><h3>{{ membro.nome }}</h3><p>{{ rotuloPerfil(membro.tipo_perfil) }}</p></div>
          <span :class="['status', membro.disponivel ? 'disponivel' : 'indisponivel']">{{ membro.disponivel ? 'Disponível' : 'Indisponível' }}</span>
          <div class="contato"><span>{{ membro.cidade ? `${membro.cidade}/${membro.estado || ''}` : 'Localização não informada' }}</span><span>{{ membro.telefone || membro.email }}</span></div>
          <div class="carga"><span>Em andamento <strong>{{ membro.carga_tecnica }}</strong></span><span v-if="membro.localizacao_atualizada_em">GPS atualizado</span></div>
        </article>
      </div>
    </section>

    <TecnicosMarketplacePanel v-else-if="aba === 'cobertura'" embedded />

    <section v-else class="card painel">
      <div class="section-head"><div><h2>Aprovação de vendedores</h2><p>Depois do aceite, o vendedor passa a trabalhar exclusivamente no CRM e deixa esta fila.</p></div></div>
      <div v-if="carregando" class="empty">Carregando solicitações…</div>
      <div v-else-if="!vendedoresFiltrados.length" class="empty">Nenhum vendedor aguardando aprovação.</div>
      <div v-else class="vendedores-grid">
        <article v-for="vendedor in vendedoresFiltrados" :key="vendedor.id" class="vendedor-card">
          <div class="avatar">{{ iniciais(vendedor.nome) }}</div>
          <div><h3>{{ vendedor.nome }}</h3><p>{{ vendedor.empresa || vendedor.cargo || 'Vendedor parceiro' }}</p><small>{{ vendedor.cidade ? `${vendedor.cidade}/${vendedor.estado || ''}` : 'Localização não informada' }} · {{ vendedor.email }}</small></div>
          <div class="approval-actions"><button class="btn-secondary danger-outline" :disabled="salvandoVendedor === vendedor.id" @click="analisarVendedor(vendedor, 'rejeitado')">Recusar</button><button class="btn-primary" :disabled="salvandoVendedor === vendedor.id" @click="analisarVendedor(vendedor, 'aprovado')">{{ salvandoVendedor === vendedor.id ? 'Salvando…' : 'Aprovar' }}</button></div>
        </article>
      </div>
    </section>
  </main>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import TecnicosMarketplacePanel from '@/views/marketplace-tecnicos/MarketplaceTecnicosView.vue'

const toast = useToast()
const route = useRoute()
const carregando = ref(false)
const salvando = ref('')
const salvandoVendedor = ref(null)
const aba = ref(route.query.aba === 'cobertura' ? 'cobertura' : 'demandas')
const busca = ref('')
const resumo = ref({})
const equipe = ref([])
const demandas = ref([])
const vendedoresPendentes = ref([])

const normalizar = (valor) => String(valor || '').toLocaleLowerCase('pt-BR')
const chave = (demanda) => `${demanda.origem}-${demanda.id}`
const iniciais = (nome) => String(nome || 'IT').split(' ').filter(Boolean).slice(0, 2).map((parte) => parte[0]).join('').toUpperCase()
const rotuloPerfil = (perfil) => ({ tecnico: 'Técnico', colaborador: 'Colaborador de campo' }[perfil] || perfil)

const demandasFiltradas = computed(() => demandas.value.filter((demanda) => normalizar(`${demanda.titulo} ${demanda.cliente} ${demanda.descricao} ${demanda.responsavel_nome}`).includes(normalizar(busca.value))))
const equipeFiltrada = computed(() => equipe.value.filter((membro) => normalizar(`${membro.nome} ${membro.email} ${membro.cidade} ${membro.estado}`).includes(normalizar(busca.value))))
const vendedoresFiltrados = computed(() => vendedoresPendentes.value.filter((vendedor) => normalizar(`${vendedor.nome} ${vendedor.email} ${vendedor.empresa} ${vendedor.cidade}`).includes(normalizar(busca.value))))
const candidatos = computed(() => equipe.value.filter((membro) => membro.tipo_perfil === 'tecnico' || membro.tipo_perfil === 'colaborador').sort((a, b) => Number(b.disponivel) - Number(a.disponivel) || a.carga_tecnica - b.carga_tecnica || a.nome.localeCompare(b.nome)))

async function carregar() {
  carregando.value = true
  try {
    const { data } = await api.get('/gestao-equipe')
    resumo.value = data.resumo || {}
    equipe.value = data.equipe || []
    demandas.value = data.demandas || []
    vendedoresPendentes.value = data.vendedores_pendentes || []
  } catch (error) {
    toast.error(error.response?.data?.message || 'Não foi possível carregar a gestão de equipe.')
  } finally { carregando.value = false }
}

async function atribuir(demanda, usuarioId) {
  salvando.value = chave(demanda)
  try {
    await api.patch(`/gestao-equipe/demandas/${demanda.origem}/${demanda.id}/atribuir`, { usuario_id: usuarioId ? Number(usuarioId) : null })
    toast.success(usuarioId ? 'Técnico responsável atualizado.' : 'Responsável removido da demanda.')
    await carregar()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Não foi possível distribuir esta demanda.')
    await carregar()
  } finally { salvando.value = '' }
}

async function analisarVendedor(vendedor, status) {
  salvandoVendedor.value = vendedor.id
  try {
    await api.patch(`/platform/cadastros/${vendedor.id}/analisar`, { status })
    toast.success(status === 'aprovado' ? 'Vendedor aprovado e liberado para o CRM.' : 'Solicitação de vendedor recusada.')
    await carregar()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Não foi possível atualizar este cadastro.')
  } finally { salvandoVendedor.value = null }
}

onMounted(carregar)
</script>

<style scoped>
.gestao-equipe{display:flex;flex-direction:column;gap:16px}.eyebrow{margin:0 0 4px;color:#22a95a;font-size:.67rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.page-subtitle{margin:5px 0 0;color:var(--text-muted);font-size:.75rem}.resumo{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.resumo article{padding:15px;border:1px solid var(--border);border-radius:11px;background:var(--bg-card)}.resumo span,.resumo strong{display:block}.resumo span{font-size:.64rem;color:var(--text-muted)}.resumo strong{margin-top:5px;font-size:1.45rem}.resumo .attention{border-color:rgba(245,158,11,.45)}.toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px}.toolbar .input{max-width:320px}.tabs{display:flex;gap:5px;overflow-x:auto}.tabs button{flex:0 0 auto;border:0;border-radius:7px;background:transparent;color:var(--text-muted);padding:8px 10px;font-size:.7rem;font-weight:800}.tabs button.active{background:rgba(34,169,90,.14);color:#22a95a}.tabs span{padding:2px 6px;border-radius:999px;background:rgba(127,127,127,.15);font-size:.58rem}.painel{padding:16px}.section-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px}.section-head h2{margin:0;font-size:.9rem}.section-head p{margin:4px 0 0;color:var(--text-muted);font-size:.65rem;line-height:1.45}.demandas-grid,.equipe-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.demanda-card,.membro-card,.vendedor-card{border:1px solid var(--border);border-radius:10px;background:var(--bg-elevated);padding:13px}.demand-card-head,.demand-card-foot{display:flex;align-items:center;justify-content:space-between;gap:8px}.tipo,.prioridade,.status{border-radius:999px;padding:3px 7px;font-size:.56rem;font-weight:800}.tipo{color:#60a5fa;background:rgba(59,130,246,.13)}.prioridade.alta{color:#fca5a5;background:rgba(239,68,68,.13)}.prioridade.normal{color:#cbd5e1;background:rgba(148,163,184,.12)}.demanda-card h3{margin:10px 0 3px;font-size:.75rem;line-height:1.35}.cliente{margin:0;color:var(--text-muted);font-size:.63rem}.descricao{min-height:34px;margin:9px 0;color:var(--text-muted);font-size:.62rem;line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.demand-card-foot{margin:10px 0;color:var(--text-muted);font-size:.58rem}.demand-card-foot a{color:#60a5fa;font-weight:800;text-decoration:none}.demanda-card label{display:block}.demanda-card label span{display:block;margin-bottom:4px;color:var(--text-muted);font-size:.58rem;font-weight:700}.demanda-card .select{width:100%;font-size:.65rem}.membro-card{display:grid;grid-template-columns:38px 1fr auto;align-items:center;gap:9px}.avatar{display:grid;place-items:center;width:38px;height:38px;border-radius:50%;background:rgba(99,102,241,.2);color:#a5b4fc;font-size:.7rem;font-weight:900}.membro-info h3,.membro-info p,.vendedor-card h3,.vendedor-card p{margin:0}.membro-info h3,.vendedor-card h3{font-size:.75rem}.membro-info p,.contato,.vendedor-card p,.vendedor-card small{font-size:.59rem;color:var(--text-muted)}.status.disponivel{color:#4ade80;background:rgba(34,197,94,.13)}.status.indisponivel{color:#fbbf24;background:rgba(245,158,11,.13)}.contato,.carga{grid-column:1/-1;display:flex;justify-content:space-between;gap:8px;border-top:1px solid var(--border);padding-top:8px}.contato span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.carga span{font-size:.58rem;color:var(--text-muted)}.carga strong{margin-left:3px;color:var(--text-primary)}.vendedores-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.vendedor-card{display:grid;grid-template-columns:38px 1fr auto;align-items:center;gap:10px}.vendedor-card small{display:block;margin-top:6px}.approval-actions{display:flex;gap:7px}.approval-actions button{font-size:.66rem}.danger-outline{border-color:rgba(239,68,68,.45);color:#fca5a5}.empty{padding:30px 15px;text-align:center;color:var(--text-muted);font-size:.72rem}@media(max-width:1050px){.demandas-grid,.equipe-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.resumo{grid-template-columns:repeat(2,minmax(0,1fr))}.toolbar,.section-head{align-items:stretch;flex-direction:column}.toolbar .input{max-width:none;width:100%}.demandas-grid,.equipe-grid,.vendedores-grid{grid-template-columns:1fr}.painel{padding:12px}.demanda-card,.membro-card,.vendedor-card{padding:12px}.vendedor-card{grid-template-columns:38px 1fr}.approval-actions{grid-column:1/-1}.approval-actions button{flex:1}}
</style>
