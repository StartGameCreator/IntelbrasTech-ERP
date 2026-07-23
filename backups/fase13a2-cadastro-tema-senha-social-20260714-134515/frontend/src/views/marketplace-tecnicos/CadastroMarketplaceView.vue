<template>
  <div class="signup">
    <header class="top">
      <RouterLink to="/login" class="brand"><span>IT</span><div><strong>IntelbrasTech</strong><small>Marketplace Nacional de Serviços Técnicos</small></div></RouterLink>
      <RouterLink to="/login" class="login">Já possui acesso? <strong>Entrar</strong></RouterLink>
    </header>

    <main>
      <section v-if="!perfil" class="hero">
        <span class="eyebrow">INTELBRASTECH PLATFORM</span>
        <h1>Como você quer<br>se cadastrar?</h1>
        <p>Escolha o tipo de conta que melhor representa seu perfil na plataforma.</p>

        <div class="pillars">
          <button class="pillar tecnico" @click="selecionar('tecnico')">
            <div class="icon"><WrenchScrewdriverIcon/></div>
            <small>PROFISSIONAL</small>
            <h2>Técnico<br>Especialista</h2>
            <p>Ofereça serviços, receba chamados e construa sua reputação profissional.</p>
            <ul><li>Perfil e portfólio</li><li>Chamados por região</li><li>RAT digital</li><li>Avaliações</li></ul>
            <span>Cadastrar como Técnico <ArrowRightIcon/></span>
          </button>

          <button class="pillar cliente" @click="selecionar('cliente')">
            <div class="icon"><UserIcon/></div>
            <small>CONTRATANTE</small>
            <h2>Cliente</h2>
            <p>Encontre profissionais, solicite orçamentos e acompanhe atendimentos.</p>
            <ul><li>Abertura de chamados</li><li>Comparação de técnicos</li><li>Chat e vídeo</li><li>Histórico completo</li></ul>
            <span>Cadastrar como Cliente <ArrowRightIcon/></span>
          </button>

          <button class="pillar colaborador" @click="selecionar('colaborador')">
            <div class="icon"><UserGroupIcon/></div>
            <small>EQUIPE INTERNA</small>
            <h2>Colaborador<br>do Sistema</h2>
            <p>Solicite acesso administrativo à plataforma IntelbrasTech.</p>
            <div class="note">O nível de acesso será definido pelo Administrador Master.</div>
            <ul><li>Administrador Jr.</li><li>Administrador Pleno</li><li>Administrador Sênior</li></ul>
            <span>Solicitar acesso <ArrowRightIcon/></span>
          </button>
        </div>
      </section>

      <section v-else class="form-card">
        <button class="back" @click="perfil=null"><ArrowLeftIcon/> Voltar</button>
        <h1>{{ titulo }}</h1>
        <p>Preencha os dados para iniciar sua participação na plataforma.</p>

        <form @submit.prevent="enviar">
          <div class="grid">
            <label><span>Nome completo *</span><input v-model="form.nome" required></label>
            <label><span>E-mail *</span><input v-model="form.email" type="email" required></label>
            <label><span>Telefone</span><input v-model="form.telefone"></label>
            <label><span>WhatsApp</span><input v-model="form.whatsapp"></label>
            <label><span>Tipo de pessoa</span><select v-model="form.tipo_pessoa"><option value="PF">Pessoa Física</option><option value="PJ">Pessoa Jurídica</option></select></label>
            <label><span>{{ form.tipo_pessoa==='PJ'?'CNPJ':'CPF' }}</span><input v-model="form[form.tipo_pessoa==='PJ'?'cnpj':'cpf']"></label>
            <label><span>CEP</span><input v-model="form.cep"></label>
            <label><span>Cidade</span><input v-model="form.cidade"></label>
            <label><span>UF</span><input v-model="form.estado" maxlength="2"></label>

            <template v-if="perfil==='tecnico'">
              <label><span>CREA</span><input v-model="form.crea"></label>
              <label><span>CRT</span><input v-model="form.crt"></label>
              <label><span>CFT</span><input v-model="form.cft"></label>
              <label><span>Anos de experiência</span><input v-model.number="form.anos_experiencia" type="number"></label>
              <label><span>Raio de atendimento (km)</span><input v-model.number="form.raio_atendimento_km" type="number"></label>
              <label class="wide"><span>Especialidades *</span><textarea v-model="form.especialidades" required></textarea></label>
            </template>

            <template v-if="perfil==='colaborador'">
              <label><span>Cargo *</span><input v-model="form.cargo" required></label>
              <label><span>Nível solicitado *</span><select v-model="form.nivel_colaborador"><option value="administrador_jr">Administrador Jr.</option><option value="administrador_pleno">Administrador Pleno</option><option value="administrador_senior">Administrador Sênior</option></select></label>
              <label class="wide"><span>Motivação *</span><textarea v-model="form.motivacao" minlength="30" required></textarea></label>
            </template>
          </div>

          <div v-if="perfil==='tecnico'" class="cats">
            <label v-for="c in categorias" :key="c.id"><input v-model="form.categorias" type="checkbox" :value="c.id"><span>{{ c.nome }}</span></label>
          </div>

          <label class="terms"><input v-model="aceite" type="checkbox" required> Confirmo a veracidade dos dados e aceito os termos.</label>
          <button class="submit" :disabled="enviando">{{ enviando?'Enviando...':'Concluir cadastro' }}</button>
        </form>
      </section>
    </main>

    <div v-if="mensagem" class="overlay">
      <div><CheckCircleIcon/><h2>Cadastro recebido!</h2><p>{{ mensagem }}</p><RouterLink to="/login">Voltar ao login</RouterLink></div>
    </div>
  </div>
