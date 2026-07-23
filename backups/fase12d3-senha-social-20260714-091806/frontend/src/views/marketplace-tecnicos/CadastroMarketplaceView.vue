<template>
  <div class="signup-page">
    <header class="signup-header">
      <RouterLink to="/login" class="brand"><span>IT</span><strong>IntelbrasTech</strong></RouterLink>
      <div>Já possui acesso? <RouterLink to="/login">Entrar</RouterLink></div>
    </header>

    <main class="signup-main">
      <section v-if="!perfil" class="choice">
        <div class="login-brand">
          <span class="brand-mark">IT</span>
          <div>
            <strong>IntelbrasTech ERP</strong>
            <small>Marketplace Nacional de Serviços Técnicos</small>
          </div>
        </div>
        <span class="eyebrow">CRIAR CONTA</span>
        <h1>Cadastre-se</h1>
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
        <p>{{ perfil === 'tecnico' ? 'Após o envio, o cadastro será analisado pelo Administrador Master.' : 'O cadastro de cliente será ativado imediatamente.' }}</p>

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
.signup-page{
  min-height:100vh;
  background:
    radial-gradient(circle at 18% 10%,rgba(99,102,241,.10),transparent 28%),
    #090910;
  color:#f5f7ff;
}
.signup-header{
  height:64px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:0 34px;
  background:#11111b;
  border-bottom:1px solid #27273a;
  font-size:12px;
}
.brand{display:flex;align-items:center;gap:9px;color:#f8fafc}
.brand span,.brand-mark{
  width:34px;
  height:34px;
  border-radius:9px;
  background:linear-gradient(135deg,#5b5ce2,#7778ff);
  color:white;
  display:grid;
  place-items:center;
  font-weight:800;
  box-shadow:0 8px 24px rgba(91,92,226,.25);
}
.brand strong{font-size:15px}
.signup-header a{color:#818cf8;font-weight:700}
.signup-main{max-width:980px;margin:auto;padding:52px 24px 70px}
.choice{text-align:center}
.login-brand{
  display:flex;
  justify-content:center;
  align-items:center;
  gap:12px;
  margin-bottom:26px;
}
.login-brand>div{text-align:left}
.login-brand strong,.login-brand small{display:block}
.login-brand strong{font-size:16px}
.login-brand small{margin-top:2px;color:#6f7895;font-size:10px}
.eyebrow{
  display:inline-block;
  font-size:10px;
  letter-spacing:1.8px;
  color:#a5b4fc;
  background:rgba(99,102,241,.12);
  border:1px solid rgba(129,140,248,.24);
  border-radius:999px;
  padding:6px 13px;
}
.choice h1,.form-card h1{font-size:30px;font-weight:800;margin-top:18px}
.choice>p,.form-card>p{color:#7f89a8;margin-top:9px;font-size:13px}
.choice-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-top:30px}
.choice-card{
  text-align:left;
  background:#131321;
  border:1px solid #2b2b42;
  border-radius:13px;
  padding:24px;
  transition:.2s;
}
.choice-card:hover{
  transform:translateY(-3px);
  border-color:#6366f1;
  box-shadow:0 18px 45px rgba(0,0,0,.28);
}
.choice-card svg{width:44px;height:44px;padding:10px;border-radius:11px}
.choice-card.tecnico svg{color:#818cf8;background:rgba(99,102,241,.14)}
.choice-card.cliente svg{color:#34d399;background:rgba(16,185,129,.12)}
.choice-card small,.choice-card strong,.choice-card p,.choice-card span{display:block}
.choice-card small{margin-top:17px;letter-spacing:1.2px;font-weight:700;color:#818cf8;font-size:10px}
.choice-card strong{font-size:20px;margin-top:5px}
.choice-card p{color:#818aa7;line-height:1.6;margin:9px 0 18px;font-size:12px}
.choice-card span{font-weight:700;color:#818cf8;font-size:12px}
.form-card{
  background:#11111b;
  border:1px solid #27273a;
  border-radius:14px;
  padding:28px;
  box-shadow:0 22px 60px rgba(0,0,0,.24);
}
.back{color:#818cf8;font-size:12px}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:24px}
.form-grid label>span{display:block;font-size:11px;font-weight:600;margin-bottom:6px;color:#aab2ca}
.form-grid .wide{grid-column:1/-1}
.input,.select{
  width:100%;
  border:1px solid #2b2b42;
  background:#1a1a2d;
  color:#f5f7ff;
  border-radius:8px;
  padding:11px 12px;
  outline:none;
}
.input:focus,.select:focus{
  border-color:#6366f1;
  box-shadow:0 0 0 3px rgba(99,102,241,.10);
}
.textarea{min-height:92px}
.category-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:7px}
.category-grid label{
  display:flex;
  align-items:center;
  gap:7px;
  border:1px solid #2b2b42;
  border-radius:8px;
  padding:9px;
  background:#171727;
}
.terms{display:flex;gap:8px;margin-top:18px;font-size:11px;color:#818aa7}
.submit{
  margin-top:16px;
  width:100%;
  border-radius:8px;
  background:linear-gradient(90deg,#5b5ce2,#7071f5);
  color:#fff;
  padding:12px;
  font-weight:700;
  box-shadow:0 10px 25px rgba(91,92,226,.22);
}
.submit:disabled{opacity:.6;cursor:not-allowed}
.success-overlay{
  position:fixed;
  inset:0;
  background:rgba(3,3,8,.82);
  display:grid;
  place-items:center;
  padding:20px;
}
.success-overlay>div{
  max-width:420px;
  text-align:center;
  background:#131321;
  border:1px solid #2b2b42;
  border-radius:14px;
  padding:34px;
}
.success-overlay svg{width:54px;height:54px;color:#34d399;margin:auto}
.success-overlay h2{font-size:22px;font-weight:800;margin-top:11px}
.success-overlay p{color:#818aa7;margin:9px 0 21px;font-size:12px}
.success-overlay a{
  display:block;
  background:#5b5ce2;
  color:#fff;
  border-radius:8px;
  padding:12px;
  font-weight:700;
}
@media(max-width:800px){
  .choice-grid,.form-grid,.category-grid{grid-template-columns:1fr}
  .form-grid .wide{grid-column:auto}
  .choice h1,.form-card h1{font-size:26px}
  .signup-header{padding:0 18px}
}
</style>
