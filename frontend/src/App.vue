<template>
  <RouterView />
  <PwaExperience />
</template>

<script setup>
import { RouterView } from 'vue-router'
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import PwaExperience from '@/components/PwaExperience.vue'

const auth = useAuthStore()

onMounted(async () => {
  if (auth.token && !auth.usuario) {
    await auth.carregarUsuario()
  }
})
</script>
