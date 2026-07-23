<template>
  <section class="space-y-5">
    <header class="flex items-start justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-[var(--text-primary)]">Central de APIs</h1>
        <p class="text-sm text-[var(--text-muted)] mt-1">Configure integrações e login social sem expor segredos no navegador.</p>
      </div>
      <button class="btn-secondary" @click="load" :disabled="loading">Atualizar</button>
    </header>

    <div v-if="loading" class="card p-8 text-center text-sm text-[var(--text-muted)]">Carregando integrações...</div>

    <template v-else>
      <div v-for="group in grouped" :key="group.category" class="space-y-3">
        <div class="flex items-center gap-2">
          <h2 class="text-sm font-semibold text-[var(--text-primary)]">{{ categoryLabel(group.category) }}</h2>
          <span class="badge">{{ group.items.length }}</span>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
          <article v-for="item in group.items" :key="item.provider" class="card p-4 space-y-4">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-start gap-3">
                <div v-if="socialProvider(item.provider)" class="w-10 h-10 rounded-xl bg-white flex items-center justify-center p-2 shadow-sm">
                  <SocialBrandLogo :provider="socialProvider(item.provider)" class="w-full h-full" />
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <h3 class="font-semibold text-[var(--text-primary)]">{{ item.name }}</h3>
                    <span :class="statusClass(item.status)" class="badge">{{ statusLabel(item.status) }}</span>
                  </div>
                  <p class="text-xs text-[var(--text-muted)] mt-1">{{ item.provider }}</p>
                </div>
              </div>
              <label class="flex items-center gap-2 text-xs text-[var(--text-secondary)]">
                <input type="checkbox" v-model="item.enabled" /> Ativa
              </label>
            </div>

            <div v-if="item.provider === 'social_google'" class="rounded-lg border border-brand-500/30 bg-brand-500/5 p-3 space-y-3">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p class="text-xs font-semibold text-[var(--text-primary)]">Importar credenciais OAuth do Google</p>
                  <p class="text-xs text-[var(--text-muted)] mt-1">Selecione o arquivo client_secret_*.json baixado do Google Cloud.</p>
                </div>
                <label class="btn-secondary btn-sm cursor-pointer">
                  Selecionar JSON
                  <input type="file" accept="application/json,.json" class="hidden" @change="importGoogleJson($event, item)" />
                </label>
              </div>
              <div v-if="googleCallback" class="rounded-md border border-[var(--border)] p-2 text-xs">
                <strong>Callback que deve estar no Google Cloud:</strong>
                <code class="block mt-1 break-all text-brand-300">{{ googleCallback }}</code>
              </div>
            </div>

            <div v-if="item.category === 'social'" class="rounded-lg border border-[var(--border)] bg-[var(--bg-input)]/40 p-3 space-y-3">
              <div class="text-xs text-[var(--text-secondary)]">
                O login social também autoriza os recursos corporativos permitidos pela conta.
              </div>
              <div v-if="['google','microsoft'].includes(socialProvider(item.provider))" class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs">
                  <span v-if="connection(socialProvider(item.provider))?.connected" class="badge-success badge">Conta conectada</span>
                  <span v-else class="badge-warning badge">Conta não conectada</span>
                  <span v-if="connection(socialProvider(item.provider))?.email" class="ml-2 text-[var(--text-muted)]">{{ connection(socialProvider(item.provider)).email }}</span>
                </div>
                <div class="flex gap-2">
                  <button v-if="connection(socialProvider(item.provider))?.connected" class="btn-secondary btn-sm" @click="disconnectSocial(socialProvider(item.provider))">Desconectar</button>
                  <button class="btn-primary btn-sm" @click="connectSocial(socialProvider(item.provider))">
                    {{ connection(socialProvider(item.provider))?.connected ? 'Reconectar conta' : 'Conectar conta' }}
                  </button>
                </div>
              </div>
              <div v-if="socialProvider(item.provider) === 'google'" class="text-xs text-[var(--text-muted)]">Google: login, Agenda, Meet e Perfil da Empresa.</div>
              <div v-if="socialProvider(item.provider) === 'microsoft'" class="text-xs text-[var(--text-muted)]">Microsoft: login, Outlook Calendar e Teams.</div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div v-for="field in item.credential_fields" :key="field" :class="field === 'private_key' ? 'md:col-span-2' : ''">
                <label class="label">{{ credentialLabel(field) }}</label>
                <textarea
                  v-if="field === 'private_key'"
                  class="input min-h-28 font-mono text-xs"
                  v-model="item._credentials[field]"
                  :placeholder="item.credentials?.[field] || 'Cole a chave privada .p8'"
                />
                <div v-else class="credential-input-wrap">
                  <input
                    class="input credential-input"
                    :type="credentialVisible(item.provider, field) ? 'text' : 'password'"
                    v-model="item._credentials[field]"
                    :placeholder="item.credentials?.[field] || 'Informe o valor'"
                    autocomplete="new-password"
                    spellcheck="false"
                    @paste="normalizeCredentialAfterPaste(item, field)"
                  />

                  <div class="credential-actions">
                    <button
                      type="button"
                      class="credential-action"
                      title="Colar da área de transferência"
                      @click="pasteCredential(item, field)"
                    >
                      Colar
                    </button>

                    <button
                      type="button"
                      class="credential-action"
                      :title="credentialVisible(item.provider, field) ? 'Ocultar valor' : 'Mostrar valor'"
                      @click="toggleCredentialVisibility(item.provider, field)"
                    >
                      {{ credentialVisible(item.provider, field) ? 'Ocultar' : 'Mostrar' }}
                    </button>

                    <button
                      v-if="item._credentials[field]"
                      type="button"
                      class="credential-action credential-action-danger"
                      title="Limpar campo"
                      @click="clearCredential(item, field)"
                    >
                      Limpar
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div v-for="(_, key) in item.settings" :key="key" :class="key === 'redirect_uri' ? 'md:col-span-2' : ''">
                <label class="label">{{ settingLabel(key) }}</label>
                <select v-if="typeof item.settings[key] === 'boolean'" class="input" v-model="item.settings[key]">
                  <option :value="true">Sim</option><option :value="false">Não</option>
                </select>
                <input v-else class="input" v-model="item.settings[key]" />
              </div>
            </div>


            <div
              v-if="item.provider === 'google_places'"
              class="google-key-share-hint"
            >
              <strong>Chave Google Cloud compartilhada</strong>
              <span>
                Ao salvar, esta chave também será aplicada automaticamente
                ao Google Maps JavaScript e ao Google Geocoding.
              </span>
            </div>
            <div v-if="item.category !== 'social'" class="flex flex-wrap gap-4 text-xs text-[var(--text-secondary)]">
              <label class="flex items-center gap-2"><input type="checkbox" v-model="item.is_primary" /> Principal</label>
              <label class="flex items-center gap-2"><input type="checkbox" v-model="item.is_fallback" /> Contingência</label>
            </div>

            <div v-if="item.last_test_message" class="rounded-lg border border-[var(--border)] p-3 text-xs text-[var(--text-secondary)]">
              {{ item.last_test_message }}
            </div>

            <div class="flex items-center gap-2 justify-end">
              <button class="btn-secondary" @click="test(item)" :disabled="busy[item.provider]">Validar configuração</button>
              <button class="btn-primary" @click="save(item)" :disabled="busy[item.provider]">Salvar</button>
            </div>
          </article>
        </div>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { apiIntegrations } from '@/services/apiIntegrations'
