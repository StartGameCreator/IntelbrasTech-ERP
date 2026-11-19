<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Contas a Pagar</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ total }} contas</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="carregar" class="btn-secondary btn-sm" :disabled="loading">
          <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
        </button>
        <button @click="abrirModalNova" class="btn-primary btn-sm">
          <PlusIcon class="w-4 h-4" /> Nova Conta
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="card p-4">
        <p class="text-xs text-[var(--text-muted)]">Vencidas</p>
        <p class="text-lg font-semibold text-danger">{{ moeda(resumo.vencidas) }}</p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-[var(--text-muted)]">Total em Aberto</p>
        <p class="text-lg font-semibold">{{ moeda(totalAberto) }}</p>
      </div>
    </div>

    <div class="card p-4">
      <div class="flex flex-wrap gap-3">
        <div class="flex-1 min-w-[220px] relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-muted)]" />
          <input v-model="busca" type="text" placeholder="Buscar por descrição..." class="input pl-9" @input="debounceBusca" />
        </div>
        <select v-model="filtroStatus" class="input w-44" @change="carregar">
          <option value="">Todos os status</option>
          <option value="aberto">Aberto</option>
          <option value="parcial">Parcial</option>
          <option value="pago">Pago</option>
          <option value="cancelado">Cancelado</option>
        </select>
      </div>
    </div>

    <div class="table-wrapper">
      <table class="table">
        <thead><tr>
          <th>Descrição</th><th>Fornecedor</th><th>Vencimento</th>
          <th class="text-right">Valor</th><th class="text-right">Saldo</th><th>Status</th><th class="text-right">Ações</th>
        </tr></thead>
        <tbody>
          <tr v-if="loading"><td colspan="7"><div class="skeleton h-8 rounded m-2" /></td></tr>
          <tr v-else-if="!items.length"><td colspan="7">
            <div class="empty-state"><p class="text-sm text-[var(--text-muted)]">Nenhuma conta encontrada</p></div>
          </td></tr>
          <tr v-else v-for="item in items" :key="item.id" :class="item.esta_vencida && 'bg-danger/5'">
            <td class="text-sm font-medium">{{ item.descricao }}</td>
            <td class="text-xs text-[var(--text-muted)]">{{ item.fornecedor?.razao_social || item.fornecedor?.nome_fantasia || '—' }}</td>
            <td class="text-xs" :class="item.esta_vencida && 'text-danger font-medium'">{{ data(item.data_vencimento) }}</td>
            <td class="text-sm text-right">{{ moeda(item.valor) }}</td>
            <td class="text-sm text-right font-medium">{{ moeda(item.saldo) }}</td>
            <td><span class="badge" :class="statusBadge(item.status)">{{ statusLabel[item.status] }}</span></td>
            <td class="text-right">
              <button v-if="item.status !== 'pago' && item.status !== 'cancelado'" @click="abrirPagamento(item)" class="btn-ghost btn-icon" title="Registrar pagamento">
                <CheckCircleIcon class="w-4 h-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Nova Conta -->
    <Teleport to="body" v-if="modalNova">
      <div class="modal-overlay" @click.self="modalNova = false">
        <div class="modal-sm animate-slide-up">
          <div class="modal-header">
            <h3 class="text-base font-semibold text-[var(--text-primary)]">Nova Conta a Pagar</h3>
            <button @click="modalNova = false" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div class="modal-body space-y-3">
            <div class="form-group">
              <label class="label">Descrição</label>
              <input v-model="nova.descricao" class="input" />
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label class="label">Valor</label>
                <input v-model.number="nova.valor" type="number" step="0.01" min="0.01" class="input" />
              </div>
              <div class="form-group">
                <label class="label">Vencimento</label>
                <input v-model="nova.data_vencimento" type="date" class="input" />
              </div>
            </div>
            <div class="form-group">
              <label class="label">Parcelas</label>
              <input v-model.number="nova.total_parcelas" type="number" min="1" max="360" class="input" />
            </div>
          </div>
          <div class="modal-footer">
            <button @click="modalNova = false" class="btn-secondary">Cancelar</button>
            <button @click="criar" class="btn-primary" :disabled="processando">Salvar</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Modal Pagamento -->
    <Teleport to="body" v-if="contaPagamento">
      <div class="modal-overlay" @click.self="contaPagamento = null">
        <div class="modal-sm animate-slide-up">
          <div class="modal-header">
            <h3 class="text-base font-semibold text-[var(--text-primary)]">Registrar Pagamento</h3>
            <button @click="contaPagamento = null" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div class="modal-body space-y-3">
            <p class="text-sm text-[var(--text-muted)]">Saldo devedor: <strong class="text-[var(--text-primary)]">{{ moeda(contaPagamento.saldo) }}</strong></p>
            <div class="form-group">
              <label class="label">Valor Pago</label>
              <input v-model.number="pagamento.valor_pago" type="number" step="0.01" min="0.01" class="input" />
            </div>
            <div class="form-group">
              <label class="label">Data do Pagamento</label>
              <input v-model="pagamento.data_pagamento" type="date" class="input" />
            </div>
          </div>
          <div class="modal-footer">
            <button @click="contaPagamento = null" class="btn-secondary">Cancelar</button>
            <button @click="confirmarPagamento" class="btn-primary" :disabled="processando">Confirmar Pagamento</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowPathIcon, PlusIcon, MagnifyingGlassIcon, CheckCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const toast = useToast()
