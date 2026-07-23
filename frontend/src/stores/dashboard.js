import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useDashboardStore = defineStore('dashboard', () => {
  const dados   = ref(null)
  const alertas = ref([])

  const totalContasVencidas = computed(() =>
    dados.value?.financeiro?.a_receber_vencido > 0 ? 1 : 0
  )

  async function carregar() {
    const { data } = await api.get('/dashboard')
    dados.value   = data
    alertas.value = data.alertas ?? []
  }

  async function carregarAlertas() {
    try {
      const { data } = await api.get('/dashboard')
      alertas.value = data.alertas ?? []
    } catch {}
  }

  return { dados, alertas, totalContasVencidas, carregar, carregarAlertas }
})
