<template>
  <div class="meeting-launcher">
    <button class="btn-secondary btn-sm" @click="open('google')" title="Criar reunião no Google Meet">
      <span class="brand-box"><SocialBrandLogo provider="google" /></span>
      Google Meet
    </button>
    <button class="btn-secondary btn-sm" @click="open('microsoft')" title="Criar reunião no Microsoft Teams">
      <span class="teams-mark">T</span>
      Microsoft Teams
    </button>

    <Teleport to="body" v-if="visible">
      <div class="modal-overlay" @click.self="close">
        <div class="modal meeting-modal">
          <header class="meeting-head">
            <div class="flex items-center gap-3">
              <span v-if="provider === 'google'" class="brand-large"><SocialBrandLogo provider="google" /></span>
              <span v-else class="teams-mark large">T</span>
              <div>
                <h2>{{ provider === 'google' ? 'Nova reunião no Google Meet' : 'Nova reunião no Microsoft Teams' }}</h2>
                <p>O link será criado na conta corporativa conectada e incluído no calendário.</p>
              </div>
            </div>
            <button class="btn-icon" @click="close">×</button>
          </header>

          <div v-if="!connected" class="connection-warning">
            <strong>Conta não conectada</strong>
            <p>Conecte a conta {{ provider === 'google' ? 'Google' : 'Microsoft' }} na Central de APIs.</p>
            <button class="btn-primary" @click="goToApis">Ir para Central de APIs</button>
          </div>

          <form v-else class="meeting-form" @submit.prevent="createMeeting">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div class="md:col-span-2">
                <label class="label">Título da reunião</label>
                <input v-model="form.titulo" class="input" required placeholder="Ex.: Apresentação comercial" />
              </div>
              <div>
                <label class="label">Data e hora de início</label>
                <input v-model="form.inicio" type="datetime-local" class="input" required />
              </div>
              <div>
                <label class="label">Duração</label>
                <select v-model.number="duration" class="input">
                  <option :value="30">30 minutos</option>
                  <option :value="45">45 minutos</option>
                  <option :value="60">1 hora</option>
                  <option :value="90">1h30</option>
                  <option :value="120">2 horas</option>
                </select>
              </div>
              <div>
                <label class="label">Nome do potencial cliente</label>
                <input v-model="form.cliente_nome" class="input" placeholder="Nome ou empresa" />
              </div>
              <div>
                <label class="label">E-mail principal</label>
                <input v-model="form.cliente_email" type="email" class="input" placeholder="cliente@empresa.com" />
              </div>
              <div class="md:col-span-2">
                <label class="label">Outros participantes</label>
                <input v-model="participantsText" class="input" placeholder="email1@empresa.com, email2@empresa.com" />
                <p class="hint">Separe os e-mails por vírgula.</p>
              </div>
              <div class="md:col-span-2">
                <label class="label">Descrição</label>
                <textarea v-model="form.descricao" class="input min-h-24" placeholder="Objetivo, pauta e observações"></textarea>
              </div>
            </div>

            <div class="connection-line">
              <span class="dot"></span>
              Conectado como <strong>{{ connectionEmail }}</strong>
            </div>

            <footer class="meeting-actions">
              <button type="button" class="btn-secondary" @click="close">Cancelar</button>
              <button type="submit" class="btn-primary" :disabled="busy">
                {{ busy ? 'Criando...' : 'Criar reunião e copiar link' }}
              </button>
            </footer>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import SocialBrandLogo from '@/components/branding/SocialBrandLogo.vue'

const router = useRouter()
const toast = useToast()
const visible = ref(false)
const provider = ref('google')
const busy = ref(false)
const duration = ref(60)
const participantsText = ref('')
const status = reactive({ google: { connected: false }, microsoft: { connected: false } })
const form = reactive({ titulo: '', descricao: '', inicio: '', cliente_nome: '', cliente_email: '' })

const connected = computed(() => !!status[provider.value]?.connected)
const connectionEmail = computed(() => status[provider.value]?.email || '')

