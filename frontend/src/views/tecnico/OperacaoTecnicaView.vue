<template>
  <main class="operacao-tecnica">
    <header class="page-header">
      <div>
        <p class="eyebrow">Aplicativo do técnico</p>
        <h1 class="page-title">Minha operação de campo</h1>
        <p class="page-subtitle">Ative sua disponibilidade, atualize o GPS e aceite os atendimentos mais próximos.</p>
      </div>
      <button class="btn-secondary" :disabled="carregando" @click="carregar"><ArrowPathIcon class="icon" /> {{ carregando ? 'Atualizando…' : 'Atualizar' }}</button>
    </header>

    <section v-if="erroAcesso" class="card acesso-negado">
      <ExclamationTriangleIcon class="warning-icon" />
      <div><h2>Acesso de técnico necessário</h2><p>{{ erroAcesso }}</p></div>
    </section>

    <template v-else>
      <section class="status-card card">
        <div class="status-copy"><span :class="['status-dot', tecnico.disponivel ? 'online' : 'offline']"></span><div><p class="eyebrow">Status de atendimento</p><h2>{{ tecnico.disponivel ? 'Disponível para novos chamados' : 'Pausado para novos chamados' }}</h2><p>{{ tecnico.disponivel ? 'Você aparece na escala e pode assumir oportunidades.' : 'Ative quando estiver pronto para receber novos atendimentos.' }}</p></div></div>
        <div class="status-actions"><button :class="tecnico.disponivel ? 'btn-secondary' : 'btn-primary'" :disabled="alterandoDisponibilidade" @click="alternarDisponibilidade"><PowerIcon class="icon" /> {{ tecnico.disponivel ? 'Pausar disponibilidade' : 'Ficar disponível' }}</button><button class="btn-secondary" :disabled="atualizandoGps" @click="atualizarGps"><MapPinIcon class="icon" /> {{ atualizandoGps ? 'Obtendo GPS…' : 'Atualizar GPS' }}</button></div>
      </section>

      <section v-if="tecnico.localizacao?.precisa_atualizar" class="location-alert"><MapPinIcon class="icon" /><span>Seu GPS ainda não foi compartilhado. Atualize a localização para ver as oportunidades ordenadas por proximidade.</span></section>

      <section class="resumo" aria-label="Resumo de campo">
        <article><span>Oportunidades próximas</span><strong>{{ resumo.oportunidades_proximas || 0 }}</strong><small>Dentro de {{ tecnico.raio_atendimento_km || 50 }} km</small></article>
        <article><span>Fila de oportunidades</span><strong>{{ resumo.oportunidades_total || 0 }}</strong><small>Chamados ainda sem técnico</small></article>
        <article><span>Meus atendimentos</span><strong>{{ resumo.atendimentos_em_andamento || 0 }}</strong><small>Em andamento</small></article>
      </section>

      <section class="operacao-grid">
        <div class="map-column card">
          <div class="section-head"><div><h2>Mapa de demandas</h2><p>Você em azul, oportunidades em verde e atendimentos assumidos em laranja.</p></div></div>
          <div ref="mapContainer" class="mapa-demandas"></div>
          <p class="mapa-nota">Demandas sem GPS exato são posicionadas pela cidade/UF apenas para orientação inicial.</p>
        </div>

        <div class="fila-column">
          <section class="card painel">
            <div class="section-head"><div><h2>Oportunidades próximas</h2><p v-if="tecnico.localizacao?.precisa_atualizar">Ative o GPS para calcular a distância.</p><p v-else>Ordenadas da mais próxima para a mais distante.</p></div></div>
            <div v-if="carregando" class="empty">Carregando oportunidades…</div>
            <div v-else-if="!oportunidades.length" class="empty">Não há oportunidades técnicas abertas neste momento.</div>
            <div v-else class="demandas-lista">
              <article v-for="demanda in oportunidades" :key="`${demanda.origem}-${demanda.id}`" class="demanda-card">
                <div class="demand-top"><span class="tipo">{{ demanda.origem === 'os' ? 'OS' : 'Visita' }}</span><span v-if="demanda.distancia_km !== null" :class="['distancia', demanda.dentro_do_raio ? 'perto' : 'fora']">{{ formatarDistancia(demanda.distancia_km) }}</span></div>
                <h3>{{ demanda.titulo }}</h3><p class="cliente">{{ demanda.cliente }}</p><p v-if="demanda.endereco" class="endereco"><MapPinIcon class="mini-icon" /> {{ demanda.endereco }}</p><p v-if="demanda.descricao" class="descricao">{{ demanda.descricao }}</p>
                <div class="demand-foot"><small>{{ demanda.localizacao_aproximada ? 'Localização aproximada pela cidade' : 'Localização confirmada' }}</small><RouterLink v-if="demanda.url" :to="demanda.url">Ver OS</RouterLink><button class="btn-primary" :disabled="aceitando === chave(demanda) || !tecnico.disponivel" @click="aceitar(demanda)">{{ aceitando === chave(demanda) ? 'Assumindo…' : 'Assumir' }}</button></div>
              </article>
            </div>
          </section>

          <section class="card painel atribuicoes">
            <div class="section-head"><div><h2>Meus atendimentos</h2><p>Demandas que já estão sob sua responsabilidade.</p></div></div>
            <div v-if="!atribuicoes.length" class="empty compact">Nenhum atendimento em andamento.</div>
            <div v-else class="atribuidos-lista"><article v-for="demanda in atribuicoes" :key="`${demanda.origem}-${demanda.id}`"><div><strong>{{ demanda.titulo }}</strong><p>{{ demanda.cliente }}<span v-if="demanda.distancia_km !== null"> · {{ formatarDistancia(demanda.distancia_km) }}</span></p></div><RouterLink v-if="demanda.url" :to="demanda.url">Abrir</RouterLink></article></div>
          </section>
        </div>
      </section>
    </template>
  </main>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { ArrowPathIcon, ExclamationTriangleIcon, MapPinIcon, PowerIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const toast = useToast()
const carregando = ref(false)
const atualizandoGps = ref(false)
const alterandoDisponibilidade = ref(false)
const aceitando = ref('')
const erroAcesso = ref('')
const tecnico = ref({ disponibilidade: false, raio_atendimento_km: 50, localizacao: {} })
const resumo = ref({})
const oportunidades = ref([])
const atribuicoes = ref([])
const mapContainer = ref(null)
let map = null
let markersLayer = null
let resizeObserver = null

const chave = (demanda) => `${demanda.origem}-${demanda.id}`
const formatarDistancia = (distancia) => distancia < 1 ? `${Math.round(distancia * 1000)} m` : `${Number(distancia).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} km`
const escapeHtml = (valor) => String(valor || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;')

function inicializarMapa () {
  if (!mapContainer.value || map) return
  map = L.map(mapContainer.value, { center: [-15.78, -47.93], zoom: 5, minZoom: 3, maxZoom: 19, scrollWheelZoom: false, preferCanvas: true })
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors', crossOrigin: true }).addTo(map)
  markersLayer = L.layerGroup().addTo(map)
  resizeObserver = new ResizeObserver(() => requestAnimationFrame(() => map?.invalidateSize(false)))
  resizeObserver.observe(mapContainer.value)
}

function destruirMapa () { resizeObserver?.disconnect(); resizeObserver = null; if (map) map.remove(); map = null; markersLayer = null }
function coordenadasValidas (item) { const lat = Number(item?.latitude); const lng = Number(item?.longitude); return Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180 }
function adicionarMarcador (item, cor, titulo) {
  if (!map || !markersLayer || !coordenadasValidas(item)) return null
  const marker = L.circleMarker([Number(item.latitude), Number(item.longitude)], { radius: 8, color: '#fff', weight: 2, fillColor: cor, fillOpacity: 0.96 })
  marker.bindTooltip(`<strong>${escapeHtml(titulo)}</strong>${item.distancia_km !== null && item.distancia_km !== undefined ? `<br>${formatarDistancia(item.distancia_km)}` : ''}`, { direction: 'top', sticky: true, opacity: 0.95 })
  marker.addTo(markersLayer)
  return [Number(item.latitude), Number(item.longitude)]
}
function desenharMapa () {
  if (!map || !markersLayer) return
  markersLayer.clearLayers()
  const pontos = []
  if (coordenadasValidas(tecnico.value.localizacao)) pontos.push(adicionarMarcador(tecnico.value.localizacao, '#3b82f6', 'Sua localização'))
  oportunidades.value.forEach((demanda) => pontos.push(adicionarMarcador(demanda, '#22c55e', demanda.titulo)))
  atribuicoes.value.forEach((demanda) => pontos.push(adicionarMarcador(demanda, '#f59e0b', demanda.titulo)))
  const validos = pontos.filter(Boolean)
  if (validos.length === 1) map.setView(validos[0], 13)
  if (validos.length > 1) map.fitBounds(L.latLngBounds(validos), { padding: [28, 28], maxZoom: 13, animate: false })
  setTimeout(() => map?.invalidateSize(false), 40)
}

async function carregar () {
  carregando.value = true
  erroAcesso.value = ''
  try {
    const { data } = await api.get('/tecnico/operacao')
    tecnico.value = data.tecnico || tecnico.value
    resumo.value = data.resumo || {}
    oportunidades.value = data.oportunidades || []
    atribuicoes.value = data.atribuicoes || []
    await nextTick()
    inicializarMapa()
    desenharMapa()
  } catch (error) {
    const mensagem = error.response?.data?.message || 'Não foi possível carregar a operação técnica.'
    if (error.response?.status === 403) erroAcesso.value = mensagem
    else toast.error(mensagem)
  } finally { carregando.value = false }
}

async function alternarDisponibilidade () {
  alterandoDisponibilidade.value = true
  try {
    await api.patch('/tecnico/operacao/disponibilidade', { disponivel: !tecnico.value.disponivel })
    toast.success(!tecnico.value.disponivel ? 'Você está disponível para novos chamados.' : 'Disponibilidade pausada.')
    await carregar()
  } catch (error) { toast.error(error.response?.data?.message || 'Não foi possível atualizar sua disponibilidade.') } finally { alterandoDisponibilidade.value = false }
}

function atualizarGps () {
  if (!navigator.geolocation) { toast.error('Este dispositivo não disponibiliza geolocalização.'); return }
  atualizandoGps.value = true
  navigator.geolocation.getCurrentPosition(async (posicao) => {
    try {
      await api.patch('/tecnico/operacao/localizacao', { latitude: posicao.coords.latitude, longitude: posicao.coords.longitude })
      toast.success('GPS atualizado. A fila foi reorganizada por proximidade.')
      await carregar()
    } catch (error) { toast.error(error.response?.data?.message || 'Não foi possível salvar sua localização.') } finally { atualizandoGps.value = false }
  }, (erro) => { atualizandoGps.value = false; toast.error(erro.code === 1 ? 'Você precisa permitir a localização para ordenar oportunidades próximas.' : 'Não foi possível obter sua localização agora.') }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 })
}

async function aceitar (demanda) {
  aceitando.value = chave(demanda)
  try {
    await api.post(`/tecnico/operacao/oportunidades/${demanda.origem}/${demanda.id}/aceitar`)
    toast.success('Atendimento assumido com sucesso.')
    await carregar()
  } catch (error) { toast.error(error.response?.data?.message || 'Esta oportunidade não pôde ser assumida.') } finally { aceitando.value = '' }
}

onMounted(carregar)
onBeforeUnmount(destruirMapa)
</script>

<style scoped>
.operacao-tecnica{display:flex;flex-direction:column;gap:16px}.eyebrow{margin:0 0 4px;color:#22a95a;font-size:.67rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase}.page-subtitle{margin:5px 0 0;color:var(--text-muted);font-size:.76rem}.icon{width:16px;height:16px}.mini-icon{width:13px;height:13px;vertical-align:-2px}.status-card{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:17px}.status-copy{display:flex;align-items:flex-start;gap:11px}.status-dot{width:12px;height:12px;margin-top:5px;flex:0 0 auto;border-radius:999px;box-shadow:0 0 0 5px rgba(148,163,184,.1)}.status-dot.online{background:#22c55e;box-shadow:0 0 0 5px rgba(34,197,94,.13)}.status-dot.offline{background:#f59e0b}.status-copy h2{margin:0;font-size:.96rem}.status-copy p:not(.eyebrow){margin:4px 0 0;font-size:.67rem;color:var(--text-muted)}.status-actions{display:flex;gap:8px;flex-wrap:wrap}.status-actions button{display:inline-flex;align-items:center;gap:6px}.location-alert{display:flex;align-items:center;gap:8px;padding:11px 13px;border:1px solid rgba(245,158,11,.42);border-radius:10px;background:rgba(245,158,11,.09);color:#fcd34d;font-size:.72rem}.resumo{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.resumo article{padding:14px;border:1px solid var(--border);border-radius:11px;background:var(--bg-card)}.resumo span,.resumo strong,.resumo small{display:block}.resumo span,.resumo small{font-size:.62rem;color:var(--text-muted)}.resumo strong{margin:4px 0;font-size:1.35rem}.operacao-grid{display:grid;grid-template-columns:minmax(340px,.93fr) minmax(400px,1.07fr);gap:14px;align-items:start}.map-column{padding:14px;position:sticky;top:10px}.section-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:11px}.section-head h2{margin:0;font-size:.9rem}.section-head p{margin:4px 0 0;font-size:.64rem;color:var(--text-muted);line-height:1.45}.mapa-demandas{height:500px;border:1px solid var(--border);border-radius:10px;overflow:hidden;background:#0b0b12}.mapa-nota{margin:9px 2px 0;color:var(--text-muted);font-size:.59rem;line-height:1.4}.fila-column{display:flex;flex-direction:column;gap:14px}.painel{padding:14px}.demandas-lista{display:flex;flex-direction:column;gap:9px;max-height:650px;overflow:auto;padding-right:3px}.demanda-card{border:1px solid var(--border);border-radius:10px;background:var(--bg-elevated);padding:12px}.demand-top,.demand-foot{display:flex;align-items:center;justify-content:space-between;gap:8px}.tipo,.distancia{padding:3px 7px;border-radius:999px;font-size:.56rem;font-weight:900}.tipo{color:#60a5fa;background:rgba(59,130,246,.13)}.distancia.perto{color:#4ade80;background:rgba(34,197,94,.13)}.distancia.fora{color:#fbbf24;background:rgba(245,158,11,.13)}.demanda-card h3{margin:8px 0 3px;font-size:.77rem}.cliente,.descricao,.endereco{margin:0;color:var(--text-muted);font-size:.64rem;line-height:1.45}.endereco{margin-top:5px}.descricao{margin-top:7px}.demand-foot{margin-top:10px}.demand-foot small{font-size:.57rem;color:var(--text-muted)}.demand-foot a{font-size:.62rem;color:#60a5fa;font-weight:800;text-decoration:none}.demand-foot .btn-primary{font-size:.64rem;padding:7px 10px}.atribuicoes{padding-bottom:4px}.atribuidos-lista article{display:flex;align-items:center;justify-content:space-between;gap:10px;border-top:1px solid var(--border);padding:10px 0}.atribuidos-lista strong{font-size:.69rem}.atribuidos-lista p{margin:3px 0 0;color:var(--text-muted);font-size:.61rem}.atribuidos-lista a{color:#60a5fa;font-size:.62rem;font-weight:800;text-decoration:none}.empty{padding:30px 15px;text-align:center;color:var(--text-muted);font-size:.72rem}.empty.compact{padding:17px}.acesso-negado{display:flex;gap:12px;align-items:flex-start;padding:22px}.warning-icon{width:28px;color:#f59e0b}.acesso-negado h2{margin:0;font-size:1rem}.acesso-negado p{margin:5px 0 0;color:var(--text-muted);font-size:.75rem}@media(max-width:980px){.operacao-grid{grid-template-columns:1fr}.map-column{position:relative;top:auto}.mapa-demandas{height:380px}}@media(max-width:680px){.status-card{align-items:stretch;flex-direction:column}.status-actions{display:grid;grid-template-columns:1fr 1fr}.status-actions button{justify-content:center;padding:10px 7px;font-size:.66rem}.resumo{grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}.resumo article{padding:10px}.resumo strong{font-size:1.1rem}.operacao-grid{display:flex;flex-direction:column;gap:12px}.map-column{order:0;position:sticky;top:0;z-index:12;width:100%;padding:10px;box-shadow:0 8px 22px rgba(0,0,0,.3)}.mapa-demandas{height:min(40dvh,320px)}.fila-column{width:100%}.painel{padding:12px}.demandas-lista{max-height:none;overflow:visible}.demand-foot{align-items:flex-end}.demand-foot small{max-width:45%}.location-alert{align-items:flex-start}.resumo small{line-height:1.3}}.mapa-demandas :deep(.leaflet-control-zoom a){background:#171724!important;color:#f8fafc!important;border-color:#2b2b45!important}.mapa-demandas :deep(.leaflet-control-attribution){background:rgba(9,9,16,.82)!important;color:#94a3b8!important}.mapa-demandas :deep(.leaflet-tooltip){background:#171724!important;border:1px solid #2b2b45!important;color:#f8fafc!important;box-shadow:0 10px 25px rgba(0,0,0,.35)!important}
</style>
