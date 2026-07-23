<template>
  <div class="signup" :data-theme="temaResolvido">
    <header class="top">
      <RouterLink to="/login" class="brand">
        <span>IT</span>
        <div>
          <strong>IntelbrasTech</strong>
          <small>Marketplace Nacional de Serviços Técnicos</small>
        </div>
      </RouterLink>

      <div class="top-actions">
        <button class="theme-toggle" type="button" @click="alternarTema" :title="tituloTema">
          <SunIcon v-if="temaResolvido === 'dark'" />
          <MoonIcon v-else />
        </button>
        <RouterLink to="/login" class="login">Já possui acesso? <strong>Entrar</strong></RouterLink>
      </div>
    </header>

    <main>
      <section v-if="!perfil" class="hero">
        <span class="eyebrow">INTELBRASTECH PLATFORM</span>
        <h1>Como você quer<br>se cadastrar?</h1>
        <p>Escolha o tipo de conta que melhor representa seu perfil na plataforma.</p>

        <div class="pillars">
          <button class="pillar tecnico" @click="selecionar('tecnico')">
            <div class="icon"><WrenchScrewdriverIcon /></div>
            <small>PROFISSIONAL</small>
            <h2>Técnico<br>Especialista</h2>
            <p>Ofereça serviços, receba chamados e construa sua reputação profissional.</p>
            <ul><li>Perfil e portfólio</li><li>Chamados por região</li><li>RAT digital</li><li>Avaliações</li></ul>
            <span>Cadastrar como Técnico <ArrowRightIcon /></span>
          </button>

          <button class="pillar cliente" @click="selecionar('cliente')">
            <div class="icon"><UserIcon /></div>
            <small>CONTRATANTE</small>
            <h2>Cliente</h2>
            <p>Encontre profissionais, solicite orçamentos e acompanhe atendimentos.</p>
            <ul><li>Abertura de chamados</li><li>Comparação de técnicos</li><li>Chat e vídeo</li><li>Histórico completo</li></ul>
            <span>Cadastrar como Cliente <ArrowRightIcon /></span>
          </button>

          <button class="pillar colaborador" @click="selecionar('colaborador')">
            <div class="icon"><UserGroupIcon /></div>
            <small>EQUIPE INTERNA</small>
            <h2>Colaborador<br>do Sistema</h2>
            <p>Solicite acesso administrativo à plataforma IntelbrasTech.</p>
            <div class="note">O nível de acesso será definido pelo Administrador Master.</div>
            <ul><li>Administrador Jr.</li><li>Administrador Pleno</li><li>Administrador Sênior</li></ul>
            <span>Solicitar acesso <ArrowRightIcon /></span>
          </button>
        </div>
      </section>

      <section v-else class="form-card">
        <button class="back" type="button" @click="perfil = null"><ArrowLeftIcon /> Voltar</button>
        <h1>{{ titulo }}</h1>
        <p>Cadastre-se com e-mail e senha ou continue usando uma conta social.</p>

        <div class="social-grid">
          <button type="button" @click="social('google')"><span class="google">G</span> Google</button>
          <button type="button" @click="social('microsoft')"><span class="microsoft">▦</span> Microsoft</button>
          <button type="button" @click="social('apple')"><span>●</span> Apple</button>
          <button type="button" @click="social('facebook')"><span class="facebook">f</span> Facebook</button>
        </div>

        <div class="separator"><span>OU CADASTRE-SE COM E-MAIL</span></div>

        <form @submit.prevent="enviar">
          <div class="grid">
            <label><span>Nome completo *</span><input v-model="form.nome" required /></label>
            <label><span>E-mail *</span><input v-model="form.email" type="email" required /></label>
            <label>
              <span>Senha *</span>
              <div class="password-field">
                <input v-model="form.senha" :type="mostrarSenha ? 'text' : 'password'" minlength="8" required />
                <button type="button" @click="mostrarSenha = !mostrarSenha">
                  <EyeSlashIcon v-if="mostrarSenha" /><EyeIcon v-else />
                </button>
              </div>
            </label>
            <label>
              <span>Confirmar senha *</span>
              <input v-model="form.senha_confirmation" type="password" minlength="8" required />
            </label>
            <label><span>Telefone</span><input v-model="form.telefone" /></label>
            <label><span>WhatsApp</span><input v-model="form.whatsapp" /></label>
            <label><span>Tipo de pessoa</span><select v-model="form.tipo_pessoa"><option value="PF">Pessoa Física</option><option value="PJ">Pessoa Jurídica</option></select></label>
            <label><span>{{ form.tipo_pessoa === 'PJ' ? 'CNPJ' : 'CPF' }}</span><input v-model="form[form.tipo_pessoa === 'PJ' ? 'cnpj' : 'cpf']" /></label>
            <label><span>CEP</span><input v-model="form.cep" /></label>
            <label><span>Cidade</span><input v-model="form.cidade" /></label>
            <label><span>UF</span><input v-model="form.estado" maxlength="2" /></label>

            <template v-if="perfil === 'tecnico'">
              <label><span>CREA</span><input v-model="form.crea" /></label>
              <label><span>CRT</span><input v-model="form.crt" /></label>
              <label><span>CFT</span><input v-model="form.cft" /></label>
              <label><span>Anos de experiência</span><input v-model.number="form.anos_experiencia" type="number" /></label>
              <label><span>Raio de atendimento (km)</span><input v-model.number="form.raio_atendimento_km" type="number" /></label>
              <label class="wide"><span>Especialidades *</span><textarea v-model="form.especialidades" required /></label>
            </template>

            <template v-if="perfil === 'colaborador'">
              <label><span>Cargo *</span><input v-model="form.cargo" required /></label>
              <label><span>Nível solicitado *</span><select v-model="form.nivel_colaborador"><option value="administrador_jr">Administrador Jr.</option><option value="administrador_pleno">Administrador Pleno</option><option value="administrador_senior">Administrador Sênior</option></select></label>
              <label class="wide"><span>Motivação *</span><textarea v-model="form.motivacao" minlength="30" required /></label>
            </template>
          </div>

          <div v-if="perfil === 'tecnico'" class="cats">
            <label v-for="c in categorias" :key="c.id"><input v-model="form.categorias" type="checkbox" :value="c.id" /><span>{{ c.nome }}</span></label>
          </div>

          <label class="terms"><input v-model="aceite" type="checkbox" required /> Confirmo a veracidade dos dados e aceito os termos.</label>
          <button class="submit" :disabled="enviando">{{ enviando ? 'Enviando...' : 'Concluir cadastro' }}</button>
        </form>
      </section>
    </main>

    <div v-if="mensagem" class="overlay">
      <div><CheckCircleIcon /><h2>Cadastro recebido!</h2><p>{{ mensagem }}</p><RouterLink to="/login">Voltar ao login</RouterLink></div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import {
  WrenchScrewdriverIcon, UserIcon, UserGroupIcon, ArrowRightIcon, ArrowLeftIcon,
  CheckCircleIcon, SunIcon, MoonIcon, EyeIcon, EyeSlashIcon,
} from '@heroicons/vue/24/outline'

