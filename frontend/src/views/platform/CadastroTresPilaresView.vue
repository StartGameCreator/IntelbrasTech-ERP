<template>
  <div class="platform-signup">
    <header class="signup-top">
      <RouterLink to="/login" class="brand">
        <span class="brand-mark">IT</span>
        <div><strong>IntelbrasTech</strong><small>Marketplace Nacional de Serviços Técnicos</small></div>
      </RouterLink>
      <RouterLink to="/login" class="login-link">Já possui acesso? <strong>Entrar</strong></RouterLink>
    </header>

    <main>
      <section v-if="!perfil" class="hero">
        <span class="eyebrow">INTELBRASTECH PLATFORM</span>
        <h1>Como você quer<br>se cadastrar?</h1>
        <p>Escolha o tipo de conta que melhor representa seu perfil na plataforma.</p>

        <div class="pillars">
          <button class="pillar technical" @click="selecionar('tecnico')">
            <span class="popular">MAIS POPULAR</span>
            <div class="pillar-icon"><WrenchScrewdriverIcon/></div>
            <small>PROFISSIONAL</small>
            <h2>Técnico<br>Especialista</h2>
            <p>Ofereça seus serviços técnicos, receba chamados e gerencie seus atendimentos em todo o Brasil.</p>
            <ul>
              <li>Perfil público com portfólio</li>
              <li>Chamados na sua região</li>
              <li>RAT digital e assinatura</li>
              <li>Recebimentos e reputação</li>
            </ul>
            <span class="action">Cadastrar como Técnico <ArrowRightIcon/></span>
          </button>

          <button class="pillar client" @click="selecionar('cliente')">
            <div class="pillar-icon"><UserIcon/></div>
            <small>CONTRATANTE</small>
            <h2>Cliente</h2>
            <p>Encontre o técnico ideal, solicite orçamentos e acompanhe os atendimentos.</p>
            <ul>
              <li>Abertura de chamados</li>
              <li>Comparar técnicos e avaliações</li>
              <li>Orçamentos rápidos</li>
              <li>Chat, vídeo e histórico</li>
            </ul>
            <span class="action">Cadastrar como Cliente <ArrowRightIcon/></span>
          </button>

          <button class="pillar collaborator" @click="selecionar('colaborador')">
            <div class="pillar-icon"><UserGroupIcon/></div>
            <small>EQUIPE INTERNA</small>
            <h2>Colaborador<br>do Sistema</h2>
            <p>Acesso administrativo à plataforma, disponível para a equipe interna IntelbrasTech.</p>
            <div class="access-note">
              O nível de acesso é definido pelo Administrador Master após aprovação.
            </div>
            <ul>
              <li>Administrador Jr. — suporte</li>
              <li>Administrador Pleno — regional</li>
              <li>Administrador Sênior — nacional</li>
            </ul>
            <span class="action">Solicitar acesso <ArrowRightIcon/></span>
          </button>
        </div>
      </section>

      <section v-else class="form-shell">
        <button class="back" @click="perfil=null"><ArrowLeftIcon/> Voltar aos três perfis</button>
        <div class="form-heading">
          <span>{{ dadosPerfil.eyebrow }}</span>
          <h1>{{ dadosPerfil.titulo }}</h1>
          <p>{{ dadosPerfil.descricao }}</p>
        </div>

        <form @submit.prevent="enviar">
          <div class="form-section">
            <h3>Dados principais</h3>
            <div class="form-grid">
              <label><span>Nome completo *</span><input v-model="form.nome" class="field" required></label>
              <label><span>E-mail *</span><input v-model="form.email" class="field" type="email" required></label>
              <label><span>Telefone</span><input v-model="form.telefone" class="field"></label>
              <label><span>WhatsApp</span><input v-model="form.whatsapp" class="field"></label>
              <label><span>Tipo de pessoa</span><select v-model="form.tipo_pessoa" class="field"><option value="PF">Pessoa Física</option><option value="PJ">Pessoa Jurídica</option></select></label>
              <label><span>{{ form.tipo_pessoa==='PJ'?'CNPJ':'CPF' }}</span><input v-model="form[form.tipo_pessoa==='PJ'?'cnpj':'cpf']" class="field"></label>
              <label v-if="form.tipo_pessoa==='PJ'"><span>Empresa</span><input v-model="form.empresa" class="field"></label>
              <label v-if="form.tipo_pessoa==='PF'"><span>RG</span><input v-model="form.rg" class="field"></label>
            </div>
          </div>

          <div class="form-section">
            <h3>Localização</h3>
            <div class="form-grid">
              <label><span>CEP</span><input v-model="form.cep" class="field"></label>
              <label><span>Estado</span><input v-model="form.estado" maxlength="2" class="field"></label>
              <label><span>Cidade</span><input v-model="form.cidade" class="field"></label>
              <label><span>Bairro</span><input v-model="form.bairro" class="field"></label>
              <label class="wide"><span>Endereço</span><input v-model="form.logradouro" class="field"></label>
              <label><span>Número</span><input v-model="form.numero" class="field"></label>
            </div>
          </div>

          <div v-if="perfil==='tecnico'" class="form-section">
            <h3>Qualificação profissional</h3>
            <div class="form-grid">
              <label><span>CREA</span><input v-model="form.crea" class="field"></label>
              <label><span>CRT</span><input v-model="form.crt" class="field"></label>
              <label><span>CFT</span><input v-model="form.cft" class="field"></label>
              <label><span>Anos de experiência</span><input v-model.number="form.anos_experiencia" type="number" min="0" class="field"></label>
              <label><span>Raio de atendimento (km)</span><input v-model.number="form.raio_atendimento_km" type="number" min="1" class="field"></label>
              <label class="wide"><span>Especialidades *</span><textarea v-model="form.especialidades" class="field textarea" required></textarea></label>
            </div>
            <div class="categories">
              <label v-for="categoria in categorias" :key="categoria.id">
                <input v-model="form.categorias" type="checkbox" :value="categoria.id">
                <span>{{ categoria.nome }}</span>
              </label>
            </div>
          </div>

          <div v-if="perfil==='colaborador'" class="form-section">
            <h3>Acesso administrativo</h3>
            <div class="form-grid">
              <label><span>Cargo *</span><input v-model="form.cargo" class="field" required></label>
              <label><span>Nível solicitado *</span><select v-model="form.nivel_colaborador" class="field" required><option value="administrador_jr">Administrador Jr.</option><option value="administrador_pleno">Administrador Pleno</option><option value="administrador_senior">Administrador Sênior</option></select></label>
              <label class="wide"><span>Motivação e área de atuação *</span><textarea v-model="form.motivacao" class="field textarea" minlength="30" required></textarea></label>
            </div>
          </div>

          <label class="terms"><input v-model="aceite" type="checkbox" required> Confirmo a veracidade dos dados e aceito os termos de uso e privacidade.</label>
          <button class="submit" :disabled="enviando">{{ enviando?'Enviando cadastro...':'Concluir cadastro' }}<ArrowRightIcon/></button>
        </form>
      </section>
    </main>

    <footer>© 2026 IntelbrasTech — Marketplace Nacional de Serviços Técnicos</footer>

    <div v-if="mensagem" class="success-overlay">
      <div class="success-card">
        <CheckCircleIcon/>
        <h2>Cadastro recebido!</h2>
        <p>{{ mensagem }}</p>
        <RouterLink to="/login">Voltar para o login</RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import {computed,onMounted,ref} from 'vue'
