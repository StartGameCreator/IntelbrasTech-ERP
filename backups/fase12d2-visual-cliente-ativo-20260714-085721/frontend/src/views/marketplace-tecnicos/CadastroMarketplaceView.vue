<template>
  <div class="signup-page">
    <header class="signup-header">
      <RouterLink to="/login" class="brand"><span>IT</span><strong>IntelbrasTech</strong></RouterLink>
      <div>Já possui acesso? <RouterLink to="/login">Entrar</RouterLink></div>
    </header>

    <main class="signup-main">
      <section v-if="!perfil" class="choice">
        <span class="eyebrow">MARKETPLACE NACIONAL</span>
        <h1>Crie seu cadastro</h1>
        <p>Escolha como deseja participar da plataforma IntelbrasTech.</p>

        <div class="choice-grid">
          <button class="choice-card tecnico" @click="selecionar('tecnico')">
            <WrenchScrewdriverIcon />
            <small>PROFISSIONAL</small>
            <strong>Sou Técnico</strong>
            <p>Receba chamados, apresente suas especialidades e construa sua reputação.</p>
            <span>Cadastrar como técnico →</span>
          </button>

          <button class="choice-card cliente" @click="selecionar('cliente')">
            <BuildingOffice2Icon />
            <small>CONTRATANTE</small>
            <strong>Sou Cliente</strong>
            <p>Abra chamados e encontre profissionais qualificados em sua região.</p>
            <span>Cadastrar como cliente →</span>
          </button>
        </div>
      </section>

      <section v-else class="form-card">
        <button class="back" @click="perfil=null">← Voltar</button>
        <h1>{{ perfil === 'tecnico' ? 'Cadastro de Técnico' : 'Cadastro de Cliente' }}</h1>
        <p>Após o envio, o cadastro será analisado pelo Administrador Master.</p>

        <form @submit.prevent="enviar">
          <div class="form-grid">
            <label><span>Nome *</span><input v-model="form.nome" required class="input"/></label>
            <label><span>E-mail *</span><input v-model="form.email" required type="email" class="input"/></label>
            <label><span>WhatsApp *</span><input v-model="form.whatsapp" required class="input"/></label>
            <label><span>Telefone</span><input v-model="form.telefone" class="input"/></label>

            <template v-if="perfil==='tecnico'">
              <label><span>CPF *</span><input v-model="form.cpf" required class="input"/></label>
              <label><span>RG</span><input v-model="form.rg" class="input"/></label>
              <label><span>CREA</span><input v-model="form.crea" class="input"/></label>
              <label><span>CRT</span><input v-model="form.crt" class="input"/></label>
              <label><span>CFT</span><input v-model="form.cft" class="input"/></label>
              <label><span>Empresa</span><input v-model="form.empresa" class="input"/></label>
              <label><span>CNPJ</span><input v-model="form.cnpj" class="input"/></label>
            </template>

            <template v-else>
              <label><span>Tipo de pessoa *</span><select v-model="form.tipo_pessoa" class="select"><option value="PF">Pessoa Física</option><option value="PJ">Pessoa Jurídica</option></select></label>
              <label v-if="form.tipo_pessoa==='PF'"><span>CPF</span><input v-model="form.cpf" class="input"/></label>
              <label v-else><span>CNPJ</span><input v-model="form.cnpj" class="input"/></label>
              <label v-if="form.tipo_pessoa==='PJ'"><span>Empresa</span><input v-model="form.empresa" class="input"/></label>
            </template>

            <label><span>CEP *</span><input v-model="form.cep" required class="input"/></label>
            <label><span>Cidade *</span><input v-model="form.cidade" required class="input"/></label>
            <label><span>UF *</span><input v-model="form.estado" required maxlength="2" class="input"/></label>
            <label><span>Endereço</span><input v-model="form.logradouro" class="input"/></label>
            <label><span>Número</span><input v-model="form.numero" class="input"/></label>
            <label><span>Bairro</span><input v-model="form.bairro" class="input"/></label>

            <template v-if="perfil==='tecnico'">
              <label class="wide"><span>Categorias *</span>
                <div class="category-grid">
                  <label v-for="categoria in categorias" :key="categoria.id">
                    <input v-model="form.categorias" type="checkbox" :value="categoria.id"/>
                    <span>{{ categoria.nome }}</span>
                  </label>
                </div>
              </label>
              <label class="wide"><span>Região de atendimento</span><textarea v-model="form.regiao_atendimento" class="input textarea"></textarea></label>
              <label><span>Raio de atendimento (km)</span><input v-model.number="form.raio_km" type="number" min="1" max="500" class="input"/></label>
              <label class="wide"><span>Experiência</span><textarea v-model="form.experiencia" class="input textarea"></textarea></label>
              <label class="wide"><span>Apresentação profissional</span><textarea v-model="form.bio" class="input textarea"></textarea></label>
            </template>
          </div>

          <label class="terms"><input v-model="aceite" type="checkbox" required/>Confirmo que os dados são verdadeiros e aceito os termos da plataforma.</label>
          <button class="submit" :disabled="enviando">{{ enviando ? 'Enviando...' : 'Concluir cadastro' }}</button>
        </form>
      </section>

      <section v-if="mensagem" class="success-overlay">
        <div>
          <CheckCircleIcon />
          <h2>Cadastro recebido</h2>
          <p>{{ mensagem }}</p>
          <RouterLink to="/login">Ir para o login</RouterLink>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { WrenchScrewdriverIcon, BuildingOffice2Icon, CheckCircleIcon } from '@heroicons/vue/24/outline'

