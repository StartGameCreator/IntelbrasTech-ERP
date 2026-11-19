<template>
  <div class="solar-simulator">
    <div class="solar-simulator-head">
      <div><span class="portal-overline">Simulador solar IntelbrasTech</span><h2>Descubra o potencial do seu projeto</h2></div>
      <span class="solar-step-label">Etapa {{ Math.min(step, 4) }} de 4</span>
    </div>
    <div class="solar-progress"><i :style="{ width: `${Math.min(step, 4) * 25}%` }" /></div>

    <form v-if="!submitted" @submit.prevent="advance">
      <section v-if="step === 1" class="solar-simulator-step">
        <h3>Qual é o tipo do local?</h3><p>Selecione onde pretende instalar ou utilizar a energia.</p>
        <div class="solar-choice-grid">
          <button v-for="item in localTypes" :key="item.value" type="button" :class="{ selected: form.tipo_local === item.value }" @click="form.tipo_local = item.value"><component :is="item.icon"/><b>{{ item.label }}</b><small>{{ item.help }}</small></button>
        </div>
      </section>

      <section v-else-if="step === 2" class="solar-simulator-step">
        <h3>Onde será o projeto?</h3><p>A região influencia a irradiação solar e as condições de conexão.</p>
        <div class="portal-form-grid">
          <label>CEP<div class="solar-cep-field"><input v-model="form.cep" maxlength="9" inputmode="numeric" placeholder="00000-000" @input="formatCep" @blur="lookupCep"/><span v-if="cepLoading">Consultando...</span><CircleCheck v-else-if="cepValid" class="cep-valid"/><CircleAlert v-else-if="cepError" class="cep-invalid"/></div><small v-if="cepError" class="field-error">{{ cepError }}</small><small v-else-if="cepValid" class="field-success">CEP validado{{ cepIbgeValidated ? ' com município IBGE' : '' }}.</small></label>
          <label>UF<select v-model="form.uf" required :disabled="ufsLoading"><option value="">{{ ufsLoading ? 'Carregando UFs...' : 'Selecione a UF' }}</option><option v-for="uf in ufs" :key="uf.id" :value="uf.sigla">{{ uf.sigla }} — {{ uf.nome }}</option></select></label>
          <label>Cidade<select v-model="form.municipio_ibge" required :disabled="!form.uf || citiesLoading"><option value="">{{ citiesLoading ? 'Carregando cidades...' : 'Selecione a cidade' }}</option><option v-for="city in cities" :key="city.id" :value="city.id">{{ city.nome }}</option></select></label>
          <label class="span-two">Endereço ou bairro<input v-model.trim="form.endereco" maxlength="250" placeholder="Opcional nesta etapa" /></label>
          <label>Concessionária<input v-model.trim="form.concessionaria" maxlength="120" placeholder="Ex.: Equatorial" /></label>
          <label>Tipo de ligação<select v-model="form.ligacao"><option value="">Não sei informar</option><option>Monofásica</option><option>Bifásica</option><option>Trifásica</option></select></label>
        </div>
        <button class="solar-location" type="button" @click="useLocation"><MapPin/> {{ locating ? 'Obtendo localização...' : 'Usar minha localização atual' }}</button>
        <p v-if="locationMessage" class="solar-inline-message">{{ locationMessage }}</p>
      </section>

      <section v-else-if="step === 3" class="solar-simulator-step">
        <h3>Como é o seu consumo de energia?</h3><p>Você pode informar kWh, valor da conta ou ambos.</p>
        <div class="portal-form-grid">
          <label>Consumo médio mensal (kWh)<input v-model.number="form.consumo_kwh" type="number" min="0" max="999999" placeholder="Ex.: 650" /></label>
          <label>Valor médio da conta (R$)<input v-model.number="form.valor_conta" type="number" min="0" max="9999999" step="0.01" placeholder="Ex.: 720,00" /></label>
          <label>Modalidade<select v-model="form.modalidade"><option>Tarifa convencional</option><option>Tarifa branca</option><option>Grupo A</option><option>Não sei informar</option></select></label>
          <label>Sistema de interesse<select v-model="form.sistema_interesse"><option>On Grid</option><option>Híbrido</option><option>Off Grid</option><option>Quero orientação</option></select></label>
        </div>
        <p class="solar-tip"><Lightbulb/> Consulte a média dos últimos 12 meses da sua conta para uma estimativa mais consistente.</p>
      </section>

      <section v-else-if="step === 4" class="solar-simulator-step">
        <h3>Como é a área disponível?</h3><p>Estas informações ajudam a qualificar a análise técnica.</p>
        <div class="portal-form-grid">
          <label>Tipo de cobertura<select v-model="form.telhado"><option value="">Selecione</option><option>Cerâmico</option><option>Metálico</option><option>Fibrocimento</option><option>Laje</option><option>Solo</option><option>Não sei informar</option></select></label>
          <label>Área aproximada (m²)<input v-model.number="form.area_disponivel" type="number" min="0" max="999999" placeholder="Opcional" /></label>
          <label class="span-two">Observações<textarea v-model.trim="form.mensagem" rows="3" maxlength="2000" placeholder="Sombreamento, expansão futura, falta de rede ou outra informação importante." /></label>
        </div>
      </section>

      <section v-else class="solar-result">
        <div class="solar-result-title"><CircleCheck/><div><span>Estimativa preliminar</span><h3>Potencial solar do seu projeto</h3></div></div>
        <div class="solar-result-grid">
          <article><Sun/><small>Potência estimada</small><b>{{ result.potencia }} kWp</b></article>
          <article><Zap/><small>Geração mensal</small><b>{{ result.geracao }} kWh</b></article>
          <article><WalletCards/><small>Economia estimada</small><b>R$ {{ money(result.economia) }}/mês</b></article>
          <article><BarChart3/><small>Retorno indicativo</small><b>{{ result.paybackMin }}–{{ result.paybackMax }} anos</b></article>
        </div>
        <div class="solar-investment"><span>Faixa preliminar de investimento</span><b>R$ {{ money(result.investimentoMin) }} a R$ {{ money(result.investimentoMax) }}</b></div>
        <p class="solar-disclaimer"><Info/> Estimativa baseada nos dados informados e em premissas médias. Não constitui proposta comercial, garantia de geração ou dimensionamento final. Tarifas, irradiação, perdas, estrutura, rede, impostos e regras da distribuidora precisam ser validados.</p>
        <div class="solar-contact-fields">
          <h3>Receba a análise detalhada</h3>
          <div class="portal-form-grid"><label>Nome completo<input v-model.trim="form.nome" required maxlength="200" autocomplete="name" /></label><label>Telefone / WhatsApp<input v-model.trim="form.telefone" required maxlength="30" autocomplete="tel" /></label><label>E-mail<input v-model.trim="form.email" type="email" maxlength="150" autocomplete="email" /></label><label class="solar-check"><input v-model="form.aceita_marketing" type="checkbox"/> Quero receber novidades e condições comerciais.</label></div>
          <input v-model="form.portal_guard" class="portal-honeypot" name="contact_reference_code" tabindex="-1" autocomplete="one-time-code" aria-hidden="true" />
          <label class="portal-consent"><input v-model="form.consentimento" type="checkbox" required /> Autorizo o uso dos dados para retorno desta solicitação, conforme a política de privacidade.</label>
        </div>
      </section>

      <p v-if="error" class="portal-form-error"><CircleAlert/> {{ error }}</p>
      <div class="solar-actions"><button v-if="step > 1" type="button" class="portal-button secondary" @click="back"><ArrowLeft/> Voltar</button><button class="portal-button" :disabled="loading || !canAdvance">{{ loading ? 'Enviando...' : step < 5 ? 'Continuar' : 'Solicitar análise detalhada' }}<ArrowRight v-if="!loading"/></button></div>
    </form>

    <div v-else class="solar-success"><CircleCheck/><h3>Simulação enviada com sucesso</h3><p>Nossa equipe recebeu os dados e fará uma análise mais detalhada.</p><b>Protocolo: {{ protocol }}</b><button class="portal-button secondary" @click="restart">Fazer nova simulação</button></div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ArrowLeft, ArrowRight, BarChart3, Building2, CircleAlert, CircleCheck, Factory, Home, Info, Lightbulb, MapPin, RadioTower, Sprout, Sun, WalletCards, Zap } from 'lucide-vue-next'
