<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Assistência Técnica</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">
          {{ sla.total_em_andamento || 0 }} em andamento
          <span v-if="sla.total_atrasadas" class="text-warning"> • {{ sla.total_atrasadas }} atrasada(s)</span>
          • tempo médio de reparo: {{ sla.tempo_medio_reparo_horas || 0 }}h
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="carregar" class="btn-secondary btn-sm" :disabled="loading">
          <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
        </button>
        <router-link to="/os/nova" class="btn-primary btn-sm">
          <PlusIcon class="w-4 h-4" /> Nova OS
        </router-link>
      </div>
    </div>

    <div v-if="loading" class="skeleton h-64 rounded-xl" />

    <div v-else class="flex gap-4 overflow-x-auto pb-2">
      <div v-for="coluna in colunas" :key="coluna.status.id" class="flex-shrink-0 w-72">
        <div class="flex items-center gap-2 mb-2 px-1">
          <span class="w-2.5 h-2.5 rounded-full" :style="{ background: coluna.status.cor || '#999' }" />
          <h3 class="text-sm font-semibold">{{ coluna.status.nome }}</h3>
          <span class="text-xs text-[var(--text-muted)] ml-auto">{{ coluna.total }}</span>
        </div>
        <div class="space-y-2 min-h-16">
          <router-link v-for="os in coluna.ordens" :key="os.id" :to="`/os/${os.id}`"
            class="card p-3 block hover:border-brand-500 transition-colors">
            <div class="flex items-center justify-between mb-1">
              <span class="text-sm font-medium">{{ os.numero }}</span>
              <span v-if="os.em_garantia" class="badge badge-info text-[10px]">Garantia</span>
            </div>
            <p class="text-xs text-[var(--text-secondary)] truncate">{{ os.cliente?.razao_social || os.cliente?.nome_fantasia || '—' }}</p>
            <p class="text-xs text-[var(--text-muted)] truncate">{{ os.equipamento_marca }} {{ os.equipamento_modelo }}</p>
            <div class="flex items-center justify-between mt-2 text-xs text-[var(--text-muted)]">
              <span>{{ os.tecnico?.nome || 'Sem técnico' }}</span>
              <span v-if="os.data_previsao">{{ data(os.data_previsao) }}</span>
            </div>
          </router-link>
          <div v-if="!coluna.ordens.length" class="text-xs text-[var(--text-muted)] text-center py-6 border border-dashed border-[var(--border)] rounded-lg">
            Nenhuma OS
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowPathIcon, PlusIcon } from '@heroicons/vue/24/outline'

const { data } = useFormato()

const colunas = ref([])
const sla = ref({})
const loading = ref(false)

async function carregar() {
  loading.value = true
  try {
    const [{ data: kanban }, { data: slaResp }] = await Promise.all([
      api.get('/os/kanban'),
      api.get('/os/sla'),
    ])
    colunas.value = kanban
    sla.value = slaResp
  } catch {
    // erros já tratados globalmente pelo interceptor do api.js
  } finally {
    loading.value = false
  }
}

onMounted(carregar)
</script>
