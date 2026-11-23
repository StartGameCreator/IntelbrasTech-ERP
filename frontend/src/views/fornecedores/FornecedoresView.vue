<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Fornecedores</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ total }} registros</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="carregar" class="btn-secondary btn-sm" :disabled="loading">
          <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
        </button>
        <router-link to="/fornecedores/novo" class="btn-primary btn-sm">
          <PlusIcon class="w-4 h-4" /> Novo Fornecedor
        </router-link>
      </div>
    </div>

    <div class="card p-4 results-toolbar">
      <div class="flex flex-wrap gap-3">
        <div class="flex-1 min-w-[220px] relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-muted)]" />
          <input v-model="busca" type="text" placeholder="Buscar por razão social, CNPJ ou e-mail..." class="input pl-9" @input="debounceBusca" />
        </div>
        <select v-model="filtroTipo" class="input w-40" @change="carregar">
          <option value="">Todos os tipos</option>
          <option value="J">Pessoa Jurídica</option>
          <option value="F">Pessoa Física</option>
        </select>
      </div>
    </div>

    <div class="table-wrapper results-scroll">
      <table class="table">
        <thead><tr>
          <th>Razão Social</th><th>CPF/CNPJ</th><th>Cidade/UF</th>
          <th>Contato</th><th class="text-right">Avaliação</th><th>Status</th><th class="text-right">Ações</th>
        </tr></thead>
        <tbody>
          <tr v-if="loading"><td colspan="7"><div class="skeleton h-8 rounded m-2" /></td></tr>
          <tr v-else-if="!items.length"><td colspan="7">
            <div class="empty-state"><p class="text-sm text-[var(--text-muted)]">Nenhum fornecedor encontrado</p></div>
          </td></tr>
          <tr v-else v-for="item in items" :key="item.id" class="cursor-pointer" @click="verItem(item.id)">
            <td class="text-sm font-medium text-[var(--text-primary)]">{{ item.razao_social }}</td>
            <td class="text-xs">{{ cpfCnpj(item.cpf_cnpj) }}</td>
            <td class="text-xs text-[var(--text-muted)]">{{ item.cidade ? `${item.cidade}/${item.estado}` : '—' }}</td>
            <td class="text-xs text-[var(--text-muted)]">{{ item.telefone || item.celular || '—' }}</td>
            <td class="text-right">
              <span v-if="item.avaliacao" class="text-warning">{{ '★'.repeat(item.avaliacao) }}<span class="text-[var(--text-muted)]">{{ '★'.repeat(5 - item.avaliacao) }}</span></span>
              <span v-else class="text-xs text-[var(--text-muted)]">—</span>
            </td>
            <td><span class="badge" :class="item.ativo ? 'badge-success' : 'badge-gray'">{{ item.ativo ? 'Ativo' : 'Inativo' }}</span></td>
            <td class="text-right" @click.stop>
              <router-link :to="`/fornecedores/${item.id}/editar`" class="btn-ghost btn-icon"><PencilIcon class="w-4 h-4" /></router-link>
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
import { ArrowPathIcon, MagnifyingGlassIcon, EyeIcon, PencilIcon, PlusIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const { cpfCnpj } = useFormato()

const items = ref([])
const total = ref(0)
const loading = ref(false)
const busca = ref('')
const filtroTipo = ref('')
let timer = null

async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get('/fornecedores', {
      params: { busca: busca.value, tipo: filtroTipo.value, per_page: 15 },
    })
    items.value = data.data ?? data
    total.value = data.total ?? items.value.length
  } catch {
    // erros já tratados globalmente pelo interceptor do api.js
  } finally {
    loading.value = false
  }
}

function debounceBusca() { clearTimeout(timer); timer = setTimeout(carregar, 400) }
function verItem(id) { router.push('/fornecedores/' + id) }
onMounted(carregar)
</script>
