<template>
  <div class="portal-shell">
    <header class="portal-header">
      <div class="portal-container portal-nav">
        <RouterLink class="portal-brand" to="/">
          <img v-if="portal.empresa?.logo_url" class="portal-brand-logo" :src="portal.empresa.logo_url" alt="IntelbrasTech" />
          <span v-else class="portal-brand-mark">IT</span>
          <span><b>IntelbrasTech</b><small>Tecnologia e energia</small></span>
        </RouterLink>
        <button class="portal-menu-button" aria-label="Abrir menu" :aria-expanded="menuOpen" @click="menuOpen = !menuOpen">
          <Menu v-if="!menuOpen" /><X v-else />
        </button>
        <nav class="portal-links" :class="{ open: menuOpen }" aria-label="Navegação principal">
          <RouterLink to="/" @click="close">Início</RouterLink>
          <RouterLink to="/energia-solar" @click="close">Energia solar</RouterLink>
          <div class="portal-dropdown">
            <button>Soluções <ChevronDown :size="15" /></button>
            <div class="portal-dropdown-menu">
              <RouterLink v-for="item in solutions" :key="item.slug" :to="`/solucoes/${item.slug}`" @click="close">{{ item.title }}</RouterLink>
            </div>
          </div>
          <RouterLink to="/assistencia-tecnica" @click="close">Assistência</RouterLink>
          <RouterLink to="/projetos" @click="close">Projetos</RouterLink>
          <RouterLink to="/sobre" @click="close">Sobre</RouterLink>
          <RouterLink to="/contato" @click="close">Contato</RouterLink>
          <RouterLink class="portal-service-request" to="/solicite-um-tecnico" @click="close">Solicite um Técnico</RouterLink>
          <a class="portal-store" :href="portal.canais?.loja_url || 'https://loja.intelbrastech.com.br'" target="_blank" rel="noopener" @click="close">Acessar loja</a>
        </nav>
      </div>
    </header>

    <main><RouterView /></main>

    <a v-if="whatsappUrl" class="portal-whatsapp" :href="whatsappUrl" target="_blank" rel="noopener" aria-label="Falar pelo WhatsApp"><MessageCircle /></a>
    <footer class="portal-footer">
      <div class="portal-container portal-footer-grid">
        <div><div class="portal-brand footer-brand"><img v-if="portal.empresa?.logo_url" class="portal-brand-logo" :src="portal.empresa.logo_url" alt="IntelbrasTech" /><span v-else class="portal-brand-mark">IT</span><span><b>IntelbrasTech</b><small>Tecnologia e energia</small></span></div><p>Soluções conectadas para residências, empresas e operações que querem evoluir.</p></div>
        <div><b>Soluções</b><RouterLink to="/energia-solar">Energia solar</RouterLink><RouterLink to="/solucoes/mercado-livre-energia-geracao-distribuida">Mercado Livre de Energia</RouterLink><RouterLink to="/assistencia-tecnica">Assistência técnica e informática</RouterLink><RouterLink to="/solucoes/infraestrutura-de-redes">Infraestrutura de redes</RouterLink><RouterLink to="/solucoes/computacao-em-nuvem">Computação em nuvem</RouterLink><RouterLink to="/solucoes/marketing-digital-empresas">Marketing digital</RouterLink></div>
        <div><b>IntelbrasTech</b><RouterLink to="/sobre">Sobre nós</RouterLink><RouterLink to="/projetos">Projetos</RouterLink><RouterLink to="/contato">Contato</RouterLink><RouterLink to="/cadastre-se">Trabalhe conosco</RouterLink><RouterLink to="/privacidade">Privacidade</RouterLink></div>
        <div><b>Atendimento</b><span>{{ portal.empresa?.telefone || 'Consulte nossos canais' }}</span><span>{{ portal.empresa?.email }}</span><span>{{ [portal.empresa?.cidade, portal.empresa?.uf].filter(Boolean).join(' - ') }}</span><RouterLink class="portal-admin" to="/login">Acesso ao ERP</RouterLink></div>
      </div>
      <div class="portal-container portal-footer-bottom">© {{ new Date().getFullYear() }} IntelbrasTech. Todos os direitos reservados.</div>
    </footer>
  </div>
</template>

<script setup>
import { computed, onMounted, provide, reactive, ref } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import { ChevronDown, Menu, MessageCircle, X } from 'lucide-vue-next'
import { carregarPortal } from '@/services/portal'
import { solutions } from '@/data/portalContent'

const menuOpen = ref(false)
const portal = reactive({ empresa: {}, canais: {}, credenciais: {}, site: {} })
provide('portal', portal)
const close = () => { menuOpen.value = false }
const whatsappUrl = computed(() => {
  const phone = String(portal.canais?.whatsapp || '').replace(/\D/g, '')
  return phone ? `https://wa.me/${phone.startsWith('55') ? phone : `55${phone}`}` : null
})
function meta(name, content, property = false) {
  if (!content) return
  const attr = property ? 'property' : 'name'
  let element = document.head.querySelector(`meta[${attr}="${name}"]`)
  if (!element) { element = document.createElement('meta'); element.setAttribute(attr, name); document.head.appendChild(element) }
  element.setAttribute('content', content)
}
function aplicarSite(site) {
  if (site.titulo) document.title = site.titulo
  meta('description', site.descricao); meta('keywords', site.palavras_chave); meta('robots', site.indexar_site ? 'index,follow' : 'noindex,nofollow')
  meta('google-site-verification', site.google_site_verification); meta('og:title', site.titulo, true); meta('og:description', site.descricao, true); meta('og:image', site.og_image_url, true)
  if (site.canonical_url) { let link=document.head.querySelector('link[rel="canonical"]'); if(!link){link=document.createElement('link');link.rel='canonical';document.head.appendChild(link)} link.href=site.canonical_url }
  if (site.favicon_url) { let icon=document.head.querySelector('link[rel~="icon"]'); if(!icon){icon=document.createElement('link');icon.rel='icon';document.head.appendChild(icon)} icon.href=site.favicon_url }
  if (site.meta_pixel_ativo && site.meta_pixel_id && !document.getElementById('meta-pixel-sdk')) {
    window.fbq=window.fbq||function(){(window.fbq.callMethod?window.fbq.callMethod:window.fbq.queue).apply(window.fbq,arguments)}; window.fbq.queue=[]; window.fbq.loaded=true; window.fbq.version='2.0'
    const script=document.createElement('script'); script.id='meta-pixel-sdk'; script.async=true; script.src='https://connect.facebook.net/en_US/fbevents.js'; document.head.appendChild(script); window.fbq('init',site.meta_pixel_id); window.fbq('track','PageView')
  }
  if (site.tiktok_pixel_ativo && site.tiktok_pixel_id && !document.getElementById('tiktok-pixel-sdk')) {
    window.ttq=window.ttq||[]; window.ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie']; window.ttq.setAndDefer=(t,e)=>{t[e]=function(){t.push([e].concat(Array.from(arguments)))}}; window.ttq.methods.forEach(method=>window.ttq.setAndDefer(window.ttq,method)); window.ttq.load=id=>{const script=document.createElement('script');script.id='tiktok-pixel-sdk';script.async=true;script.src='https://analytics.tiktok.com/i18n/pixel/events.js?sdkid='+id+'&lib=ttq';document.head.appendChild(script)}; window.ttq.load(site.tiktok_pixel_id); window.ttq.page()
  }
}
onMounted(async () => {
  try { Object.assign(portal, await carregarPortal()); aplicarSite(portal.site || {}) } catch { /* conteúdo público mantém fallback */ }
})
</script>
