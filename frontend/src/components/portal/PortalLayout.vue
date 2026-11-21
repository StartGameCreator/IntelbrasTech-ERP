<template>
  <div class="portal-shell">
    <header class="portal-header">
      <div class="portal-container portal-nav">
        <RouterLink class="portal-brand" to="/">
          <img v-if="portal.empresa?.logo_url" class="portal-brand-logo" :src="portal.empresa.logo_url" alt="IntelbrasTech" />
          <span v-else class="portal-brand-mark">IT</span>
          <span><b>IntelbrasTech</b><small>-Tecnologia e Energia</small></span>
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
          <RouterLink class="portal-service-request" to="/atendimento" @click="close">Solicite um Técnico</RouterLink>
          <button class="portal-copy-attendance" type="button" @click="copiarLinkAtendimento">
            <Copy :size="17" />
            {{ linkAtendimentoCopiado ? 'Link copiado!' : 'Copiar link de atendimento' }}
          </button>
          <a class="portal-store" :href="portal.canais?.loja_url || 'https://www.loja.intelbrastech.com.br'" target="_blank" rel="noopener" @click="close">Acessar loja</a>
        </nav>
      </div>
    </header>

    <main><RouterView /></main>

    <a v-if="whatsappUrl" class="portal-whatsapp" :href="whatsappUrl" target="_blank" rel="noopener" aria-label="Falar pelo WhatsApp"><MessageCircle /></a>
    <aside v-if="consentimento === null && possuiRastreadores" class="portal-cookie" aria-label="Preferências de cookies">
      <div><b>Privacidade e métricas</b><p>Usamos cookies de análise para entender o uso do site e melhorar sua experiência. Você pode aceitar ou continuar apenas com os cookies essenciais.</p><RouterLink to="/privacidade">Saiba mais</RouterLink></div>
      <div class="portal-cookie-actions"><button class="secondary" @click="definirConsentimento(false)">Somente essenciais</button><button @click="definirConsentimento(true)">Aceitar análise</button></div>
    </aside>
    <footer class="portal-footer">
      <div class="portal-container portal-footer-grid">
        <div><div class="portal-brand footer-brand"><img v-if="portal.empresa?.logo_url" class="portal-brand-logo" :src="portal.empresa.logo_url" alt="IntelbrasTech" /><span v-else class="portal-brand-mark">IT</span><span><b>IntelbrasTech</b><small>-Tecnologia e Energia</small></span></div><p>Revenda Certificada e Serviço Autorizado Intelbras</p></div>
        <div><b>Soluções</b><RouterLink to="/energia-solar">Energia solar</RouterLink><RouterLink to="/solucoes/mercado-livre-energia-geracao-distribuida">Mercado Livre de Energia</RouterLink><RouterLink to="/assistencia-tecnica">Assistência técnica e informática</RouterLink><RouterLink to="/solucoes/infraestrutura-de-redes">Infraestrutura de redes</RouterLink><RouterLink to="/solucoes/computacao-em-nuvem">Computação em nuvem</RouterLink><RouterLink to="/solucoes/marketing-digital-empresas">Marketing digital</RouterLink></div>
        <div><b>IntelbrasTech</b><RouterLink to="/sobre">Sobre nós</RouterLink><RouterLink to="/projetos">Projetos</RouterLink><RouterLink to="/contato">Contato</RouterLink><RouterLink to="/cadastre-se">Trabalhe conosco</RouterLink><RouterLink to="/privacidade">Privacidade</RouterLink></div>
        <div><b>Atendimento</b><span>{{ portal.empresa?.telefone || 'Consulte nossos canais' }}</span><span>{{ portal.empresa?.email }}</span><span>{{ [portal.empresa?.cidade, portal.empresa?.uf].filter(Boolean).join(' - ') }}</span><RouterLink to="/atendimento">Abrir chamado</RouterLink><RouterLink class="portal-admin" to="/login">Acesso ao ERP</RouterLink></div>
      </div>
      <div class="portal-container portal-footer-bottom">© {{ new Date().getFullYear() }} IntelbrasTech. Todos os direitos reservados.</div>
    </footer>
  </div>
</template>

