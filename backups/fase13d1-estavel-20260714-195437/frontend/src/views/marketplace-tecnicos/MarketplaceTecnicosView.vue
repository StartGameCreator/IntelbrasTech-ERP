<template>
  <div class="platform-admin">
    <header class="page-header">
      <div>
        <h1 class="page-title">Marketplace de Técnicos</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">Gestão dos usuários da IntelbrasTech Platform.</p>
      </div>
      <div class="toolbar">
        <RouterLink to="/cadastre-se" target="_blank" class="btn-secondary">Abrir cadastro</RouterLink>
        <button class="btn-secondary" @click="alternarLixeira">
          {{ lixeira ? 'Voltar aos usuários' : `Lixeira (${resumo.excluidos || 0})` }}
        </button>
        <button class="btn-primary" @click="carregar">Atualizar</button>
      </div>
    </header>

    <section class="cards">
      <article><strong>{{ resumo.total || 0 }}</strong><span>Ativos</span></article>
      <article><strong>{{ resumo.tecnicos || 0 }}</strong><span>Técnicos</span></article>
      <article><strong>{{ resumo.clientes || 0 }}</strong><span>Clientes</span></article>
      <article><strong>{{ resumo.colaboradores || 0 }}</strong><span>Colaboradores</span></article>
      <article><strong>{{ resumo.pendentes || 0 }}</strong><span>Pendentes</span></article>
      <article><strong>{{ resumo.excluidos || 0 }}</strong><span>Na lixeira</span></article>
    </section>

    <section class="card p-4">
      <div class="filters">
        <input v-model="busca" class="input" placeholder="Pesquisar...">
        <select v-model="tipo" class="select">
          <option value="">Todos os perfis</option>
          <option value="tecnico">Técnicos</option>
          <option value="cliente">Clientes</option>
          <option value="colaborador">Colaboradores</option>
        </select>
        <select v-model="status" class="select" :disabled="lixeira">
          <option value="">Todos os status</option>
          <option value="pendente">Pendente</option>
          <option value="aprovado">Aprovado</option>
          <option value="rejeitado">Rejeitado</option>
          <option value="suspenso">Suspenso</option>
        </select>
      </div>

      <div class="overflow-auto">
        <table class="table">
          <thead>
            <tr><th>Perfil</th><th>Nome</th><th>Contato</th><th>Localização</th><th>Status</th><th>Ações</th></tr>
          </thead>
          <tbody>
            <tr v-for="item in filtrados" :key="item.id">
              <td>{{ label(item.tipo_perfil) }}</td>
              <td><strong>{{ item.nome }}</strong><small>{{ item.empresa || item.cargo || '' }}</small></td>
              <td>{{ item.email }}<small>{{ item.whatsapp || item.telefone || '' }}</small></td>
              <td>{{ item.cidade || '—' }}/{{ item.estado || '—' }}</td>
              <td>{{ item.status }}</td>
              <td>
                <div v-if="!lixeira" class="actions">
                  <select v-model="item.status" class="select" @change="analisar(item)">
                    <option value="pendente">Pendente</option>
                    <option value="aprovado">Aprovado</option>
                    <option value="rejeitado">Rejeitado</option>
                    <option value="suspenso">Suspenso</option>
                  </select>
                  <button class="danger-btn" @click="abrirExcluir(item)">
                    <TrashIcon /> Excluir
                  </button>
                </div>
                <div v-else class="actions">
                  <button class="restore-btn" @click="restaurar(item)">
                    <ArrowPathIcon /> Restaurar
                  </button>
                  <button class="danger-btn" @click="abrirDefinitivo(item)">
                    <XMarkIcon /> Definitivo
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!filtrados.length">
              <td colspan="6" class="empty">{{ lixeira ? 'A lixeira está vazia.' : 'Nenhum cadastro.' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="modalExcluir" class="backdrop" @click.self="fechar">
      <section class="modal">
        <TrashIcon class="modal-icon" />
        <h2>Mover usuário para a lixeira?</h2>
        <p>O login será bloqueado, mas o histórico será preservado.</p>
        <div class="summary"><strong>{{ selecionado?.nome }}</strong><span>{{ selecionado?.email }}</span></div>
        <label><span>Motivo</span><textarea v-model="motivo"></textarea></label>
        <div class="modal-actions">
          <button class="btn-secondary" @click="fechar">Cancelar</button>
          <button class="danger-confirm" @click="excluir">Mover para a lixeira</button>
        </div>
      </section>
    </div>

    <div v-if="modalDefinitivo" class="backdrop" @click.self="fechar">
      <section class="modal">
        <ExclamationTriangleIcon class="modal-icon" />
        <h2>Excluir definitivamente</h2>
        <p>Somente o Administrador Master pode executar esta ação.</p>
        <label><span>Digite EXCLUIR</span><input v-model="confirmacao" class="input"></label>
        <label><span>Motivo</span><textarea v-model="motivo"></textarea></label>
        <div class="modal-actions">
          <button class="btn-secondary" @click="fechar">Cancelar</button>
          <button class="danger-confirm" :disabled="confirmacao !== 'EXCLUIR'" @click="excluirDefinitivo">Excluir definitivamente</button>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { ArrowPathIcon, ExclamationTriangleIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const toast = useToast()
const resumo = ref({})
const cadastros = ref([])
const busca = ref('')
const tipo = ref('')
const status = ref('')
const lixeira = ref(false)
const selecionado = ref(null)
const motivo = ref('')
const confirmacao = ref('')
const modalExcluir = ref(false)
const modalDefinitivo = ref(false)

const filtrados = computed(() => cadastros.value.filter(item => {
  const q = busca.value.toLowerCase()
  return (!tipo.value || item.tipo_perfil === tipo.value)
    && (!status.value || item.status === status.value)
    && (!q || `${item.nome} ${item.email} ${item.cidade}`.toLowerCase().includes(q))
}))

const label = p => ({ tecnico: 'Técnico', cliente: 'Cliente', colaborador: 'Colaborador' }[p] || p)

async function carregar() {
  try {
    const { data } = await api.get('/platform/cadastros', { params: { lixeira: lixeira.value ? 1 : 0 } })
    resumo.value = data.resumo || {}
    cadastros.value = data.cadastros || []
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha ao carregar.')
  }
}

async function analisar(item) {
  try {
    await api.patch(`/platform/cadastros/${item.id}/analisar`, { status: item.status })
    toast.success('Status atualizado.')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha ao atualizar.')
  }
}

function alternarLixeira() {
  lixeira.value = !lixeira.value
  status.value = ''
  carregar()
}

function abrirExcluir(item) {
  selecionado.value = item
  motivo.value = ''
  modalExcluir.value = true
}

function abrirDefinitivo(item) {
  selecionado.value = item
  motivo.value = ''
  confirmacao.value = ''
  modalDefinitivo.value = true
}

function fechar() {
  modalExcluir.value = false
  modalDefinitivo.value = false
  selecionado.value = null
  motivo.value = ''
  confirmacao.value = ''
}

async function excluir() {
  try {
    await api.delete(`/platform/cadastros/${selecionado.value.id}`, { data: { motivo: motivo.value } })
    toast.success('Usuário movido para a lixeira.')
    fechar()
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha ao excluir.')
  }
}

async function restaurar(item) {
  try {
    await api.post(`/platform/cadastros/${item.id}/restaurar`)
    toast.success('Usuário restaurado.')
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha ao restaurar.')
  }
}

async function excluirDefinitivo() {
  try {
    await api.delete(`/platform/cadastros/${selecionado.value.id}/definitivo`, {
      data: { confirmacao: confirmacao.value, motivo: motivo.value },
    })
    toast.success('Usuário excluído definitivamente.')
    fechar()
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha ao excluir definitivamente.')
  }
}

onMounted(carregar)
</script>

<style scoped>
.platform-admin{display:flex;flex-direction:column;gap:14px}.toolbar{display:flex;gap:8px}.cards{display:grid;grid-template-columns:repeat(6,1fr);gap:9px}.cards article{background:var(--bg-card);border:1px solid var(--border);border-radius:9px;padding:14px}.cards strong,.cards span{display:block}.cards strong{font-size:1.2rem}.cards span{font-size:.55rem;color:var(--text-muted)}.filters{display:grid;grid-template-columns:1fr 210px 180px;gap:8px;margin-bottom:12px}.table td strong,.table td small{display:block}.table td small{font-size:.52rem;color:var(--text-muted)}.actions{display:flex;gap:6px}.danger-btn,.restore-btn{display:flex;align-items:center;gap:4px;border-radius:6px;padding:7px 9px;font-size:.55rem;font-weight:700}.danger-btn{border:1px solid rgba(239,68,68,.3);background:rgba(239,68,68,.08);color:#f87171}.restore-btn{border:1px solid rgba(34,197,94,.3);background:rgba(34,197,94,.08);color:#4ade80}.danger-btn svg,.restore-btn svg{width:13px}.empty{text-align:center;padding:40px!important;color:var(--text-muted)}.backdrop{position:fixed;inset:0;background:rgba(2,6,23,.75);display:grid;place-items:center;z-index:100}.modal{width:min(430px,calc(100% - 30px));background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:22px}.modal-icon{width:38px;color:#f87171}.modal h2{font-size:1rem;font-weight:800;margin-top:10px}.modal p{font-size:.65rem;color:var(--text-muted);margin-top:5px}.summary{border:1px solid var(--border);border-radius:8px;padding:10px;margin-top:14px}.summary strong,.summary span{display:block}.summary span{font-size:.6rem;color:var(--text-muted)}.modal label{display:block;margin-top:13px}.modal label span{display:block;font-size:.58rem;margin-bottom:5px}.modal textarea{width:100%;min-height:75px;background:var(--bg-muted);border:1px solid var(--border);border-radius:7px;color:var(--text-primary);padding:8px}.modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:15px}.danger-confirm{background:#dc2626;color:white;border-radius:7px;padding:9px 12px;font-size:.62rem;font-weight:800}.danger-confirm:disabled{opacity:.4}@media(max-width:900px){.cards{grid-template-columns:repeat(3,1fr)}.filters{grid-template-columns:1fr}.actions{flex-direction:column}}@media(max-width:600px){.cards{grid-template-columns:repeat(2,1fr)}}
</style>
