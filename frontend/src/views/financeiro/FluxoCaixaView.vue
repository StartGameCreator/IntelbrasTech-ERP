<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Fluxo de Caixa</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ periodo.inicio && data(periodo.inicio) }} — {{ periodo.fim && data(periodo.fim) }}</p>
      </div>
      <div class="flex items-center gap-2">
        <input v-model="dataInicio" type="date" class="input" @change="carregar" />
        <input v-model="dataFim" type="date" class="input" @change="carregar" />
      </div>
    </div>

    <div v-if="loading" class="skeleton h-40 rounded-xl" />

    <template v-else>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="card p-4">
          <p class="text-xs text-[var(--text-muted)]">Entradas Realizadas</p>
          <p class="text-lg font-semibold text-success">{{ moeda(resumo.entradas_realizadas) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-[var(--text-muted)]">Saídas Realizadas</p>
          <p class="text-lg font-semibold text-danger">{{ moeda(resumo.saidas_realizadas) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-[var(--text-muted)]">Saldo Realizado</p>
          <p class="text-lg font-semibold" :class="resumo.saldo_realizado < 0 && 'text-danger'">{{ moeda(resumo.saldo_realizado) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-[var(--text-muted)]">Previsto (Entradas − Saídas)</p>
          <p class="text-lg font-semibold text-[var(--text-secondary)]">{{ moeda(resumo.entradas_previstas - resumo.saidas_previstas) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-[var(--text-muted)]">Saldo em Contas Bancárias</p>
          <p class="text-lg font-semibold">{{ moeda(resumo.saldo_contas_bancarias) }}</p>
        </div>
      </div>

      <div class="table-wrapper results-scroll">
        <table class="table">
          <thead><tr><th>Data</th><th class="text-right">Entradas</th><th class="text-right">Saídas</th><th class="text-right">Saldo do Dia</th><th class="text-right">Saldo Acumulado</th></tr></thead>
          <tbody>
            <tr v-if="!serie.length"><td colspan="5"><div class="empty-state"><p class="text-sm text-[var(--text-muted)]">Sem movimentações no período</p></div></td></tr>
            <tr v-for="dia in serie" :key="dia.data" v-else>
              <td class="text-xs">{{ data(dia.data) }}</td>
              <td class="text-sm text-right text-success">{{ dia.entradas ? moeda(dia.entradas) : '—' }}</td>
              <td class="text-sm text-right text-danger">{{ dia.saidas ? moeda(dia.saidas) : '—' }}</td>
              <td class="text-sm text-right" :class="dia.saldo_dia < 0 && 'text-danger'">{{ moeda(dia.saldo_dia) }}</td>
              <td class="text-sm text-right font-medium" :class="dia.saldo_acumulado < 0 && 'text-danger'">{{ moeda(dia.saldo_acumulado) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'

const { moeda, data } = useFormato()

const loading = ref(false)
const resumo = ref({})
const serie = ref([])
const periodo = ref({})
const dataInicio = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10))
const dataFim = ref(new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().slice(0, 10))

async function carregar() {
  loading.value = true
  try {
    const { data: resp } = await api.get('/fluxo-caixa', { params: { data_inicio: dataInicio.value, data_fim: dataFim.value } })
    resumo.value = resp.resumo
    serie.value = resp.serie_diaria
    periodo.value = resp.periodo
  } catch {
    // erros já tratados globalmente pelo interceptor do api.js
  } finally {
    loading.value = false
  }
}

onMounted(carregar)
</script>
