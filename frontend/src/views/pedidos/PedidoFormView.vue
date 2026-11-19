<template>
  <div class="space-y-5 animate-fade-in max-w-5xl">
    <div class="page-header">
      <div class="flex items-center gap-4">
        <button @click="$router.back()" class="btn-ghost btn-icon"><ArrowLeftIcon class="w-5 h-5"/></button>
        <h1 class="page-title">{{ isEdit ? 'Editar Pedido' : 'Novo Pedido' }}</h1>
      </div>
    </div>
    <form @submit.prevent="salvar" class="space-y-4">
      <!-- Cliente e canal -->
      <div class="card p-5 space-y-4">
        <h3 class="text-sm font-semibold border-b border-[var(--border)] pb-2">Dados do Pedido</h3>
        <div class="form-row-3">
          <div class="form-group col-span-2">
            <label class="label">Cliente</label>
            <input v-model="buscaCliente" @input="filtrarClientes" class="input" placeholder="Buscar cliente..." />
            <div v-if="clientesFiltrados.length" class="card-elevated mt-1 divide-y divide-[var(--border)] z-10 relative">
              <button type="button" v-for="c in clientesFiltrados" :key="c.id"
                @click="selecionarCliente(c)"
                class="w-full text-left px-4 py-2 text-sm hover:bg-[var(--bg-elevated)]">
                {{ c.nome_fantasia || c.razao_social }}
                <span class="text-xs text-[var(--text-muted)] ml-2">{{ c.cpf_cnpj }}</span>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label class="label">Canal</label>
            <select v-model="form.canal" class="select">
              <option value="interno">Interno</option>
              <option value="marketplace">Marketplace</option>
              <option value="ecommerce">E-commerce</option>
              <option value="whatsapp">WhatsApp</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Itens -->
      <div class="card p-5 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold">Itens do Pedido</h3>
          <button type="button" @click="adicionarItem" class="btn-secondary btn-sm">
            <PlusIcon class="w-4 h-4"/> Adicionar Item
          </button>
        </div>
        <div class="table-wrapper">
          <table class="table">
            <thead><tr>
              <th>Produto</th><th>Qtd</th><th>Preço Unit.</th><th>Desconto</th><th>Total</th><th></th>
            </tr></thead>
            <tbody>
              <tr v-for="(item,i) in form.itens" :key="i">
                <td><input v-model="item.descricao" class="input input-sm" placeholder="Produto..." /></td>
                <td><input v-model.number="item.quantidade" type="number" min="0.001" step="0.001" class="input input-sm w-20" @input="calcItem(i)" /></td>
                <td><input v-model.number="item.preco_unitario" type="number" min="0" step="0.01" class="input input-sm w-28" @input="calcItem(i)" /></td>
                <td><input v-model.number="item.desconto_percentual" type="number" min="0" max="100" step="0.01" class="input input-sm w-20" @input="calcItem(i)" /></td>
                <td class="font-semibold text-sm">{{ fmt.moeda(item.total) }}</td>
                <td><button type="button" @click="removerItem(i)" class="btn-ghost btn-icon text-danger"><TrashIcon class="w-4 h-4"/></button></td>
              </tr>
              <tr v-if="!form.itens.length"><td colspan="6" class="text-center text-xs text-[var(--text-muted)] py-4">Nenhum item adicionado</td></tr>
            </tbody>
          </table>
        </div>
        <!-- Totais -->
        <div class="flex flex-col items-end gap-2 pt-2 border-t border-[var(--border)]">
          <div class="flex items-center gap-8 text-sm">
            <span class="text-[var(--text-muted)]">Subtotal:</span>
            <span class="font-semibold w-32 text-right">{{ fmt.moeda(subtotal) }}</span>
          </div>
          <div class="flex items-center gap-8 text-sm">
            <span class="text-[var(--text-muted)]">Desconto:</span>
            <input v-model.number="form.desconto_valor" type="number" class="input input-sm w-32" @input="calcTotal" />
          </div>
          <div class="flex items-center gap-8 text-sm">
            <span class="text-[var(--text-muted)]">Frete:</span>
            <input v-model.number="form.frete" type="number" class="input input-sm w-32" @input="calcTotal" />
          </div>
          <div class="flex items-center gap-8 text-base font-bold">
            <span>Total:</span>
            <span class="text-brand-400 w-32 text-right">{{ fmt.moeda(totalGeral) }}</span>
          </div>
        </div>
      </div>

      <div class="card p-5">
        <h3 class="text-sm font-semibold border-b border-[var(--border)] pb-2 mb-4">Observações</h3>
        <div class="form-row-2">
          <div class="form-group">
            <label class="label">Observações</label>
            <textarea v-model="form.observacoes" class="input min-h-20" />
          </div>
          <div class="form-group">
            <label class="label">Observações NF-e</label>
            <textarea v-model="form.observacoes_nfe" class="input min-h-20" />
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-3">
        <button type="button" @click="$router.back()" class="btn-secondary">Cancelar</button>
        <button type="submit" class="btn-primary" :disabled="salvando">
          <ArrowPathIcon v-if="salvando" class="w-4 h-4 animate-spin"/>
          {{ salvando ? 'Salvando...' : 'Salvar Pedido' }}
        </button>
      </div>
    </form>
  </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowLeftIcon, ArrowPathIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