const toast = useToast()
const perfil = ref(null)
const categorias = ref([])
const enviando = ref(false)
const aceite = ref(false)
const mensagem = ref('')

const blank = () => ({
  nome: '', email: '', whatsapp: '', telefone: '',
  cpf: '', rg: '', crea: '', crt: '', cft: '',
  empresa: '', cnpj: '', tipo_pessoa: 'PF',
  cep: '', cidade: '', estado: '', logradouro: '',
  numero: '', bairro: '', regiao_atendimento: '',
  raio_km: 50, experiencia: '', bio: '', categorias: [],
})
const form = ref(blank())

function selecionar(tipo) {
  perfil.value = tipo
  form.value = blank()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function carregarCategorias() {
  try {
    const { data } = await api.get('/marketplace-tecnicos/public-config')
    categorias.value = data.categorias || []
  } catch (error) {
    toast.error('Não foi possível carregar as categorias.')
  }
}

async function enviar() {
  enviando.value = true
  try {
    const { data } = await api.post(`/marketplace-tecnicos/cadastro/${perfil.value}`, form.value)
    mensagem.value = data.message
  } catch (error) {
    const errors = error.response?.data?.errors || {}
    const first = Object.values(errors)?.[0]?.[0]
    toast.error(first || error.response?.data?.message || 'Não foi possível concluir o cadastro.')
  } finally {
    enviando.value = false
  }
}

onMounted(carregarCategorias)
</script>

<style scoped>
.signup-page{min-height:100vh;background:#f0f2f7;color:#0a0f1e}.signup-header{height:72px;display:flex;align-items:center;justify-content:space-between;padding:0 5vw;background:#fff;border-bottom:1px solid #dde1ee;font-size:13px}.brand{display:flex;align-items:center;gap:9px}.brand span{width:36px;height:36px;border-radius:9px;background:#0047ff;color:#fff;display:grid;place-items:center;font-weight:800}.brand strong{font-size:17px}.signup-header a{color:#0047ff;font-weight:700}.signup-main{max-width:1050px;margin:auto;padding:55px 24px}.choice{text-align:center}.eyebrow{font-size:11px;letter-spacing:2px;color:#0047ff;background:#e8eeff;border-radius:999px;padding:6px 14px}.choice h1,.form-card h1{font-size:38px;font-weight:900;margin-top:22px}.choice>p,.form-card>p{color:#5a6278;margin-top:12px}.choice-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:22px;margin-top:42px}.choice-card{text-align:left;background:#fff;border:2px solid #dde1ee;border-radius:18px;padding:34px;transition:.2s}.choice-card:hover{transform:translateY(-5px);box-shadow:0 18px 50px rgba(0,0,0,.1)}.choice-card svg{width:50px;height:50px;padding:11px;border-radius:13px}.choice-card.tecnico:hover{border-color:#0047ff}.choice-card.tecnico svg{color:#0047ff;background:#e8eeff}.choice-card.cliente:hover{border-color:#12a05c}.choice-card.cliente svg{color:#12a05c;background:#e3f7ef}.choice-card small,.choice-card strong,.choice-card p,.choice-card span{display:block}.choice-card small{margin-top:20px;letter-spacing:1.4px;font-weight:800;color:#0047ff}.choice-card strong{font-size:24px;margin-top:7px}.choice-card p{color:#5a6278;line-height:1.7;margin:12px 0 25px}.choice-card span{font-weight:800;color:#0047ff}.form-card{background:#fff;border:1px solid #dde1ee;border-radius:18px;padding:34px}.back{color:#0047ff;font-size:13px}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-top:28px}.form-grid label>span{display:block;font-size:12px;font-weight:700;margin-bottom:6px}.form-grid .wide{grid-column:1/-1}.input,.select{width:100%;border:1px solid #ccd2e2;background:#f8f9fc;color:#0a0f1e;border-radius:9px;padding:12px}.textarea{min-height:100px}.category-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.category-grid label{display:flex;align-items:center;gap:7px;border:1px solid #dde1ee;border-radius:8px;padding:9px;background:#f8f9fc}.terms{display:flex;gap:8px;margin-top:20px;font-size:12px;color:#5a6278}.submit{margin-top:18px;width:100%;border-radius:10px;background:#0047ff;color:#fff;padding:14px;font-weight:800}.success-overlay{position:fixed;inset:0;background:rgba(10,15,30,.72);display:grid;place-items:center;padding:20px}.success-overlay>div{max-width:440px;text-align:center;background:#fff;border-radius:18px;padding:40px}.success-overlay svg{width:60px;height:60px;color:#12a05c;margin:auto}.success-overlay h2{font-size:25px;font-weight:900;margin-top:12px}.success-overlay p{color:#5a6278;margin:10px 0 24px}.success-overlay a{display:block;background:#0047ff;color:#fff;border-radius:9px;padding:13px;font-weight:800}@media(max-width:800px){.choice-grid,.form-grid,.category-grid{grid-template-columns:1fr}.form-grid .wide{grid-column:auto}.choice h1,.form-card h1{font-size:29px}}
</style>