<script setup>
import { computed, onMounted, provide, reactive, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { ChevronDown, Copy, Menu, MessageCircle, X } from 'lucide-vue-next'
import { carregarPortal } from '@/services/portal'
import { solutions } from '@/data/portalContent'

const menuOpen = ref(false)
const linkAtendimentoCopiado = ref(false)
const route = useRoute()
const portal = reactive({ empresa: {}, canais: {}, credenciais: {}, site: {} })
const consentimento = ref(localStorage.getItem('intelbrastech_analytics_consent') === null ? null : localStorage.getItem('intelbrastech_analytics_consent') === 'aceito')
const possuiRastreadores = computed(() => ['google_analytics_ativo','google_tag_manager_ativo','microsoft_clarity_ativo','meta_pixel_ativo','tiktok_pixel_ativo'].some(key => portal.site?.[key]))
provide('portal', portal)
const close = () => { menuOpen.value = false }
const linkAtendimento = 'https://intelbrastech.com.br/atendimento'
async function copiarLinkAtendimento() {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(linkAtendimento)
    } else {
      const campo = document.createElement('textarea')
      campo.value = linkAtendimento
      campo.setAttribute('readonly', '')
      campo.style.cssText = 'position:fixed;opacity:0;pointer-events:none'
      document.body.appendChild(campo)
      campo.select()
      document.execCommand('copy')
      campo.remove()
    }
    linkAtendimentoCopiado.value = true
    window.setTimeout(() => { linkAtendimentoCopiado.value = false }, 2500)
  } catch {
    window.prompt('Copie o link de atendimento:', linkAtendimento)
  }
}
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
  meta('google-site-verification', site.google_site_verification); meta('og:title', site.titulo, true); meta('og:description', site.descricao, true); meta('og:image', site.og_image_url, true); meta('twitter:title', site.titulo); meta('twitter:description', site.descricao); meta('twitter:image', site.og_image_url)
  if (site.canonical_url) { let link=document.head.querySelector('link[rel="canonical"]'); if(!link){link=document.createElement('link');link.rel='canonical';document.head.appendChild(link)} link.href=site.canonical_url }
  if (site.favicon_url) { let icon=document.head.querySelector('link[rel~="icon"]'); if(!icon){icon=document.createElement('link');icon.rel='icon';document.head.appendChild(icon)} icon.href=site.favicon_url }
}
function carregarRastreadores(site) {
  const ga4 = String(site.google_analytics_id || '').trim().toUpperCase()
  const gtm = String(site.google_tag_manager_id || '').trim().toUpperCase()
  const clarity = String(site.microsoft_clarity_id || '').trim()
  if (site.google_analytics_ativo && /^G-[A-Z0-9]+$/.test(ga4) && !document.getElementById('ga4-sdk')) {
    window.dataLayer = window.dataLayer || []; window.gtag = window.gtag || function(){window.dataLayer.push(arguments)}
    const script=document.createElement('script'); script.id='ga4-sdk'; script.async=true; script.src=`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(ga4)}`; document.head.appendChild(script); window.gtag('js',new Date()); window.gtag('config',ga4,{send_page_view:false})
  }
  if (site.google_tag_manager_ativo && /^GTM-[A-Z0-9]+$/.test(gtm) && !document.getElementById('gtm-sdk')) {
    window.dataLayer=window.dataLayer||[]; window.dataLayer.push({'gtm.start':Date.now(),event:'gtm.js'}); const script=document.createElement('script'); script.id='gtm-sdk'; script.async=true; script.src=`https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(gtm)}`; document.head.appendChild(script)
  }
  if (site.microsoft_clarity_ativo && /^[A-Za-z0-9_-]+$/.test(clarity) && !document.getElementById('clarity-sdk')) {
    window.clarity=window.clarity||function(){(window.clarity.q=window.clarity.q||[]).push(arguments)}; const script=document.createElement('script'); script.id='clarity-sdk'; script.async=true; script.src=`https://www.clarity.ms/tag/${encodeURIComponent(clarity)}`; document.head.appendChild(script)
  }
  if (site.meta_pixel_ativo && /^\d+$/.test(String(site.meta_pixel_id || '')) && !document.getElementById('meta-pixel-sdk')) {
    window.fbq=window.fbq||function(){(window.fbq.callMethod?window.fbq.callMethod:window.fbq.queue).apply(window.fbq,arguments)}; window.fbq.queue=[]; window.fbq.loaded=true; window.fbq.version='2.0'
    const script=document.createElement('script'); script.id='meta-pixel-sdk'; script.async=true; script.src='https://connect.facebook.net/en_US/fbevents.js'; document.head.appendChild(script); window.fbq('init',site.meta_pixel_id)
  }
  if (site.tiktok_pixel_ativo && site.tiktok_pixel_id && !document.getElementById('tiktok-pixel-sdk')) {
    window.ttq=window.ttq||[]; window.ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie']; window.ttq.setAndDefer=(t,e)=>{t[e]=function(){t.push([e].concat(Array.from(arguments)))}}; window.ttq.methods.forEach(method=>window.ttq.setAndDefer(window.ttq,method)); window.ttq.load=id=>{const script=document.createElement('script');script.id='tiktok-pixel-sdk';script.async=true;script.src='https://analytics.tiktok.com/i18n/pixel/events.js?sdkid='+encodeURIComponent(id)+'&lib=ttq';document.head.appendChild(script)}; window.ttq.load(site.tiktok_pixel_id)
  }
  registrarPagina()
}
function registrarPagina() {
  const pagePath=route.fullPath; const title=document.title
  if (window.gtag && portal.site?.google_analytics_ativo) window.gtag('event','page_view',{page_title:title,page_location:window.location.href,page_path:pagePath})
  if (window.dataLayer && portal.site?.google_tag_manager_ativo) window.dataLayer.push({event:'virtual_page_view',page_path:pagePath,page_title:title})
  if (window.fbq && portal.site?.meta_pixel_ativo) window.fbq('track','PageView')
  if (window.ttq?.page && portal.site?.tiktok_pixel_ativo) window.ttq.page()
}
function definirConsentimento(valor) {
  consentimento.value=valor; localStorage.setItem('intelbrastech_analytics_consent',valor?'aceito':'recusado')
  if (valor) carregarRastreadores(portal.site || {})
}
onMounted(async () => {
  try { Object.assign(portal, await carregarPortal()); aplicarSite(portal.site || {}); if(consentimento.value===true)carregarRastreadores(portal.site || {}) } catch { /* conteúdo público mantém fallback */ }
})
watch(()=>route.fullPath,()=>{if(consentimento.value===true)registrarPagina()})
</script>