const router = useRouter(); const route = useRoute(); const toast = useToast(); const fmt = useFormato()
const isEdit = computed(() => !!route.params.id)
const salvando = ref(false)
const buscaCliente = ref(''); const clientesFiltrados = ref([])
const form = ref({ cliente_id: null, canal: 'interno', frete: 0, desconto_valor: 0, observacoes: '', observacoes_nfe: '', itens: [] })
const subtotal = computed(() => form.value.itens.reduce((s,i) => s + (i.total||0), 0))
const totalGeral = computed(() => subtotal.value - (form.value.desconto_valor||0) + (form.value.frete||0))
function calcItem(i) {
  const item = form.value.itens[i]
  const bruto = (item.quantidade||0) * (item.preco_unitario||0)
  const desc  = bruto * ((item.desconto_percentual||0)/100)
  item.total  = Math.max(0, bruto - desc)
}
function calcTotal() {}
function adicionarItem() {
  form.value.itens.push({ produto_id: null, descricao: '', quantidade: 1, preco_unitario: 0, desconto_percentual: 0, total: 0 })
}
function removerItem(i) { form.value.itens.splice(i, 1) }
let debTimer = null
async function filtrarClientes() {
  clearTimeout(debTimer)
  if (!buscaCliente.value) { clientesFiltrados.value = []; return }
  debTimer = setTimeout(async () => {
    const { data } = await api.get('/clientes', { params: { busca: buscaCliente.value, per_page: 5 } })
    clientesFiltrados.value = data.data
  }, 300)
}
function selecionarCliente(c) {
  form.value.cliente_id = c.id; buscaCliente.value = c.nome_fantasia || c.razao_social; clientesFiltrados.value = []
}
async function salvar() {
  if (!form.value.itens.length) { toast.warning('Adicione ao menos um item.'); return }
  salvando.value = true
  try {
    const payload = { ...form.value, itens: form.value.itens.map(i => ({ ...i, produto_id: i.produto_id || 0 })) }
    if (isEdit.value) { await api.put(`/pedidos/${route.params.id}`, payload); toast.success('Pedido atualizado.') }
    else { await api.post('/pedidos', payload); toast.success('Pedido criado.') }
    router.push('/pedidos')
  } catch(e) { toast.error(e.response?.data?.message || 'Erro ao salvar.') }
  finally { salvando.value = false }
}
onMounted(async () => {
  if (isEdit.value) {
    const { data } = await api.get(`/pedidos/${route.params.id}`)
    Object.assign(form.value, data)
    buscaCliente.value = data.cliente?.nome_fantasia || data.cliente?.razao_social || ''
  }
})
</script>
