<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <button @click="$router.push('/estoque')" class="text-xs text-[var(--text-muted)] hover:text-[var(--text-primary)] flex items-center gap-1 mb-1">
          <ArrowLeftIcon class="w-3.5 h-3.5" /> Voltar para Estoque
        </button>
        <h1 class="page-title">Inventário</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ total }} inventários</p>
      </div>
      <button @click="modalNovo = true" class="btn-primary btn-sm">
        <PlusIcon class="w-4 h-4" /> Abrir Inventário
      </button>
    </div>

    <div class="table-wrapper results-scroll">
      <table class="table">
        <thead><tr><th>Número</th><th>Depósito</th><th>Responsável</th><th>Status</th><th>Início</th><th class="text-right">Ações</th></tr></thead>
        <tbody>
          <tr v-if="loading"><td colspan="6"><div class="skeleton h-8 rounded m-2" /></td></tr>
          <tr v-else-if="!items.length"><td colspan="6">
            <div class="empty-state"><p class="text-sm text-[var(--text-muted)]">Nenhum inventário aberto</p></div>
          </td></tr>
          <tr v-else v-for="item in items" :key="item.id" class="cursor-pointer" @click="abrirDetalhe(item.id)">
            <td class="text-sm font-medium">{{ item.numero }}</td>
            <td class="text-xs">{{ item.deposito?.nome || 'Todos os depósitos' }}</td>
            <td class="text-xs">{{ item.responsavel?.nome || '—' }}</td>
            <td><span class="badge" :class="statusBadge(item.status)">{{ statusLabel[item.status] }}</span></td>
            <td class="text-xs text-[var(--text-muted)]">{{ data(item.data_inicio) }}</td>
            <td class="text-right" @click.stop><button class="btn-ghost btn-icon" @click="abrirDetalhe(item.id)"><EyeIcon class="w-4 h-4" /></button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Novo Inventário -->
    <Teleport to="body" v-if="modalNovo">
      <div class="modal-overlay" @click.self="modalNovo = false">
        <div class="modal-sm animate-slide-up">
          <div class="modal-header">
            <h3 class="text-base font-semibold text-[var(--text-primary)]">Abrir Novo Inventário</h3>
            <button @click="modalNovo = false" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div class="modal-body space-y-3">
            <div class="form-group">
              <label class="label">Depósito (opcional — deixe em branco para todos)</label>
              <select v-model.number="novo.deposito_id" class="select">
                <option :value="null">Todos os depósitos</option>
                <option v-for="d in depositos" :key="d.id" :value="d.id">{{ d.nome }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="label">Observações</label>
              <textarea v-model="novo.observacoes" class="input" rows="2" />
            </div>
          </div>
          <div class="modal-footer">
            <button @click="modalNovo = false" class="btn-secondary">Cancelar</button>
            <button @click="criar" class="btn-primary" :disabled="processando">Abrir Inventário</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal Detalhe / Contagem -->
    <Teleport to="body" v-if="detalhe">
      <div class="modal-overlay" @click.self="detalhe = null">
        <div class="modal-lg animate-slide-up">
          <div class="modal-header">
            <h3 class="text-base font-semibold text-[var(--text-primary)]">{{ detalhe.numero }}</h3>
            <button @click="detalhe = null" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div class="modal-body space-y-3">
            <div class="flex items-center justify-between">
              <span class="badge" :class="statusBadge(detalhe.status)">{{ statusLabel[detalhe.status] }}</span>
              <span class="text-xs text-[var(--text-muted)]">{{ detalhe.itens?.length }} itens</span>
            </div>
            <table class="table">
              <thead><tr><th>Produto</th><th class="text-right">Sistema</th><th class="text-right">Contado</th><th class="text-right">Diferença</th></tr></thead>
              <tbody>
                <tr v-for="item in detalhe.itens" :key="item.id">
                  <td class="text-sm">{{ item.produto?.nome }}</td>
                  <td class="text-sm text-right text-[var(--text-muted)]">{{ decimal(item.quantidade_sistema, 3) }}</td>
                  <td class="text-sm text-right">
                    <input v-if="detalhe.pode_editar" v-model.number="contagens[item.id]" type="number" step="0.001" min="0" class="input py-1 w-28 text-right" />
                    <span v-else>{{ decimal(item.quantidade_contada, 3) }}</span>
                  </td>
                  <td class="text-sm text-right" :class="parseFloat(item.diferenca) !== 0 && 'text-warning font-medium'">{{ decimal(item.diferenca, 3) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="modal-footer" v-if="detalhe.pode_editar">
            <button @click="salvarContagens" class="btn-secondary" :disabled="processando">Salvar Contagens</button>
            <button @click="finalizar" class="btn-primary" :disabled="processando">Finalizar e Ajustar Estoque</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowLeftIcon, PlusIcon, EyeIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const toast = useToast()
const { decimal, data } = useFormato()

const items = ref([])
const depositos = ref([])
const total = ref(0)
const loading = ref(false)
const processando = ref(false)
const modalNovo = ref(false)
const novo = ref({ deposito_id: null, observacoes: '' })
const detalhe = ref(null)
const contagens = ref({})

const statusLabel = { aberto: 'Aberto', em_andamento: 'Em Andamento', finalizado: 'Finalizado', cancelado: 'Cancelado' }
function statusBadge(s) {
  return { aberto: 'badge-info', em_andamento: 'badge-warning', finalizado: 'badge-success', cancelado: 'badge-gray' }[s] || 'badge-gray'
}

async function carregar() {
  loading.value = true
  try {
    const { data: resp } = await api.get('/inventarios')
    items.value = resp.data ?? resp
    total.value = resp.total ?? items.value.length
  } catch {
    // erros já tratados globalmente pelo interceptor do api.js
  } finally {
    loading.value = false
  }
}

async function carregarDepositos() {
  try {
    const { data } = await api.get('/depositos')
    depositos.value = data
  } catch { /* opcional */ }
}

async function criar() {
  processando.value = true
  try {
    const { data: resp } = await api.post('/inventarios', novo.value)
    toast.success(resp.message)
    modalNovo.value = false
    novo.value = { deposito_id: null, observacoes: '' }
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível abrir o inventário.')
  } finally {
    processando.value = false
  }
}

async function abrirDetalhe(id) {
  try {
    const { data } = await api.get(`/inventarios/${id}`)
    detalhe.value = data
    contagens.value = {}
    data.itens?.forEach(item => {
      contagens.value[item.id] = item.quantidade_contada ?? item.quantidade_sistema
    })
  } catch (e) {
    toast.error('Não foi possível carregar o inventário.')
  }
}

async function salvarContagens() {
  processando.value = true
  try {
    const itens = Object.entries(contagens.value).map(([id, quantidade_contada]) => ({ id: Number(id), quantidade_contada }))
    const { data } = await api.put(`/inventarios/${detalhe.value.id}`, { itens })
    toast.success(data.message)
    detalhe.value = data.inventario
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível salvar as contagens.')
  } finally {
    processando.value = false
  }
}

async function finalizar() {
  await salvarContagens()
  processando.value = true
  try {
    const { data } = await api.post(`/inventarios/${detalhe.value.id}/finalizar`)
    toast.success(data.message)
    detalhe.value = null
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível finalizar o inventário.')
  } finally {
    processando.value = false
  }
}

onMounted(() => {
  carregar()
  carregarDepositos()
})
</script>
