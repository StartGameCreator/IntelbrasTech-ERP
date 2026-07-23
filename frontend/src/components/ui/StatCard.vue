<template>
  <div class="stat-card">
    <div :class="['stat-icon', bgCor]">
      <component :is="icon" class="w-6 h-6" :class="textCor" />
    </div>
    <div class="flex-1 min-w-0">
      <p class="kpi-label">{{ label }}</p>
      <div v-if="loading" class="skeleton h-7 w-28 rounded mt-1" />
      <p v-else class="kpi-value">{{ value }}</p>
      <div class="flex items-center gap-2 mt-0.5">
        <span v-if="trend !== undefined && trend !== null"
          :class="trend >= 0 ? 'kpi-trend-up' : 'kpi-trend-down'">
          <ArrowTrendingUpIcon v-if="trend >= 0" class="w-3 h-3" />
          <ArrowTrendingDownIcon v-else class="w-3 h-3" />
          {{ Math.abs(trend) }}%
        </span>
        <p v-if="sub" class="text-xs" :class="subCorClasse">{{ sub }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ArrowTrendingUpIcon, ArrowTrendingDownIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  label:   String,
  value:   String,
  sub:     String,
  subCor:  String,
  trend:   Number,
  icon:    Object,
  cor:     { type: String, default: 'brand' },
  loading: Boolean,
})

const mapa = {
  brand:  { bg: 'bg-brand-500/15', text: 'text-brand-400' },
  green:  { bg: 'bg-green-500/15',  text: 'text-green-400' },
  blue:   { bg: 'bg-blue-500/15',   text: 'text-blue-400' },
  purple: { bg: 'bg-purple-500/15', text: 'text-purple-400' },
  red:    { bg: 'bg-red-500/15',    text: 'text-red-400' },
  orange: { bg: 'bg-orange-500/15', text: 'text-orange-400' },
  teal:   { bg: 'bg-teal-500/15',   text: 'text-teal-400' },
  pink:   { bg: 'bg-pink-500/15',   text: 'text-pink-400' },
}

const bgCor   = computed(() => mapa[props.cor]?.bg   || mapa.brand.bg)
const textCor = computed(() => mapa[props.cor]?.text || mapa.brand.text)
const subCorClasse = computed(() => ({
  success: 'text-success', warning: 'text-warning', danger: 'text-danger', info: 'text-info'
})[props.subCor] || 'text-[var(--text-muted)]')
</script>
