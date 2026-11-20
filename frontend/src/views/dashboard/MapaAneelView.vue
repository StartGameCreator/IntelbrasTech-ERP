<template>
  <div class="space-y-4 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Mapa ANEEL</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">Consulta de microgeração distribuída pela base pública da ANEEL.</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="centralizarBrasil" class="btn-secondary btn-sm"><ArrowsPointingInIcon class="w-4 h-4" /> Brasil</button>
        <button @click="carregar" class="btn-primary btn-sm" :disabled="loading">
          <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
          {{ loading ? 'Consultando...' : 'Consultar ANEEL' }}
        </button>
      </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="card p-3"><p class="kpi-value text-lg">{{ resumo.total || 0 }}</p><p class="kpi-label">Registros na ANEEL</p></div>
      <div class="card p-3"><p class="kpi-value text-lg text-green-400">{{ registros.length }}</p><p class="kpi-label">Resultados carregados</p></div>
      <div class="card p-3"><p class="kpi-value text-lg text-amber-400">{{ comCoordenadas }}</p><p class="kpi-label">Com coordenadas</p></div>
      <div class="card p-3"><p class="kpi-value text-lg">{{ formatarNumero(potenciaTotal) }} MW</p><p class="kpi-label">Potência da consulta</p></div>
    </div>

    <div class="card p-3">
      <div class="grid grid-cols-1 md:grid-cols-7 gap-2">
        <select v-model="filtros.perfil" class="select" @change="perfilAlterado">
          <option value="gd_residencial_comercial">GD resid. + comercial</option>
          <option value="gd_residencial">GD residencial</option>
          <option value="gd_comercial">GD comercial</option>
          <option value="gd_todos">GD todos</option>
          <option value="siga">SIGA usinas</option>
        </select>
        <input v-model="filtros.busca" class="input md:col-span-2" placeholder="Titular, município, distribuidora ou código" @keyup.enter="carregar" />
        <select v-model="filtros.uf" class="select" @change="estadoAlterado">
          <option value="">Todos os estados</option>
          <option v-for="uf in opcoes.ufs" :key="uf" :value="uf">{{ uf }}</option>
        </select>
        <select v-model="filtros.cidade" class="select" :disabled="!filtros.uf || !opcoes.cidades.length" @change="carregar">
          <option value="">Todas as cidades</option>
          <option v-for="cidade in opcoes.cidades" :key="cidade" :value="cidade">{{ cidade }}</option>
        </select>
        <input v-model="filtros.cep_inicio" class="input" inputmode="numeric" placeholder="CEP ou prefixo" @keyup.enter="carregar" />
        <input v-model="filtros.cep_fim" class="input" inputmode="numeric" placeholder="CEP final opcional" @keyup.enter="carregar" />
        <div class="flex gap-2">
          <input v-model="filtros.classe" class="input min-w-0" placeholder="Tipo ANEEL (UFV)" @keyup.enter="carregar" />
          <button type="button" class="btn-secondary whitespace-nowrap" @click="limparTipo">Limpar</button>
        </div>
        <input v-model="filtros.potencia_min" class="input" type="number" min="0" step="0.01" placeholder="Potência nominal: todas" />
        <button @click="carregar" class="btn-primary"><MagnifyingGlassIcon class="w-4 h-4" /> Buscar</button>
      </div>
      <p v-if="cidadeStatus" class="mt-2 text-[11px] text-[var(--text-muted)]">{{ cidadeStatus }}</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[360px_minmax(0,1fr)] gap-3 mapa-aneel-grid">
      <div class="card overflow-hidden flex flex-col min-h-0">
        <div class="p-3 border-b border-[var(--border)] flex items-center justify-between">
          <div>
            <p class="font-semibold text-sm">{{ registros.length }} resultados ANEEL</p>
            <p class="text-[11px] text-[var(--text-muted)]">Microgeração distribuída</p>
          </div>
          <button class="btn-secondary btn-sm" @click="carregar" :disabled="loading"><ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" /></button>
        </div>
        <div class="overflow-y-auto flex-1 divide-y divide-[var(--border)]">
          <div v-if="loading" class="p-8 text-center text-sm text-[var(--text-muted)]">Consultando ANEEL...</div>
          <div v-else-if="!registros.length" class="p-8 text-center">
            <MapIcon class="w-10 h-10 mx-auto mb-2 text-[var(--text-muted)]" />
            <p class="text-sm font-medium">Nenhum registro carregado</p>
            <p class="text-xs text-[var(--text-muted)] mt-1">Use os filtros para consultar a base pública da ANEEL.</p>
          </div>
          <button v-for="item in registros" :key="item.id" @click="selecionar(item)" class="w-full text-left p-3 hover:bg-white/5 transition" :class="selecionado?.id === item.id && 'bg-brand-500/10'">
            <div class="flex gap-2 justify-between">
              <div class="min-w-0">
                <p class="font-semibold text-sm truncate">{{ item.nome }}</p>
                <p class="text-xs text-[var(--text-muted)] truncate">{{ item.municipio || 'Município não informado' }} · {{ item.uf || 'UF' }}</p>
              </div>
              <span class="badge badge-info whitespace-nowrap">{{ formatarNumero(item.potencia_mw) }} MW</span>
            </div>
            <div class="flex items-center justify-between mt-2">
              <span class="text-[11px] text-green-400">{{ item.fonte || 'ANEEL' }}</span>
              <span class="text-[11px] text-[var(--text-muted)]">{{ item.distribuidora || item.classe }}</span>
            </div>
          </button>
        </div>
      </div>

      <div class="card overflow-hidden relative min-h-0 mapa-aneel-canvas">
        <div ref="mapContainer" class="mapa-aneel-container"></div>
        <div v-if="mapError" class="absolute inset-0 z-[700] flex items-center justify-center bg-[#090910] p-6">
          <div class="max-w-md text-center">
            <ExclamationTriangleIcon class="w-12 h-12 mx-auto text-amber-400" />
            <p class="mt-3 font-semibold">Não foi possível inicializar o mapa</p>
            <p class="mt-2 text-xs text-[var(--text-muted)]">{{ mapError }}</p>
            <button class="btn-primary mt-4" @click="reinicializarMapa">Tentar novamente</button>
          </div>
        </div>
        <div v-if="selecionado" class="absolute z-[650] right-3 top-3 w-[360px] max-w-[calc(100%-24px)] card p-4 shadow-2xl">
          <button @click="selecionado = null" class="absolute right-3 top-3 text-[var(--text-muted)]"><XMarkIcon class="w-5 h-5" /></button>
          <p class="font-bold pr-8">{{ selecionado.nome }}</p>
          <p class="text-xs text-[var(--text-muted)] mt-1">Fonte: ANEEL · {{ selecionado.codigo || 'sem código' }}</p>
          <div class="grid grid-cols-2 gap-2 mt-3 text-xs">
            <div><span class="text-[var(--text-muted)]">Município</span><p>{{ selecionado.municipio || '-' }}</p></div>
            <div><span class="text-[var(--text-muted)]">UF</span><p>{{ selecionado.uf || '-' }}</p></div>
            <div><span class="text-[var(--text-muted)]">Potência</span><p>{{ formatarNumero(selecionado.potencia_mw) }} MW</p></div>
            <div><span class="text-[var(--text-muted)]">Classe/Fonte</span><p>{{ selecionado.classe || selecionado.fonte || '-' }}</p></div>
            <div><span class="text-[var(--text-muted)]">CNPJ</span><p>{{ formatarCnpj(selecionado.cnpj_principal) || '-' }}</p></div>
            <div><span class="text-[var(--text-muted)]">Contato</span><p>Enriquecer pelo CRM</p></div>
          </div>
          <div v-if="selecionado.titular" class="mt-3"><span class="text-xs text-[var(--text-muted)]">Titular</span><p class="text-xs mt-1 line-clamp-3">{{ selecionado.titular }}</p></div>
          <div v-if="selecionado.distribuidora" class="mt-3"><span class="text-xs text-[var(--text-muted)]">Distribuidora</span><p class="text-xs mt-1">{{ selecionado.distribuidora }}</p></div>
          <p class="mt-3 text-xs text-amber-400">{{ selecionado.contato?.observacao || 'Contato direto não informado pela ANEEL.' }}</p>
          <div class="flex gap-2 mt-4">
            <button type="button" class="btn-secondary btn-sm flex-1 justify-center" @click="verNoMapa(selecionado)">Ver no mapa</button>
            <button type="button" class="btn-primary btn-sm flex-1 justify-center" :disabled="importando" @click="enviarCrm(selecionado)">{{ importando ? 'Enviando...' : 'Enviar ao CRM' }}</button>
          </div>
        </div>
      </div>
    </div>

    <p class="text-[11px] text-[var(--text-muted)]">Fonte: ANEEL - dados abertos, recurso {{ resourceId }}. Esta tela é separada do Mapa ONS para evitar mistura de origem dos dados.</p>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import api from '@/services/api'
