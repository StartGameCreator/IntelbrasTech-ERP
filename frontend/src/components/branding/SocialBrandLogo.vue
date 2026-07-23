<template>
  <span class="inline-flex items-center justify-center" aria-hidden="true">
    <svg v-if="provider === 'google'" viewBox="0 0 24 24" fill="none" class="w-full h-full">
      <path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.06H12v3.9h5.39a4.61 4.61 0 0 1-2 3.02v2.53h3.24c1.9-1.75 2.97-4.33 2.97-7.39Z"/>
      <path fill="#34A853" d="M12 22c2.7 0 4.97-.9 6.63-2.38l-3.24-2.53c-.9.6-2.05.96-3.39.96-2.61 0-4.82-1.76-5.61-4.13H3.05v2.61A10 10 0 0 0 12 22Z"/>
      <path fill="#FBBC05" d="M6.39 13.92A6.02 6.02 0 0 1 6.08 12c0-.67.11-1.32.31-1.92V7.47H3.05A10 10 0 0 0 2 12c0 1.61.38 3.14 1.05 4.53l3.34-2.61Z"/>
      <path fill="#EA4335" d="M12 5.95c1.47 0 2.79.51 3.83 1.5l2.87-2.87A9.62 9.62 0 0 0 12 2a10 10 0 0 0-8.95 5.47l3.34 2.61C7.18 7.71 9.39 5.95 12 5.95Z"/>
    </svg>
    <svg v-else-if="provider === 'microsoft'" viewBox="0 0 24 24" class="w-full h-full">
      <path fill="#F25022" d="M2 2h9v9H2z"/><path fill="#7FBA00" d="M13 2h9v9h-9z"/>
      <path fill="#00A4EF" d="M2 13h9v9H2z"/><path fill="#FFB900" d="M13 13h9v9h-9z"/>
    </svg>
    <svg v-else-if="provider === 'apple'" viewBox="0 0 24 24" class="w-full h-full" fill="currentColor">
      <path d="M17.05 12.54c-.03-3.08 2.51-4.58 2.62-4.65a5.63 5.63 0 0 0-4.43-2.4c-1.86-.2-3.66 1.12-4.61 1.12-.97 0-2.43-1.1-4.01-1.07a5.88 5.88 0 0 0-4.95 3.02c-2.14 3.7-.55 9.14 1.5 12.13 1.03 1.47 2.23 3.1 3.81 3.04 1.54-.06 2.11-.98 3.97-.98 1.84 0 2.38.98 3.98.94 1.66-.02 2.7-1.47 3.69-2.95a12.1 12.1 0 0 0 1.69-3.45 5.3 5.3 0 0 1-3.26-4.75ZM14.02 3.52A5.38 5.38 0 0 0 15.25 0a5.48 5.48 0 0 0-3.54 1.68 5.12 5.12 0 0 0-1.26 3.38 4.52 4.52 0 0 0 3.57-1.54Z"/>
    </svg>
    <svg v-else-if="provider === 'facebook'" viewBox="0 0 24 24" class="w-full h-full" fill="#1877F2">
      <path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.03 1.79-4.7 4.53-4.7 1.31 0 2.68.23 2.68.23v2.97h-1.51c-1.49 0-1.95.93-1.95 1.89v2.27h3.32l-.53 3.49h-2.79V24C19.61 23.1 24 18.1 24 12.07Z"/>
    </svg>
  </span>
</template>
<script setup>
defineProps({ provider: { type: String, required: true } })
</script>
