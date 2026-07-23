<template>
  <div class="space-y-4">
    <div class="page-header items-start gap-4">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-brand-500/15 text-brand-400 flex items-center justify-center"><MapPinIcon class="w-6 h-6" /></div>
        <div>
          <h1 class="page-title">Prospecção de Leads — Google Maps e CNPJ</h1>
          <p class="text-sm text-[var(--text-secondary)] mt-1">Encontre empresas por perfil, visualize a localização e selecione quais serão salvas no CRM.</p>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <span :class="statusConfigurado ? 'badge-success' : 'badge-warning'">{{ statusConfigurado ? 'Places configurado' : 'Places pendente' }}</span>
        <span :class="mapaConfigurado ? 'badge-success' : 'badge-warning'">{{ mapaConfigurado ? 'Mapa configurado' : 'Mapa pendente' }}</span>
      </div>
    </div>

    <div v-if="(!statusConfigurado || !mapaConfigurado) && statusCarregado" class="card border-warning/30 p-4 flex gap-3">
      <ExclamationTriangleIcon class="w-5 h-5 text-warning flex-shrink-0 mt-0.5" />
      <div class="space-y-1">
        <p class="font-medium text-sm">Configuração do Google incompleta</p>
        <p v-if="!statusConfigurado" class="text-xs text-[var(--text-secondary)]">Backend: configure <code class="text-brand-400">GOOGLE_PLACES_API_KEY</code>.</p>
        <p v-if="!mapaConfigurado" class="text-xs text-[var(--text-secondary)]">Frontend: configure <code class="text-brand-400">VITE_GOOGLE_MAPS_API_KEY</code> e reinicie o Vite.</p>
      </div>
    </div>

    <section class="card p-4">
      <div class="flex items-center justify-between mb-4">
        <div><h2 class="font-semibold">Filtros da prospecção</h2><p class="text-xs text-[var(--text-muted)] mt-1">Pesquise por atividade, cidade e qualidade do perfil.</p></div>
        <button class="btn-secondary btn-sm" @click="limparFiltros"><ArrowPathIcon class="w-4 h-4" /> Limpar</button>
      </div>
      <form @submit.prevent="pesquisar(true)" class="space-y-3">
        <div class="grid grid-cols-1 xl:grid-cols-[2fr_1fr] gap-3">
          <div><label class="label">Atividade, segmento ou palavra-chave *</label><input v-model.trim="filtros.termo" class="input" placeholder="Ex.: assistência técnica de celulares" required minlength="2" /></div>
          <div><label class="label">Cidade</label><input v-model.trim="filtros.cidade" class="input" placeholder="Ex.: Goiânia" /></div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
          <div><label class="label">UF</label><select v-model="filtros.estado" class="select"><option value="">Todas</option><option v-for="uf in ufs" :key="uf" :value="uf">{{ uf }}</option></select></div>
          <div><label class="label">Avaliação mínima</label><select v-model="filtros.min_avaliacao" class="select"><option value="">Qualquer</option><option value="3">3,0+</option><option value="3.5">3,5+</option><option value="4">4,0+</option><option value="4.5">4,5+</option></select></div>
          <div><label class="label">Mínimo de avaliações</label><input v-model.number="filtros.min_avaliacoes" type="number" min="0" class="input" placeholder="Ex.: 10" /></div>
          <label class="card-elevated px-3 py-2.5 flex items-center gap-2 cursor-pointer xl:mt-5"><input v-model="filtros.somente_com_site" type="checkbox" class="accent-brand-500" /><span class="text-xs">Somente com site</span></label>
          <label class="card-elevated px-3 py-2.5 flex items-center gap-2 cursor-pointer xl:mt-5"><input v-model="filtros.somente_com_fone" type="checkbox" class="accent-brand-500" /><span class="text-xs">Somente com telefone</span></label>
        </div>
        <div class="flex flex-wrap gap-3 items-center justify-between">
          <label class="flex items-center gap-2 text-xs text-[var(--text-secondary)] cursor-pointer"><input v-model="filtros.somente_abertos" type="checkbox" class="accent-brand-500" /> Mostrar somente empresas abertas agora</label>
          <button class="btn-primary" :disabled="carregando || !statusConfigurado"><MagnifyingGlassIcon class="w-4 h-4" />{{ carregando ? 'Buscando...' : 'Buscar no Google' }}</button>
        </div>
      </form>

      <div class="mt-4 pt-4 border-t border-[var(--border-subtle)]">
        <div class="flex items-center gap-2 mb-2"><IdentificationIcon class="w-5 h-5 text-brand-400" /><div><h3 class="text-sm font-semibold">Consulta rápida por CNPJ</h3><p class="text-xs text-[var(--text-muted)]">Enriqueça um lead sem sair desta tela.</p></div></div>
        <form @submit.prevent="consultarCnpj" class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_auto] gap-2">
          <input v-model="cnpjEntrada" class="input" inputmode="numeric" maxlength="18" placeholder="00.000.000/0000-00" @input="formatarCnpj" />
          <button class="btn-secondary" :disabled="cnpjCarregando || cnpjNumeros.length !== 14"><MagnifyingGlassIcon class="w-4 h-4" />{{ cnpjCarregando ? 'Consultando...' : 'Consultar CNPJ' }}</button>
        </form>
        <article v-if="cnpjResultado" class="mt-3 rounded-xl border border-[var(--border)] bg-[var(--bg-elevated)] p-3">
          <div class="flex flex-col lg:flex-row lg:items-center gap-3 justify-between">
            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="badge-success">CNPJ localizado</span><span v-if="cnpjResultado.situacao" class="badge-gray">{{ cnpjResultado.situacao }}</span></div><h3 class="font-semibold mt-2 truncate">{{ cnpjResultado.razao_social || cnpjResultado.nome_fantasia }}</h3><p class="text-xs text-[var(--text-muted)] mt-1">{{ cnpjResultado.cnpj }} · {{ enderecoCnpj(cnpjResultado) }}</p></div>
            <div class="flex gap-2"><button class="btn-ghost btn-sm" @click="cnpjResultado = null">Não guardar</button><button class="btn-primary btn-sm" :disabled="cnpjImportando || cnpjImportado" @click="importarCnpj"><CheckIcon v-if="cnpjImportado" class="w-4 h-4" /><PlusIcon v-else class="w-4 h-4" />{{ cnpjImportado ? 'Salvo no CRM' : cnpjImportando ? 'Salvando...' : 'Guardar no CRM' }}</button></div>
          </div>
        </article>
      </div>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-[minmax(340px,38%)_minmax(0,62%)] gap-4 items-stretch min-h-[620px]">
      <div class="card overflow-hidden flex flex-col min-h-[620px]">
        <div class="px-4 py-3 border-b border-[var(--border-subtle)] flex items-center justify-between gap-3">
          <div><h2 class="font-semibold">Empresas encontradas</h2><p class="text-xs text-[var(--text-muted)] mt-1">{{ buscaExecutada ? `${resultados.length} empresa(s)` : 'Faça uma busca para localizar oportunidades.' }}</p></div>
          <button v-if="resultados.length" class="btn-secondary btn-sm" @click="exportarCsv"><ArrowDownTrayIcon class="w-4 h-4" /> CSV</button>
        </div>
        <div class="flex-1 overflow-y-auto p-3 space-y-3 max-h-[720px]">
          <template v-if="carregando && !resultados.length"><div v-for="n in 4" :key="n" class="card-elevated p-4"><div class="skeleton h-28" /></div></template>
          <div v-else-if="!resultados.length" class="h-full min-h-[460px] flex items-center justify-center text-center p-8"><div><BuildingOffice2Icon class="w-12 h-12 mx-auto text-[var(--text-muted)]" /><p class="font-medium mt-3">Nenhuma consulta realizada</p><p class="text-sm text-[var(--text-muted)] mt-1">Informe o perfil desejado e clique em Buscar no Google.</p></div></div>
          <article v-for="(empresa, index) in resultados" v-else :key="empresa.google_place_id" class="card-elevated p-4 cursor-pointer transition-all" :class="empresaSelecionada?.google_place_id === empresa.google_place_id ? 'ring-2 ring-brand-500/60' : ''" @click="selecionarEmpresa(empresa)">
            <div class="flex gap-3"><div class="w-9 h-9 rounded-lg bg-brand-500/15 text-brand-400 flex items-center justify-center flex-shrink-0 font-semibold">{{ index + 1 }}</div><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><div class="min-w-0"><h3 class="font-semibold truncate">{{ empresa.nome }}</h3><p class="text-xs text-[var(--text-muted)] truncate">{{ empresa.categoria || 'Categoria não informada' }}</p></div><span v-if="empresa.aberto_agora !== null" :class="empresa.aberto_agora ? 'badge-success' : 'badge-gray'">{{ empresa.aberto_agora ? 'Aberto' : 'Fechado' }}</span></div><div class="flex items-center gap-2 mt-2 text-xs"><StarIcon class="w-4 h-4 text-warning" /><strong>{{ empresa.avaliacao ?? '—' }}</strong><span class="text-[var(--text-muted)]">({{ empresa.total_avaliacoes || 0 }})</span></div><p class="flex gap-2 mt-3 text-xs text-[var(--text-secondary)]"><MapPinIcon class="w-4 h-4 flex-shrink-0" /><span class="line-clamp-2">{{ empresa.endereco || 'Endereço não informado' }}</span></p><div class="flex flex-wrap gap-2 pt-3 mt-3 border-t border-[var(--border-subtle)]" @click.stop><a v-if="empresa.maps_url" :href="empresa.maps_url" target="_blank" rel="noopener" class="btn-secondary btn-sm"><MapIcon class="w-4 h-4" /> Maps</a><button class="btn-ghost btn-sm ml-auto" :disabled="importandoId === empresa.google_place_id || importados.has(empresa.google_place_id)" @click="alternarIgnorado(empresa.google_place_id)">{{ ignorados.has(empresa.google_place_id) ? 'Reconsiderar' : 'Não guardar' }}</button><button class="btn-primary btn-sm" :disabled="importandoId === empresa.google_place_id || importados.has(empresa.google_place_id) || ignorados.has(empresa.google_place_id)" @click="importar(empresa)"><CheckIcon v-if="importados.has(empresa.google_place_id)" class="w-4 h-4" /><PlusIcon v-else class="w-4 h-4" />{{ importados.has(empresa.google_place_id) ? 'No CRM' : importandoId === empresa.google_place_id ? 'Salvando...' : 'Guardar' }}</button></div></div></div>
          </article>
          <div v-if="nextPageToken" class="flex justify-center py-2"><button class="btn-secondary" :disabled="carregando" @click="carregarMais"><ArrowDownIcon class="w-4 h-4" />{{ carregando ? 'Carregando...' : 'Mais resultados' }}</button></div>
        </div>
      </div>

      <div class="card overflow-hidden flex flex-col min-h-[620px] xl:sticky xl:top-4">
        <div class="px-4 py-3 border-b border-[var(--border-subtle)] flex items-center justify-between gap-3"><div><h3 class="text-sm font-semibold">Mapa das empresas</h3><p class="text-xs text-[var(--text-muted)]">O mapa permanece disponível durante toda a prospecção.</p></div><button class="btn-secondary btn-sm" :disabled="!resultadosComCoordenadas.length" @click="ajustarMapa"><ArrowsPointingOutIcon class="w-4 h-4" /> Ver todas</button></div>
        <div v-if="!mapaConfigurado" class="flex-1 min-h-[560px] flex items-center justify-center p-8 text-center bg-[var(--bg-elevated)]"><div><MapIcon class="w-12 h-12 mx-auto text-[var(--text-muted)]" /><p class="font-medium mt-3">Mapa aguardando chave do frontend</p><p class="text-xs text-[var(--text-muted)] mt-2">Configure VITE_GOOGLE_MAPS_API_KEY e reinicie npm run dev.</p></div></div>
        <div v-else ref="mapElement" class="flex-1 min-h-[560px] w-full bg-[var(--bg-elevated)]"></div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import {
  ArrowDownIcon, ArrowDownTrayIcon, ArrowPathIcon, ArrowsPointingOutIcon,
  BuildingOffice2Icon, CheckIcon, ExclamationTriangleIcon, IdentificationIcon,
  MagnifyingGlassIcon, MapIcon, MapPinIcon, PhoneIcon, PlusIcon, StarIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()
const resultados = ref([])
const carregando = ref(false)
const buscaExecutada = ref(false)
const statusCarregado = ref(false)
const statusConfigurado = ref(false)
const nextPageToken = ref(null)
const consulta = ref('')
const importandoId = ref(null)
const importados = reactive(new Set())
const ignorados = reactive(new Set())
const empresaSelecionada = ref(null)

const mapElement = ref(null)
const googleMapsKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || ''
const googleMapId = import.meta.env.VITE_GOOGLE_MAP_ID || 'DEMO_MAP_ID'
const mapaConfigurado = computed(() => Boolean(googleMapsKey))
let map = null
let infoWindow = null
let markers = []
let mapsPromise = null

const cnpjEntrada = ref('')
const cnpjResultado = ref(null)
const cnpjCarregando = ref(false)
const cnpjImportando = ref(false)
const cnpjImportado = ref(false)
const cnpjNumeros = computed(() => cnpjEntrada.value.replace(/\D/g, ''))

const ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']
const filtros = reactive({
  termo: '', cidade: '', estado: '', min_avaliacao: '', min_avaliacoes: null,
  somente_abertos: false, somente_com_site: false, somente_com_fone: false,
})

const resultadosComCoordenadas = computed(() => resultados.value.filter(e => Number.isFinite(Number(e.latitude)) && Number.isFinite(Number(e.longitude))))

onMounted(async () => {
  try {
    const { data } = await api.get('/crm/google-business/status')
    statusConfigurado.value = Boolean(data.configured)
  } catch {
    statusConfigurado.value = false
  } finally {
    statusCarregado.value = true
    await nextTick()
    await inicializarMapaBase()
  }
})

onBeforeUnmount(() => limparMarkers())

function payload(token = null) {
  return {
    ...filtros,
    min_avaliacao: filtros.min_avaliacao || undefined,
    min_avaliacoes: filtros.min_avaliacoes || undefined,
    pagina_token: token || undefined,
    limite: 20,
  }
}

async function pesquisar(nova = true) {
  if (!filtros.termo || filtros.termo.length < 2) return
  carregando.value = true
  buscaExecutada.value = true
  try {
    const { data } = await api.post('/crm/google-business/pesquisar', payload(nova ? null : nextPageToken.value))
    resultados.value = nova ? data.data : [...resultados.value, ...data.data]
    nextPageToken.value = data.next_page_token || null
    consulta.value = data.consulta || ''
    empresaSelecionada.value = resultados.value[0] || null
    await nextTick()
    await renderizarMapa()
  } catch (error) {
    toast.error(error.response?.data?.google_error || error.response?.data?.message || 'Falha ao consultar empresas.')
  } finally {
    carregando.value = false
  }
}

function carregarMais() { pesquisar(false) }

function alternarIgnorado(id) {
  ignorados.has(id) ? ignorados.delete(id) : ignorados.add(id)
}

async function importar(empresa) {
  importandoId.value = empresa.google_place_id
  try {
    await api.post('/crm/google-business/importar-lead', empresa)
    importados.add(empresa.google_place_id)
    toast.success('Empresa adicionada ao CRM.')
  } catch (error) {
    if (error.response?.status === 409) {
      importados.add(empresa.google_place_id)
      toast.info('Esta empresa já está no CRM.')
    } else {
      toast.error(error.response?.data?.message || 'Não foi possível importar a empresa.')
    }
  } finally {
    importandoId.value = null
  }
}

function selecionarEmpresa(empresa) {
  empresaSelecionada.value = empresa
  const marker = markers.find(item => item.placeId === empresa.google_place_id)
  if (map && marker) {
    map.panTo(marker.position)
    map.setZoom(16)
    abrirInfo(marker, empresa)
  }
}

function carregarGoogleMaps() {
  if (window.google?.maps?.importLibrary) return Promise.resolve(window.google.maps)
  if (mapsPromise) return mapsPromise
  mapsPromise = new Promise((resolve, reject) => {
    const callback = `initIntelbrasMaps_${Date.now()}`
    window[callback] = () => {
      delete window[callback]
      resolve(window.google.maps)
    }
    const script = document.createElement('script')
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(googleMapsKey)}&v=weekly&libraries=marker&callback=${callback}`
    script.async = true
    script.defer = true
    script.onerror = () => reject(new Error('Falha ao carregar Google Maps JavaScript API.'))
    document.head.appendChild(script)
  })
  return mapsPromise
}

async function inicializarMapaBase() {
  if (!mapaConfigurado.value || !mapElement.value) return
  try {
    await carregarGoogleMaps()
    const { Map } = await window.google.maps.importLibrary('maps')
    if (!map) {
      map = new Map(mapElement.value, {
        center: { lat: -15.77972, lng: -47.92972 },
        zoom: 4,
        mapId: googleMapId,
        streetViewControl: false,
        mapTypeControl: false,
        fullscreenControl: true,
      })
      infoWindow = new window.google.maps.InfoWindow()
    }
  } catch (error) {
    toast.error(error.message || 'Não foi possível carregar o mapa.')
  }
}

async function renderizarMapa() {
  if (!mapaConfigurado.value || !mapElement.value) return
  if (!resultadosComCoordenadas.value.length) { await inicializarMapaBase(); return }
  try {
    await carregarGoogleMaps()
    const { Map } = await window.google.maps.importLibrary('maps')
    const { AdvancedMarkerElement } = await window.google.maps.importLibrary('marker')
    if (!map) {
      map = new Map(mapElement.value, {
        center: { lat: Number(resultadosComCoordenadas.value[0].latitude), lng: Number(resultadosComCoordenadas.value[0].longitude) },
        zoom: 12,
        mapId: googleMapId,
        streetViewControl: false,
        mapTypeControl: false,
        fullscreenControl: true,
      })
      infoWindow = new window.google.maps.InfoWindow()
    }
    limparMarkers()
    resultadosComCoordenadas.value.forEach((empresa, index) => {
      const position = { lat: Number(empresa.latitude), lng: Number(empresa.longitude) }
      const marker = new AdvancedMarkerElement({ map, position, title: empresa.nome })
      marker.placeId = empresa.google_place_id
      marker.position = position
      marker.addListener('click', () => {
        empresaSelecionada.value = empresa
        abrirInfo(marker, empresa, index)
      })
      markers.push(marker)
    })
    ajustarMapa()
  } catch (error) {
    toast.error(error.message || 'Não foi possível carregar o mapa.')
  }
}

function abrirInfo(marker, empresa, index = resultados.value.findIndex(e => e.google_place_id === empresa.google_place_id)) {
  if (!infoWindow || !map) return
  const nome = escaparHtml(empresa.nome || '')
  const endereco = escaparHtml(empresa.endereco || '')
  const avaliacao = empresa.avaliacao ? `${empresa.avaliacao} (${empresa.total_avaliacoes || 0})` : 'Sem avaliação'
  infoWindow.setContent(`<div style="max-width:250px;padding:4px"><strong>${index + 1}. ${nome}</strong><div style="font-size:12px;margin-top:5px">${endereco}</div><div style="font-size:12px;margin-top:5px">Avaliação: ${avaliacao}</div></div>`)
  infoWindow.open({ map, anchor: marker })
}

function ajustarMapa() {
  if (!map || !resultadosComCoordenadas.value.length || !window.google?.maps) return
  const bounds = new window.google.maps.LatLngBounds()
  resultadosComCoordenadas.value.forEach(e => bounds.extend({ lat: Number(e.latitude), lng: Number(e.longitude) }))
  map.fitBounds(bounds, 60)
  if (resultadosComCoordenadas.value.length === 1) map.setZoom(15)
}

function limparMarkers() {
  markers.forEach(marker => { marker.map = null })
  markers = []
}

function escaparHtml(value) {
  return String(value).replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]))
}

function formatarCnpj() {
  const n = cnpjNumeros.value.slice(0, 14)
  cnpjEntrada.value = n
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\d{4})(\d)/, '$1-$2')
}

async function consultarCnpj() {
  if (cnpjNumeros.value.length !== 14) return
  cnpjCarregando.value = true
  cnpjResultado.value = null
  cnpjImportado.value = false
  try {
    const { data } = await api.get('/cnpj/consultar', { params: { cnpj: cnpjNumeros.value, fonte: 'auto' } })
    cnpjResultado.value = data.empresa || data.data || data
  } catch (error) {
    toast.error(error.response?.data?.message || 'Não foi possível consultar o CNPJ.')
  } finally {
    cnpjCarregando.value = false
  }
}

async function importarCnpj() {
  if (!cnpjResultado.value) return
  cnpjImportando.value = true
  try {
    await api.post('/cnpj/importar-lead', { ...cnpjResultado.value, fonte: 'auto' })
    cnpjImportado.value = true
    toast.success('Empresa do CNPJ adicionada ao CRM.')
  } catch (error) {
    if (error.response?.status === 409) {
      cnpjImportado.value = true
      toast.info('Esta empresa já está no CRM.')
    } else {
      toast.error(error.response?.data?.message || 'Não foi possível guardar o CNPJ no CRM.')
    }
  } finally {
    cnpjImportando.value = false
  }
}

function enderecoCnpj(empresa) {
  return [empresa.logradouro, empresa.numero, empresa.bairro, empresa.cidade, empresa.uf].filter(Boolean).join(', ') || 'Endereço não informado'
}

function limparFiltros() {
  Object.assign(filtros, { termo: '', cidade: '', estado: '', min_avaliacao: '', min_avaliacoes: null, somente_abertos: false, somente_com_site: false, somente_com_fone: false })
  resultados.value = []
  buscaExecutada.value = false
  nextPageToken.value = null
  empresaSelecionada.value = null
  limparMarkers()
}

function exportarCsv() {
  const cab = ['Nome','Categoria','Telefone','Site','Avaliação','Qtd. avaliações','Endereço','Cidade','Estado','Latitude','Longitude','Google Maps']
  const esc = value => `"${String(value ?? '').replaceAll('"', '""')}"`
  const linhas = resultados.value.map(e => [e.nome,e.categoria,e.telefone,e.site,e.avaliacao,e.total_avaliacoes,e.endereco,e.cidade,e.estado,e.latitude,e.longitude,e.maps_url].map(esc).join(';'))
  const blob = new Blob(['\uFEFF' + [cab.join(';'), ...linhas].join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `empresas-google-${new Date().toISOString().slice(0,10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}
</script>
