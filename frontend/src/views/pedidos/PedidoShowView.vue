<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <button @click="router.push('/pedidos')" class="text-xs text-[var(--text-muted)] hover:text-[var(--text-primary)] flex items-center gap-1 mb-1">
          <ArrowLeftIcon class="w-3.5 h-3.5" /> Voltar
        </button>
        <h1 class="page-title">Pedido {{ pedido?.numero }}</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ pedido?.cliente?.razao_social || pedido?.cliente?.nome_fantasia || '—' }}</p>
      </div>
      <div class="flex items-center gap-2 flex-wrap justify-end" v-if="pedido">
        <span class="badge" :style="{ background: (pedido.status_cor || '#999') + '26', color: pedido.status_cor || '#999' }">{{ pedido.status }}</span>

        <button v-if="pedido.pode_cancelar" @click="cancelar" class="btn-danger btn-sm" :disabled="processando">
          <XCircleIcon class="w-4 h-4" /> Cancelar
        </button>

        <!-- Documento de venda: fiscal OU recibo não fiscal (MEI vendendo p/ pessoa física) -->
        <div class="relative" v-if="pedido.pode_emitir_nfe || !pedido.notas_fiscais?.length">
          <button @click="menuDocumento = !menuDocumento" class="btn-primary btn-sm">
            <DocumentTextIcon class="w-4 h-4" /> Emitir Documento
          </button>
          <div v-if="menuDocumento" class="absolute right-0 mt-1 w-64 card p-2 z-10 shadow-lg">
            <button v-if="pedido.pode_emitir_nfe" @click="emitirNfe" class="w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-[var(--bg-elevated)]">
              <span class="font-medium">Emitir NF-e / NFC-e</span>
              <p class="text-xs text-[var(--text-muted)]">Documento fiscal, enviado à SEFAZ</p>
            </button>
            <button @click="abrirModalRecibo" class="w-full text-left px-3 py-2 text-sm rounded-lg hover:bg-[var(--bg-elevated)]">
              <span class="font-medium">Emitir Recibo (não fiscal)</span>
              <p class="text-xs text-[var(--text-muted)]">Comprovante para garantia e controle de entrega. Sem envio à SEFAZ — use para venda a pessoa física como MEI.</p>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="skeleton h-40 rounded-xl" />

    <template v-else-if="pedido">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="card p-4 lg:col-span-2">
          <h3 class="text-sm font-semibold mb-3">Itens</h3>
          <table class="table">
            <thead><tr><th>Produto</th><th class="text-right">Qtd.</th><th class="text-right">Preço</th><th class="text-right">Total</th></tr></thead>
            <tbody>
              <tr v-for="item in pedido.itens" :key="item.id">
                <td class="text-sm">{{ item.descricao || item.produto?.nome }}</td>
                <td class="text-sm text-right">{{ decimal(item.quantidade, 2) }}</td>
                <td class="text-sm text-right">{{ moeda(item.preco_unitario) }}</td>
                <td class="text-sm text-right font-medium">{{ moeda(item.total) }}</td>
              </tr>
            </tbody>
          </table>
          <div class="mt-4 pt-4 border-t border-[var(--border)] space-y-1 text-sm">
            <div class="flex justify-between text-[var(--text-muted)]"><span>Subtotal</span><span>{{ moeda(pedido.subtotal) }}</span></div>
            <div class="flex justify-between text-[var(--text-muted)]"><span>Frete</span><span>{{ moeda(pedido.frete) }}</span></div>
            <div class="flex justify-between text-[var(--text-muted)]" v-if="pedido.desconto_valor > 0"><span>Desconto</span><span>-{{ moeda(pedido.desconto_valor) }}</span></div>
            <div class="flex justify-between text-base font-semibold pt-1"><span>Total</span><span>{{ moeda(pedido.total) }}</span></div>
          </div>
        </div>

        <div class="card p-4">
          <h3 class="text-sm font-semibold mb-3">Cliente</h3>
          <dl class="space-y-2 text-sm">
            <div><dt class="text-xs text-[var(--text-muted)]">Nome</dt><dd>{{ pedido.cliente?.razao_social || pedido.cliente?.nome_fantasia || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Telefone</dt><dd>{{ pedido.cliente?.celular || pedido.cliente?.telefone || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Vendedor</dt><dd>{{ pedido.vendedor?.nome || '—' }}</dd></div>
            <div><dt class="text-xs text-[var(--text-muted)]">Canal</dt><dd class="capitalize">{{ pedido.canal }}</dd></div>
          </dl>

          <div v-if="pedido.notas_fiscais?.length" class="mt-4 pt-4 border-t border-[var(--border)]">
            <p class="text-xs text-[var(--text-muted)] mb-1">Documentos Fiscais</p>
            <router-link v-for="nf in pedido.notas_fiscais" :key="nf.id" :to="`/fiscal/nfe/${nf.id}`" class="block text-sm text-brand-400 hover:underline">
              {{ nf.tipo }} nº {{ nf.numero || '—' }}
            </router-link>
          </div>

          <div v-if="recibosEmitidos.length" class="mt-4 pt-4 border-t border-[var(--border)]">
            <p class="text-xs text-[var(--text-muted)] mb-1">Recibos Emitidos</p>
            <div v-for="r in recibosEmitidos" :key="r.id" class="flex items-center justify-between text-sm">
              <span>{{ r.numero }}</span>
              <button @click="baixarPdfRecibo(r.id)" class="btn-ghost btn-icon"><ArrowDownTrayIcon class="w-4 h-4" /></button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="empty-state"><p class="text-sm text-[var(--text-muted)]">Pedido não encontrado</p></div>

    <!-- Modal Emitir Recibo -->
    <Teleport to="body" v-if="modalRecibo">
      <div class="modal-overlay" @click.self="modalRecibo = false">
        <div class="modal-lg animate-slide-up">
          <div class="modal-header">
            <h3 class="text-base font-semibold text-[var(--text-primary)]">Emitir Recibo (não fiscal)</h3>
            <button @click="modalRecibo = false" class="btn-ghost btn-icon"><XMarkIcon class="w-4 h-4" /></button>
          </div>
          <div class="modal-body space-y-4">
            <div class="card p-3 border border-info/30 bg-info/5">
              <p class="text-xs text-[var(--text-secondary)]">
                Este documento <strong>não é uma nota fiscal</strong>. Use-o quando, como MEI, não houver
                obrigatoriedade de emissão de NF-e para o cliente (pessoa física). Ele serve como
                comprovante de compra para controle de garantia e para conferência da entrega
                pelo motorista/parceiro de transporte.
              </p>
            </div>

            <div class="form-group">
              <label class="label">Prazo de Garantia (dias)</label>
              <input v-model.number="recibo.prazo_garantia_dias" type="number" min="0" class="input w-40" />
            </div>

            <div class="border-t border-[var(--border)] pt-3">
              <h4 class="text-sm font-semibold mb-2">Entrega / Parceiro de Transporte <span class="text-xs text-[var(--text-muted)] font-normal">(opcional)</span></h4>
              <div class="form-row-3">
                <div class="form-group">
                  <label class="label">Tipo</label>
                  <select v-model="recibo.entregador_tipo" class="select">
                    <option :value="null">Não se aplica</option>
                    <option value="proprio">Entregador próprio</option>
                    <option value="uber">Motorista Uber</option>
                    <option value="app_parceiro">Parceiro de app (99, Lalamove, etc.)</option>
                    <option value="outro">Outro</option>
                  </select>
                </div>
                <div class="form-group" v-if="recibo.entregador_tipo === 'app_parceiro'">
                  <label class="label">Nome do App</label>
                  <input v-model="recibo.entregador_app_nome" class="input" placeholder="Ex: 99 Entregas" />
                </div>
                <div class="form-group">
                  <label class="label">Nome do Entregador</label>
                  <input v-model="recibo.entregador_nome" class="input" />
                </div>
              </div>
              <div class="form-row-3">
                <div class="form-group">
                  <label class="label">Documento (CPF)</label>
                  <input v-model="recibo.entregador_documento" class="input" />
                </div>
                <div class="form-group">
                  <label class="label">Telefone</label>
                  <input v-model="recibo.entregador_telefone" class="input" />
                </div>
                <div class="form-group">
                  <label class="label">Placa do Veículo</label>
                  <input v-model="recibo.veiculo_placa" class="input" />
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="label">Observações</label>
              <textarea v-model="recibo.observacoes" class="input" rows="2" />
            </div>
          </div>
          <div class="modal-footer">
            <button @click="modalRecibo = false" class="btn-secondary">Cancelar</button>
            <button @click="emitirRecibo" class="btn-primary" :disabled="processando">Emitir Recibo</button>
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
import {
  ArrowLeftIcon, XCircleIcon, DocumentTextIcon, XMarkIcon, ArrowDownTrayIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { moeda, decimal } = useFormato()

const pedido = ref(null)
const loading = ref(false)
const processando = ref(false)
const menuDocumento = ref(false)
const modalRecibo = ref(false)
const recibosEmitidos = ref([])

const recibo = ref({
  prazo_garantia_dias: 90, entregador_tipo: null, entregador_app_nome: '',
  entregador_nome: '', entregador_documento: '', entregador_telefone: '',
  veiculo_placa: '', observacoes: '',
})

async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get(`/pedidos/${route.params.id}`)
    pedido.value = data
  } catch {
    pedido.value = null
  } finally {
    loading.value = false
  }
}

async function carregarRecibos() {
  try {
    const { data } = await api.get('/recibos', { params: { busca: pedido.value?.numero } })
    recibosEmitidos.value = (data.data ?? data).filter(r => r.pedido_id === pedido.value?.id)
  } catch { /* opcional */ }
}

async function cancelar() {
  if (!confirm('Tem certeza que deseja cancelar este pedido?')) return
  processando.value = true
  try {
    const { data } = await api.post(`/pedidos/${pedido.value.id}/cancelar`)
    toast.success(data.message)
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível cancelar o pedido.')
  } finally {
    processando.value = false
  }
}

async function emitirNfe() {
  menuDocumento.value = false
  processando.value = true
  try {
    const { data } = await api.post(`/pedidos/${pedido.value.id}/emitir-nfe`)
    toast.success(data.message)
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível emitir a NF-e.')
  } finally {
    processando.value = false
  }
}

function abrirModalRecibo() {
  menuDocumento.value = false
  modalRecibo.value = true
}

async function emitirRecibo() {
  processando.value = true
  try {
    const { data } = await api.post('/recibos', { pedido_id: pedido.value.id, ...recibo.value })
    toast.success(data.message)
    modalRecibo.value = false
    await carregarRecibos()
    await baixarPdfRecibo(data.recibo.id)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível emitir o recibo.')
  } finally {
    processando.value = false
  }
}

async function baixarPdfRecibo(id) {
  try {
    const { data } = await api.get(`/recibos/${id}/pdf`)
    window.open(data.url, '_blank')
  } catch (e) {
    toast.error(e.response?.data?.message || 'PDF indisponível.')
  }
}

onMounted(async () => {
  await carregar()
  await carregarRecibos()
})
</script>
