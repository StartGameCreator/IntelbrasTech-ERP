<template>
  <div class="space-y-5 animate-fade-in">

    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Clientes</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ paginacao.total || 0 }} cadastrados</p>
      </div>
      <div class="flex items-center gap-2">
        <RouterLink to="/clientes/mapa" class="btn-secondary btn-sm">
          <MapIcon class="w-4 h-4" /> Mapa
        </RouterLink>
        <RouterLink to="/clientes/novo" class="btn-primary btn-sm" v-if="auth.pode('clientes.create')">
          <PlusIcon class="w-4 h-4" /> Novo Cliente
        </RouterLink>
      </div>
    </div>

    <!-- Filtros -->
    <div class="card p-4 results-toolbar">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-2 relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-muted)]" />
          <input v-model="filtros.busca" type="text" placeholder="Buscar por nome, CNPJ, e-mail..."
            class="input pl-9" @input="debounceBusca" />
        </div>
        <select v-model="filtros.tipo" class="select" @change="buscar">
          <option value="">Todos os tipos</option>
          <option value="F">Pessoa Física</option>
          <option value="J">Pessoa Jurídica</option>
        </select>
        <select v-model="filtros.ativo" class="select" @change="buscar">
          <option value="">Todos</option>
          <option value="true">Ativos</option>
          <option value="false">Inativos</option>
        </select>
      </div>
      <div class="flex items-center gap-3 mt-3">
        <select v-model="filtros.estado" class="select w-40" @change="buscar">
          <option value="">Todos os estados</option>
          <option v-for="uf in estados" :key="uf" :value="uf">{{ uf }}</option>
        </select>
        <button v-if="temFiltros" @click="limparFiltros" class="btn-ghost btn-sm text-danger">
          <XMarkIcon class="w-4 h-4" /> Limpar filtros
        </button>
        <span class="ml-auto text-xs text-[var(--text-muted)]">
          {{ paginacao.total }} resultados
        </span>
      </div>
    </div>

    <!-- Tabela -->
    <div class="table-wrapper results-scroll">
      <table class="table">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>CPF/CNPJ</th>
            <th>Contato</th>
            <th>Cidade/UF</th>
            <th>Status</th>
            <th class="text-right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <!-- Skeleton -->
          <template v-if="loading">
            <tr v-for="i in 8" :key="i">
              <td><div class="flex items-center gap-3">
                <div class="skeleton w-8 h-8 rounded-full" />
                <div class="space-y-1.5">
                  <div class="skeleton h-3 w-32 rounded" />
                  <div class="skeleton h-2.5 w-20 rounded" />
                </div>
              </div></td>
              <td><div class="skeleton h-3 w-28 rounded" /></td>
              <td><div class="skeleton h-3 w-32 rounded" /></td>
              <td><div class="skeleton h-3 w-24 rounded" /></td>
              <td><div class="skeleton h-5 w-16 rounded-full" /></td>
              <td><div class="skeleton h-7 w-20 rounded ml-auto" /></td>
            </tr>
          </template>

          <!-- Dados -->
          <tr v-else v-for="cliente in clientes" :key="cliente.id"
            @click="$router.push(`/clientes/${cliente.id}`)" class="cursor-pointer">
            <td>
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-brand-500/20 flex items-center justify-center flex-shrink-0">
                  <span class="text-brand-400 font-semibold text-xs">
                    {{ (cliente.nome_fantasia || cliente.razao_social)?.charAt(0)?.toUpperCase() }}
                  </span>
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-medium text-[var(--text-primary)] truncate max-w-[200px]">
                    {{ cliente.nome_fantasia || cliente.razao_social }}
                  </p>
                  <p v-if="cliente.nome_fantasia" class="text-xs text-[var(--text-muted)] truncate max-w-[200px]">
                    {{ cliente.razao_social }}
                  </p>
                </div>
              </div>
            </td>
            <td class="font-mono text-xs text-[var(--text-secondary)]">
              {{ formatarDoc(cliente.cpf_cnpj) }}
            </td>
            <td>
              <p class="text-xs text-[var(--text-secondary)]">{{ cliente.email }}</p>
              <p class="text-xs text-[var(--text-muted)]">{{ cliente.celular || cliente.telefone }}</p>
            </td>
            <td class="text-xs text-[var(--text-secondary)]">
              {{ cliente.cidade }}{{ cliente.estado ? `/${cliente.estado}` : '' }}
            </td>
            <td>
              <div class="flex items-center gap-2">
                <span :class="cliente.ativo ? 'badge-success' : 'badge-gray'" class="badge">
                  {{ cliente.ativo ? 'Ativo' : 'Inativo' }}
                </span>
                <span v-if="cliente.bloqueado" class="badge badge-danger">Bloqueado</span>
              </div>
            </td>
            <td class="text-right" @click.stop>
              <div class="flex items-center justify-end gap-1">
                <RouterLink :to="`/clientes/${cliente.id}`" class="btn-ghost btn-icon" title="Ver">
                  <EyeIcon class="w-4 h-4" />
                </RouterLink>
                <RouterLink :to="`/clientes/${cliente.id}/editar`" class="btn-ghost btn-icon" title="Editar"
                  v-if="auth.pode('clientes.edit')">
                  <PencilIcon class="w-4 h-4" />
                </RouterLink>
                <button @click="confirmarExclusao(cliente)" class="btn-ghost btn-icon text-danger hover:text-danger"
                  title="Excluir" v-if="auth.pode('clientes.delete')">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </td>
          </tr>

          <!-- Empty -->
          <tr v-if="!loading && !clientes.length">
            <td colspan="6">
              <div class="empty-state">
                <UsersIcon class="empty-state-icon" />
                <p class="text-sm font-medium text-[var(--text-secondary)]">Nenhum cliente encontrado</p>
                <p class="text-xs text-[var(--text-muted)] mt-1">
                  {{ temFiltros ? 'Tente ajustar os filtros' : 'Cadastre seu primeiro cliente' }}
                </p>
                <RouterLink v-if="!temFiltros && auth.pode('clientes.create')"
                  to="/clientes/novo" class="btn-primary btn-sm mt-4">
                  <PlusIcon class="w-4 h-4" /> Novo Cliente
                </RouterLink>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginação -->
    <div v-if="paginacao.last_page > 1" class="flex items-center justify-between">
      <p class="text-xs text-[var(--text-muted)]">
        Página {{ paginacao.current_page }} de {{ paginacao.last_page }}
        · {{ paginacao.total }} registros
      </p>
      <div class="flex items-center gap-1">
        <button :disabled="paginacao.current_page <= 1" @click="mudarPagina(paginacao.current_page - 1)"
          class="btn-secondary btn-sm">
          <ChevronLeftIcon class="w-4 h-4" />
        </button>
        <template v-for="p in paginasVisiveis" :key="p">
          <span v-if="p === '...'" class="px-2 text-[var(--text-muted)]">…</span>
          <button v-else @click="mudarPagina(p)"
            :class="['btn-sm', p === paginacao.current_page ? 'btn-primary' : 'btn-secondary']">
            {{ p }}
          </button>
        </template>
        <button :disabled="paginacao.current_page >= paginacao.last_page"
          @click="mudarPagina(paginacao.current_page + 1)" class="btn-secondary btn-sm">
          <ChevronRightIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Modal exclusão -->
    <ModalConfirmar
      v-if="clienteExcluir"
      titulo="Excluir Cliente"
      :mensagem="`Confirma a exclusão de ${clienteExcluir.razao_social}? Esta ação não pode ser desfeita.`"
      tipo="danger"
      @confirmar="excluir"
      @cancelar="clienteExcluir = null"
    />

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import ModalConfirmar from '@/components/ui/ModalConfirmar.vue'
import {
  PlusIcon, MagnifyingGlassIcon, MapIcon, EyeIcon,
  PencilIcon, TrashIcon, UsersIcon, XMarkIcon,
  ChevronLeftIcon, ChevronRightIcon,
} from '@heroicons/vue/24/outline'

