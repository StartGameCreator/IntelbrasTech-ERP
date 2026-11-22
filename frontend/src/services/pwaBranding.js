function ensureLink(rel, selector = `link[rel="${rel}"]`) {
  let link = document.head.querySelector(selector)
  if (!link) {
    link = document.createElement('link')
    link.rel = rel
    document.head.appendChild(link)
  }
  return link
}

export function aplicarIdentidadePwa(pwa = {}) {
  if (pwa.manifest_url) {
    ensureLink('manifest').href = pwa.manifest_url
  }

  if (pwa.apple_touch_icon_url) {
    const appleIcon = ensureLink('apple-touch-icon')
    appleIcon.sizes = '180x180'
    appleIcon.href = pwa.apple_touch_icon_url
  }

  if (pwa.icon_32_url) {
    document.head.querySelectorAll('link[rel="icon"][sizes="32x32"], link[rel="shortcut icon"]').forEach(link => {
      link.href = pwa.icon_32_url
    })
  }

  if (pwa.icon_192_url) {
    document.head.querySelectorAll('link[rel="icon"][sizes="192x192"]').forEach(link => {
      link.href = pwa.icon_192_url
    })
  }

  document.documentElement.dataset.pwaLogoVersion = pwa.logo_version || ''
}

export async function sincronizarIdentidadePwa() {
  const apiBase = String(import.meta.env.VITE_API_URL || '/api/v1').replace(/\/$/, '')
  const response = await fetch(`${apiBase}/portal/configuracao?pwa_refresh=${Date.now()}`, {
    cache: 'no-store',
    headers: { Accept: 'application/json' },
  })
  if (!response.ok) throw new Error('Identidade PWA indisponível')
  const data = await response.json()

  aplicarIdentidadePwa(data.pwa || {})

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.getRegistration().then(registration => registration?.update()).catch(() => {})
  }

  return data.pwa || {}
}