import { ArrowsPointingInIcon, ArrowPathIcon, ExclamationTriangleIcon, MagnifyingGlassIcon, MapIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const mapContainer = ref(null)
const loading = ref(false)
const importando = ref(false)
const registros = ref([])
const selecionado = ref(null)
const mapError = ref('')
const cidadeStatus = ref('')
const resourceId = ref('11ec447d-698d-4ab8-977f-b424d5deee6a')
const resumo = ref({ total: 0, potencia_mw: 0 })
const filtros = reactive({ perfil: 'gd_residencial_comercial', busca: '', uf: '', cidade: '', cep_inicio: '', cep_fim: '', classe: 'UFV', potencia_min: '' })
const opcoes = reactive({ ufs: [], cidades: [] })
let map = null
let markersLayer = null
let resizeObserver = null
const markersById = new Map()

const comCoordenadas = computed(() => registros.value.filter(coordenadasValidas).length)
const potenciaTotal = computed(() => registros.value.reduce((total, item) => total + Number(item.potencia_mw || 0), 0))

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
function coordenadasValidas(item) { const lat=Number(item?.latitude), lng=Number(item?.longitude); return Number.isFinite(lat)&&Number.isFinite(lng)&&lat>=-90&&lat<=90&&lng>=-180&&lng<=180 }
function corMarcador(item) { if (String(item.fonte || '').toLowerCase().includes('solar')) return '#f59e0b'; return '#22c55e' }

function desenharMarcadores() {
  if (!map || !markersLayer) return
  markersLayer.clearLayers(); markersById.clear(); const limites=[]
  for (const item of registros.value) {
    if (!coordenadasValidas(item)) continue
    const lat=Number(item.latitude), lng=Number(item.longitude), cor=corMarcador(item)
    const marker=L.circleMarker([lat,lng],{radius:selecionado.value?.id===item.id?9:6,color:cor,fillColor:cor,fillOpacity:.85,weight:selecionado.value?.id===item.id?3:1})
    marker.bindTooltip(`<strong>${escapeHtml(item.nome||'')}</strong><br>${escapeHtml(item.municipio||'')} / ${escapeHtml(item.uf||'')}<br>${formatarNumero(item.potencia_mw)} MW · ANEEL`,{direction:'top',sticky:true,opacity:.95})
    marker.on('click',()=>selecionar(item)); marker.addTo(markersLayer); markersById.set(item.id,marker); limites.push([lat,lng])
  }
  if (limites.length && !selecionado.value) map.fitBounds(L.latLngBounds(limites),{padding:[30,30],maxZoom:7,animate:false})
  setTimeout(()=>map?.invalidateSize(false),50)
}

async function carregarResumo() {
  const { data } = await api.get('/mapa-aneel/resumo', { params: { perfil: filtros.perfil } })
  resumo.value = data
  resourceId.value = data.resource_id || resourceId.value
}

async function carregarFiltros() {
  cidadeStatus.value = filtros.uf ? 'Carregando cidades da ANEEL...' : ''
  try {
    const { data } = await api.get('/mapa-aneel/filtros', {
      params: { perfil: filtros.perfil, uf: filtros.uf, classe: filtros.classe },
    })
    opcoes.ufs = data.ufs || opcoes.ufs
    opcoes.cidades = data.cidades || []
    if (filtros.uf) {
      const total = Number(data.total_remoto || 0)
      const varridos = Number(data.varridos || 0)
      const cidades = opcoes.cidades.length
      const origem = data.cache ? 'cache local' : 'ANEEL'
      cidadeStatus.value = data.parcial
        ? `Lista parcial: ${cidades} cidades, ${varridos}/${total || varridos} registros lidos.`
        : `${cidades} cidades carregadas (${varridos || total} registros, ${origem}).`
    } else {
      cidadeStatus.value = ''
    }
  } catch {
    if (filtros.uf) opcoes.cidades = []
    cidadeStatus.value = filtros.uf ? 'Nao foi possivel atualizar as cidades agora.' : ''
  }
}

async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get('/mapa-aneel/microgeracao', { params: filtros })
    registros.value = data.data || []
    resourceId.value = data.resource_id || resourceId.value
    await nextTick()
    if (!map) inicializarMapa()
    desenharMarcadores()
  } catch (e) {
    alert(e.response?.data?.message || 'Não foi possível consultar a base da ANEEL.')
  } finally {
    loading.value = false
  }
}

