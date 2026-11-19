<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">DRE — Demonstrativo de Resultado</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ periodo.inicio && data(periodo.inicio) }} — {{ periodo.fim && data(periodo.fim) }}</p>
      </div>
      <div class="flex items-center gap-2">
        <input v-model="dataInicio" type="date" class="input" @change="carregar" />
        <input v-model="dataFim" type="date" class="input" @change="carregar" />
      </div>
    </div>

    <div v-if="loading" class="skeleton h-40 rounded-xl" />

    <template v-else>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="card p-4">
          <p class="text-xs text-[var(--text-muted)]">Receitas</p>
          <p class="text-lg font-semibold text-success">{{ moeda(totalReceitas) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-[var(--text-muted)]">Despesas</p>
          <p class="text-lg font-semibold text-danger">{{ moeda(totalDespesas) }}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs text-[var(--text-muted)]">Resultado Líquido</p>
          <p class="text-lg font-semibold" :class="resultadoLiquido < 0 ? 'text-danger' : 'text-success'">
            {{ moeda(resultadoLiquido) }} <span class="text-xs text-[var(--text-muted)] font-normal">({{ margemLiquida }}%)</span>
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="card p-4">
          <h3 class="text-sm font-semibold mb-3 text-success">Receitas por Categoria</h3>
          <div v-if="!receitas.length" class="empty-state"><p class="text-xs text-[var(--text-muted)]">Sem receitas no período</p></div>
          <div v-else class="space-y-2">
            <div v-for="r in receitas" :key="r.categoria" class="flex items-center justify-between text-sm">
              <span class="text-[var(--text-secondary)]">{{ r.categoria }}</span>
              <span class="font-medium">{{ moeda(r.total) }}</span>
            </div>
          </div>
        </div>
        <div class="card p-4">
          <h3 class="text-sm font-semibold mb-3 text-danger">Despesas por Categoria</h3>
          <div v-if="!despesas.length" class="empty-state"><p class="text-xs text-[var(--text-muted)]">Sem despesas no período</p></div>
          <div v-else class="space-y-2">
            <div v-for="d in despesas" :key="d.categoria" class="flex items-center justify-between text-sm">
              <span class="text-[var(--text-secondary)]">{{ d.categoria }}</span>
              <span class="font-medium">{{ moeda(d.total) }}</span>
            </div>
          </div>
        </div>
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
const receitas = ref([])
const despesas = ref([])
const totalReceitas = ref(0)
const totalDespesas = ref(0)
const resultadoLiquido = ref(0)
const margemLiquida = ref(0)
const periodo = ref({})
const dataInicio = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10))
const dataFim = ref(new Date().toISOString().slice(0, 10))

async function carregar() {
  loading.value = true
  try {
    const { data: resp } = await api.get('/dre', { params: { data_inicio: dataInicio.value, data_fim: dataFim.value } })
    receitas.value = resp.receitas
    despesas.value = resp.despesas
    totalReceitas.value = resp.total_receitas
    totalDespesas.value = resp.total_despesas
    resultadoLiquido.value = resp.resultado_liquido
    margemLiquida.value = resp.margem_liquida
    periodo.value = resp.periodo
  } catch {
    // erros já tratados globalmente pelo interceptor do api.js
  } finally {
    loading.value = false
  }
}

onMounted(carregar)
</script>
