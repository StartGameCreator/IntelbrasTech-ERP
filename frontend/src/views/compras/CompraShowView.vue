<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <button @click="router.push('/compras')" class="text-xs text-[var(--text-muted)] hover:text-[var(--text-primary)] flex items-center gap-1 mb-1">
          <ArrowLeftIcon class="w-3.5 h-3.5" /> Voltar
        </button>
        <h1 class="page-title">{{ pedido?.numero }}</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ pedido?.fornecedor?.razao_social || '—' }}</p>
      </div>
      <div class="flex items-center gap-2" v-if="pedido">
        <span class="badge" :class="statusBadge(pedido.status)">{{ statusLabel[pedido.status] }}</span>
        <button v-if="pedido.status === 'rascunho'" @click="enviar" class="btn-primary btn-sm" :disabled="processando">
          <PaperAirplaneIcon class="w-4 h-4" /> Enviar ao Fornecedor
        </button>
        <button v-if="pedido.pode_receber" @click="modalReceber = true" class="btn-primary btn-sm">
          <ArchiveBoxArrowDownIcon class="w-4 h-4" /> Registrar Recebimento
        </button>
        <button v-if="pedido.status !== 'recebido' && pedido.status !== 'cancelado'" @click="cancelar" class="btn-danger btn-sm">
          <XCircleIcon class="w-4 h-4" /> Cancelar
        </button>
      </div>
    </div>

    <div v-if="loading" class="skeleton h-40 rounded-xl" />

    <template v-else-if="pedido">
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="card p-4"><p class="text-xs text-[var(--text-muted)]">Subtotal</p><p class="text-lg font-semibold">{{ moeda(pedido.subtotal) }}</p></div>
        <div class="card p-4"><p class="text-xs text-[var(--text-muted)]">Frete</p><p class="text-lg font-semibold">{{ moeda(pedido.frete) }}</p></div>
        <div class="card p-4"><p class="text-xs text-[var(--text-muted)]">Desconto</p><p class="text-lg font-semibold">{{ moeda(pedido.desconto_valor) }}</p></div>
        <div class="card p-4"><p class="text-xs text-[var(--text-muted)]">Total</p><p class="text-lg font-semibold">{{ moeda(pedido.total) }}</p></div>
      </div>

      <div class="table-wrapper">
        <table class="table">
          <thead><tr>
            <th>Produto</th><th class="text-right">Qtd. Pedida</th><th class="text-right">Qtd. Recebida</th>
            <th class="text-right">Saldo</th><th class="text-right">Preço Unit.</th><th class="text-right">Total</th>
          </tr></thead>
          <tbody>
            <tr v-for="item in pedido.itens" :key="item.id">
              <td class="text-sm">{{ item.descricao || item.produto?.nome }}</td>
              <td class="text-sm text-right">{{ decimal(item.quantidade, 3) }}</td>
              <td class="text-sm text-right">{{ decimal(item.quantidade_recebida, 3) }}</td>
              <td class="text-sm text-right font-medium" :class="item.saldo_a_receber > 0 && 'text-warning'">{{ decimal(item.saldo_a_receber, 3) }}</td>
              <td class="text-sm text-right">{{ moeda(item.preco_unitario) }}</td>
              <td class="text-sm text-right font-medium">{{ moeda(item.total) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pedido.observacoes" class="card p-4">
        <h3 class="text-sm font-semibold mb-2">Observações</h3>
        <p class="text-sm text-[var(--text-secondary)]">{{ pedido.observacoes }}</p>
      </div>
    </template>

    <!-- Modal Receber -->
    <Teleport to="body" v-if="modalReceber">
      <div class="modal-overlay" @click.self="modalReceber = false">
        <div class="modal-lg animate-slide-up">
          <div class="modal-header">
            <h3 class="text-base font-semibold text-[var(--text-primary)]">Registrar Recebimento</h3>
            <button @click="modalReceber = false" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div class="modal-body space-y-3">
            <p class="text-xs text-[var(--text-muted)]">Informe a quantidade recebida de cada item. Deixe zero para itens não recebidos nesta entrega.</p>
            <table class="table">
              <thead><tr><th>Produto</th><th class="text-right">Saldo Pendente</th><th class="text-right">Receber Agora</th></tr></thead>
              <tbody>
                <tr v-for="item in pedido.itens" :key="item.id">
                  <td class="text-sm">{{ item.descricao || item.produto?.nome }}</td>
                  <td class="text-sm text-right">{{ decimal(item.saldo_a_receber, 3) }}</td>
                  <td class="text-right">
                    <input v-model.number="recebimentos[item.id]" type="number" step="0.001" min="0" :max="item.saldo_a_receber"
                           class="input py-1 w-28 text-right" :disabled="item.saldo_a_receber <= 0" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button @click="modalReceber = false" class="btn-secondary">Cancelar</button>
            <button @click="confirmarRecebimento" class="btn-primary" :disabled="processando">Confirmar Recebimento</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowLeftIcon, PaperAirplaneIcon, ArchiveBoxArrowDownIcon, XCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { moeda, decimal } = useFormato()

const pedido = ref(null)
const loading = ref(false)
const processando = ref(false)
const modalReceber = ref(false)
const recebimentos = ref({})

const statusLabel = { rascunho: 'Rascunho', enviado: 'Enviado', parcial: 'Recebido Parcial', recebido: 'Recebido', cancelado: 'Cancelado' }
function statusBadge(s) {
  return { rascunho: 'badge-gray', enviado: 'badge-info', parcial: 'badge-warning', recebido: 'badge-success', cancelado: 'badge-danger' }[s] || 'badge-gray'
}

async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get(`/pedidos-compra/${route.params.id}`)
    pedido.value = data
    recebimentos.value = {}
    data.itens?.forEach(item => { recebimentos.value[item.id] = 0 })
  } catch {
    pedido.value = null
  } finally {
    loading.value = false
  }
}

async function enviar() {
  processando.value = true
  try {
    const { data } = await api.post(`/pedidos-compra/${pedido.value.id}/enviar`)
    toast.success(data.message)
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível enviar o pedido.')
  } finally {
    processando.value = false
  }
}

async function confirmarRecebimento() {
  const itens = Object.entries(recebimentos.value)
    .filter(([, qtd]) => qtd > 0)
    .map(([item_id, quantidade_recebida]) => ({ item_id: Number(item_id), quantidade_recebida }))

  if (!itens.length) {
    toast.error('Informe ao menos uma quantidade recebida.')
    return
  }

  processando.value = true
  try {
    const { data } = await api.post(`/pedidos-compra/${pedido.value.id}/receber`, { itens })
    toast.success(data.message)
    modalReceber.value = false
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível registrar o recebimento.')
  } finally {
    processando.value = false
  }
}

async function cancelar() {
  processando.value = true
  try {
    const { data } = await api.post(`/pedidos-compra/${pedido.value.id}/cancelar`)
    toast.success(data.message)
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível cancelar o pedido.')
  } finally {
    processando.value = false
  }
}

onMounted(carregar)
</script>