</template>

<script setup>
import {computed,onMounted,ref} from 'vue'
import {RouterLink} from 'vue-router'
import {useToast} from 'vue-toastification'
import api from '@/services/api'
import {WrenchScrewdriverIcon,UserIcon,UserGroupIcon,ArrowRightIcon,ArrowLeftIcon,CheckCircleIcon} from '@heroicons/vue/24/outline'

const toast=useToast(),perfil=ref(null),categorias=ref([]),enviando=ref(false),aceite=ref(false),mensagem=ref('')
const blank=()=>({nome:'',email:'',telefone:'',whatsapp:'',tipo_pessoa:'PF',cpf:'',cnpj:'',cep:'',cidade:'',estado:'',crea:'',crt:'',cft:'',anos_experiencia:null,raio_atendimento_km:50,especialidades:'',categorias:[],cargo:'',nivel_colaborador:'administrador_jr',motivacao:''})
const form=ref(blank())
const titulo=computed(()=>({tecnico:'Cadastro de Técnico Especialista',cliente:'Cadastro de Cliente',colaborador:'Solicitação de Acesso'}[perfil.value]))
function selecionar(p){perfil.value=p;form.value=blank();window.scrollTo(0,0)}
async function carregar(){try{categorias.value=(await api.get('/platform/categorias')).data||[]}catch{}}
async function enviar(){
 enviando.value=true
 try{mensagem.value=(await api.post(`/platform/cadastros/${perfil.value}`,form.value)).data.message}
 catch(e){const x=e.response?.data?.errors;toast.error(x?Object.values(x)[0][0]:(e.response?.data?.message||'Falha no cadastro.'))}
 finally{enviando.value=false}
}
onMounted(carregar)
</script>

