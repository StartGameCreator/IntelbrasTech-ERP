<template>
  <div>
    <section class="portal-page-hero assistance">
      <div class="portal-container">
        <span class="portal-overline light">Atendimento IntelbrasTech</span>
        <h1>Abra seu chamado técnico</h1>
        <p>Cadastre seus dados, descreva a demanda e acompanhe todo o fluxo: análise, orçamento, autorização e termo de prestação de serviços.</p>
      </div>
    </section>

    <section class="portal-section">
      <div class="portal-container atendimento-layout">
        <aside class="atendimento-intro">
          <span class="portal-overline">Fluxo transparente</span>
          <h2>Do primeiro contato à execução</h2>
          <ol>
            <li><span>1</span><div><b>Chamado registrado</b><p>Seus dados entram com protocolo no CRM da IntelbrasTech.</p></div></li>
            <li><span>2</span><div><b>Orçamento</b><p>A equipe avalia a demanda e envia uma proposta pelo mesmo link.</p></div></li>
            <li><span>3</span><div><b>Autorização</b><p>Você analisa o escopo e confirma eletronicamente.</p></div></li>
            <li><span>4</span><div><b>Termo gerado</b><p>O contrato em PDF fica disponível após o aceite.</p></div></li>
          </ol>
          <p class="atendimento-note">Não envie senhas, dados bancários ou códigos de autenticação pelo formulário.</p>
        </aside>

        <form v-if="!enviado" class="atendimento-card" @submit.prevent="enviar">
          <div class="form-heading"><span class="portal-overline">Cadastro do cliente</span><h2>Conte o que você precisa</h2><p>Os campos com * são necessários para abrir o chamado.</p></div>
          <div class="form-grid">
            <label><span>Tipo de cadastro *</span><select v-model="form.tipo_pessoa"><option value="F">Pessoa física</option><option value="J">Pessoa jurídica</option></select></label>
            <label><span>{{ form.tipo_pessoa === 'J' ? 'Razão social *' : 'Nome completo *' }}</span><input v-model.trim="form.nome" required maxlength="200" autocomplete="name" /></label>
            <label><span>{{ form.tipo_pessoa === 'J' ? 'CNPJ *' : 'CPF *' }}</span><input v-model.trim="form.cpf_cnpj" required inputmode="numeric" :placeholder="form.tipo_pessoa === 'J' ? '00.000.000/0000-00' : '000.000.000-00'" /></label>
            <label><span>E-mail *</span><input v-model.trim="form.email" required type="email" autocomplete="email" /></label>
            <label><span>Telefone *</span><input v-model.trim="form.telefone" required inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000" /></label>
            <label><span>WhatsApp</span><input v-model.trim="form.whatsapp" inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000" /></label>
          </div>

          <div class="address-heading"><b>Endereço do atendimento</b><small>Usado para planejar visita, rota e disponibilidade técnica.</small></div>
          <div class="form-grid">
            <label><span>CEP *</span><div class="cep-field"><input v-model.trim="form.cep" required inputmode="numeric" maxlength="9" placeholder="00000-000" @blur="buscarCep" /><button type="button" :disabled="cepLoading" @click="buscarCep">{{ cepLoading ? '...' : 'Buscar' }}</button></div><small v-if="cepError" class="field-error">{{ cepError }}</small></label>
            <label><span>UF *</span><input v-model.trim="form.estado" required maxlength="2" autocomplete="address-level1" /></label>
            <label class="wide"><span>Logradouro *</span><input v-model.trim="form.logradouro" required autocomplete="address-line1" /></label>
            <label><span>Número *</span><input v-model.trim="form.numero" required autocomplete="address-line2" /></label>
            <label><span>Complemento</span><input v-model.trim="form.complemento" maxlength="100" /></label>
            <label><span>Bairro *</span><input v-model.trim="form.bairro" required /></label>
            <label><span>Cidade *</span><input v-model.trim="form.cidade" required autocomplete="address-level2" /></label>
          </div>

          <div class="address-heading"><b>Demanda</b><small>Estas informações ajudam a equipe a direcionar o atendimento correto.</small></div>
          <div class="form-grid">
            <label><span>Tipo de demanda *</span><select v-model="form.tipo_demanda" required><option value="">Selecione</option><option>Assistência técnica em informática</option><option>Infraestrutura de redes</option><option>Computação em nuvem</option><option>Energia solar</option><option>Segurança e automação</option><option>Marketing digital</option><option>Outro serviço</option></select></label>
            <label><span>Prioridade</span><select v-model="form.prioridade"><option value="baixa">Baixa</option><option value="normal">Normal</option><option value="alta">Alta</option><option value="urgente">Urgente</option></select></label>
            <label class="wide"><span>Descreva a necessidade *</span><textarea v-model.trim="form.descricao" required rows="5" minlength="15" maxlength="4000" placeholder="Informe o cenário, os equipamentos envolvidos, o que já foi tentado e o resultado esperado."></textarea></label>
            <label class="wide"><span>Melhor horário para contato</span><input v-model.trim="form.melhor_horario" maxlength="120" placeholder="Ex.: dias úteis, das 9h às 17h" /></label>
          </div>
          <input v-model="form.portal_guard" class="portal-honeypot" tabindex="-1" autocomplete="one-time-code" aria-hidden="true" />
          <label class="consent"><input v-model="form.consentimento" required type="checkbox" /> <span>Autorizo o uso dos dados informados para criar e acompanhar este atendimento, conforme a <RouterLink to="/privacidade">Política de Privacidade</RouterLink>.</span></label>
          <p v-if="erro" class="form-error">{{ erro }}</p>
          <button class="portal-button submit" :disabled="loading">{{ loading ? 'Registrando chamado...' : 'Abrir chamado' }}</button>
        </form>

        <section v-else class="atendimento-card success-card">
          <CircleCheckBig /><span class="portal-overline">Chamado registrado</span><h2>Recebemos sua solicitação</h2><p>{{ resultado.message }}</p><div class="protocol">Protocolo <strong>{{ resultado.protocolo }}</strong></div>
          <p>Guarde este link: ele permite acompanhar a análise, visualizar o orçamento e autorizar o serviço pelo celular.</p>
          <RouterLink class="portal-button" :to="{ name: 'portal.atendimento.acompanhar', params: { token: resultado.acompanhamento_token } }">Acompanhar chamado</RouterLink>
          <button class="secondary-button" type="button" @click="novoChamado">Abrir outro chamado</button>
        </section>
      </div>
    </section>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { CircleCheckBig } from 'lucide-vue-next'
