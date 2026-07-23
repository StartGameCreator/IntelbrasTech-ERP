<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <button @click="$router.push('/estoque')" class="text-xs text-[var(--text-muted)] hover:text-[var(--text-primary)] flex items-center gap-1 mb-1">
          <ArrowLeftIcon class="w-3.5 h-3.5" /> Voltar para Estoque
        </button>
        <h1 class="page-title">Movimentações de Estoque</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ total }} registros</p>
      </div>
      <button @click="carregar" class="btn-secondary btn-sm" :disabled="loading">
        <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
      </button>
    </div>

    <div class="card p-4">
      <div class="flex flex-wrap gap-3">
        <select v-model="filtroTipo" class="input w-44" @change="carregar">
          <option value="">Todos os tipos</option>
          <option value="entrada">Entrada</option>
          <option value="saida">Saída</option>
          <option value="transferencia">Transferência</option>
          <option value="ajuste">Ajuste</option>
        </select>
      </div>
    </div>

    <div class="table-wrapper">
      <table class="table">
        <thead><tr>
          <th>Data</th><th>Produto</th><th>Tipo</th><th>Motivo</th>
          <th>Depósito</th><th class="text-right">Quantidade</th>
          <th class="text-right">Saldo Anterior</th><th class="text-right">Saldo Posterior</th><th>Usuário</th>
        </tr></thead>
        <tbody>
          <tr v-if="loading"><td colspan="9"><div class="skeleton h-8 rounded m-2" /></td></tr>
          <tr v-else-if="!items.length"><td colspan="9">
            <div class="empty-state"><p class="text-sm text-[var(--text-muted)]">Nenhuma movimentação encontrada</p></div>
          </td></tr>
          <tr v-else v-for="item in items" :key="item.id">
            <td class="text-xs text-[var(--text-muted)]">{{ dataHora(item.created_at) }}</td>
            <td class="text-sm">{{ item.produto?.nome || '—' }}</td>
            <td>
              <span class="badge" :class="{
                'badge-success': item.tipo === 'entrada',
                'badge-danger': item.tipo === 'saida',
                'badge-info': item.tipo === 'transferencia',
                'badge-gray': item.tipo === 'ajuste',
              }">{{ tiposLabel[item.tipo] || item.tipo }}</span>
            </td>
            <td class="text-xs text-[var(--text-muted)] capitalize">{{ item.motivo || '—' }}</td>
            <td class="text-xs">{{ item.deposito?.nome }}<span v-if="item.deposito_destino"> → {{ item.deposito_destino.nome }}</span></td>
            <td class="text-sm text-right font-medium">{{ decimal(item.quantidade, 3) }}</td>
            <td class="text-xs text-right text-[var(--text-muted)]">{{ decimal(item.saldo_anterior, 3) }}</td>
            <td class="text-xs text-right text-[var(--text-muted)]">{{ decimal(item.saldo_posterior, 3) }}</td>
            <td class="text-xs text-[var(--text-muted)]">{{ item.usuario?.nome || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowPathIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

const { decimal, dataHora } = useFormato()

const items = ref([])
const total = ref(0)
const loading = ref(false)
const filtroTipo = ref('')

const tiposLabel = { entrada: 'Entrada', saida: 'Saída', transferencia: 'Transferência', ajuste: 'Ajuste' }

async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get('/movimentacoes', { params: { tipo: filtroTipo.value, per_page: 20 } })
    items.value = data.data ?? data
    total.value = data.total ?? items.value.length
  } catch {
    // erros já tratados globalmente pelo interceptor do api.js
  } finally {
    loading.value = false
  }
}

onMounted(carregar)
</script>