const { moeda, data } = useFormato()

const items = ref([])
const total = ref(0)
const loading = ref(false)
const processando = ref(false)
const busca = ref('')
const filtroStatus = ref('')
const resumo = ref({ vencidas: 0 })
const modalNova = ref(false)
const nova = ref({ descricao: '', valor: null, data_vencimento: '', total_parcelas: 1 })
const contaPagamento = ref(null)
const pagamento = ref({ valor_pago: null, data_pagamento: '' })
let timer = null

const statusLabel = { aberto: 'Aberto', parcial: 'Parcial', pago: 'Pago', cancelado: 'Cancelado', atrasado: 'Atrasado' }
function statusBadge(s) {
  return { aberto: 'badge-info', parcial: 'badge-warning', pago: 'badge-success', cancelado: 'badge-gray', atrasado: 'badge-danger' }[s] || 'badge-gray'
}

const totalAberto = computed(() => items.value.filter(i => i.status !== 'pago' && i.status !== 'cancelado').reduce((s, i) => s + parseFloat(i.saldo || 0), 0))

async function carregar() {
  loading.value = true
  try {
    const { data: resp } = await api.get('/contas-pagar', { params: { busca: busca.value, status: filtroStatus.value, per_page: 20 } })
    items.value = resp.data ?? resp
    total.value = resp.total ?? items.value.length
  } catch {
    // erros já tratados globalmente pelo interceptor do api.js
  } finally {
    loading.value = false
  }
}

async function carregarResumo() {
  try {
    const { data } = await api.get('/contas-pagar/vencidas')
    resumo.value = { vencidas: data.valor_total }
  } catch { /* opcional */ }
}

function abrirModalNova() {
  nova.value = { descricao: '', valor: null, data_vencimento: '', total_parcelas: 1 }
  modalNova.value = true
}

async function criar() {
  processando.value = true
  try {
    const { data } = await api.post('/contas-pagar', nova.value)
    toast.success(data.message)
    modalNova.value = false
    await Promise.all([carregar(), carregarResumo()])
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível criar a conta.')
  } finally {
    processando.value = false
  }
}

function abrirPagamento(item) {
  contaPagamento.value = item
  pagamento.value = { valor_pago: parseFloat(item.saldo), data_pagamento: new Date().toISOString().slice(0, 10) }
}

async function confirmarPagamento() {
  processando.value = true
  try {
    const { data } = await api.post(`/contas-pagar/${contaPagamento.value.id}/pagar`, pagamento.value)
    toast.success(data.message)
    contaPagamento.value = null
    await Promise.all([carregar(), carregarResumo()])
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível registrar o pagamento.')
  } finally {
    processando.value = false
  }
}

function debounceBusca() { clearTimeout(timer); timer = setTimeout(carregar, 400) }

onMounted(() => {
  carregar()
  carregarResumo()
})
</script>