function defaultDateTime() {
  const d = new Date(Date.now() + 60 * 60 * 1000)
  d.setMinutes(Math.ceil(d.getMinutes() / 15) * 15, 0, 0)
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}

async function loadStatus() {
  try {
    const { data } = await api.get('/crm/reunioes/status')
    Object.assign(status.google, data.data.google)
    Object.assign(status.microsoft, data.data.microsoft)
  } catch (_) {}
}

async function open(value) {
  provider.value = value
  form.inicio = form.inicio || defaultDateTime()
  await loadStatus()
  visible.value = true
}

function close() { visible.value = false }
function goToApis() { close(); router.push('/configuracoes/apis') }

function participantEmails() {
  const values = participantsText.value.split(',').map(v => v.trim()).filter(Boolean)
  if (form.cliente_email && !values.includes(form.cliente_email)) values.unshift(form.cliente_email)
  return [...new Set(values)]
}

async function createMeeting() {
  busy.value = true
  try {
    const start = new Date(form.inicio)
    const end = new Date(start.getTime() + duration.value * 60000)
    const { data } = await api.post('/crm/reunioes', {
      provider: provider.value,
      titulo: form.titulo,
      descricao: form.descricao,
      inicio: start.toISOString(),
      fim: end.toISOString(),
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Sao_Paulo',
      participantes: participantEmails(),
      cliente_nome: form.cliente_nome || null,
      cliente_email: form.cliente_email || null,
    })
    const url = data.data?.join_url || data.data?.event_url
    if (url) {
      await navigator.clipboard?.writeText(url).catch(() => {})
      window.open(url, '_blank', 'noopener,noreferrer')
    }
    toast.success(data.message || 'Reunião criada com sucesso.')
    close()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível criar a reunião.')
    if (e.response?.data?.code === 'SOCIAL_ACCOUNT_NOT_CONNECTED') await loadStatus()
  } finally { busy.value = false }
}

onMounted(loadStatus)
</script>

<style scoped>
.meeting-launcher { display: contents; }
.brand-box, .brand-large { display:inline-flex; align-items:center; justify-content:center; background:#fff; border-radius:.45rem; padding:.18rem; width:1.35rem; height:1.35rem; }
.brand-large { width:2.5rem; height:2.5rem; padding:.45rem; border-radius:.8rem; }
.teams-mark { display:inline-flex; align-items:center; justify-content:center; width:1.35rem; height:1.35rem; border-radius:.4rem; color:white; font-weight:800; background:linear-gradient(135deg,#6264a7,#464775); }
.teams-mark.large { width:2.5rem; height:2.5rem; border-radius:.8rem; font-size:1.1rem; }
.meeting-modal { width:min(720px, calc(100vw - 2rem)); max-height:calc(100vh - 2rem); overflow:auto; }
.meeting-head { display:flex; justify-content:space-between; gap:1rem; align-items:flex-start; padding-bottom:1rem; border-bottom:1px solid var(--border); }
.meeting-head h2 { color:var(--text-primary); font-size:1.1rem; font-weight:700; }
.meeting-head p { color:var(--text-muted); font-size:.8rem; margin-top:.2rem; }
.meeting-form { margin-top:1rem; }
.connection-warning { margin-top:1rem; padding:1rem; border:1px solid var(--border); background:var(--bg-elevated); border-radius:.9rem; }
.connection-warning p { color:var(--text-secondary); font-size:.85rem; margin:.35rem 0 1rem; }
.connection-line { margin-top:1rem; padding:.75rem; display:flex; align-items:center; gap:.45rem; border-radius:.7rem; background:var(--bg-elevated); color:var(--text-secondary); font-size:.8rem; }
.connection-line .dot { width:.5rem; height:.5rem; border-radius:999px; background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.14); }
.meeting-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1rem; }
.hint { color:var(--text-muted); font-size:.7rem; margin-top:.3rem; }
</style>