const auth  = useAuthStore()
const toast = useToast()

const clientes       = ref([])
const loading        = ref(false)
const clienteExcluir = ref(null)
const paginacao      = ref({ current_page: 1, last_page: 1, total: 0 })
const debounceTimer  = ref(null)

const filtros = ref({ busca: '', tipo: '', ativo: '', estado: '', page: 1 })

const estados = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG',
  'PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']

const temFiltros = computed(() =>
  filtros.value.busca || filtros.value.tipo || filtros.value.ativo !== '' || filtros.value.estado
)

const paginasVisiveis = computed(() => {
  const total = paginacao.value.last_page
  const atual = paginacao.value.current_page
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  if (atual <= 4) return [1,2,3,4,5,'...',total]
  if (atual >= total - 3) return [1,'...',total-4,total-3,total-2,total-1,total]
  return [1,'...',atual-1,atual,atual+1,'...',total]
})

function formatarDoc(doc) {
  if (!doc) return '—'
  const d = doc.replace(/\D/g, '')
  if (d.length === 11) return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4')
  return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5')
}

async function buscar() {
  loading.value = true
  try {
    const params = { page: filtros.value.page, per_page: 15 }
    if (filtros.value.busca) params.busca = filtros.value.busca
    if (filtros.value.tipo)  params.tipo  = filtros.value.tipo
    if (filtros.value.ativo !== '') params.ativo = filtros.value.ativo
    if (filtros.value.estado) params.estado = filtros.value.estado

    const { data } = await api.get('/clientes', { params })
    clientes.value = data.data
    paginacao.value = {
      current_page: data.current_page,
      last_page: data.last_page,
      total: data.total,
    }
  } finally {
    loading.value = false
  }
}

function debounceBusca() {
  clearTimeout(debounceTimer.value)
  debounceTimer.value = setTimeout(() => {
    filtros.value.page = 1
    buscar()
  }, 400)
}

function mudarPagina(p) {
  filtros.value.page = p
  buscar()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function limparFiltros() {
  filtros.value = { busca: '', tipo: '', ativo: '', estado: '', page: 1 }
  buscar()
}

function confirmarExclusao(cliente) {
  clienteExcluir.value = cliente
}

async function excluir() {
  try {
    await api.delete(`/clientes/${clienteExcluir.value.id}`)
    toast.success('Cliente excluído com sucesso.')
    clienteExcluir.value = null
    buscar()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Erro ao excluir cliente.')
  }
}

onMounted(buscar)
</script>
