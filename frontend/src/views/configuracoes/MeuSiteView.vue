<template>
  <div class="site-admin">
    <header>
      <div><span>CONFIGURAÇÕES</span><h1>Meu Site</h1><p>Central administrativa do portal público IntelbrasTech.</p></div>
      <a href="/" target="_blank" rel="noopener">Abrir site <ArrowTopRightOnSquareIcon /></a>
    </header>

    <section class="overview">
      <article><small>Cards no carrossel</small><strong>{{ carousel.length }}</strong><span>{{ carousel.filter(i => i.ativo).length }} ativos</span></article>
      <article><small>SEO do site</small><strong>{{ seo.seo_titulo ? 'Configurado' : 'Pendente' }}</strong><span>{{ seo.indexar_site ? 'Indexação permitida' : 'Indexação bloqueada' }}</span></article>
      <article><small>Pixels ativos</small><strong>{{ pixelsAtivos }}</strong><span>Meta e TikTok</span></article>
    </section>

    <section class="admin-grid">
      <RouterLink to="/configuracoes/site/carrossel" class="admin-card">
        <div class="icon violet"><RectangleStackIcon /></div><span>CONTEÚDO</span><h2>Carrossel do portal</h2><p>Crie, edite, agende, ordene e desative produtos, serviços ou campanhas da página inicial.</p><b>Administrar carrossel <ArrowRightIcon /></b>
      </RouterLink>
      <RouterLink to="/configuracoes/site/seo" class="admin-card">
        <div class="icon green"><GlobeAltIcon /></div><span>VISIBILIDADE</span><h2>SEO e rastreamento</h2><p>Configure Search Console, Meta Pixel, TikTok Pixel, metadados, logo e favicon.</p><b>Administrar SEO <ArrowRightIcon /></b>
      </RouterLink>
      <RouterLink to="/configuracoes/site/email" class="admin-card">
        <div class="icon blue"><EnvelopeIcon /></div><span>COMUNICAÇÃO</span><h2>Meu E-mail</h2><p>Configure SMTP, contatos autorizados, modelos, campanhas, fila e descadastros.</p><b>Administrar e-mails <ArrowRightIcon /></b>
      </RouterLink>
    </section>

    <section class="status-card"><div><h2>Status da publicação</h2><p>O conteúdo salvo nestes blocos é aplicado automaticamente ao portal público.</p></div><span class="online"><i></i> Portal conectado</span></section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowRightIcon, ArrowTopRightOnSquareIcon, EnvelopeIcon, GlobeAltIcon, RectangleStackIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'
const carousel=ref([]); const seo=reactive({seo_titulo:'',indexar_site:true,meta_pixel_ativo:false,tiktok_pixel_ativo:false})
const pixelsAtivos=computed(()=>Number(Boolean(seo.meta_pixel_ativo))+Number(Boolean(seo.tiktok_pixel_ativo)))
onMounted(async()=>{const [items,settings]=await Promise.allSettled([api.get('/portal/conteudo/carrossel'),api.get('/portal/configuracoes/site')]);if(items.status==='fulfilled')carousel.value=items.value.data.data||[];if(settings.status==='fulfilled')Object.assign(seo,settings.value.data.data||{})})
</script>

<style scoped>
.site-admin{max-width:1380px;margin:auto;padding:26px;color:#e8edf7}.site-admin>header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px}.site-admin>header span,.admin-card>span{font-size:10px;font-weight:850;letter-spacing:.13em;color:#748dff}.site-admin h1{font-size:30px;font-weight:900;margin-top:4px}.site-admin>header p{font-size:13px;color:#8490a7;margin-top:5px}.site-admin>header>a{display:flex;align-items:center;gap:7px;border:1px solid #333951;border-radius:8px;padding:10px 14px;font-weight:750}.site-admin>header svg{width:17px}.overview{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:19px}.overview article,.admin-card,.status-card{background:#111424;border:1px solid #282e45;border-radius:15px}.overview article{padding:18px}.overview small,.overview span{display:block;color:#8490a7;font-size:11px}.overview strong{display:block;font-size:22px;margin:5px 0}.admin-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.admin-card{display:block;padding:23px;transition:.2s}.admin-card:hover{transform:translateY(-2px);border-color:#5368ba}.icon{width:48px;height:48px;border-radius:12px;display:grid;place-items:center;margin-bottom:20px}.icon svg{width:25px}.icon.violet{background:#564fe925;color:#8d88ff}.icon.green{background:#16a66a22;color:#49dda0}.admin-card h2{font-size:20px;font-weight:850;margin-top:7px}.admin-card p{color:#8894aa;font-size:12px;line-height:1.65;margin:9px 0 22px}.admin-card b{display:flex;align-items:center;gap:8px;color:#8ba0ff;font-size:12px}.admin-card b svg{width:16px}.status-card{display:flex;justify-content:space-between;align-items:center;margin-top:18px;padding:19px}.status-card h2{font-size:15px;font-weight:800}.status-card p{color:#8490a7;font-size:11px;margin-top:4px}.online{display:flex;align-items:center;gap:7px;color:#5ee0a0;font-size:11px;font-weight:800}.online i{width:8px;height:8px;border-radius:50%;background:#2fd285;box-shadow:0 0 12px #2fd285}@media(max-width:750px){.site-admin{padding:15px}.site-admin>header{gap:14px;flex-direction:column}.overview,.admin-grid{grid-template-columns:1fr}.status-card{align-items:flex-start;gap:15px;flex-direction:column}}
</style>
