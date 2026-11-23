<template>
  <div class="min-h-screen bg-dark-900 flex items-center justify-center p-4">
    <div class="card p-8 max-w-md w-full text-center">
      <ArrowPathIcon v-if="loading" class="w-9 h-9 mx-auto animate-spin text-brand-400" />
      <CheckCircleIcon v-else-if="success" class="w-9 h-9 mx-auto text-success" />
      <ExclamationCircleIcon v-else class="w-9 h-9 mx-auto text-danger" />
      <h1 class="text-lg font-semibold mt-4">{{ title }}</h1>
      <p class="text-sm text-[var(--text-muted)] mt-2">{{ message }}</p>
      <router-link v-if="!loading && !success" to="/login" class="btn-primary inline-flex mt-5">Voltar ao login</router-link>
    </div>
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { ArrowPathIcon, CheckCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'
const route=useRoute(), router=useRouter(), auth=useAuthStore()
const loading=ref(true), success=ref(false), message=ref('Concluindo autenticação segura...')
const title=computed(()=>loading.value?'Aguarde':success.value?'Login concluído':'Não foi possível entrar')
onMounted(async()=>{ try { if(route.query.error) throw new Error(route.query.error); await auth.socialExchange(route.query.code); success.value=true; const pendente=!!auth.usuario?.empresa?.cadastro_pendente; message.value=pendente?'Login concluído. Complete agora os dados oficiais da empresa.':'Conta conectada. Redirecionando...'; setTimeout(()=>router.replace(pendente?'/configuracoes/empresa':'/pdv'),500) } catch(e){ message.value=e.response?.data?.message||e.message||'Falha no login social.' } finally{ loading.value=false } })
</script>
