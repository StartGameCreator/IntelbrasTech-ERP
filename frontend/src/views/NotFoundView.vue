<template>
  <div class="flex flex-col items-center justify-center min-h-[60vh] text-center">
    <div class="text-8xl font-black text-brand-500/20 mb-4">404</div>
    <h1 class="text-2xl font-bold text-[var(--text-primary)] mb-2">Página não encontrada</h1>
    <p class="text-sm text-[var(--text-muted)] mb-8">A página que você está procurando não existe ou foi movida.</p>
    <RouterLink to="/dashboard" class="btn-primary">
      <HomeIcon class="w-4 h-4" /> Voltar ao Dashboard
    </RouterLink>
  </div>
</template>
<script setup>
import { RouterLink } from 'vue-router'
import { HomeIcon } from '@heroicons/vue/24/outline'
</script>