const toast = useToast()
const perfil = ref(null)
const categorias = ref([])
const enviando = ref(false)
const aceite = ref(false)
const mensagem = ref('')
const mostrarSenha = ref(false)
const tema = ref(localStorage.getItem('intelbrastech-theme') || 'auto')
const sistemaEscuro = ref(window.matchMedia('(prefers-color-scheme: dark)').matches)
const media = window.matchMedia('(prefers-color-scheme: dark)')
const temaResolvido = computed(() => tema.value === 'auto' ? (sistemaEscuro.value ? 'dark' : 'light') : tema.value)
const tituloTema = computed(() => temaResolvido.value === 'dark' ? 'Acender tela' : 'Escurecer tela')

const blank = () => ({
  nome: '', email: '', senha: '', senha_confirmation: '', telefone: '', whatsapp: '',
  tipo_pessoa: 'PF', cpf: '', cnpj: '', cep: '', cidade: '', estado: '', crea: '', crt: '',
  cft: '', anos_experiencia: null, raio_atendimento_km: 50, especialidades: '',
  categorias: [], cargo: '', nivel_colaborador: 'administrador_jr', motivacao: '',
})

const form = ref(blank())
const titulo = computed(() => ({
  tecnico: 'Cadastro de Técnico Especialista',
  cliente: 'Cadastro de Cliente',
  colaborador: 'Solicitação de Acesso',
}[perfil.value]))

