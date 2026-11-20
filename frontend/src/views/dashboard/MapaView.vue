<template>
  <div class="space-y-4 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Mapa ONS</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">Localizador nacional de usinas fotovoltaicas e oportunidades comerciais.</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="centralizarBrasil" class="btn-secondary btn-sm"><ArrowsPointingInIcon class="w-4 h-4" /> Brasil</button>
        <button @click="sincronizar" class="btn-primary btn-sm" :disabled="sincronizando">
          <ArrowPathIcon class="w-4 h-4" :class="sincronizando && 'animate-spin'" />
          {{ sincronizando ? 'Sincronizando...' : 'Atualizar base pública' }}
        </button>
      </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="card p-3"><p class="kpi-value text-lg">{{ resumo.total || 0 }}</p><p class="kpi-label">Usinas solares</p></div>
      <div class="card p-3"><p class="kpi-value text-lg text-green-400">{{ resumo.operacao || 0 }}</p><p class="kpi-label">Em operação</p></div>
      <div class="card p-3"><p class="kpi-value text-lg text-amber-400">{{ resumo.construcao || 0 }}</p><p class="kpi-label">Em construção</p></div>
      <div class="card p-3"><p class="kpi-value text-lg">{{ formatarNumero(resumo.potencia_mw) }} MW</p><p class="kpi-label">Potência cadastrada</p></div>
    </div>

    <div class="card p-3">
      <div class="grid grid-cols-1 md:grid-cols-6 gap-2">
        <input v-model="filtros.busca" class="input md:col-span-2" placeholder="Nome, município ou código CEG" @keyup.enter="carregar" />
        <select v-model="filtros.uf" class="select"><option value="">Todos os estados</option><option v-for="uf in resumo.ufs || []" :key="uf" :value="uf">{{ uf }}</option></select>
        <select v-model="filtros.fase" class="select"><option value="">Todas as fases</option><option v-for="fase in resumo.fases || []" :key="fase" :value="fase">{{ fase }}</option></select>
        <input v-model="filtros.potencia_min" class="input" type="number" min="0" step="0.1" placeholder="Potência mín. MW" />
        <button @click="carregar" class="btn-primary"><MagnifyingGlassIcon class="w-4 h-4" /> Buscar</button>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[330px_minmax(0,1fr)] gap-3 mapa-ons-grid">
      <div class="card overflow-hidden flex flex-col min-h-0">
        <div class="p-3 border-b border-[var(--border)] flex items-center justify-between">
          <div><p class="font-semibold text-sm">{{ usinas.length }} resultados</p><p class="text-[11px] text-[var(--text-muted)]">Clique para localizar e prospectar</p></div>
          <button class="btn-secondary btn-sm" @click="carregar" :disabled="loading"><ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" /></button>
        </div>
        <div class="overflow-y-auto flex-1 divide-y divide-[var(--border)]">
          <div v-if="loading" class="p-8 text-center text-sm text-[var(--text-muted)]">Carregando usinas...</div>
          <div v-else-if="!usinas.length" class="p-8 text-center">
            <MapIcon class="w-10 h-10 mx-auto mb-2 text-[var(--text-muted)]" />
            <p class="text-sm font-medium">Nenhuma usina carregada</p>
            <p class="text-xs text-[var(--text-muted)] mt-1">Clique em “Atualizar base pública” na primeira utilização.</p>
          </div>
          <button v-for="usina in usinas" :key="usina.id" @click="selecionar(usina)" class="w-full text-left p-3 hover:bg-white/5 transition" :class="selecionada?.id === usina.id && 'bg-brand-500/10'">
            <div class="flex gap-2 justify-between">
              <div class="min-w-0"><p class="font-semibold text-sm truncate">{{ usina.nome }}</p><p class="text-xs text-[var(--text-muted)] truncate">{{ usina.municipio }} · {{ usina.uf }}</p></div>
              <span class="badge badge-info whitespace-nowrap">{{ formatarNumero(usina.potencia_mw) }} MW</span>
            </div>
            <div class="flex items-center justify-between mt-2">
              <span class="text-[11px]" :class="usina.fase === 'Operação' ? 'text-green-400' : 'text-amber-400'">{{ usina.fase }}</span>
              <span v-if="usina.lead_id" class="text-[11px] text-green-400">Já está no CRM</span>
            </div>
          </button>
        </div>
      </div>

      <div class="card overflow-hidden relative min-h-0 mapa-ons-canvas">
        <div ref="mapContainer" class="mapa-ons-container"></div>
        <div v-if="mapError" class="absolute inset-0 z-[700] flex items-center justify-center bg-[#090910] p-6">
          <div class="max-w-md text-center">
            <ExclamationTriangleIcon class="w-12 h-12 mx-auto text-amber-400" />
            <p class="mt-3 font-semibold">Não foi possível inicializar o mapa</p>
            <p class="mt-2 text-xs text-[var(--text-muted)]">{{ mapError }}</p>
            <button class="btn-primary mt-4" @click="reinicializarMapa">Tentar novamente</button>
          </div>
        </div>
        <div v-if="selecionada" class="absolute z-[650] right-3 top-3 w-[340px] max-w-[calc(100%-24px)] card p-4 shadow-2xl">
          <button @click="selecionada = null" class="absolute right-3 top-3 text-[var(--text-muted)]"><XMarkIcon class="w-5 h-5" /></button>
          <p class="font-bold pr-8">{{ selecionada.nome }}</p>
          <p class="text-xs text-[var(--text-muted)] mt-1">{{ selecionada.codigo_ceg }}</p>
          <div v-if="selecionada.cnpjs_proprietarios?.length" class="mt-3">
            <label class="text-xs text-[var(--text-muted)]">CNPJ do proprietário</label>
            <select v-model="cnpjSelecionado" class="select mt-1 w-full">
              <option
                v-for="cnpj in selecionada.cnpjs_proprietarios"
                :key="cnpj"
                :value="cnpj"
              >
                {{ formatarCnpj(cnpj) }}
              </option>
            </select>
          </div>
          <div v-else class="mt-3 text-xs text-amber-400">
            CNPJ não identificado na base pública. O Lead será criado para enriquecimento posterior.
          </div>
          <div class="grid grid-cols-2 gap-2 mt-3 text-xs">
            <div><span class="text-[var(--text-muted)]">Município</span><p>{{ selecionada.municipio }}</p></div>
            <div><span class="text-[var(--text-muted)]">UF</span><p>{{ selecionada.uf }}</p></div>
            <div><span class="text-[var(--text-muted)]">Potência</span><p>{{ formatarNumero(selecionada.potencia_mw) }} MW</p></div>
            <div><span class="text-[var(--text-muted)]">Fase</span><p>{{ selecionada.fase }}</p></div>
          </div>
          <div v-if="selecionada.proprietarios" class="mt-3"><span class="text-xs text-[var(--text-muted)]">Proprietários / participação</span><p class="text-xs mt-1 line-clamp-4">{{ selecionada.proprietarios }}</p></div>
          <div class="flex gap-2 mt-4">
            <button type="button" class="btn-secondary btn-sm flex-1 justify-center" @click="verNoMapa(selecionada)">Ver no mapa</button>
            <button @click="importar(selecionada)" class="btn-primary btn-sm flex-1 justify-center" :disabled="selecionada.lead_id || importando">{{ selecionada.lead_id ? 'No CRM' : importando ? 'Salvando...' : 'Levar ao CRM' }}</button>
          </div>
        </div>
      </div>
    </div>

    <p class="text-[11px] text-[var(--text-muted)]">Fonte geográfica inicial: ANEEL SIGA. O módulo usa o ONS como referência operacional e será ampliado com camadas de transmissão, subestações e dados de operação.</p>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import api from '@/services/api'