import api from '@/services/api'
import SocialBrandLogo from '@/components/branding/SocialBrandLogo.vue'

const toast = useToast()
const items = ref([])
const loading = ref(true)
const googleCallback = ref('http://127.0.0.1:8033/api/v1/auth/social/google/callback')
const busy = reactive({})
const visibleCredentials = reactive({})
const connections = reactive({ google: { connected: false }, microsoft: { connected: false } })

const grouped = computed(() => Object.entries(items.value.reduce((acc, item) => {
  ;(acc[item.category] ||= []).push(item)
  return acc
}, {})).map(([category, values]) => ({ category, items: values })))


function credentialKey(provider, field) {
  return `${provider}:${field}`
}

function credentialVisible(provider, field) {
  return !!visibleCredentials[credentialKey(provider, field)]
}

function toggleCredentialVisibility(provider, field) {
  const key = credentialKey(provider, field)
  visibleCredentials[key] = !visibleCredentials[key]
}

function normalizeCredential(value) {
  return String(value ?? '')
    .replace(/[\r\n\t]/g, '')
    .trim()
}

function normalizeCredentialAfterPaste(item, field) {
  window.setTimeout(() => {
    item._credentials[field] = normalizeCredential(item._credentials[field])
  }, 0)
}

async function pasteCredential(item, field) {
  try {
    if (!navigator.clipboard?.readText) {
      throw new Error('clipboard-unavailable')
    }

    const text = await navigator.clipboard.readText()
    item._credentials[field] = normalizeCredential(text)

    if (!item._credentials[field]) {
      toast.warning('A área de transferência está vazia.')
      return
    }

    toast.success('Valor colado com sucesso.')
  } catch (_) {
    toast.info('Use Ctrl+V ou clique com o botão direito no campo para colar.')
  }
}