import { carregarMunicipios, carregarUfs, consultarCep, enviarSolicitacao } from '@/services/portal'

const localTypes = [
  { value:'Residencial', label:'Residencial', help:'Casa ou condomínio', icon:Home },
  { value:'Empresarial', label:'Empresarial', help:'Comércio ou escritório', icon:Building2 },
  { value:'Agronegócio', label:'Agronegócio', help:'Propriedade rural', icon:Sprout },
  { value:'Industrial', label:'Industrial', help:'Planta ou galpão', icon:Factory },
  { value:'Sem rede', label:'Sem conexão à rede', help:'Área remota ou isolada', icon:RadioTower },
]
const initial = () => ({ tipo:'solar',tipo_local:'',cep:'',uf:'',cidade:'',municipio_ibge:'',endereco:'',concessionaria:'',ligacao:'',consumo_kwh:null,valor_conta:null,modalidade:'Tarifa convencional',sistema_interesse:'On Grid',telhado:'',area_disponivel:null,mensagem:'',nome:'',telefone:'',email:'',consentimento:false,aceita_marketing:false,portal_guard:'',latitude:null,longitude:null })
const form = reactive(initial()), step = ref(1), loading = ref(false), error = ref(''), submitted = ref(false), protocol = ref(''), locating = ref(false), locationMessage = ref('')
const ufs = ref([]), cities = ref([]), ufsLoading = ref(false), citiesLoading = ref(false), cepLoading = ref(false), cepValid = ref(false), cepError = ref(''), cepIbgeValidated = ref(false), applyingCep = ref(false)
const consumption = computed(() => Number(form.consumo_kwh) > 0 ? Number(form.consumo_kwh) : Number(form.valor_conta) > 0 ? Number(form.valor_conta) / 1.05 : 0)
const result = computed(() => {
  const kwh = consumption.value
  const potencia = Math.max(.5, kwh / 118)
  const investimentoMin = potencia * 3800, investimentoMax = potencia * 5200
  const economia = Number(form.valor_conta) > 0 ? Number(form.valor_conta) * .82 : kwh * 1.05 * .82
  return { potencia:potencia.toFixed(2),geracao:Math.round(potencia*118),economia,investimentoMin,investimentoMax,paybackMin:Math.max(3,Math.floor(investimentoMin/(economia*12))),paybackMax:Math.max(4,Math.ceil(investimentoMax/(Math.max(economia,1)*12))) }
})
const canAdvance = computed(() => step.value===1?!!form.tipo_local:step.value===2?!!form.uf&&!!form.municipio_ibge:step.value===3?consumption.value>0:true)
const money = value => Number(value||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})
function advance(){ error.value=''; if(!canAdvance.value) return; if(step.value<5){step.value+=1;return} submit() }
function back(){ if(step.value>1) step.value-=1 }
async function submit(){ loading.value=true; error.value=''; try { const r=result.value; const response=await enviarSolicitacao({...form,potencia_estimada_kwp:r.potencia,geracao_estimada_kwh:r.geracao,economia_estimada:r.economia,investimento_min:r.investimentoMin,investimento_max:r.investimentoMax,payback_min:r.paybackMin,payback_max:r.paybackMax}); if(!response?.protocolo||!response?.lead_id) throw new Error('CONFIRMACAO_AUSENTE'); protocol.value=response.protocolo;submitted.value=true } catch(e){ error.value=e.response?.data?.message||'O CRM não confirmou o recebimento. Nenhum sucesso foi registrado; tente novamente.' } finally{loading.value=false} }
function useLocation(){ if(!navigator.geolocation){locationMessage.value='Geolocalização não disponível neste dispositivo.';return} locating.value=true;navigator.geolocation.getCurrentPosition(p=>{form.latitude=p.coords.latitude;form.longitude=p.coords.longitude;locationMessage.value='Localização registrada. Informe também a cidade para confirmar.';locating.value=false},()=>{locationMessage.value='Não foi possível acessar a localização. Você pode informar a cidade manualmente.';locating.value=false},{enableHighAccuracy:false,timeout:8000}) }
function restart(){Object.assign(form,initial());step.value=1;submitted.value=false;protocol.value=''}
function formatCep(){ const digits=String(form.cep||'').replace(/\D/g,'').slice(0,8); form.cep=digits.length>5?`${digits.slice(0,5)}-${digits.slice(5)}`:digits; cepValid.value=false;cepError.value='';cepIbgeValidated.value=false }
async function loadCities(uf, selected='') { cities.value=[]; if(!uf)return; citiesLoading.value=true; try { cities.value=await carregarMunicipios(uf); if(selected) form.municipio_ibge=String(selected) } catch { error.value='Não foi possível carregar as cidades do IBGE.' } finally { citiesLoading.value=false } }
async function lookupCep(){ const digits=String(form.cep||'').replace(/\D/g,''); if(!digits)return; if(digits.length!==8){cepError.value='Informe os 8 dígitos do CEP.';return} cepLoading.value=true;cepError.value=''; try { const address=await consultarCep(digits); applyingCep.value=true; form.cep=address.cep; form.uf=address.uf; await loadCities(address.uf,address.ibge); form.cidade=address.cidade; form.municipio_ibge=String(address.ibge||''); form.endereco=[address.logradouro,address.bairro].filter(Boolean).join(', '); cepValid.value=true;cepIbgeValidated.value=Boolean(address.ibge_validado) } catch(e){cepValid.value=false;cepError.value=e.response?.data?.message||'Não foi possível validar o CEP.'} finally {applyingCep.value=false;cepLoading.value=false} }
watch(()=>form.uf, async (uf, previous)=>{ if(applyingCep.value||uf===previous)return; form.municipio_ibge='';form.cidade='';await loadCities(uf) })
watch(()=>form.municipio_ibge, id=>{ const city=cities.value.find(item=>String(item.id)===String(id)); if(city)form.cidade=city.nome })
onMounted(async()=>{ufsLoading.value=true;try{ufs.value=await carregarUfs()}catch{error.value='Não foi possível carregar as UFs do IBGE.'}finally{ufsLoading.value=false}})
</script>