import { ArrowsPointingInIcon, ArrowPathIcon, ExclamationTriangleIcon, MagnifyingGlassIcon, MapIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const mapContainer = ref(null)
const loading = ref(false)
const sincronizando = ref(false)
const importando = ref(false)
const usinas = ref([])
const selecionada = ref(null)
const cnpjSelecionado = ref('')
const mapError = ref('')
const resumo = ref({ total: 0, operacao: 0, construcao: 0, potencia_mw: 0, ufs: [], fases: [] })
const filtros = reactive({ busca: '', uf: '', fase: '', potencia_min: '' })
let map = null
let markersLayer = null
let resizeObserver = null
const markersById = new Map()

function inicializarMapa() {
  if (!mapContainer.value || map) return
  try {
    mapError.value = ''
    map = L.map(mapContainer.value, { center: [-15.78, -47.93], zoom: 5, minZoom: 3, maxZoom: 19, zoomControl: true, preferCanvas: true, scrollWheelZoom: false })
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors', crossOrigin: true }).addTo(map)
    markersLayer = L.layerGroup().addTo(map)
    resizeObserver = new ResizeObserver(() => { if (map) requestAnimationFrame(() => map.invalidateSize(false)) })
    resizeObserver.observe(mapContainer.value)
    setTimeout(() => { map?.invalidateSize(true); desenharMarcadores() }, 300)
  } catch (error) {
    console.error('Falha ao inicializar Leaflet:', error)
    mapError.value = error?.message || 'Erro desconhecido ao iniciar o Leaflet.'
  }
}
function reinicializarMapa() { destruirMapa(); nextTick(() => inicializarMapa()) }
function destruirMapa() { resizeObserver?.disconnect(); resizeObserver = null; markersById.clear(); if (map) { map.remove(); map = null } markersLayer = null }
function coordenadasValidas(usina) { const lat=Number(usina?.latitude), lng=Number(usina?.longitude); return Number.isFinite(lat)&&Number.isFinite(lng)&&lat>=-90&&lat<=90&&lng>=-180&&lng<=180 }
function corMarcador(usina) { if (usina.lead_id) return '#22c55e'; if (usina.fase === 'Operação') return '#3b82f6'; if (String(usina.fase||'').toLowerCase().includes('constru')) return '#f59e0b'; return '#a855f7' }
function desenharMarcadores() {
  if (!map || !markersLayer) return
  markersLayer.clearLayers(); markersById.clear(); const limites=[]
  for (const usina of usinas.value) {
    if (!coordenadasValidas(usina)) continue
    const lat=Number(usina.latitude), lng=Number(usina.longitude), cor=corMarcador(usina)
    const marker=L.circleMarker([lat,lng],{radius:selecionada.value?.id===usina.id?9:6,color:cor,fillColor:cor,fillOpacity:.85,weight:selecionada.value?.id===usina.id?3:1})
    marker.bindTooltip(`<strong>${escapeHtml(usina.nome||'')}</strong><br>${escapeHtml(usina.municipio||'')} / ${escapeHtml(usina.uf||'')}<br>${formatarNumero(usina.potencia_mw)} MW · ${escapeHtml(usina.fase||'')}`,{direction:'top',sticky:true,opacity:.95})
    marker.on('click',()=>selecionar(usina)); marker.addTo(markersLayer); markersById.set(usina.id,marker); limites.push([lat,lng])
  }
  if (limites.length && !selecionada.value) map.fitBounds(L.latLngBounds(limites),{padding:[30,30],maxZoom:7,animate:false})
  setTimeout(()=>map?.invalidateSize(false),50)
}
async function carregarResumo(){const {data}=await api.get('/mapa-ons/resumo'); resumo.value=data}
async function carregar(){loading.value=true; try{const {data}=await api.get('/mapa-ons/usinas',{params:filtros}); usinas.value=data.data||[]; await nextTick(); if(!map) inicializarMapa(); desenharMarcadores()}catch(e){alert(e.response?.data?.message||'Não foi possível carregar as usinas.')}finally{loading.value=false}}
async function sincronizar(){sincronizando.value=true; try{const {data}=await api.post('/mapa-ons/sincronizar'); alert(`${data.message} ${data.total||0} usinas disponíveis.`); await Promise.all([carregarResumo(),carregar()])}catch(e){alert(e.response?.data?.message||'Falha na sincronização.')}finally{sincronizando.value=false}}
function selecionar(usina){selecionada.value=usina
  cnpjSelecionado.value = usina.cnpj_principal || usina.cnpjs_proprietarios?.[0] || ''; verNoMapa(usina); desenharMarcadores()}
function verNoMapa(usina){if(!map||!coordenadasValidas(usina))return; const lat=Number(usina.latitude),lng=Number(usina.longitude); map.invalidateSize(true); map.flyTo([lat,lng],15,{animate:true,duration:1.1}); const marker=markersById.get(usina.id); if(marker)setTimeout(()=>marker.openTooltip(),500)}
function centralizarBrasil(){selecionada.value=null; if(!map){inicializarMapa();return} map.invalidateSize(true); map.flyTo([-15.78,-47.93],5,{animate:true,duration:.9}); desenharMarcadores()}
async function importar(usina){importando.value=true; try{const {data}=await api.post(`/mapa-ons/usinas/${usina.id}/importar-lead`, { cnpj: cnpjSelecionado.value || usina.cnpj_principal || null }); usina.lead_id=data.lead.id; alert('Oportunidade adicionada ao funil do CRM.'); desenharMarcadores()}catch(e){alert(e.response?.data?.message||'Não foi possível importar para o CRM.')}finally{importando.value=false}}

function formatarCnpj(value) {
  const digits = String(value || '').replace(/\D/g, '').slice(0, 14)
  if (digits.length !== 14) return value || ''
  return digits.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5')
}
function formatarNumero(v){return Number(v||0).toLocaleString('pt-BR',{maximumFractionDigits:2})}
function escapeHtml(v){return String(v).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;')}
onMounted(async()=>{await nextTick(); inicializarMapa(); try{await Promise.all([carregarResumo(),carregar()])}catch(e){console.error(e)}})
onBeforeUnmount(()=>destruirMapa())
</script>

<style>
.mapa-ons-grid{height:calc(100dvh - 325px);min-height:540px;overflow:hidden}
.mapa-ons-canvas{isolation:isolate;contain:layout paint;z-index:0}
.mapa-ons-container{position:absolute;inset:0;width:100%;height:100%;min-height:540px;background:#0b0b12;z-index:0}
.mapa-ons-canvas .leaflet-container{z-index:0}
.leaflet-control-zoom a{background:#171724!important;color:#f8fafc!important;border-color:#2b2b45!important}
.leaflet-control-attribution{background:rgba(9,9,16,.82)!important;color:#94a3b8!important}
.leaflet-control-attribution a{color:#a5b4fc!important}
.leaflet-tooltip{background:#171724!important;border:1px solid #2b2b45!important;color:#f8fafc!important;box-shadow:0 10px 25px rgba(0,0,0,.35)!important}
.leaflet-tooltip-top::before{border-top-color:#171724!important}
@media(max-width:1279px){.mapa-ons-grid{height:auto;overflow:visible}.mapa-ons-grid>:first-child{height:420px}.mapa-ons-grid>:last-child{height:min(620px,65dvh);min-height:440px}}
@media(max-width:767px){.mapa-ons-grid>:first-child{height:300px}.mapa-ons-grid>:last-child{height:58dvh;min-height:380px}.mapa-ons-container{min-height:380px}}
</style>
