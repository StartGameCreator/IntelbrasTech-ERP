<template>
  <div class="space-y-5 animate-fade-in max-w-4xl">
    <div class="page-header">
      <div class="flex items-center gap-4">
        <button @click="$router.back()" class="btn-ghost btn-icon"><ArrowLeftIcon class="w-5 h-5"/></button>
        <h1 class="page-title">{{ isEdit ? 'Editar OS' : 'Nova Ordem de Serviço' }}</h1>
      </div>
    </div>
    <form @submit.prevent="salvar" class="space-y-4">
      <div class="card p-5 space-y-4">
        <h3 class="text-sm font-semibold border-b border-[var(--border)] pb-2">Dados do Cliente e Equipamento</h3>
        <div class="form-row-2">
          <div class="form-group">
            <label class="label">Cliente *</label>
            <input v-model="buscaCliente" @input="filtrarClientes" class="input" placeholder="Buscar cliente..." />
            <div v-if="clientesFiltrados.length" class="card-elevated mt-1 divide-y divide-[var(--border)]">
              <button type="button" v-for="c in clientesFiltrados" :key="c.id"
                @click="selecionarCliente(c)" class="w-full text-left px-4 py-2 text-sm hover:bg-[var(--bg-elevated)]">
                {{ c.nome_fantasia || c.razao_social }}
              </button>
            </div>
          </div>
          <div class="form-group">
            <label class="label">Técnico Responsável</label>
            <select v-model="form.tecnico_id" class="select">
              <option value="">Selecione...</option>
              <option v-for="t in tecnicos" :key="t.id" :value="t.id">{{ t.nome }}</option>
            </select>
          </div>
        </div>
        <div class="form-row-3">
          <div class="form-group">
            <label class="label">Marca do Equipamento</label>
            <input v-model="form.equipamento_marca" class="input" value="Intelbras" />
          </div>
          <div class="form-group">
            <label class="label">Modelo</label>
            <input v-model="form.equipamento_modelo" class="input" placeholder="Ex: ONT 110B" />
          </div>
          <div class="form-group">
            <label class="label">Número de Série</label>
            <input v-model="form.equipamento_serie" class="input" />
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="label">Defeito Relatado pelo Cliente *</label>
            <textarea v-model="form.defeito_relatado" class="input min-h-24" required />
          </div>
          <div class="form-group">
            <label class="label">Data Previsão de Entrega</label>
            <input v-model="form.data_previsao" type="date" class="input" />
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="label">Valor do Serviço (R$)</label>
            <input v-model.number="form.valor_servico" type="number" min="0" step="0.01" class="input" />
          </div>
          <div class="form-group flex items-center gap-3 pt-6">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="form.garantia" class="rounded border-[var(--border)] bg-[var(--bg-input)] text-brand-500" />
              <span class="text-sm text-[var(--text-secondary)]">Equipamento em Garantia</span>
            </label>
          </div>
        </div>
        <div v-if="form.garantia" class="form-row-2">
          <div class="form-group">
            <label class="label">Nº NF de Compra</label>
            <input v-model="form.numero_nf_compra" class="input" />
          </div>
          <div class="form-group">
            <label class="label">Data da Compra</label>
            <input v-model="form.data_compra" type="date" class="input" />
          </div>
        </div>
        <div class="form-group">
          <label class="label">Observações Internas</label>
          <textarea v-model="form.observacoes_internas" class="input min-h-16" />
        </div>
      </div>
      <div class="flex justify-end gap-3">
        <button type="button" @click="$router.back()" class="btn-secondary">Cancelar</button>
        <button type="submit" class="btn-primary" :disabled="salvando">
          <ArrowPathIcon v-if="salvando" class="w-4 h-4 animate-spin" />
          {{ salvando ? 'Salvando...' : 'Abrir OS' }}
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
import { ArrowLeftIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'
const router = useRouter(); const route = useRoute(); const toast = useToast()
const isEdit = computed(() => !!route.params.id)
const salvando = ref(false); const tecnicos = ref([]); const clientesFiltrados = ref([]); const buscaCliente = ref('')
const form = ref({ cliente_id: null, tecnico_id: '', equipamento_marca: 'Intelbras', equipamento_modelo: '', equipamento_serie: '', defeito_relatado: '', data_previsao: '', valor_servico: 0, garantia: false, numero_nf_compra: '', data_compra: '', observacoes_internas: '' })
let debTimer = null
async function filtrarClientes() {
  clearTimeout(debTimer)
  if (!buscaCliente.value) { clientesFiltrados.value = []; return }
  debTimer = setTimeout(async () => {
    const { data } = await api.get('/clientes', { params: { busca: buscaCliente.value, per_page: 5 } })
    clientesFiltrados.value = data.data
  }, 300)
}
function selecionarCliente(c) { form.value.cliente_id = c.id; buscaCliente.value = c.nome_fantasia || c.razao_social; clientesFiltrados.value = [] }
async function salvar() {
  if (!form.value.cliente_id) { toast.warning('Selecione um cliente.'); return }
  salvando.value = true
  try {
    if (isEdit.value) { await api.put(`/os/${route.params.id}`, form.value); toast.success('OS atualizada.') }
    else { await api.post('/os', form.value); toast.success('OS aberta com sucesso.') }
    router.push('/os')
  } catch(e) { toast.error(e.response?.data?.message || 'Erro ao salvar.') }
  finally { salvando.value = false }
}
onMounted(async () => {
  const { data: users } = await api.get('/usuarios', { params: { per_page: 100 } })
  tecnicos.value = users.data || []
  if (isEdit.value) { const { data } = await api.get(`/os/${route.params.id}`); Object.assign(form.value, data) }
})
</script>