function selecionar(p) {
  perfil.value = p
  form.value = blank()
  window.scrollTo(0, 0)
}

function alternarTema() {
  tema.value = temaResolvido.value === 'dark' ? 'light' : 'dark'
  localStorage.setItem('intelbrastech-theme', tema.value)
}

function social(provider) {
  if (!perfil.value) {
    toast.info('Escolha primeiro um perfil.')
    return
  }

  sessionStorage.setItem('intelbrastech-register-profile', perfil.value)
  const base = String(api.defaults.baseURL || '/api/v1').replace(/\/$/, '')
  window.location.href = `${base}/auth/social/${provider}/redirect?intent=register&perfil=${encodeURIComponent(perfil.value)}`
}

async function carregar() {
  try {
    categorias.value = (await api.get('/platform/categorias')).data || []
  } catch {}
}

async function enviar() {
  enviando.value = true
  try {
    mensagem.value = (await api.post(`/platform/cadastros/${perfil.value}`, form.value)).data.message
  } catch (e) {
    const errors = e.response?.data?.errors
    toast.error(errors ? Object.values(errors)[0][0] : (e.response?.data?.message || 'Falha no cadastro.'))
  } finally {
    enviando.value = false
  }
}

function onSystemTheme(e) {
  sistemaEscuro.value = e.matches
}

onMounted(() => {
  media.addEventListener('change', onSystemTheme)
  carregar()
})
onBeforeUnmount(() => media.removeEventListener('change', onSystemTheme))
</script>

