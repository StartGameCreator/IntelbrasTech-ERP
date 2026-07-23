<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Notas Fiscais</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ total }} registros</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="carregar" class="btn-secondary btn-sm" :disabled="loading">
          <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
        </button>
        <router-link to="/pedidos" class="btn-primary btn-sm">
          <PlusIcon class="w-4 h-4" /> Emitir a partir de Pedido
        </router-link>
      </div>
    </div>

    <div class="card p-4">
      <div class="flex flex-wrap gap-3">
        <div class="flex-1 min-w-[220px] relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-muted)]" />
          <input v-model="busca" type="text" placeholder="Buscar por número, chave ou destinatário..." class="input pl-9" @input="debounceBusca" />
        </div>
        <select v-model="filtroTipo" class="input w-40" @change="carregar">
          <option value="">Todos os tipos</option>
          <option value="NFe">NF-e</option>
          <option value="NFCe">NFC-e</option>
          <option value="NFSe">NFS-e</option>
        </select>
        <select v-model="filtroStatus" class="input w-44" @change="carregar">
          <option value="">Todos os status</option>
          <option value="rascunho">Rascunho</option>
          <option value="processando">Processando</option>
          <option value="autorizada">Autorizada</option>
          <option value="cancelada">Cancelada</option>
          <option value="rejeitada">Rejeitada</option>
        </select>
      </div>
    </div>

    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Número / Série</th>
            <th>Destinatário</th>
            <th>Ambiente</th>
            <th class="text-right">Valor Total</th>
            <th>Status</th>
            <th>Emissão</th>
            <th class="text-right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="7"><div class="skeleton h-8 rounded m-2" /></td></tr>
          <tr v-else-if="!items.length"><td colspan="7">
            <div class="empty-state">
              <p class="text-sm text-[var(--text-muted)]">Nenhuma NF-e encontrada</p>
            </div>
          </td></tr>
          <tr v-else v-for="item in items" :key="item.id" class="cursor-pointer" @click="verItem(item.id)">
            <td class="text-sm font-medium text-[var(--text-primary)]">
              {{ item.tipo }} {{ item.numero ?? '—' }}<span v-if="item.serie" class="text-[var(--text-muted)]"> / {{ item.serie }}</span>
            </td>
            <td class="text-sm">
              {{ item.destinatario_nome || item.pedido?.cliente?.razao_social || item.pedido?.cliente?.nome_fantasia || '—' }}
            </td>
            <td class="text-xs text-[var(--text-muted)]">{{ item.ambiente === 1 ? 'Produção' : 'Homologação' }}</td>
            <td class="text-sm text-right font-medium">{{ moeda(item.valor_total) }}</td>
            <td><BadgeStatus :status="item.status" /></td>
            <td class="text-xs text-[var(--text-muted)]">{{ data(item.data_emissao) }}</td>
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
import BadgeStatus from '@/components/ui/BadgeStatus.vue'
import { useFormato } from '@/composables/useFormato'
import { ArrowPathIcon, MagnifyingGlassIcon, EyeIcon, PlusIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const { moeda, data } = useFormato()

const items = ref([])
const total = ref(0)
const loading = ref(false)
const busca = ref('')
const filtroTipo = ref('')
const filtroStatus = ref('')
let timer = null

async function carregar() {
  loading.value = true
  try {
    const { data: resp } = await api.get('/nfe', {
      params: {
        busca: busca.value,
        tipo: filtroTipo.value,
        status: filtroStatus.value,
        per_page: 15,
      },
    })
    items.value = resp.data ?? resp
    total.value = resp.total ?? items.value.length
  } catch {
    // erros de rede/permissão já são tratados globalmente pelo interceptor do api.js
  } finally {
    loading.value = false
  }
}

function debounceBusca() { clearTimeout(timer); timer = setTimeout(carregar, 400) }
function verItem(id) { router.push('/fiscal/nfe/' + id) }
onMounted(carregar)
</script>