import { abrirChamado, consultarCep } from '@/services/portal'

const blank = () => ({ tipo_pessoa: 'F', nome: '', cpf_cnpj: '', email: '', telefone: '', whatsapp: '', cep: '', logradouro: '', numero: '', complemento: '', bairro: '', cidade: '', estado: '', ibge: '', tipo_demanda: '', prioridade: 'normal', descricao: '', melhor_horario: '', consentimento: false, portal_guard: '' })
const form = reactive(blank())
const loading = ref(false), enviado = ref(false), erro = ref(''), cepLoading = ref(false), cepError = ref(''), resultado = reactive({})

async function buscarCep() {
  const cep = String(form.cep || '').replace(/\D/g, '')
  if (cep.length !== 8) { cepError.value = 'Informe os 8 dígitos do CEP.'; return }
  cepError.value = ''; cepLoading.value = true
  try {
    const endereco = await consultarCep(cep)
    form.cep = endereco.cep || cep; form.logradouro = endereco.logradouro || form.logradouro; form.complemento = endereco.complemento || form.complemento
    form.bairro = endereco.bairro || form.bairro; form.cidade = endereco.cidade || form.cidade; form.estado = endereco.uf || form.estado; form.ibge = endereco.ibge || ''
  } catch (e) { cepError.value = e.response?.data?.message || 'Não foi possível consultar este CEP.' } finally { cepLoading.value = false }
}
async function enviar() {
  loading.value = true; erro.value = ''
  try { Object.assign(resultado, await abrirChamado(form)); enviado.value = true }
  catch (e) { erro.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Não foi possível abrir o chamado agora.' }
  finally { loading.value = false }
}
function novoChamado() { Object.assign(form, blank()); Object.keys(resultado).forEach(key => delete resultado[key]); enviado.value = false; erro.value = ''; cepError.value = '' }
</script>

<style scoped>
.atendimento-layout{display:grid;grid-template-columns:.85fr 1.25fr;gap:42px;align-items:start}.atendimento-intro h2,.atendimento-card h2{font-size:clamp(1.65rem,3vw,2.35rem);line-height:1.12;margin:10px 0 14px;color:#163a29}.atendimento-intro ol{display:grid;gap:16px;margin:28px 0;list-style:none;padding:0}.atendimento-intro li{display:flex;gap:13px}.atendimento-intro li>span{width:28px;height:28px;flex:0 0 28px;display:grid;place-items:center;border-radius:50%;background:#daf5e2;color:#008937;font-weight:800}.atendimento-intro b{display:block;color:#173a29}.atendimento-intro p{color:#60756a;font-size:.92rem;margin:3px 0}.atendimento-note{padding:14px;border-radius:12px;background:#f2f7f3;border:1px solid #dbe9df;color:#4c6457}.atendimento-card{border:1px solid #d6e5da;border-radius:22px;background:#fff;padding:clamp(20px,4vw,36px);box-shadow:0 16px 45px #0b2c1610}.form-heading p,.address-heading small{color:#63796d;font-size:.88rem}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.form-grid label{display:block;color:#1d4430;font-size:.8rem;font-weight:750}.form-grid label>span{display:block;margin-bottom:6px}.form-grid input,.form-grid select,.form-grid textarea{width:100%;border:1px solid #bdd5c4;border-radius:10px;padding:11px 12px;color:#153727;background:#fff;font:inherit;font-weight:500}.form-grid textarea{resize:vertical}.wide{grid-column:1/-1}.address-heading{display:flex;flex-direction:column;gap:2px;padding-top:25px;margin-top:24px;border-top:1px solid #e1ece4}.cep-field{display:flex;gap:8px}.cep-field input{min-width:0}.cep-field button{padding:0 12px;border:1px solid #0c9b42;border-radius:9px;color:#087d35;font-size:.78rem;font-weight:800}.field-error,.form-error{display:block;color:#bf3333;font-size:.78rem;margin-top:5px}.consent{display:flex;gap:9px;align-items:flex-start;margin:22px 0 16px;font-size:.82rem;color:#50685a}.consent input{margin-top:3px;accent-color:#00993b}.consent a{color:#008a35;text-decoration:underline}.submit{width:100%;justify-content:center}.success-card{display:flex;align-items:center;text-align:center;flex-direction:column;min-height:430px;justify-content:center}.success-card>svg{width:62px;height:62px;color:#00a33b;margin-bottom:16px}.success-card>p{max-width:540px;color:#597064}.protocol{margin:18px 0;padding:13px 22px;border-radius:12px;background:#eff9f1;color:#1c4b2c}.protocol strong{font-size:1.12rem;margin-left:7px}.success-card .portal-button{margin-top:12px}.secondary-button{margin-top:10px;color:#087d35;font-weight:750}@media(max-width:850px){.atendimento-layout{grid-template-columns:1fr;gap:25px}.atendimento-intro{padding:0 5px}.form-grid{grid-template-columns:1fr}.wide{grid-column:auto}}@media(max-width:460px){.atendimento-card{padding:19px 15px;border-radius:16px}.cep-field button{padding:0 9px}.consent{font-size:.76rem}}
</style>
