<template>
  <div class="cashflow-page animate-fade-in">
    <div class="page-header cashflow-header">
      <div>
        <h1 class="page-title">Fluxo de Caixa</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ periodo.inicio && data(periodo.inicio) }} — {{ periodo.fim && data(periodo.fim) }}</p>
      </div>
      <div class="cashflow-period flex items-center gap-2">
        <input v-model="dataInicio" type="date" class="input" @change="carregar" />
        <input v-model="dataFim" type="date" class="input" @change="carregar" />
      </div>
    </div>

    <div v-if="loading" class="skeleton h-40 rounded-xl" />

    <template v-else>
      <div class="cashflow-summary grid grid-cols-2 lg:grid-cols-5 gap-2 sm:gap-4">
        <div class="cashflow-card card p-4">
          <p class="text-xs text-[var(--text-muted)]">Entradas Realizadas</p>
          <p class="cashflow-value text-lg font-semibold text-success">{{ moeda(resumo.entradas_realizadas) }}</p>
        </div>
        <div class="cashflow-card card p-4">
          <p class="text-xs text-[var(--text-muted)]">Saídas Realizadas</p>
          <p class="cashflow-value text-lg font-semibold text-danger">{{ moeda(resumo.saidas_realizadas) }}</p>
        </div>
        <div class="cashflow-card card p-4">
          <p class="text-xs text-[var(--text-muted)]">Saldo Realizado</p>
          <p class="cashflow-value text-lg font-semibold" :class="resumo.saldo_realizado < 0 && 'text-danger'">{{ moeda(resumo.saldo_realizado) }}</p>
        </div>
        <div class="cashflow-card card p-4">
          <p class="text-xs text-[var(--text-muted)]">Previsto (Entradas − Saídas)</p>
          <p class="cashflow-value text-lg font-semibold text-[var(--text-secondary)]">{{ moeda(resumo.entradas_previstas - resumo.saidas_previstas) }}</p>
        </div>
        <div class="cashflow-card card p-4">
          <p class="text-xs text-[var(--text-muted)]">Saldo em Contas Bancárias</p>
          <p class="cashflow-value text-lg font-semibold">{{ moeda(resumo.saldo_contas_bancarias) }}</p>
        </div>
      </div>

      <div class="cashflow-table table-wrapper results-scroll">
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

<style scoped>
.cashflow-page {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

@media (max-width: 767px) {
  .cashflow-page {
    height: 100%;
    min-height: 0;
    gap: 0.5rem;
    overflow: hidden;
  }

  .cashflow-header {
    flex: 0 0 auto;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0;
  }

  .cashflow-header > div:first-child {
    flex: 0 0 auto;
  }

  .cashflow-header .page-title {
    font-size: 1rem;
    line-height: 1.2;
    white-space: nowrap;
  }

  .cashflow-header > div:first-child > p {
    display: none;
  }

  .cashflow-period {
    display: grid;
    flex: 1 1 auto;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 0.35rem;
    min-width: 0;
  }

  .cashflow-period .input {
    height: 2.15rem;
    min-width: 0;
    width: 100%;
    padding: 0.3rem 0.4rem;
    font-size: 0.68rem;
  }

  .cashflow-summary {
    flex: 0 0 auto;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 0.35rem;
  }

  .cashflow-card {
    display: flex;
    grid-column: span 2;
    min-height: 3.65rem;
    align-items: center;
    flex-direction: column;
    justify-content: center;
    padding: 0.4rem 0.3rem;
    text-align: center;
  }

  .cashflow-card:nth-child(n + 4) {
    grid-column: span 3;
  }

  .cashflow-card > p:first-child {
    font-size: 0.58rem;
    line-height: 1.15;
  }

  .cashflow-value {
    margin-top: 0.2rem;
    font-size: 0.88rem;
    line-height: 1.1;
    white-space: nowrap;
  }

  .cashflow-table {
    flex: 1 1 auto;
    min-height: 0 !important;
    max-height: none !important;
    overflow: auto;
  }
}
</style>