import {RouterLink} from 'vue-router'
import {useToast} from 'vue-toastification'
import api from '@/services/api'
import {WrenchScrewdriverIcon,UserIcon,UserGroupIcon,ArrowRightIcon,ArrowLeftIcon,CheckCircleIcon} from '@heroicons/vue/24/outline'

const toast=useToast()
const perfil=ref(null),categorias=ref([]),enviando=ref(false),aceite=ref(false),mensagem=ref('')
const vazio=()=>({nome:'',email:'',telefone:'',whatsapp:'',tipo_pessoa:'PF',cpf:'',rg:'',cnpj:'',empresa:'',cargo:'',nivel_colaborador:'administrador_jr',cep:'',logradouro:'',numero:'',bairro:'',cidade:'',estado:'',crea:'',crt:'',cft:'',anos_experiencia:null,raio_atendimento_km:50,especialidades:'',experiencia:'',motivacao:'',categorias:[]})
const form=ref(vazio())
const dadosPerfil=computed(()=>({
 tecnico:{eyebrow:'PROFISSIONAL',titulo:'Cadastro de Técnico Especialista',descricao:'Apresente suas qualificações e área de atendimento.'},
 cliente:{eyebrow:'CONTRATANTE',titulo:'Cadastro de Cliente',descricao:'Crie seu acesso para solicitar e acompanhar serviços.'},
 colaborador:{eyebrow:'EQUIPE INTERNA',titulo:'Solicitação de acesso',descricao:'O Administrador Master analisará o nível solicitado.'},
}[perfil.value]))
function selecionar(tipo){perfil.value=tipo;form.value=vazio();window.scrollTo({top:0,behavior:'smooth'})}
async function carregarCategorias(){try{categorias.value=(await api.get('/platform/categorias')).data||[]}catch{}}
async function enviar(){
 enviando.value=true
 try{
  const {data}=await api.post(`/platform/cadastros/${perfil.value}`,form.value)
  mensagem.value=data.message
 }catch(e){
  const errors=e.response?.data?.errors
  toast.error(errors?Object.values(errors)[0][0]:(e.response?.data?.message||'Não foi possível concluir o cadastro.'))
 }finally{enviando.value=false}
}
onMounted(carregarCategorias)
</script>

