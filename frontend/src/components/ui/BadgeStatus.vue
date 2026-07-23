<!-- BadgeStatus.vue -->
<template>
  <span :class="['badge', classeStatus]">
    <span class="status-dot" :class="dotClasse" />
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ status: String })

const mapa = {
  orcamento:   { badge: 'badge-gray',    dot: 'bg-gray-400',    label: 'Orçamento' },
  confirmado:  { badge: 'badge-info',    dot: 'bg-blue-400',    label: 'Confirmado' },
  faturado:    { badge: 'badge-brand',   dot: 'bg-indigo-400',  label: 'Faturado' },
  entregue:    { badge: 'badge-success', dot: 'bg-green-400',   label: 'Entregue' },
  cancelado:   { badge: 'badge-danger',  dot: 'bg-red-400',     label: 'Cancelado' },
  aberto:      { badge: 'badge-warning', dot: 'bg-amber-400',   label: 'Em Aberto' },
  pago:        { badge: 'badge-success', dot: 'bg-green-400',   label: 'Pago' },
  vencido:     { badge: 'badge-danger',  dot: 'bg-red-400',     label: 'Vencido' },
  parcial:     { badge: 'badge-warning', dot: 'bg-amber-400',   label: 'Parcial' },
  autorizada:  { badge: 'badge-success', dot: 'bg-green-400',   label: 'Autorizada' },
  rascunho:    { badge: 'badge-gray',    dot: 'bg-gray-400',    label: 'Rascunho' },
  processando: { badge: 'badge-info',    dot: 'bg-blue-400 animate-pulse', label: 'Processando' },
  cancelada:   { badge: 'badge-danger',  dot: 'bg-red-400',     label: 'Cancelada' },
  rejeitada:   { badge: 'badge-danger',  dot: 'bg-red-400',     label: 'Rejeitada' },
  andamento:   { badge: 'badge-info',    dot: 'bg-blue-400',    label: 'Em Andamento' },
  concluida:   { badge: 'badge-success', dot: 'bg-green-400',   label: 'Concluída' },
}

const entry        = computed(() => mapa[props.status] || { badge: 'badge-gray', dot: 'bg-gray-400', label: props.status })
const classeStatus = computed(() => entry.value.badge)
const dotClasse    = computed(() => entry.value.dot)
const label        = computed(() => entry.value.label)
</script>
