<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Pedidos de Compra</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ total }} pedidos</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="carregar" class="btn-secondary btn-sm" :disabled="loading">
          <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
        </button>
        <router-link to="/compras/novo" class="btn-primary btn-sm">
          <PlusIcon class="w-4 h-4" /> Novo Pedido
        </router-link>
      </div>
    </div>

    <div class="card p-4">
      <div class="flex flex-wrap gap-3">
        <div class="flex-1 min-w-[220px] relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-muted)]" />
          <input v-model="busca" type="text" placeholder="Buscar por número..." class="input pl-9" @input="debounceBusca" />
        </div>
        <select v-model="filtroStatus" class="input w-48" @change="carregar">
          <option value="">Todos os status</option>
          <option value="rascunho">Rascunho</option>
          <option value="enviado">Enviado</option>
          <option value="parcial">Recebido Parcial</option>
          <option value="recebido">Recebido</option>
          <option value="cancelado">Cancelado</option>
        </select>
      </div>
    </div>

    <div class="table-wrapper">
      <table class="table">
        <thead><tr>
          <th>Número</th><th>Fornecedor</th><th>Depósito</th>
          <th class="text-right">Total</th><th>Status</th><th>Previsão</th><th class="text-right">Ações</th>
        </tr></thead>
        <tbody>
          <tr v-if="loading"><td colspan="7"><div class="skeleton h-8 rounded m-2" /></td></tr>
          <tr v-else-if="!items.length"><td colspan="7">
            <div class="empty-state"><p class="text-sm text-[var(--text-muted)]">Nenhum pedido de compra encontrado</p></div>
          </td></tr>
          <tr v-else v-for="item in items" :key="item.id" class="cursor-pointer" @click="verItem(item.id)">
            <td class="text-sm font-medium">{{ item.numero }}</td>
            <td class="text-sm">{{ item.fornecedor?.razao_social || item.fornecedor?.nome_fantasia || '—' }}</td>
            <td class="text-xs text-[var(--text-muted)]">{{ item.deposito?.nome || '—' }}</td>
            <td class="text-sm text-right font-medium">{{ moeda(item.total) }}</td>
            <td><span class="badge" :class="statusBadge(item.status)">{{ statusLabel[item.status] }}</span></td>
            <td class="text-xs text-[var(--text-muted)]">{{ item.data_previsao ? data(item.data_previsao) : '—' }}</td>
            <td class="text-right" @click.stop>
              <button @click="verItem(item.id)" class="btn-ghost btn-icon"><EyeIcon class="w-4 h-4" /></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowPathIcon, MagnifyingGlassIcon, EyeIcon, PlusIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const { moeda, data } = useFormato()

const items = ref([])
const total = ref(0)
const loading = ref(false)
const busca = ref('')
const filtroStatus = ref('')
let timer = null

const statusLabel = { rascunho: 'Rascunho', enviado: 'Enviado', parcial: 'Recebido Parcial', recebido: 'Recebido', cancelado: 'Cancelado' }
function statusBadge(s) {
  return { rascunho: 'badge-gray', enviado: 'badge-info', parcial: 'badge-warning', recebido: 'badge-success', cancelado: 'badge-danger' }[s] || 'badge-gray'
}

async function carregar() {
  loading.value = true
  try {
    const { data: resp } = await api.get('/pedidos-compra', { params: { busca: busca.value, status: filtroStatus.value, per_page: 15 } })
    items.value = resp.data ?? resp
    total.value = resp.total ?? items.value.length
  } catch {
    // erros já tratados globalmente pelo interceptor do api.js
  } finally {
    loading.value = false
  }
}

function debounceBusca() { clearTimeout(timer); timer = setTimeout(carregar, 400) }
function verItem(id) { router.push('/compras/' + id) }
onMounted(carregar)
</script>
