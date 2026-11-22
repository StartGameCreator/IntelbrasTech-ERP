import { createApp } from 'vue'
import { createPinia } from 'pinia'
import Toast from 'vue-toastification'
import 'vue-toastification/dist/index.css'
import VueApexCharts from 'vue3-apexcharts'

import App    from './App.vue'
import router from './router'
import { sincronizarIdentidadePwa } from './services/pwaBranding'
import './assets/main.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(Toast, {
  position: 'top-right',
  timeout: 4000,
  closeOnClick: true,
  pauseOnHover: true,
  draggable: true,
  toastClassName: 'erp-toast',
})
app.use(VueApexCharts)

// Aplica dark mode padrão
document.documentElement.classList.remove('light')

app.mount('#app')

function agendarSincronizacaoPwa() {
  const sincronizar = () => {
    if (document.documentElement.dataset.pwaLogoVersion) return
    sincronizarIdentidadePwa().catch(() => {})
  }
  if ('requestIdleCallback' in window) {
    window.requestIdleCallback(sincronizar, { timeout: 3000 })
  } else {
    window.setTimeout(sincronizar, 750)
  }
}

if (document.readyState === 'complete') agendarSincronizacaoPwa()
else window.addEventListener('load', agendarSincronizacaoPwa, { once: true })

window.addEventListener('pwa-branding-refresh', () => {
  sincronizarIdentidadePwa().catch(() => {})
})

if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' })
      registration.addEventListener('updatefound', () => {
        const worker = registration.installing
        worker?.addEventListener('statechange', () => {
          if (worker.state === 'installed' && navigator.serviceWorker.controller) window.dispatchEvent(new CustomEvent('pwa-update-ready', { detail: registration }))
        })
      })
      setInterval(() => registration.update(), 60 * 60 * 1000)
    } catch (error) { console.warn('PWA indisponível:', error) }
  })
}