function clearCredential(item, field) {
  item._credentials[field] = ''
}
async function loadConnections() {
  try {
    const { data } = await api.get('/auth/social/connections')
    connections.google = { connected: false }
    connections.microsoft = { connected: false }
    for (const item of data || []) {
      if (item.provider === 'google' || item.provider === 'microsoft') {
        connections[item.provider] = { connected: true, email: item.email, expires_at: item.expires_at }
      }
    }
  } catch (_) {}
}
const connection = provider => connections[provider] || { connected: false }
async function connectSocial(provider) {
  try {
    const { data } = await api.get(`/auth/social/${provider}/redirect`)
    window.location.href = data.url
  } catch (e) { toast.error(e.response?.data?.message || 'Não foi possível iniciar a conexão.') }
}
async function disconnectSocial(provider) {
  try {
    await api.delete(`/auth/social/${provider}`)
    toast.success('Conta desconectada.')
    await loadConnections()
  } catch (e) { toast.error(e.response?.data?.message || 'Não foi possível desconectar.') }
}


async function importGoogleJson(event, item) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return

  busy[item.provider] = true
  try {
    const form = new FormData()
    form.append('arquivo', file)
    const { data } = await api.post('/configuracoes/apis/social_google/importar-json', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    googleCallback.value = data.callback_uri || googleCallback.value
    toast.success(data.message)
    await load()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível importar o JSON do Google.')
  } finally {
    busy[item.provider] = false
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await apiIntegrations.list()
    items.value = data.data.map(item => ({ ...item, _credentials: {} }))
    await loadConnections()
  } catch (e) { toast.error(e.response?.data?.message || 'Não foi possível carregar as integrações.') }
  finally { loading.value = false }
}
async function save(item) {
  busy[item.provider] = true
  try {
        const credentials = Object.fromEntries(
      Object.entries(item._credentials || {})
        .map(([key, value]) => [key, normalizeCredential(value)])
        .filter(([, value]) => value !== ''),
    )

    await apiIntegrations.save(item.provider, {
      credentials,
      settings: item.settings,
      enabled: !!item.enabled,
      is_primary: !!item.is_primary,
      is_fallback: !!item.is_fallback,
    })
    toast.success('Configuração salva.')
    await load()
  } catch (e) { toast.error(e.response?.data?.message || 'Falha ao salvar.') }
  finally { busy[item.provider] = false }
}
async function test(item) {
  busy[item.provider] = true
  try { const { data } = await apiIntegrations.test(item.provider); toast.success(data.message) }
  catch (e) { toast.error(e.response?.data?.message || 'Validação falhou.') }
  finally { busy[item.provider] = false; await load() }
}
const socialProvider = p => p.startsWith('social_') ? p.replace('social_', '') : null
const categoryLabel = c => ({ google: 'Google Cloud', cnpj: 'Dados CNPJ', social: 'Login social' }[c] || c)
const credentialLabel = k => ({ api_key:'Chave da API', token:'Token', client_id:'Client ID', client_secret:'Client Secret', team_id:'Team ID', key_id:'Key ID', private_key:'Chave privada Apple (.p8)' }[k] || k)
const settingLabel = k => ({ language:'Idioma', region:'Região', timeout:'Tempo limite', map_id:'Map ID', base_url:'URL base', advanced_search:'Pesquisa avançada', redirect_uri:'URL de retorno (Callback)', tenant:'Tenant Microsoft', sync_calendar:'Sincronizar calendário', sync_business:'Sincronizar Perfil da Empresa', share_google_key:'Compartilhar chave Google' }[k] || k)
const statusLabel = s => ({ connected:'Configurada', error:'Erro', not_tested:'Não validada', not_configured:'Não configurada' }[s] || s)
const statusClass = s => s === 'connected' ? 'badge-success' : s === 'error' ? 'badge-danger' : 'badge-warning'
onMounted(load)
</script>

<style scoped>
.credential-input-wrap {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.credential-input {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  letter-spacing: 0.01em;
}

.credential-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.credential-action {
  min-height: 28px;
  padding: 4px 9px;
  border: 1px solid var(--border);
  border-radius: 7px;
  background: var(--bg-input);
  color: var(--text-secondary);
  font-size: 11px;
  font-weight: 600;
  transition: border-color .15s ease, color .15s ease, background .15s ease;
}

.credential-action:hover {
  border-color: var(--primary);
  color: var(--text-primary);
  background: color-mix(in srgb, var(--bg-input) 86%, var(--primary) 14%);
}

.credential-action-danger:hover {
  border-color: #ef4444;
  color: #f87171;
}
</style>

<style scoped>
.google-key-share-hint {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 10px 12px;
  border: 1px solid rgba(99, 102, 241, .35);
  border-radius: 9px;
  background: rgba(79, 70, 229, .08);
  font-size: 11px;
}

.google-key-share-hint strong {
  color: var(--text-primary);
}

.google-key-share-hint span {
  color: var(--text-muted);
  line-height: 1.4;
}
</style>
