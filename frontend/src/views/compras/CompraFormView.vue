<template>
  <div class="space-y-5 animate-fade-in max-w-5xl">
    <div class="page-header">
      <div class="flex items-center gap-4">
        <button @click="$router.back()" class="btn-ghost btn-icon"><ArrowLeftIcon class="w-5 h-5" /></button>
        <h1 class="page-title">Novo Pedido de Compra</h1>
      </div>
    </div>

    <form @submit.prevent="salvar" class="space-y-4">
      <div class="card p-5 space-y-4">
        <h3 class="text-sm font-semibold border-b border-[var(--border)] pb-2">Dados Gerais</h3>
        <div class="form-row-3">
          <div class="form-group">
            <label class="label">Fornecedor</label>
            <select v-model.number="form.fornecedor_id" class="select">
              <option :value="null">Selecione</option>
              <option v-for="f in fornecedores" :key="f.id" :value="f.id">{{ f.razao_social }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="label">Depósito de Destino</label>
            <select v-model.number="form.deposito_id" class="select">
              <option :value="null">Selecione</option>
              <option v-for="d in depositos" :key="d.id" :value="d.id">{{ d.nome }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="label">Previsão de Entrega</label>
            <input v-model="form.data_previsao" type="date" class="input" />
          </div>
        </div>
      </div>

      <div class="card p-5 space-y-4">
        <div class="flex items-center justify-between border-b border-[var(--border)] pb-2">
          <h3 class="text-sm font-semibold">Itens</h3>
          <button type="button" @click="adicionarItem" class="btn-secondary btn-sm"><PlusIcon class="w-4 h-4" /> Adicionar Item</button>
        </div>

        <div v-if="!form.itens.length" class="empty-state"><p class="text-xs text-[var(--text-muted)]">Nenhum item adicionado</p></div>

        <div v-for="(item, i) in form.itens" :key="i" class="grid grid-cols-12 gap-2 items-end border-b border-[var(--border)] pb-3">
          <div class="col-span-4 form-group !mb-0">
            <label class="label">Produto (ID)</label>
            <input v-model.number="item.produto_id" type="number" class="input" placeholder="ID do produto" />
          </div>
          <div class="col-span-2 form-group !mb-0">
            <label class="label">Qtd.</label>
            <input v-model.number="item.quantidade" type="number" step="0.001" min="0.001" class="input" />
          </div>
          <div class="col-span-2 form-group !mb-0">
            <label class="label">Preço Unit.</label>
            <input v-model.number="item.preco_unitario" type="number" step="0.01" min="0" class="input" />
          </div>
          <div class="col-span-2 form-group !mb-0">
            <label class="label">Desc. (%)</label>
            <input v-model.number="item.desconto_percentual" type="number" step="0.01" min="0" max="100" class="input" />
          </div>
          <div class="col-span-1 text-sm text-right font-medium pb-2">{{ moeda(totalItem(item)) }}</div>
          <div class="col-span-1 text-right pb-1">
            <button type="button" @click="form.itens.splice(i, 1)" class="btn-ghost btn-icon text-danger"><TrashIcon class="w-4 h-4" /></button>
          </div>
        </div>
      </div>

      <div class="card p-5 space-y-4">
        <h3 class="text-sm font-semibold border-b border-[var(--border)] pb-2">Totais</h3>
        <div class="form-row-3">
          <div class="form-group">
            <label class="label">Frete</label>
            <input v-model.number="form.frete" type="number" step="0.01" min="0" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Outras Despesas</label>
            <input v-model.number="form.outras_despesas" type="number" step="0.01" min="0" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Desconto (R$)</label>
            <input v-model.number="form.desconto_valor" type="number" step="0.01" min="0" class="input" />
          </div>
        </div>
        <div class="flex items-center justify-between pt-2 border-t border-[var(--border)]">
          <span class="text-sm text-[var(--text-muted)]">Subtotal: {{ moeda(subtotal) }}</span>
          <span class="text-lg font-semibold">Total: {{ moeda(totalGeral) }}</span>
        </div>
        <div class="form-group">
          <label class="label">Observações</label>
          <textarea v-model="form.observacoes" class="input min-h-20" />
        </div>
      </div>

      <div class="flex items-center justify-end gap-3">
        <button type="button" @click="$router.back()" class="btn-secondary">Cancelar</button>
        <button type="submit" class="btn-primary" :disabled="salvando || !form.itens.length">
          <ArrowPathIcon v-if="salvando" class="w-4 h-4 animate-spin" />
          {{ salvando ? 'Salvando...' : 'Salvar Pedido de Compra' }}
        </button>
      </div>
    </form>
  </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowLeftIcon, ArrowPathIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const toast = useToast()
const { moeda } = useFormato()

const salvando = ref(false)
const fornecedores = ref([])
const depositos = ref([])

const form = ref({
  fornecedor_id: null, deposito_id: null, data_previsao: '',
  frete: 0, outras_despesas: 0, desconto_valor: 0, observacoes: '',
  itens: [],
})

function adicionarItem() {
  form.value.itens.push({ produto_id: null, quantidade: 1, preco_unitario: 0, desconto_percentual: 0 })
}

function totalItem(item) {
  const bruto = (item.quantidade || 0) * (item.preco_unitario || 0)
  return bruto - (bruto * ((item.desconto_percentual || 0) / 100))
}

const subtotal = computed(() => form.value.itens.reduce((s, i) => s + totalItem(i), 0))
const totalGeral = computed(() => subtotal.value + (form.value.frete || 0) + (form.value.outras_despesas || 0) - (form.value.desconto_valor || 0))

async function carregarAuxiliares() {
  try {
    const [{ data: forns }, { data: deps }] = await Promise.all([
      api.get('/fornecedores', { params: { per_page: 100 } }),
      api.get('/depositos'),
    ])
    fornecedores.value = forns.data ?? forns
    depositos.value = deps
  } catch { /* opcional */ }
}

async function salvar() {
  salvando.value = true
  try {
    const { data } = await api.post('/pedidos-compra', form.value)
    toast.success(data.message)
    router.push(`/compras/${data.data.id}`)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível salvar o pedido de compra.')
  } finally {
    salvando.value = false
  }
}

onMounted(() => {
  carregarAuxiliares()
  adicionarItem()
})
</script>
