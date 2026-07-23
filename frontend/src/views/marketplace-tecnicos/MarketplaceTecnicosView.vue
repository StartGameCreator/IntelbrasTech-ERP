<template>
  <div class="platform-admin">
    <header class="page-header">
      <div>
        <h1 class="page-title">Marketplace de Tecnicos</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">Gestao dos usuarios da IntelbrasTech Platform.</p>
      </div>
      <div class="toolbar">
        <RouterLink to="/cadastre-se" target="_blank" class="btn-secondary">Abrir cadastro</RouterLink>
        <button class="btn-secondary" @click="alternarLixeira">
          {{ lixeira ? 'Voltar aos usuarios' : `Lixeira (${resumo.excluidos || 0})` }}
        </button>
        <button class="btn-primary" @click="atualizarTudo">Atualizar</button>
      </div>
    </header>

    <section class="cards">
      <article><strong>{{ resumo.total || 0 }}</strong><span>Ativos</span></article>
      <article><strong>{{ resumo.tecnicos || 0 }}</strong><span>Tecnicos</span></article>
      <article><strong>{{ mapaResumo.pontos || 0 }}</strong><span>Regioes com tecnicos</span></article>
      <article><strong>{{ mapaResumo.estados || 0 }}</strong><span>Estados cobertos</span></article>
      <article><strong>{{ resumo.pendentes || 0 }}</strong><span>Pendentes</span></article>
      <article><strong>{{ resumo.excluidos || 0 }}</strong><span>Na lixeira</span></article>
    </section>

    <section class="card p-4 mapa-card">
      <div class="mapa-header">
        <div>
          <h2>Mapa de cobertura dos tecnicos</h2>
          <p>Veja onde estao concentrados os profissionais cadastrados.</p>
        </div>
        <div class="mapa-actions">
          <select v-model="filtroMapaStatus" class="select" @change="carregarMapa">
            <option value="">Todos os status</option>
            <option value="aprovado">Aprovados</option>
            <option value="pendente">Pendentes</option>
            <option value="suspenso">Suspensos</option>
            <option value="rejeitado">Rejeitados</option>
          </select>
          <button class="btn-secondary" @click="centralizarBrasil">
            <MapPinIcon /> Brasil
          </button>
        </div>
      </div>

      <div class="mapa-grid">
        <div class="mapa-wrap">
          <div ref="mapContainer" class="mapa-tecnicos"></div>
          <div v-if="mapaLoading" class="map-overlay">Carregando mapa...</div>
          <div v-if="!mapaLoading && !pontosMapa.length" class="map-overlay">
            Nenhum tecnico com cidade/UF cadastrado.
          </div>
        </div>

        <aside class="ranking-panel">
          <div class="ranking-block">
            <h3>Maiores regioes</h3>
            <button
              v-for="ponto in topCidades"
              :key="ponto.estado + ponto.cidade"
              type="button"
              class="ranking-row"
              @click="selecionarPonto(ponto)"
            >
              <span>{{ ponto.cidade }}/{{ ponto.estado || '--' }}</span>
              <strong>{{ ponto.total }}</strong>
            </button>
            <p v-if="!topCidades.length" class="empty-small">Sem tecnicos para ranquear.</p>
          </div>

          <div class="ranking-block">
            <h3>Por estado</h3>
            <div v-for="item in rankingEstados" :key="item.estado" class="estado-row">
              <span>{{ item.estado }}</span>
              <div><i :style="{ width: barraEstado(item.total) + '%' }"></i></div>
              <strong>{{ item.total }}</strong>
            </div>
          </div>
        </aside>
      </div>
      <p class="map-note">Quando o cadastro nao tiver latitude/longitude, o ponto e aproximado pela cidade/UF para orientar a regiao comercial.</p>
    </section>

    <section class="card p-4">
      <div class="filters">
        <input v-model="busca" class="input" placeholder="Pesquisar...">
        <select v-model="tipo" class="select">
          <option value="">Todos os perfis</option>
          <option value="tecnico">Tecnicos</option>
          <option value="cliente">Clientes</option>
          <option value="colaborador">Colaboradores</option>
        </select>
        <select v-model="status" class="select" :disabled="lixeira">
          <option value="">Todos os status</option>
          <option value="pendente">Pendente</option>
          <option value="aprovado">Aprovado</option>
          <option value="rejeitado">Rejeitado</option>
          <option value="suspenso">Suspenso</option>
        </select>
      </div>

      <div class="overflow-auto">
        <table class="table">
          <thead>
            <tr><th>Perfil</th><th>Nome</th><th>Contato</th><th>Localizacao</th><th>Status</th><th>Acoes</th></tr>
          </thead>
          <tbody>
            <tr v-for="item in filtrados" :key="item.id">
              <td>{{ label(item.tipo_perfil) }}</td>
              <td><strong>{{ item.nome }}</strong><small>{{ item.empresa || item.cargo || '' }}</small></td>
              <td>{{ item.email }}<small>{{ item.whatsapp || item.telefone || '' }}</small></td>
              <td>{{ item.cidade || '-' }}/{{ item.estado || '-' }}</td>
              <td>{{ item.status }}</td>
              <td>
                <div v-if="!lixeira" class="actions">
                  <button v-if="item.tipo_perfil === 'tecnico'" class="profile-btn" @click="abrirPerfil(item)">
                    <UserCircleIcon /> Perfil
                  </button>
                  <select v-model="item.status" class="select" @change="analisar(item)">
                    <option value="pendente">Pendente</option>
                    <option value="aprovado">Aprovado</option>
                    <option value="rejeitado">Rejeitado</option>
                    <option value="suspenso">Suspenso</option>
                  </select>
                  <button class="danger-btn" @click="abrirExcluir(item)">
                    <TrashIcon /> Excluir
                  </button>
                </div>
                <div v-else class="actions">
                  <button class="restore-btn" @click="restaurar(item)">
                    <ArrowPathIcon /> Restaurar
                  </button>
                  <button class="danger-btn" @click="abrirDefinitivo(item)">
                    <XMarkIcon /> Definitivo
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!filtrados.length">
              <td colspan="6" class="empty">{{ lixeira ? 'A lixeira esta vazia.' : 'Nenhum cadastro.' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="perfilAberto" class="backdrop" @click.self="perfilAberto = null">
      <section class="profile-modal">
        <button class="close-btn" @click="perfilAberto = null"><XMarkIcon /></button>
        <div class="profile-hero">
          <img v-if="perfilAberto.foto_url" :src="perfilAberto.foto_url" alt="">
          <div v-else class="avatar">{{ iniciais(perfilAberto.nome) }}</div>
          <div>
            <h2>{{ perfilAberto.nome }}</h2>
            <p>{{ perfilAberto.cidade || 'Cidade nao informada' }}/{{ perfilAberto.estado || '--' }}</p>
            <span class="status-pill">{{ perfilAberto.status }}</span>
          </div>
        </div>

        <div class="profile-grid">
          <article>
            <h3><AcademicCapIcon /> Qualificacoes</h3>
            <div class="chips">
              <span v-if="perfilAberto.crea">CREA {{ perfilAberto.crea }}</span>
              <span v-if="perfilAberto.crt">CRT {{ perfilAberto.crt }}</span>
              <span v-if="perfilAberto.cft">CFT {{ perfilAberto.cft }}</span>
              <span v-if="perfilAberto.anos_experiencia">{{ perfilAberto.anos_experiencia }} anos</span>
              <span v-if="perfilAberto.raio_atendimento_km">Raio {{ perfilAberto.raio_atendimento_km }} km</span>
            </div>
            <p v-if="perfilAberto.experiencia" class="profile-text">{{ perfilAberto.experiencia }}</p>
            <ul v-if="perfilEspecialidades.length">
              <li v-for="item in perfilEspecialidades" :key="item">{{ item }}</li>
            </ul>
            <p v-else class="empty-small">Qualificacoes ainda nao informadas.</p>
          </article>

          <article>
            <h3><ShieldCheckIcon /> Certificados</h3>
            <div v-if="perfilCertificados.length" class="cert-list">
              <div v-for="cert in perfilCertificados" :key="textoItem(cert)">
                <strong>{{ textoItem(cert) }}</strong>
                <a v-if="cert.url" :href="cert.url" target="_blank">Abrir documento</a>
              </div>
            </div>
            <p v-else class="empty-small">Nenhum certificado cadastrado ainda.</p>
          </article>
        </div>

        <article class="portfolio-block">
          <h3><PhotoIcon /> Servicos entregues</h3>
          <div v-if="perfilPortfolio.length" class="portfolio-grid">
            <figure v-for="foto in perfilPortfolio" :key="textoItem(foto)">
              <img v-if="foto.url || typeof foto === 'string'" :src="foto.url || foto" alt="">
              <figcaption>{{ foto.titulo || foto.descricao || 'Servico entregue' }}</figcaption>
            </figure>
          </div>
          <p v-else class="empty-small">Fotos de servicos ainda nao cadastradas.</p>
        </article>
      </section>
    </div>

    <div v-if="modalExcluir" class="backdrop" @click.self="fechar">
      <section class="modal">
        <TrashIcon class="modal-icon" />
        <h2>Mover usuario para a lixeira?</h2>
        <p>O login sera bloqueado, mas o historico sera preservado.</p>
        <div class="summary"><strong>{{ selecionado?.nome }}</strong><span>{{ selecionado?.email }}</span></div>
        <label><span>Motivo</span><textarea v-model="motivo"></textarea></label>
        <div class="modal-actions">
          <button class="btn-secondary" @click="fechar">Cancelar</button>
          <button class="danger-confirm" @click="excluir">Mover para a lixeira</button>
        </div>
      </section>
    </div>

    <div v-if="modalDefinitivo" class="backdrop" @click.self="fechar">
      <section class="modal">
        <ExclamationTriangleIcon class="modal-icon" />
        <h2>Excluir definitivamente</h2>
        <p>Somente o Administrador Master pode executar esta acao.</p>
        <label><span>Digite EXCLUIR</span><input v-model="confirmacao" class="input"></label>
        <label><span>Motivo</span><textarea v-model="motivo"></textarea></label>
        <div class="modal-actions">
          <button class="btn-secondary" @click="fechar">Cancelar</button>
          <button class="danger-confirm" :disabled="confirmacao !== 'EXCLUIR'" @click="excluirDefinitivo">Excluir definitivamente</button>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import api from '@/services/api'
import {
  AcademicCapIcon,
  ArrowPathIcon,
  ExclamationTriangleIcon,
  MapPinIcon,
  PhotoIcon,
  ShieldCheckIcon,
  TrashIcon,
  UserCircleIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()
const resumo = ref({})
const cadastros = ref([])
const mapaResumo = ref({})
const pontosMapa = ref([])
const rankingEstados = ref([])
const topCidades = ref([])
const busca = ref('')
const tipo = ref('')
const status = ref('')
const filtroMapaStatus = ref('')
const lixeira = ref(false)
const selecionado = ref(null)
const motivo = ref('')
const confirmacao = ref('')
const modalExcluir = ref(false)
const modalDefinitivo = ref(false)
const perfilAberto = ref(null)
const mapaLoading = ref(false)
const mapContainer = ref(null)
let map = null
let markers = null
let resizeObserver = null

const filtrados = computed(() => cadastros.value.filter(item => {
  const q = busca.value.toLowerCase()
  return (!tipo.value || item.tipo_perfil === tipo.value)
    && (!status.value || item.status === status.value)
    && (!q || `${item.nome} ${item.email} ${item.cidade} ${item.estado}`.toLowerCase().includes(q))
}))

const perfilEspecialidades = computed(() => {
  const perfil = perfilAberto.value
  if (!perfil) return []
  return [
    ...normalizarLista(perfil.especialidades),
    ...normalizarLista(perfil.qualificacoes?.especialidades),
  ].filter(Boolean)
})

const perfilCertificados = computed(() => normalizarLista(perfilAberto.value?.certificados))
const perfilPortfolio = computed(() => normalizarLista(perfilAberto.value?.portfolio))

const label = p => ({ tecnico: 'Tecnico', cliente: 'Cliente', colaborador: 'Colaborador' }[p] || p)

async function atualizarTudo() {
  await Promise.all([carregar(), carregarMapa()])
}

async function carregar() {
  try {
    const { data } = await api.get('/platform/cadastros', { params: { lixeira: lixeira.value ? 1 : 0 } })
    resumo.value = data.resumo || {}
    cadastros.value = data.cadastros || []
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha ao carregar.')
  }
}

async function carregarMapa() {
  mapaLoading.value = true
  try {
    const { data } = await api.get('/platform/cadastros/mapa-tecnicos', {
      params: { status: filtroMapaStatus.value || undefined },
    })
    mapaResumo.value = data.resumo || {}
    pontosMapa.value = data.pontos || []
    rankingEstados.value = data.rankings?.por_estado || []
    topCidades.value = data.rankings?.por_cidade || []
    await nextTick()
    inicializarMapa()
    desenharMapa()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha ao carregar mapa dos tecnicos.')
  } finally {
    mapaLoading.value = false
  }
}

function inicializarMapa() {
  if (!mapContainer.value || map) return
  map = L.map(mapContainer.value, {
    center: [-15.78, -47.93],
    zoom: 4,
    minZoom: 3,
    maxZoom: 18,
    scrollWheelZoom: false,
    preferCanvas: true,
  })
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap contributors',
    crossOrigin: true,
  }).addTo(map)
  markers = L.layerGroup().addTo(map)
  resizeObserver = new ResizeObserver(() => requestAnimationFrame(() => map?.invalidateSize(false)))
  resizeObserver.observe(mapContainer.value)
}

function desenharMapa() {
  if (!map || !markers) return
  markers.clearLayers()
  const bounds = []
  for (const ponto of pontosMapa.value) {
    const lat = Number(ponto.latitude)
    const lng = Number(ponto.longitude)
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue
    const radius = Math.max(8, Math.min(28, 8 + ponto.total * 3))
    const marker = L.circleMarker([lat, lng], {
      radius,
      color: '#60a5fa',
      fillColor: ponto.aprovados ? '#22c55e' : '#f59e0b',
      fillOpacity: 0.78,
      weight: 2,
    })
    marker.bindTooltip(`${ponto.cidade}/${ponto.estado}<br>${ponto.total} tecnico(s)`, {
      direction: 'top',
      sticky: true,
      opacity: 0.95,
    })
    marker.on('click', () => selecionarPonto(ponto))
    marker.addTo(markers)
    bounds.push([lat, lng])
  }
  if (bounds.length) map.fitBounds(L.latLngBounds(bounds), { padding: [35, 35], maxZoom: 6 })
  else centralizarBrasil()
  setTimeout(() => map?.invalidateSize(false), 100)
}

function selecionarPonto(ponto) {
  if (map && ponto.latitude && ponto.longitude) {
    map.flyTo([Number(ponto.latitude), Number(ponto.longitude)], 8, { duration: 0.8 })
  }
  const tecnico = ponto.tecnicos?.[0] || pontosMapa.value.find(p => p.id === ponto.id)?.tecnicos?.[0]
  if (tecnico) abrirPerfil(tecnico)
}

function centralizarBrasil() {
  if (!map) return
  map.flyTo([-15.78, -47.93], 4, { duration: 0.8 })
}

function barraEstado(total) {
  const maior = Math.max(...rankingEstados.value.map(item => Number(item.total || 0)), 1)
  return Math.max(8, Math.round((Number(total || 0) / maior) * 100))
}

async function analisar(item) {
  try {
    await api.patch(`/platform/cadastros/${item.id}/analisar`, { status: item.status })
    toast.success('Status atualizado.')
    await carregarMapa()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha ao atualizar.')
  }
}

function alternarLixeira() {
  lixeira.value = !lixeira.value
  status.value = ''
  carregar()
}

function abrirPerfil(item) {
  perfilAberto.value = normalizarPerfil(item)
}

function abrirExcluir(item) {
  selecionado.value = item
  motivo.value = ''
  modalExcluir.value = true
}

function abrirDefinitivo(item) {
  selecionado.value = item
  motivo.value = ''
  confirmacao.value = ''
  modalDefinitivo.value = true
}

function fechar() {
  modalExcluir.value = false
  modalDefinitivo.value = false
  selecionado.value = null
  motivo.value = ''
  confirmacao.value = ''
}

async function excluir() {
  try {
    await api.delete(`/platform/cadastros/${selecionado.value.id}`, { data: { motivo: motivo.value } })
    toast.success('Usuario movido para a lixeira.')
    fechar()
    await atualizarTudo()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha ao excluir.')
  }
}

async function restaurar(item) {
  try {
    await api.post(`/platform/cadastros/${item.id}/restaurar`)
    toast.success('Usuario restaurado.')
    await carregar()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha ao restaurar.')
  }
}

async function excluirDefinitivo() {
  try {
    await api.delete(`/platform/cadastros/${selecionado.value.id}/definitivo`, {
      data: { confirmacao: confirmacao.value, motivo: motivo.value },
    })
    toast.success('Usuario excluido definitivamente.')
    fechar()
    await atualizarTudo()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha ao excluir definitivamente.')
  }
}

function normalizarPerfil(item) {
  const qual = item.qualificacoes || {}
  const metadados = parseJson(item.metadados)
  return {
    ...item,
    foto_url: item.foto_url || metadados.foto_url || metadados.avatar,
    crea: item.crea || qual.crea,
    crt: item.crt || qual.crt,
    cft: item.cft || qual.cft,
    anos_experiencia: item.anos_experiencia || qual.anos_experiencia,
    raio_atendimento_km: item.raio_atendimento_km || qual.raio_atendimento_km,
    especialidades: item.especialidades || qual.especialidades,
    experiencia: item.experiencia || qual.experiencia,
    certificados: parseJson(item.certificados),
    portfolio: parseJson(item.portfolio),
  }
}

function normalizarLista(valor) {
  if (!valor) return []
  if (Array.isArray(valor)) return valor
  if (typeof valor === 'object') return Object.values(valor)
  return String(valor).split(/[\n;,]+/).map(item => item.trim()).filter(Boolean)
}

function parseJson(valor) {
  if (!valor) return Array.isArray(valor) ? valor : {}
  if (Array.isArray(valor) || typeof valor === 'object') return valor
  try {
    return JSON.parse(valor)
  } catch {
    return valor
  }
}

function textoItem(item) {
  if (!item) return ''
  if (typeof item === 'string') return item
  return item.nome || item.titulo || item.descricao || item.url || 'Item cadastrado'
}

function iniciais(nome) {
  return String(nome || 'T').split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase()
}

onMounted(atualizarTudo)
onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  if (map) map.remove()
  map = null
})
</script>

