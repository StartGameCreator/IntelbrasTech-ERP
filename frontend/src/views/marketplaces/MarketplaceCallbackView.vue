<template>
  <div class="flex items-center justify-center min-h-[60vh]">
    <div class="text-center space-y-3">
      <div v-if="processando" class="skeleton w-12 h-12 rounded-full mx-auto" />
      <component v-else :is="sucesso ? CheckCircleIcon : XCircleIcon" class="w-12 h-12 mx-auto" :class="sucesso ? 'text-success' : 'text-danger'" />
      <p class="text-sm text-[var(--text-secondary)]">{{ mensagem }}</p>
      <router-link to="/marketplaces" class="btn-primary btn-sm inline-flex">Voltar para Marketplaces</router-link>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const processando = ref(true)
const sucesso = ref(false)
const mensagem = ref('Finalizando conexão...')

onMounted(async () => {
  const tipo = route.params.plataforma // 'mercadolivre' ou 'shopee'
  const integracaoId = localStorage.getItem(`integracao_${tipo}_id`)
  const code = route.query.code

  if (!code || !integracaoId) {
    processando.value = false
    mensagem.value = 'Parâmetros de retorno inválidos. Tente conectar novamente.'
    return
  }

  try {
    const payload = { integracao_id: integracaoId, code }
    if (tipo === 'shopee') payload.shop_id = route.query.shop_id
    else payload.redirect_uri = `${window.location.origin}/marketplaces/callback/${tipo}`

    const { data } = await api.post(`/marketplace/${tipo}/callback`, payload)
    sucesso.value = true
    mensagem.value = data.message
    localStorage.removeItem(`integracao_${tipo}_id`)
  } catch (e) {
    sucesso.value = false
    mensagem.value = e.response?.data?.message || 'Não foi possível concluir a conexão.'
  } finally {
    processando.value = false
  }
})
</script>