function limparTipo() {
  filtros.classe = ''
  filtros.cidade = ''
  carregarFiltros()
  carregar()
}

async function enviarCrm(item) {
  importando.value = true
  try {
    const { data } = await api.post('/mapa-aneel/importar-lead', item)
    alert(data.message || 'Registro enviado ao CRM.')
  } catch (e) {
    alert(e.response?.data?.message || 'Não foi possível enviar para o CRM.')
  } finally {
    importando.value = false
  }
}

async function estadoAlterado() {
  filtros.cidade = ''
  opcoes.cidades = []
  await carregarFiltros()
  await carregar()
}

async function perfilAlterado() {
  filtros.cidade = ''
  opcoes.cidades = []
  await carregarFiltros()
  await carregarResumo()
  await carregar()
}

function preencherCidades(cidades) {
  opcoes.cidades = [...new Set((cidades || []).filter(Boolean))]
    .sort((a, b) => String(a).localeCompare(String(b), 'pt-BR'))
}

function selecionar(item){selecionado.value=item; verNoMapa(item); desenharMarcadores()}
function verNoMapa(item){if(!map||!coordenadasValidas(item))return; const lat=Number(item.latitude),lng=Number(item.longitude); map.invalidateSize(true); map.flyTo([lat,lng],15,{animate:true,duration:1.1}); const marker=markersById.get(item.id); if(marker)setTimeout(()=>marker.openTooltip(),500)}
function centralizarBrasil(){selecionado.value=null; if(!map){inicializarMapa();return} map.invalidateSize(true); map.flyTo([-15.78,-47.93],5,{animate:true,duration:.9}); desenharMarcadores()}
function formatarNumero(v){return Number(v||0).toLocaleString('pt-BR',{maximumFractionDigits:2})}
function formatarCnpj(value){const digits=String(value||'').replace(/\D/g,'');return digits.length===14?digits.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/,'$1.$2.$3/$4-$5'):''}
function escapeHtml(v){return String(v).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;')}

onMounted(async()=>{await nextTick(); inicializarMapa(); try{await carregarFiltros(); await Promise.all([carregarResumo(),carregar()])}catch(e){console.error(e)}})
onBeforeUnmount(()=>destruirMapa())
</script>

<style>
.mapa-aneel-grid{height:calc(100dvh - 325px);min-height:540px;overflow:hidden}
.mapa-aneel-canvas{isolation:isolate;contain:layout paint;z-index:0}
.mapa-aneel-container{position:absolute;inset:0;width:100%;height:100%;min-height:540px;background:#0b0b12;z-index:0}
.mapa-aneel-canvas .leaflet-container{z-index:0}
.mapa-aneel-grid .overflow-y-auto{overscroll-behavior:contain}
.leaflet-control-zoom a{background:#171724!important;color:#f8fafc!important;border-color:#2b2b45!important}
.leaflet-control-attribution{background:rgba(9,9,16,.82)!important;color:#94a3b8!important}
.leaflet-control-attribution a{color:#a5b4fc!important}
.leaflet-tooltip{background:#171724!important;border:1px solid #2b2b45!important;color:#f8fafc!important;box-shadow:0 10px 25px rgba(0,0,0,.35)!important}
.leaflet-tooltip-top::before{border-top-color:#171724!important}
@media(max-width:1279px){.mapa-aneel-grid{height:auto;overflow:visible}.mapa-aneel-grid>:first-child{height:420px}.mapa-aneel-grid>:last-child{height:min(620px,65dvh);min-height:440px}}
@media(max-width:767px){.mapa-aneel-grid>:first-child{height:300px}.mapa-aneel-grid>:last-child{height:58dvh;min-height:380px}.mapa-aneel-container{min-height:380px}}
</style>
