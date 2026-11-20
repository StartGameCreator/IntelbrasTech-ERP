<template>
  <div class="seo-page">
    <header><div><span>PORTAL PÚBLICO</span><h1>SEO e Analytics</h1><p>Gerencie buscadores, métricas de audiência, comportamento e campanhas em um único lugar.</p></div><button :disabled="saving" @click="salvar"><CheckIcon />{{ saving ? 'Salvando...' : 'Salvar configurações' }}</button></header>

    <div v-if="loading" class="loading">Carregando configurações...</div>
    <template v-else>
      <section><div class="section-title"><MagnifyingGlassIcon /><div><h2>SEO do site</h2><p>Informações usadas pelo Google e por outros mecanismos de busca.</p></div></div><div class="grid">
        <label><span>Nome do site</span><input v-model="form.site_nome" placeholder="IntelbrasTech" /></label>
        <label><span>Título SEO</span><input v-model="form.seo_titulo" maxlength="180" placeholder="IntelbrasTech — Tecnologia e Energia" /><small>{{ (form.seo_titulo || '').length }}/180</small></label>
        <label class="wide"><span>Descrição SEO</span><textarea v-model="form.seo_descricao" maxlength="500" rows="3" placeholder="Descrição que aparecerá nos resultados de busca"></textarea><small>{{ (form.seo_descricao || '').length }}/500</small></label>
        <label class="wide"><span>Palavras-chave</span><input v-model="form.seo_palavras_chave" placeholder="energia solar, tecnologia, assistência técnica" /></label>
        <label><span>URL canônica</span><input v-model="form.seo_canonical_url" type="url" placeholder="https://seudominio.com.br" /></label>
        <label><span>Imagem de compartilhamento (Open Graph)</span><input v-model="form.seo_og_image_url" type="url" placeholder="https://.../imagem.jpg" /></label>
        <label class="toggle wide"><input v-model="form.indexar_site" type="checkbox" /><span><b>Permitir indexação</b><small>Autoriza mecanismos de busca a indexarem o portal.</small></span></label>
      </div></section>

      <section><div class="section-title"><GlobeAltIcon /><div><h2>Google Search Console</h2><p>Cole somente o conteúdo do código de verificação fornecido pelo Google.</p></div></div><div class="grid"><label class="wide"><span>Google site verification</span><input v-model="form.google_site_verification" placeholder="Ex.: AbCdEf123..." /><small>Será publicado como meta tag google-site-verification.</small></label></div></section>

      <section><div class="section-title"><ChartBarIcon /><div><h2>Analytics e desempenho</h2><p>Os rastreadores só são carregados após o visitante autorizar cookies de análise.</p></div></div>
        <div v-if="form.google_analytics_ativo && form.google_tag_manager_ativo" class="warning">GA4 e GTM estão ativos. Não cadastre a mesma propriedade GA4 dentro do GTM para evitar visitas duplicadas.</div>
        <div class="pixel-grid">
        <article><div class="brand google">G</div><div><h3>Google Analytics 4</h3><p>Audiência, aquisição e conversões</p></div><label><span>ID de métricas</span><input v-model.trim="form.google_analytics_id" placeholder="G-XXXXXXXXXX" /></label><label class="toggle"><input v-model="form.google_analytics_ativo" type="checkbox" :disabled="!form.google_analytics_id" /><span><b>GA4 ativo</b></span></label></article>
        <article><div class="brand gtm">GTM</div><div><h3>Google Tag Manager</h3><p>Gerenciador central de tags</p></div><label><span>ID do contêiner</span><input v-model.trim="form.google_tag_manager_id" placeholder="GTM-XXXXXXX" /></label><label class="toggle"><input v-model="form.google_tag_manager_ativo" type="checkbox" :disabled="!form.google_tag_manager_id" /><span><b>GTM ativo</b></span></label></article>
        <article><div class="brand clarity">C</div><div><h3>Microsoft Clarity</h3><p>Mapas de calor e gravações de sessão</p></div><label><span>ID do projeto</span><input v-model.trim="form.microsoft_clarity_id" placeholder="Ex.: abc123def4" /></label><label class="toggle"><input v-model="form.microsoft_clarity_ativo" type="checkbox" :disabled="!form.microsoft_clarity_id" /><span><b>Clarity ativo</b></span></label></article>
        <article><div class="brand meta">f</div><div><h3>Meta Analytics / Pixel</h3><p>Facebook e Instagram Ads</p></div><label><span>ID do Pixel</span><input v-model.trim="form.meta_pixel_id" inputmode="numeric" placeholder="123456789012345" /></label><label class="toggle"><input v-model="form.meta_pixel_ativo" type="checkbox" :disabled="!form.meta_pixel_id" /><span><b>Meta ativo</b></span></label></article>
        <article><div class="brand tiktok">♪</div><div><h3>TikTok Pixel</h3><p>TikTok Ads Manager</p></div><label><span>ID do Pixel</span><input v-model="form.tiktok_pixel_id" placeholder="CXXXXXXXXXXXXXX" /></label><label class="toggle"><input v-model="form.tiktok_pixel_ativo" type="checkbox" :disabled="!form.tiktok_pixel_id" /><span><b>Pixel ativo</b></span></label></article>
      </div></section>

      <section><div class="section-title"><PhotoIcon /><div><h2>Logo e favicon</h2><p>Identidade visual exibida no portal e na aba do navegador.</p></div></div><div class="grid">
        <label><span>URL da logo</span><input v-model="form.logo_url" type="url" placeholder="https://.../logo.png" /><div v-if="form.logo_url" class="logo-preview"><img :src="form.logo_url" alt="Prévia da logo" /></div></label>
        <label><span>URL do favicon</span><input v-model="form.favicon_url" type="url" placeholder="https://.../favicon.png" /><div v-if="form.favicon_url" class="favicon-preview"><img :src="form.favicon_url" alt="Prévia do favicon" /></div></label>
      </div></section>
    </template>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { ChartBarIcon, CheckIcon, GlobeAltIcon, MagnifyingGlassIcon, PhotoIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
const toast=useToast(); const loading=ref(true); const saving=ref(false)
const form=reactive({site_nome:'',seo_titulo:'',seo_descricao:'',seo_palavras_chave:'',seo_canonical_url:'',seo_og_image_url:'',google_site_verification:'',google_analytics_id:'',google_analytics_ativo:false,google_tag_manager_id:'',google_tag_manager_ativo:false,microsoft_clarity_id:'',microsoft_clarity_ativo:false,meta_pixel_id:'',meta_pixel_ativo:false,tiktok_pixel_id:'',tiktok_pixel_ativo:false,logo_url:'',favicon_url:'',indexar_site:true})
;['google_analytics','google_tag_manager','microsoft_clarity','meta_pixel','tiktok_pixel'].forEach(key=>watch(()=>form[`${key}_id`],v=>{if(!v)form[`${key}_ativo`]=false}))
async function carregar(){try{Object.assign(form,(await api.get('/portal/configuracoes/site')).data.data||{})}finally{loading.value=false}}
async function salvar(){saving.value=true;try{await api.put('/portal/configuracoes/site',form);toast.success('SEO e rastreamento atualizados.')}catch(e){toast.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||'Não foi possível salvar as configurações.')}finally{saving.value=false}}
onMounted(carregar)
</script>

