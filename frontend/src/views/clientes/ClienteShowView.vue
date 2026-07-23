<template>
  <div class="space-y-5 animate-fade-in" v-if="cliente">
    <div class="page-header">
      <div class="flex items-center gap-4">
        <button @click="$router.back()" class="btn-ghost btn-icon"><ArrowLeftIcon class="w-5 h-5" /></button>
        <div>
          <h1 class="page-title">{{ cliente.nome_fantasia || cliente.razao_social }}</h1>
          <p class="text-xs text-[var(--text-muted)]">{{ fmt.cpfCnpj(cliente.cpf_cnpj) }}</p>
        </div>
      </div>
      <div class="flex gap-2">
        <RouterLink :to="`/clientes/${cliente.id}/editar`" class="btn-secondary btn-sm">
          <PencilIcon class="w-4 h-4" /> Editar
        </RouterLink>
      </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <!-- Dados principais -->
      <div class="card p-5 lg:col-span-2 space-y-4">
        <h3 class="text-sm font-semibold text-[var(--text-primary)] border-b border-[var(--border)] pb-2">Dados Cadastrais</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div><p class="kpi-label">Razão Social</p><p>{{ cliente.razao_social }}</p></div>
          <div><p class="kpi-label">Nome Fantasia</p><p>{{ cliente.nome_fantasia || '—' }}</p></div>
          <div><p class="kpi-label">E-mail</p><p>{{ cliente.email || '—' }}</p></div>
          <div><p class="kpi-label">Celular</p><p>{{ fmt.telefone(cliente.celular) }}</p></div>
          <div><p class="kpi-label">WhatsApp</p><p>{{ fmt.telefone(cliente.whatsapp) }}</p></div>
          <div><p class="kpi-label">Cidade/UF</p><p>{{ cliente.cidade }}/{{ cliente.estado }}</p></div>
          <div><p class="kpi-label">CEP</p><p>{{ fmt.cep(cliente.cep) }}</p></div>
          <div><p class="kpi-label">Logradouro</p><p>{{ cliente.logradouro }}, {{ cliente.numero }}</p></div>
        </div>
      </div>
      <!-- Resumo financeiro -->
      <div class="space-y-3">
        <div class="card p-4">
          <h3 class="text-xs font-semibold text-[var(--text-muted)] uppercase mb-3">Resumo Financeiro</h3>
          <div class="space-y-3">
            <div class="flex justify-between"><span class="text-xs text-[var(--text-muted)]">Total de Pedidos</span>
              <span class="text-sm font-semibold">{{ resumo.total_pedidos }}</span></div>
            <div class="flex justify-between"><span class="text-xs text-[var(--text-muted)]">Valor Total</span>
              <span class="text-sm font-semibold">{{ fmt.moeda(resumo.valor_total) }}</span></div>
            <div class="flex justify-between"><span class="text-xs text-[var(--text-muted)]">Em Aberto</span>
              <span class="text-sm font-semibold text-warning">{{ fmt.moeda(resumo.em_aberto) }}</span></div>
            <div class="flex justify-between"><span class="text-xs text-[var(--text-muted)]">Em Atraso</span>
              <span class="text-sm font-semibold text-danger">{{ fmt.moeda(resumo.em_atraso) }}</span></div>
          </div>
        </div>
        <div class="card p-4">
          <div class="flex items-center justify-between mb-1">
            <span class="text-xs text-[var(--text-muted)]">Status</span>
            <span :class="cliente.ativo ? 'badge-success' : 'badge-danger'" class="badge">
              {{ cliente.ativo ? 'Ativo' : 'Inativo' }}
            </span>
          </div>
          <div v-if="cliente.bloqueado" class="badge badge-danger mt-2">Bloqueado</div>
        </div>
      </div>
    </div>
  </div>
  <div v-else-if="loading" class="space-y-4">
    <div class="skeleton h-12 rounded-xl" />
    <div class="skeleton h-64 rounded-xl" />
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowLeftIcon, PencilIcon } from '@heroicons/vue/24/outline'
const route = useRoute()
const fmt = useFormato()
const cliente = ref(null)
const resumo = ref({})
const loading = ref(false)
onMounted(async () => {
  loading.value = true
  try {
    const { data } = await api.get(`/clientes/${route.params.id}`)
    cliente.value = data.cliente
    resumo.value = data.resumo
  } finally { loading.value = false }
})
</script>