<style scoped>
.signup{min-height:100vh;background:#f1f4fa;color:#101827}.top{height:74px;background:#fff;border-bottom:1px solid #dfe5ef;display:flex;align-items:center;justify-content:space-between;padding:0 5vw}.brand{display:flex;align-items:center;gap:10px}.brand>span{width:40px;height:40px;border-radius:10px;background:#0d5df5;color:#fff;display:grid;place-items:center;font-weight:900}.brand strong,.brand small{display:block}.brand small{font-size:10px;color:#64748b}.login{font-size:13px;color:#64748b}.login strong{color:#0d5df5}.hero{max-width:1180px;margin:auto;padding:60px 24px 80px;text-align:center}.eyebrow{font-size:10px;letter-spacing:1.8px;background:#e7edff;color:#4f46e5;padding:6px 13px;border-radius:999px;font-weight:900}.hero h1{font-size:47px;line-height:1.03;font-weight:950;margin-top:18px}.hero>p{color:#6b7280;margin-top:14px}.pillars{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:42px;text-align:left}.pillar{min-height:500px;background:#fff;border:2px solid #dbe2ec;border-radius:17px;padding:28px;display:flex;flex-direction:column;transition:.2s}.pillar:hover{transform:translateY(-6px);box-shadow:0 20px 50px rgba(15,23,42,.12)}.tecnico{border-color:#2563eb}.cliente:hover{border-color:#10b981}.colaborador{border-color:#a855f7}.icon{width:48px;height:48px;border-radius:12px;display:grid;place-items:center}.icon svg{width:24px}.tecnico .icon{background:#eaf1ff;color:#2563eb}.cliente .icon{background:#e4f8ee;color:#10b981}.colaborador .icon{background:#f2e8ff;color:#9333ea}.pillar small{margin-top:20px;font-size:10px;font-weight:900;letter-spacing:1.2px}.pillar h2{font-size:25px;line-height:1.08;font-weight:900;margin-top:7px}.pillar p{color:#64748b;font-size:13px;line-height:1.65;margin-top:13px}.pillar ul{margin-top:20px;border-top:1px solid #e2e8f0;padding-top:15px;display:flex;flex-direction:column;gap:8px}.pillar li{font-size:12px;color:#475569}.pillar li:before{content:'•';margin-right:8px}.pillar>span{margin-top:auto;color:white;border-radius:10px;padding:13px;display:flex;justify-content:space-between;font-size:13px;font-weight:800;background:#0d5df5}.cliente>span{background:#10b981}.colaborador>span{background:#9333ea}.pillar>span svg{width:16px}.note{margin-top:15px;padding:11px;background:#faf5ff;border:1px solid #dfc7ff;border-radius:8px;color:#7e22ce;font-size:11px}.form-card{max-width:950px;margin:42px auto;padding:34px;background:#fff;border:1px solid #dfe5ef;border-radius:18px}.back{display:flex;gap:6px;align-items:center;color:#2563eb}.back svg{width:16px}.form-card h1{font-size:30px;font-weight:900;margin-top:20px}.form-card>p{color:#64748b;margin-top:6px}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:13px;margin-top:25px}.grid label span{display:block;font-size:11px;font-weight:700;margin-bottom:5px}.grid input,.grid select,.grid textarea{width:100%;border:1px solid #cad3e2;border-radius:9px;background:#f8fafc;padding:11px}.grid textarea{min-height:90px}.wide{grid-column:span 2}.cats{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-top:15px}.cats label{display:flex;gap:7px;align-items:center;border:1px solid #dce2ec;border-radius:8px;padding:9px;font-size:11px}.terms{display:flex;gap:7px;margin-top:20px;font-size:11px;color:#64748b}.submit{width:100%;margin-top:17px;background:#0d5df5;color:#fff;padding:14px;border-radius:10px;font-weight:900}.overlay{position:fixed;inset:0;background:rgba(15,23,42,.72);display:grid;place-items:center}.overlay>div{max-width:420px;background:#fff;border-radius:18px;padding:38px;text-align:center}.overlay svg{width:58px;color:#10b981;margin:auto}.overlay h2{font-size:24px;font-weight:900;margin-top:12px}.overlay p{color:#64748b;margin:10px 0 20px}.overlay a{display:block;background:#0d5df5;color:#fff;padding:12px;border-radius:9px;font-weight:800}@media(max-width:900px){.pillars{grid-template-columns:1fr}.pillar{min-height:auto}.grid,.cats{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.hero h1{font-size:35px}.grid,.cats{grid-template-columns:1fr}.wide{grid-column:auto}.form-card{margin:20px 12px;padding:22px}}
</style>