<style scoped>
.platform-admin{display:flex;flex-direction:column;gap:14px}.toolbar{display:flex;gap:8px}.cards{display:grid;grid-template-columns:repeat(6,1fr);gap:9px}.cards article{background:var(--bg-card);border:1px solid var(--border);border-radius:9px;padding:14px}.cards strong,.cards span{display:block}.cards strong{font-size:1.2rem}.cards span{font-size:.55rem;color:var(--text-muted)}.filters{display:grid;grid-template-columns:1fr 210px 180px;gap:8px;margin-bottom:12px}.table td strong,.table td small{display:block}.table td small{font-size:.52rem;color:var(--text-muted)}.actions{display:flex;gap:6px;align-items:center}.danger-btn,.restore-btn,.profile-btn{display:flex;align-items:center;gap:4px;border-radius:6px;padding:7px 9px;font-size:.55rem;font-weight:700}.danger-btn{border:1px solid rgba(239,68,68,.3);background:rgba(239,68,68,.08);color:#f87171}.restore-btn{border:1px solid rgba(34,197,94,.3);background:rgba(34,197,94,.08);color:#4ade80}.profile-btn{border:1px solid rgba(96,165,250,.32);background:rgba(96,165,250,.1);color:#93c5fd}.danger-btn svg,.restore-btn svg,.profile-btn svg{width:13px}.empty{text-align:center;padding:40px!important;color:var(--text-muted)}.backdrop{position:fixed;inset:0;background:rgba(2,6,23,.75);display:grid;place-items:center;z-index:1000}.modal{width:min(430px,calc(100% - 30px));background:var(--bg-card);border:1px solid var(--border);border-radius:12px;padding:22px}.modal-icon{width:38px;color:#f87171}.modal h2{font-size:1rem;font-weight:800;margin-top:10px}.modal p{font-size:.65rem;color:var(--text-muted);margin-top:5px}.summary{border:1px solid var(--border);border-radius:8px;padding:10px;margin-top:14px}.summary strong,.summary span{display:block}.summary span{font-size:.6rem;color:var(--text-muted)}.modal label{display:block;margin-top:13px}.modal label span{display:block;font-size:.58rem;margin-bottom:5px}.modal textarea{width:100%;min-height:75px;background:var(--bg-muted);border:1px solid var(--border);border-radius:7px;color:var(--text-primary);padding:8px}.modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:15px}.danger-confirm{background:#dc2626;color:white;border-radius:7px;padding:9px 12px;font-size:.62rem;font-weight:800}.danger-confirm:disabled{opacity:.4}
.mapa-card{display:flex;flex-direction:column;gap:12px}.mapa-header{display:flex;justify-content:space-between;gap:12px;align-items:center}.mapa-header h2{font-size:.95rem;font-weight:800}.mapa-header p,.map-note,.empty-small{font-size:.62rem;color:var(--text-muted)}.mapa-actions{display:flex;gap:8px;align-items:center}.mapa-actions svg{width:14px}.mapa-grid{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:12px}.mapa-wrap{position:relative;border:1px solid var(--border);border-radius:8px;overflow:hidden;min-height:360px;background:#0b0b12}.mapa-tecnicos{position:absolute;inset:0;min-height:360px}.map-overlay{position:absolute;inset:0;display:grid;place-items:center;background:rgba(9,9,16,.72);font-size:.75rem;color:var(--text-muted);z-index:500}.ranking-panel{display:flex;flex-direction:column;gap:10px;min-width:0}.ranking-block{border:1px solid var(--border);border-radius:8px;padding:12px;background:rgba(255,255,255,.02)}.ranking-block h3{font-size:.72rem;font-weight:800;margin-bottom:9px}.ranking-row{width:100%;display:flex;justify-content:space-between;align-items:center;padding:8px;border-radius:7px;text-align:left;font-size:.65rem}.ranking-row:hover{background:rgba(255,255,255,.06)}.ranking-row strong,.estado-row strong{color:#93c5fd}.estado-row{display:grid;grid-template-columns:34px 1fr 34px;align-items:center;gap:7px;font-size:.62rem;margin-top:7px}.estado-row div{height:7px;border-radius:999px;background:rgba(148,163,184,.18);overflow:hidden}.estado-row i{display:block;height:100%;border-radius:999px;background:#22c55e}.profile-modal{width:min(980px,calc(100% - 30px));max-height:calc(100vh - 40px);overflow:auto;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:22px;position:relative}.close-btn{position:absolute;right:14px;top:14px;color:var(--text-muted)}.close-btn svg{width:20px}.profile-hero{display:flex;gap:14px;align-items:center;padding-bottom:16px;border-bottom:1px solid var(--border)}.profile-hero img,.avatar{width:82px;height:82px;border-radius:12px;object-fit:cover;background:linear-gradient(135deg,#2563eb,#22c55e)}.avatar{display:grid;place-items:center;font-size:1.35rem;font-weight:900;color:white}.profile-hero h2{font-size:1.25rem;font-weight:900}.profile-hero p{font-size:.72rem;color:var(--text-muted);margin-top:2px}.status-pill{display:inline-flex;margin-top:8px;border:1px solid rgba(34,197,94,.28);background:rgba(34,197,94,.08);color:#4ade80;border-radius:999px;padding:4px 8px;font-size:.58rem;font-weight:800}.profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}.profile-grid article,.portfolio-block{border:1px solid var(--border);border-radius:10px;padding:14px;background:rgba(255,255,255,.02)}.profile-grid h3,.portfolio-block h3{display:flex;align-items:center;gap:7px;font-size:.78rem;font-weight:900;margin-bottom:10px}.profile-grid h3 svg,.portfolio-block h3 svg{width:17px;color:#93c5fd}.chips{display:flex;flex-wrap:wrap;gap:6px}.chips span{border:1px solid rgba(148,163,184,.24);border-radius:999px;padding:5px 8px;font-size:.58rem}.profile-text{font-size:.7rem;color:var(--text-muted);margin:10px 0}.profile-grid li{font-size:.66rem;margin-top:5px;color:var(--text-primary)}.cert-list{display:grid;gap:8px}.cert-list div{border:1px solid var(--border);border-radius:8px;padding:9px}.cert-list strong,.cert-list a{display:block;font-size:.65rem}.cert-list a{color:#93c5fd;margin-top:4px}.portfolio-block{margin-top:12px}.portfolio-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.portfolio-grid figure{border:1px solid var(--border);border-radius:9px;overflow:hidden;background:rgba(255,255,255,.03)}.portfolio-grid img{width:100%;aspect-ratio:4/3;object-fit:cover}.portfolio-grid figcaption{padding:8px;font-size:.62rem;color:var(--text-muted)}
:deep(.leaflet-control-zoom a){background:#171724!important;color:#f8fafc!important;border-color:#2b2b45!important}:deep(.leaflet-control-attribution){background:rgba(9,9,16,.82)!important;color:#94a3b8!important}:deep(.leaflet-tooltip){background:#171724!important;border:1px solid #2b2b45!important;color:#f8fafc!important;box-shadow:0 10px 25px rgba(0,0,0,.35)!important}
@media(max-width:1100px){.cards{grid-template-columns:repeat(3,1fr)}.mapa-grid,.profile-grid{grid-template-columns:1fr}.ranking-panel{display:grid;grid-template-columns:1fr 1fr}.portfolio-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){.toolbar,.mapa-header,.mapa-actions,.actions{flex-direction:column;align-items:stretch}.cards{grid-template-columns:repeat(2,1fr)}.filters{grid-template-columns:1fr}.ranking-panel{grid-template-columns:1fr}.profile-hero{align-items:flex-start}.portfolio-grid{grid-template-columns:1fr}}
</style>
