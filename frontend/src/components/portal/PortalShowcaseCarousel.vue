<template>
  <div class="portal-carousel" @mouseenter="pause" @mouseleave="play" @focusin="pause" @focusout="play">
    <div class="portal-carousel-top">
      <div>
        <span class="portal-overline">Produtos e serviços</span>
        <h2>Tudo o que a IntelbrasTech entrega</h2>
      </div>
      <div class="portal-carousel-controls">
        <button aria-label="Item anterior" @click="previous"><ChevronLeft /></button>
        <button aria-label="Próximo item" @click="next"><ChevronRight /></button>
      </div>
    </div>

    <div ref="viewport" class="portal-carousel-viewport">
      <div class="portal-carousel-track" :style="trackStyle">
        <article v-for="item in items" :key="`${item.kind}-${item.id}`" class="portal-carousel-card">
          <div class="portal-carousel-media" :style="item.image ? { backgroundImage: `url('${item.image}')` } : {}">
            <component :is="item.icon" v-if="!item.image && item.icon" />
            <span>{{ item.kind === 'product' ? 'Produto em destaque' : 'Serviço especializado' }}</span>
          </div>
          <div class="portal-carousel-copy">
            <small>{{ item.category }}</small>
            <h3>{{ item.title }}</h3>
            <p>{{ item.description }}</p>
            <a v-if="item.external" :href="item.href" target="_blank" rel="noopener">{{ item.linkText || 'Saiba mais' }} <ArrowUpRight /></a>
            <RouterLink v-else :to="item.href">{{ item.linkText || 'Conhecer solução' }} <ArrowRight /></RouterLink>
          </div>
        </article>
      </div>
    </div>

    <div class="portal-carousel-dots" role="tablist" aria-label="Selecionar item da vitrine">
      <button v-for="(_, index) in items" :key="index" :class="{ active: index === active }" :aria-label="`Exibir item ${index + 1}`" @click="goTo(index)" />
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { ArrowRight, ArrowUpRight, ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { RouterLink } from 'vue-router'
import { solutions } from '@/data/portalContent'
import { carregarCarrossel, carregarCatalogo } from '@/services/portal'

const serviceImages = {
  'energia-solar': 'https://images.unsplash.com/photo-1508514177221-188b1cf16e9d?auto=format&fit=crop&w=900&q=82',
  'seguranca-eletronica': 'https://images.unsplash.com/photo-1557597774-9d273605dfa9?auto=format&fit=crop&w=900&q=82',
  'casa-inteligente': 'https://images.unsplash.com/photo-1558002038-1055907df827?auto=format&fit=crop&w=900&q=82',
  'rastreamento-de-frotas': 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&w=900&q=82',
  'automacao-residencial': 'https://images.unsplash.com/photo-1558089687-f282ffcbc126?auto=format&fit=crop&w=900&q=82',
  'assistencia-tecnica-informatica': 'https://images.unsplash.com/photo-1593642532400-2682810df593?auto=format&fit=crop&w=900&q=82',
  'infraestrutura-de-redes': 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=900&q=82',
  'computacao-em-nuvem': 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=900&q=82',
  'marketing-digital-empresas': 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=900&q=82',
  'gestao-de-trafego-pago': 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=900&q=82',
  'mercado-livre-energia-geracao-distribuida': 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?auto=format&fit=crop&w=900&q=82',
}

const services = solutions.map(item => ({
  id: item.slug, kind: 'service', title: item.title, category: item.eyebrow,
  description: item.description, icon: item.icon, image: serviceImages[item.slug] || null,
  href: item.slug === 'energia-solar' ? '/energia-solar' : `/solucoes/${item.slug}`,
}))
const products = ref([])
const managedItems = ref([])
const items = computed(() => {
  if (managedItems.value.length) return managedItems.value
  const merged = []
  const maximum = Math.max(products.value.length, services.length)
  for (let index = 0; index < maximum; index += 1) {
    if (products.value[index]) merged.push(products.value[index])
    if (services[index]) merged.push(services[index])
  }
  return merged
})
const active = ref(0)
const perView = ref(3)
const viewport = ref(null)
const viewportWidth = ref(0)
let timer

const trackStyle = computed(() => {
  const cardWidth = (viewportWidth.value - ((perView.value - 1) * 18)) / perView.value
  return { transform: `translateX(-${active.value * (cardWidth + 18)}px)` }
})
function updatePerView() {
  perView.value = window.innerWidth < 680 ? 1 : window.innerWidth < 1020 ? 2 : 3
  viewportWidth.value = viewport.value?.clientWidth || 0
}
function next() { active.value = items.value.length ? (active.value + 1) % items.value.length : 0 }
function previous() { active.value = items.value.length ? (active.value - 1 + items.value.length) % items.value.length : 0 }
function goTo(index) { active.value = index; restart() }
function play() { pause(); timer = window.setInterval(next, 4500) }
function pause() { if (timer) window.clearInterval(timer) }
function restart() { play() }

onMounted(async () => {
  await nextTick(); updatePerView(); window.addEventListener('resize', updatePerView); play()
  try {
    const managed = await carregarCarrossel()
    managedItems.value = managed.map(item => ({
      id: item.uuid, kind: item.tipo === 'produto' ? 'product' : 'service', title: item.titulo,
      category: item.categoria || 'IntelbrasTech', description: item.descricao,
      image: item.imagem_url, href: item.link_url || '/contato', external: Boolean(item.link_externo),
      linkText: item.texto_link,
    }))
    if (managedItems.value.length) return
    const catalog = await carregarCatalogo()
    products.value = catalog.map(product => ({
      id: product.id, kind: 'product', title: product.nome,
      category: [product.marca, product.categoria].filter(Boolean).join(' · ') || 'Catálogo IntelbrasTech',
      description: product.descricao || 'Produto selecionado no catálogo IntelbrasTech.',
      image: product.imagem, href: product.link || '/contato?assunto=produto', external: Boolean(product.link),
    }))
  } catch { products.value = [] }
})
onBeforeUnmount(() => { pause(); window.removeEventListener('resize', updatePerView) })
</script>
