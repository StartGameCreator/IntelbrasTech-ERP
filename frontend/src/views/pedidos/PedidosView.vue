<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Pedidos</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ total }} registros</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="carregar" class="btn-secondary btn-sm" :disabled="loading">
          <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
        </button>
      </div>
    </div>
    <div class="card p-4 results-toolbar">
      <div class="flex gap-3">
        <div class="flex-1 relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-muted)]" />
          <input v-model="busca" type="text" placeholder="Buscar..." class="input pl-9" @input="debounceBusca" />
        </div>
      </div>
    </div>
    <div class="table-wrapper results-scroll">
      <table class="table">
        <thead><tr>
          <th>Nome</th><th>Status</th><th class="text-right">Ações</th>
        </tr></thead>
        <tbody>
          <tr v-if="loading"><td colspan="3"><div class="skeleton h-8 rounded m-2" /></td></tr>
          <tr v-else-if="!items.length"><td colspan="3">
            <div class="empty-state"><p class="text-sm text-[var(--text-muted)]">Nenhum registro encontrado</p></div>
          </td></tr>
          <tr v-else v-for="item in items" :key="item.id">
            <td class="text-sm">{{ item.nome || item.razao_social || item.numero || item.descricao }}</td>
            <td><BadgeStatus :status="item.status || item.ativo ? 'confirmado' : 'cancelado'" /></td>
            <td class="text-right">
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
import BadgeStatus from '@/components/ui/BadgeStatus.vue'
import { ArrowPathIcon, MagnifyingGlassIcon, EyeIcon } from '@heroicons/vue/24/outline'
const router = useRouter()
const items = ref([])
const total = ref(0)
const loading = ref(false)
const busca = ref('')
let timer = null
async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get('/pedidos', { params: { busca: busca.value, per_page: 15 } })
    items.value = data.data ?? data
    total.value = data.total ?? items.value.length
  } catch {} finally { loading.value = false }
}
function debounceBusca() { clearTimeout(timer); timer = setTimeout(carregar, 400) }
function verItem(id) { router.push('/pedidos/' + id) }
onMounted(carregar)
</script>