<style scoped>
.signup{--bg:#f1f4fa;--surface:#fff;--surface-2:#f8fafc;--text:#101827;--muted:#64748b;--border:#dbe2ec;--shadow:rgba(15,23,42,.12);min-height:100vh;background:var(--bg);color:var(--text);transition:.2s}
.signup[data-theme="dark"]{--bg:#080a12;--surface:#121421;--surface-2:#1b1d31;--text:#f8fafc;--muted:#94a3b8;--border:#292d45;--shadow:rgba(0,0,0,.45)}
.top{height:74px;background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 5vw}.brand{display:flex;align-items:center;gap:10px}.brand>span{width:40px;height:40px;border-radius:10px;background:#0d5df5;color:#fff;display:grid;place-items:center;font-weight:900}.brand strong,.brand small{display:block}.brand small{font-size:10px;color:var(--muted)}.top-actions{display:flex;align-items:center;gap:13px}.login{font-size:13px;color:var(--muted)}.login strong{color:#4f7cff}.theme-toggle{width:36px;height:36px;border:1px solid var(--border);border-radius:9px;background:var(--surface-2);display:grid;place-items:center}.theme-toggle svg{width:18px}
.hero{max-width:1180px;margin:auto;padding:60px 24px 80px;text-align:center}.eyebrow{font-size:10px;letter-spacing:1.8px;background:#e7edff;color:#4f46e5;padding:6px 13px;border-radius:999px;font-weight:900}.hero h1{font-size:47px;line-height:1.03;font-weight:950;margin-top:18px}.hero>p{color:var(--muted);margin-top:14px}.pillars{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:42px;text-align:left}.pillar{min-height:500px;background:var(--surface);border:2px solid var(--border);border-radius:17px;padding:28px;display:flex;flex-direction:column;transition:.2s;color:var(--text)}.pillar:hover{transform:translateY(-6px);box-shadow:0 20px 50px var(--shadow)}.tecnico{border-color:#2563eb}.cliente:hover{border-color:#10b981}.colaborador{border-color:#a855f7}.icon{width:48px;height:48px;border-radius:12px;display:grid;place-items:center}.icon svg{width:24px}.tecnico .icon{background:#eaf1ff;color:#2563eb}.cliente .icon{background:#e4f8ee;color:#10b981}.colaborador .icon{background:#f2e8ff;color:#9333ea}.pillar small{margin-top:20px;font-size:10px;font-weight:900;letter-spacing:1.2px}.pillar h2{font-size:25px;line-height:1.08;font-weight:900;margin-top:7px}.pillar p{color:var(--muted);font-size:13px;line-height:1.65;margin-top:13px}.pillar ul{margin-top:20px;border-top:1px solid var(--border);padding-top:15px;display:flex;flex-direction:column;gap:8px}.pillar li{font-size:12px;color:var(--muted)}.pillar li:before{content:'•';margin-right:8px}.pillar>span{margin-top:auto;color:#fff;border-radius:10px;padding:13px;display:flex;justify-content:space-between;font-size:13px;font-weight:800;background:#0d5df5}.cliente>span{background:#10b981}.colaborador>span{background:#9333ea}.pillar>span svg{width:16px}.note{margin-top:15px;padding:11px;background:rgba(147,51,234,.08);border:1px solid rgba(147,51,234,.35);border-radius:8px;color:#a855f7;font-size:11px}
.form-card{max-width:950px;margin:42px auto;padding:34px;background:var(--surface);border:1px solid var(--border);border-radius:18px}.back{display:flex;gap:6px;align-items:center;color:#4f7cff}.back svg{width:16px}.form-card h1{font-size:30px;font-weight:900;margin-top:20px}.form-card>p{color:var(--muted);margin-top:6px}.social-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:9px;margin-top:22px}.social-grid button{border:1px solid var(--border);background:var(--surface-2);border-radius:9px;padding:11px;display:flex;align-items:center;justify-content:center;gap:8px;font-size:12px;font-weight:700}.google{color:#4285f4}.microsoft{color:#00a4ef}.facebook{color:#1877f2}.separator{display:flex;align-items:center;gap:12px;color:var(--muted);font-size:9px;margin:22px 0}.separator:before,.separator:after{content:'';height:1px;flex:1;background:var(--border)}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:13px}.grid label span{display:block;font-size:11px;font-weight:700;margin-bottom:5px}.grid input,.grid select,.grid textarea{width:100%;border:1px solid var(--border);border-radius:9px;background:var(--surface-2);color:var(--text);padding:11px}.grid textarea{min-height:90px}.wide{grid-column:span 2}.password-field{position:relative}.password-field input{padding-right:40px}.password-field button{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:28px;height:28px;display:grid;place-items:center;color:var(--muted)}.password-field svg{width:17px}.cats{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-top:15px}.cats label{display:flex;gap:7px;align-items:center;border:1px solid var(--border);border-radius:8px;padding:9px;font-size:11px}.terms{display:flex;gap:7px;margin-top:20px;font-size:11px;color:var(--muted)}.submit{width:100%;margin-top:17px;background:#0d5df5;color:#fff;padding:14px;border-radius:10px;font-weight:900}.overlay{position:fixed;inset:0;background:rgba(15,23,42,.72);display:grid;place-items:center}.overlay>div{max-width:420px;background:var(--surface);border-radius:18px;padding:38px;text-align:center}.overlay svg{width:58px;color:#10b981;margin:auto}.overlay h2{font-size:24px;font-weight:900;margin-top:12px}.overlay p{color:var(--muted);margin:10px 0 20px}.overlay a{display:block;background:#0d5df5;color:#fff;padding:12px;border-radius:9px;font-weight:800}
@media(max-width:900px){.pillars{grid-template-columns:1fr}.pillar{min-height:auto}.grid,.cats{grid-template-columns:repeat(2,1fr)}.social-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.hero h1{font-size:35px}.grid,.cats{grid-template-columns:1fr}.wide{grid-column:auto}.form-card{margin:20px 12px;padding:22px}.top{padding:0 18px}.brand small{display:none}}
</style>