<style scoped>
.platform-signup{min-height:100vh;background:#f1f4fa;color:#111827;font-family:Inter,system-ui,sans-serif}.signup-top{height:76px;padding:0 5vw;background:#fff;border-bottom:1px solid #dfe5f0;display:flex;align-items:center;justify-content:space-between}.brand{display:flex;align-items:center;gap:10px}.brand-mark{width:40px;height:40px;border-radius:10px;background:#0d5df5;color:white;display:grid;place-items:center;font-weight:900}.brand strong,.brand small{display:block}.brand strong{font-size:17px}.brand small{font-size:10px;color:#64748b}.login-link{font-size:13px;color:#64748b}.login-link strong{color:#0d5df5}.hero{max-width:1200px;margin:auto;padding:64px 24px 80px;text-align:center}.eyebrow,.form-heading>span{display:inline-block;padding:6px 13px;border-radius:999px;background:#e7edff;color:#4f46e5;font-size:10px;font-weight:800;letter-spacing:1.7px}.hero>h1{font-size:48px;line-height:1.02;font-weight:950;margin-top:18px}.hero>p{font-size:15px;color:#718096;margin-top:14px}.pillars{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:46px;text-align:left}.pillar{position:relative;min-height:530px;background:#fff;border:2px solid #dce2ec;border-radius:17px;padding:28px;display:flex;flex-direction:column;transition:.2s}.pillar:hover{transform:translateY(-6px);box-shadow:0 20px 50px rgba(15,23,42,.12)}.pillar.technical{border-color:#2563eb}.pillar.client:hover{border-color:#10b981}.pillar.collaborator{border-color:#a855f7;background:linear-gradient(145deg,#fff,#f7f1ff)}.popular{position:absolute;right:-3px;top:13px;background:#f97316;color:#fff;font-size:9px;font-weight:900;padding:5px 12px;transform:rotate(38deg)}.pillar-icon{width:48px;height:48px;border-radius:12px;display:grid;place-items:center}.pillar-icon svg{width:24px}.technical .pillar-icon{background:#eaf1ff;color:#2563eb}.client .pillar-icon{background:#e4f8ee;color:#10b981}.collaborator .pillar-icon{background:#f2e8ff;color:#9333ea}.pillar>small{margin-top:22px;font-size:10px;font-weight:900;letter-spacing:1.2px;color:#2563eb}.client>small{color:#10b981}.collaborator>small{color:#9333ea}.pillar h2{font-size:25px;line-height:1.08;font-weight:900;margin-top:7px}.pillar>p{color:#64748b;font-size:13px;line-height:1.65;margin-top:13px}.pillar ul{margin-top:22px;border-top:1px solid #e2e8f0;padding-top:16px;display:flex;flex-direction:column;gap:9px}.pillar li{font-size:12px;color:#475569}.pillar li:before{content:'•';color:#2563eb;margin-right:8px}.client li:before{color:#10b981}.collaborator li:before{color:#9333ea}.access-note{margin-top:15px;padding:11px;border:1px solid #dfc7ff;border-radius:9px;background:#faf5ff;color:#7e22ce;font-size:11px}.action{margin-top:auto;border-radius:10px;background:#0d5df5;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:14px;font-size:13px;font-weight:800}.client .action{background:#12aa64}.collaborator .action{background:#9747ef}.action svg{width:17px}.form-shell{max-width:980px;margin:44px auto;padding:36px;background:#fff;border:1px solid #dfe5ef;border-radius:18px}.back{display:flex;align-items:center;gap:7px;color:#2563eb;font-size:12px}.back svg{width:15px}.form-heading{text-align:center;margin:16px 0 30px}.form-heading h1{font-size:30px;font-weight:900;margin-top:12px}.form-heading p{color:#64748b;margin-top:7px}.form-section{border-top:1px solid #e2e8f0;padding-top:22px;margin-top:22px}.form-section h3{font-size:15px;font-weight:900}.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:13px;margin-top:14px}.form-grid label>span{display:block;font-size:11px;font-weight:700;margin-bottom:6px}.wide{grid-column:span 2}.field{width:100%;border:1px solid #cad3e2;border-radius:9px;background:#f8fafc;padding:11px;color:#111827;outline:none}.field:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}.textarea{min-height:90px;resize:vertical}.categories{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:14px}.categories label{display:flex;gap:7px;align-items:center;border:1px solid #dce2ec;border-radius:8px;padding:9px;font-size:11px}.terms{display:flex;gap:8px;margin-top:22px;font-size:11px;color:#64748b}.submit{width:100%;margin-top:18px;padding:14px;background:#0d5df5;color:#fff;border-radius:10px;font-weight:900;display:flex;align-items:center;justify-content:center;gap:9px}.submit svg{width:17px}footer{text-align:center;border-top:1px solid #dfe5ef;padding:22px;color:#94a3b8;font-size:11px}.success-overlay{position:fixed;inset:0;background:rgba(15,23,42,.72);display:grid;place-items:center;padding:20px;z-index:100}.success-card{max-width:430px;background:#fff;border-radius:18px;padding:38px;text-align:center}.success-card svg{width:60px;color:#10b981;margin:auto}.success-card h2{font-size:24px;font-weight:900;margin-top:12px}.success-card p{color:#64748b;margin:10px 0 22px}.success-card a{display:block;background:#0d5df5;color:white;padding:12px;border-radius:9px;font-weight:800}@media(max-width:900px){.pillars{grid-template-columns:1fr}.pillar{min-height:auto}.form-grid,.categories{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.signup-top{padding:0 18px}.brand small{display:none}.hero>h1{font-size:35px}.form-shell{margin:20px 12px;padding:22px}.form-grid,.categories{grid-template-columns:1fr}.wide{grid-column:auto}}
</style>