<style scoped>
.seo-page{max-width:1350px;margin:auto;padding:24px;color:#e8edf7}.seo-page>header{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:22px}.seo-page>header>div>span{font-size:10px;letter-spacing:.13em;color:#708cff;font-weight:800}.seo-page h1{font-size:28px;font-weight:850;margin-top:5px}.seo-page>header p,.section-title p{font-size:12px;color:#8793aa;margin-top:5px}.seo-page>header button{display:flex;gap:8px;align-items:center;background:#5d5fef;padding:11px 16px;border-radius:8px;font-weight:800}.seo-page>header button svg{width:18px}.seo-page>header button:disabled{opacity:.55}.loading,section{background:#111424;border:1px solid #292e45;border-radius:14px;padding:21px;margin-bottom:17px}.section-title{display:flex;gap:12px;align-items:flex-start;padding-bottom:17px;border-bottom:1px solid #252a40;margin-bottom:17px}.section-title>svg{width:23px;color:#768dff}.section-title h2{font-size:17px;font-weight:800}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.wide{grid-column:1/-1}label>span{display:block;font-size:11px;font-weight:750;margin-bottom:6px;color:#b6bfd0}input,textarea{width:100%;background:#0a0d19;border:1px solid #30364e;border-radius:8px;padding:10px 12px;color:#f5f7fb}label>small{display:block;color:#69758c;font-size:9px;margin-top:5px}.toggle{display:flex;align-items:center;gap:10px;background:#0c0f1c;border:1px solid #292f46;border-radius:9px;padding:11px}.toggle input{width:auto}.toggle span{margin:0}.toggle b,.toggle small{display:block}.toggle small{font-weight:400;color:#7f8ba2;margin-top:2px}.pixel-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.pixel-grid article{display:grid;grid-template-columns:auto 1fr;gap:10px;background:#0c0f1c;border:1px solid #292f46;border-radius:12px;padding:16px}.pixel-grid article>label{grid-column:1/-1}.pixel-grid h3{font-weight:800}.pixel-grid p{font-size:10px;color:#7e8aa1}.brand{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;font-size:22px;font-weight:900}.brand.meta{background:#166fe51f;color:#4b91ff}.brand.tiktok{background:#ff1f581c;color:#ff557c}.logo-preview,.favicon-preview{height:90px;margin-top:10px;border-radius:9px;background:#fff;display:grid;place-items:center;padding:10px}.logo-preview img{max-width:100%;max-height:70px}.favicon-preview img{width:48px;height:48px;object-fit:contain}@media(max-width:750px){.seo-page{padding:15px}.seo-page>header{flex-direction:column}.grid,.pixel-grid{grid-template-columns:1fr}.wide{grid-column:auto}}
.brand.google{background:#4285f422;color:#68a0ff}.brand.gtm{background:#f9ab0022;color:#ffc247;font-size:11px}.brand.clarity{background:#18a8e522;color:#38c4ff}.warning{margin-bottom:14px;padding:11px 13px;border:1px solid #8f711e;background:#7a5f171f;color:#f0ce6d;border-radius:9px;font-size:11px;line-height:1.5}
</style>
