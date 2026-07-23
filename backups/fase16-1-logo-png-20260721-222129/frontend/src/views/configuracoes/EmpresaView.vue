<template>
  <div class="space-y-5 max-w-[1500px] mx-auto">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-xl font-bold text-[var(--text-primary)]">Dados da empresa</h1>
        <p class="text-sm text-[var(--text-muted)] mt-1">Cadastro institucional, fiscal, comercial e de contato da empresa.</p>
      </div>
      <button class="btn-primary" :disabled="salvando" @click="salvar">
        {{ salvando ? 'Salvando...' : 'Salvar alterações' }}
      </button>
    </div>

    
    <div
      v-if="form.cadastro_pendente"
      class="cadastro-pendente-banner"
    >
      <div>
        <strong>Conclua o cadastro oficial da empresa</strong>
        <p>
          Sua conta foi criada pelo login social. Informe o CNPJ e use
          “Importar dados da RFB” para preencher os dados empresariais.
        </p>
      </div>
    </div>
<form class="card-elevated p-5 space-y-7" @submit.prevent="salvar">
      <section>
        <h2 class="section-title">Identificação</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mt-4">
          <div><label class="label">Razão social *</label><input v-model="form.razao_social" class="input" required /></div>
          <div><label class="label">Nome fantasia</label><input v-model="form.nome_fantasia" class="input" /></div>
          <div><label class="label">Tipo da pessoa *</label><select v-model="form.tipo_pessoa" class="input"><option value="juridica">Pessoa Jurídica</option><option value="fisica">Pessoa Física</option></select></div>
          <div><label class="label">CNPJ</label><div class="flex gap-2"><input v-model="form.cnpj" class="input" @input="form.cnpj = mascaraCnpj(form.cnpj)"/><button type="button" class="btn-secondary whitespace-nowrap" :disabled="consultandoCnpj" @click="consultarCnpj">{{ consultandoCnpj ? 'Importando...' : 'Importar dados da RFB' }}</button></div><p class="lookup-hint">Consulta fontes públicas que reproduzem os dados cadastrais da Receita Federal.</p></div>
          <div><label class="label">Inscrição Estadual</label><div class="flex gap-2"><input v-model="form.ie" class="input" :disabled="form.ie_isento"/><label class="check-card whitespace-nowrap"><input v-model="form.ie_isento" type="checkbox"/> Isento</label></div></div>
          <div><label class="label">Inscrição Municipal</label><input v-model="form.im" class="input" /></div>
          <div><label class="label">CNAE</label><input v-model="form.cnae" class="input" /></div>
          <div><label class="label">Atividade principal</label><input v-model="form.atividade_principal" class="input" /></div>
                    <div class="assistente-fiscal xl:col-span-3">
            <div>
              <strong>Assistente de configuração fiscal</strong>
              <p>{{ resumoFiscal }}</p>
              <small>
                A sugestão é um ponto de partida para operações comuns. O cadastro do produto
                continua tendo prioridade sobre o padrão da empresa.
              </small>
            </div>
            <button type="button" class="btn-secondary" @click="aplicarSugestaoFiscal(true)">
              Aplicar sugestão
            </button>
          </div>
          <div>
            <label class="label">Forma de tributação atual *</label>
            <select v-model="form.regime_apuracao" class="input" required>
              <option value="">Selecione</option>
              <option value="mei">MEI / SIMEI — CRT 1</option>
              <option value="simples_nacional">Simples Nacional — CRT 1</option>
              <option value="simples_excesso">Simples com excesso de sublimite — CRT 2</option>
              <option value="lucro_presumido">Lucro Presumido — CRT 3</option>
              <option value="lucro_real">Lucro Real — CRT 3</option>
              <option value="arbitrado">Lucro Arbitrado — CRT 3</option>
            </select>
          </div>

          <div v-if="usaCsosn">
            <label class="label">CSOSN padrão *</label>
            <select v-model="form.icms_csosn_padrao" class="input">
              <option value="">Selecione</option>
              <option v-for="item in csosnDisponiveis" :key="item.codigo" :value="item.codigo">{{ item.codigo }} — {{ item.descricao }}</option>
            </select>
            <small v-if="form.regime_apuracao === 'mei'" class="ajuda-fiscal">
              Para MEI, use inicialmente 102. Você poderá trocar para 400 e salvar novamente
              caso a regra da operação ou o retorno da SEFAZ indique.
            </small>
          </div>

          <div v-else>
            <label class="label">CST de ICMS padrão *</label>
            <select v-model="form.icms_cst_padrao" class="input">
              <option value="">Selecione</option>
              <option v-for="item in cstIcmsDisponiveis" :key="item.codigo" :value="item.codigo">{{ item.codigo }} — {{ item.descricao }}</option>
            </select>
          </div>

          <div>
            <label class="label">CST padrão do PIS *</label>
            <input v-model="form.pis_cst_padrao" class="input" maxlength="2" placeholder="Ex.: 01 ou 49" />
            <small class="ajuda-fiscal">01 = operação tributável; 49 = outras operações de saída.</small>
          </div>

          <div>
            <label class="label">CST padrão da COFINS *</label>
            <input v-model="form.cofins_cst_padrao" class="input" maxlength="2" placeholder="Ex.: 01 ou 49" />
            <small class="ajuda-fiscal">01 = operação tributável; 49 = outras operações de saída.</small>
          </div>

          <div class="xl:col-span-2">
            <label class="label">Observação fiscal</label>
            <input v-model="form.fiscal_observacao" class="input" placeholder="Ex.: regime confirmado no e-CAC em..." />
          </div>

          <label class="regime-confirmacao xl:col-span-3">
            <input v-model="form.regime_fiscal_confirmado" type="checkbox" />
            Confirmo que esta é a tributação atual da empresa e que os códigos foram revisados.
          </label>
          <div><label class="label">Porte da empresa</label><select v-model="form.porte_empresa" class="input"><option value="">Selecione</option><option>MEI</option><option>ME</option><option>EPP</option><option>Médio Porte</option><option>Grande Porte</option></select></div>
          <div><label class="label">Faturamento do último ano</label><select v-model="form.faturamento_anual_faixa" class="input"><option value="">Não informado</option><option>Até R$ 81 mil</option><option>R$ 81 mil a R$ 360 mil</option><option>R$ 360 mil a R$ 4,8 milhões</option><option>Acima de R$ 4,8 milhões</option></select></div>
          <div><label class="label">Quantidade de funcionários</label><input v-model.number="form.quantidade_funcionarios" class="input" type="number" min="0" /></div>
        </div>

        <div class="mt-4">
          <label class="label">Segmentos de atuação</label>
          <div class="flex flex-wrap gap-3">
            <label v-for="item in segmentosDisponiveis" :key="item.value" class="check-card"><input v-model="form.segmentos" type="checkbox" :value="item.value"/> {{ item.label }}</label>
          </div>
        </div>
      </section>

      <section>
        <h2 class="section-title">Endereço</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mt-4">
          <div><label class="label">CEP</label><div class="flex gap-2"><input v-model="form.cep" class="input" @input="form.cep = mascaraCep(form.cep)"/><button type="button" class="btn-secondary whitespace-nowrap" :disabled="consultandoCep" @click="consultarCep">{{ consultandoCep ? 'Buscando...' : 'Buscar CEP' }}</button></div></div>
          <div><label class="label">UF</label><input v-model="form.estado" class="input uppercase" maxlength="2" /></div>
          <div class="xl:col-span-2"><label class="label">Cidade</label><input v-model="form.cidade" class="input" /></div>
          <div><label class="label">Bairro</label><input v-model="form.bairro" class="input" /></div>
          <div class="xl:col-span-2"><label class="label">Endereço</label><input v-model="form.logradouro" class="input" /></div>
          <div><label class="label">Número</label><input v-model="form.numero" class="input" /></div>
          <div class="xl:col-span-2"><label class="label">Complemento</label><input v-model="form.complemento" class="input" /></div>
        </div>
      </section>

      <section>
        <h2 class="section-title">Contato</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mt-4">
          <div><label class="label">Pessoa de contato</label><input v-model="form.contato_nome" class="input" /></div>
          <div><label class="label">Telefone</label><input v-model="form.telefone" class="input" /></div>
          <div><label class="label">Celular</label><input v-model="form.celular" class="input" /></div>
          <div class="xl:col-span-2"><label class="label">E-mail</label><input v-model="form.email" class="input" type="email" /></div>
          <div><label class="label">Site</label><input v-model="form.site" class="input" placeholder="https://" /></div>
        </div>
      </section>

      <section>
        <div class="flex items-center justify-between gap-3"><h2 class="section-title">Inscrições Estaduais de substitutos tributários</h2><button type="button" class="btn-secondary btn-sm" @click="adicionarInscricao">+ Adicionar inscrição</button></div>
        <div class="space-y-2 mt-4">
          <div v-for="(item, index) in form.inscricoes_substitutas" :key="index" class="grid grid-cols-[110px_1fr_auto] gap-2">
            <input v-model="item.uf" class="input uppercase" maxlength="2" placeholder="UF" />
            <input v-model="item.inscricao_estadual" class="input" placeholder="Inscrição Estadual" />
            <button type="button" class="btn-danger btn-icon" @click="form.inscricoes_substitutas.splice(index, 1)">×</button>
          </div>
          <p v-if="!form.inscricoes_substitutas.length" class="text-xs text-[var(--text-muted)]">Nenhuma inscrição adicional cadastrada.</p>
        </div>
      </section>

      <section>
        <h2 class="section-title">Marca da empresa</h2>
        <div class="logo-panel mt-4">
          <div class="logo-preview">
            <img
              v-if="logoUrl && !logoFalhou"
              :key="logoCacheKey"
              :src="logoUrl"
              class="logo-image"
              alt="Logo da empresa"
              @load="logoFalhou = false"
              @error="logoFalhou = true"
            />
            <div v-else class="logo-placeholder">
              <span class="logo-placeholder-icon">IT</span>
              <span>{{ logoFalhou ? 'Não foi possível carregar a logo' : 'Nenhuma logo cadastrada' }}</span>
            </div>
          </div>

          <div class="logo-actions">
            <input
              ref="logoInput"
              type="file"
              accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
              class="hidden"
              @change="enviarLogo"
            />

            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="btn-secondary"
                :disabled="enviandoLogo"
                @click="logoInput?.click()"
              >
                {{ enviandoLogo ? 'Enviando...' : (form.logo ? 'Alterar imagem' : 'Selecionar imagem') }}
              </button>

              <button
                v-if="form.logo"
                type="button"
                class="btn-ghost"
                :disabled="enviandoLogo"
                @click="removerLogo"
              >
                Remover
              </button>
            </div>

            <p class="logo-help">PNG, JPG, JPEG ou WEBP. Máximo 4 MB.</p>
            <p class="logo-help">A imagem será utilizada no DANFE da NF-e e da NFC-e.</p>
          </div>
        </div>
      </section>

      <div class="flex justify-end pt-2 border-t border-[var(--border-subtle)]"><button class="btn-primary" type="submit" :disabled="salvando">{{ salvando ? 'Salvando...' : 'Salvar alterações' }}</button></div>
    </form>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast = useToast()
const salvando = ref(false)
const consultandoCep = ref(false)
const consultandoCnpj = ref(false)
const logoInput = ref(null)
const csosnDisponiveis = [
  { codigo: '101', descricao: 'Tributada com permissão de crédito' },
  { codigo: '102', descricao: 'Tributada sem permissão de crédito' },
  { codigo: '103', descricao: 'Isenção por faixa de receita' },
  { codigo: '300', descricao: 'Imune' },
  { codigo: '400', descricao: 'Não tributada pelo Simples Nacional' },
  { codigo: '500', descricao: 'ICMS cobrado anteriormente por substituição tributária' },
  { codigo: '900', descricao: 'Outros casos' },
]
const cstIcmsDisponiveis = [
  { codigo: '00', descricao: 'Tributada integralmente' },
  { codigo: '10', descricao: 'Tributada com ICMS-ST' },
  { codigo: '20', descricao: 'Tributada com redução de base' },
  { codigo: '30', descricao: 'Isenta ou não tributada com ICMS-ST' },
  { codigo: '40', descricao: 'Isenta' },
  { codigo: '41', descricao: 'Não tributada' },
  { codigo: '50', descricao: 'Suspensão' },
  { codigo: '51', descricao: 'Diferimento' },
  { codigo: '60', descricao: 'ICMS cobrado anteriormente por substituição tributária' },
  { codigo: '70', descricao: 'Redução de base com ICMS-ST' },
  { codigo: '90', descricao: 'Outros casos' },
]
const enviandoLogo = ref(false)
const logoFalhou = ref(false)
const logoCacheKey = ref(Date.now())
const segmentosDisponiveis = [{value:'comercio',label:'Comércio'},{value:'ecommerce',label:'E-commerce'},{value:'industria',label:'Indústria'},{value:'servicos',label:'Serviços'}]
const form = reactive({ cadastro_pendente:false, dados_fonte:'', razao_social:'', nome_fantasia:'', tipo_pessoa:'juridica', cnpj:'', ie:'', ie_isento:false, im:'', cnae:'', atividade_principal:'', regime_tributario:1, regime_apuracao:'', regime_fiscal_confirmado:false, icms_csosn_padrao:'', icms_cst_padrao:'', pis_cst_padrao:'49', cofins_cst_padrao:'49', fiscal_observacao:'', porte_empresa:'', faturamento_anual_faixa:'', quantidade_funcionarios:null, segmentos:[], cep:'', estado:'', cidade:'', bairro:'', logradouro:'', numero:'', complemento:'', ibge:'', contato_nome:'', telefone:'', celular:'', email:'', site:'', inscricoes_substitutas:[], logo:null, logo_url:null })
const sugestoesFiscais = {
  mei: {
    icms_csosn_padrao: '102',
    icms_cst_padrao: '',
    pis_cst_padrao: '49',
    cofins_cst_padrao: '49',
    resumo: 'MEI / SIMEI: CRT 1, CSOSN 102 como padrão inicial, PIS 49 e COFINS 49. Se a SEFAZ ou a regra da operação exigir, altere manualmente o CSOSN para 400.',
  },
  simples_nacional: {
    icms_csosn_padrao: '102',
    icms_cst_padrao: '',
    pis_cst_padrao: '49',
    cofins_cst_padrao: '49',
    resumo: 'Sugestão inicial: CSOSN 102, PIS 49 e COFINS 49.',
  },
  simples_excesso: {
    icms_csosn_padrao: '102',
    icms_cst_padrao: '',
    pis_cst_padrao: '49',
    cofins_cst_padrao: '49',
    resumo: 'Sugestão inicial: CSOSN 102, PIS 49 e COFINS 49. Revise o tratamento do excesso de sublimite.',
  },
  lucro_presumido: {
    icms_csosn_padrao: '',
    icms_cst_padrao: '00',
    pis_cst_padrao: '01',
    cofins_cst_padrao: '01',
    resumo: 'Sugestão inicial para venda comum tributada: ICMS 00, PIS 01 e COFINS 01.',
  },
  lucro_real: {
    icms_csosn_padrao: '',
    icms_cst_padrao: '00',
    pis_cst_padrao: '01',
    cofins_cst_padrao: '01',
    resumo: 'Sugestão inicial para venda comum tributada: ICMS 00, PIS 01 e COFINS 01.',
  },
  arbitrado: {
    icms_csosn_padrao: '',
    icms_cst_padrao: '00',
    pis_cst_padrao: '01',
    cofins_cst_padrao: '01',
    resumo: 'Sugestão inicial para venda comum tributada: ICMS 00, PIS 01 e COFINS 01.',
  },
}

const codigosCsosn = computed(() => csosnDisponiveis.map((item) => item.codigo))
const codigosCstIcms = computed(() => cstIcmsDisponiveis.map((item) => item.codigo))

const resumoFiscal = computed(() => {
  return sugestoesFiscais[form.regime_apuracao]?.resumo
    || 'Selecione a forma de tributação para o sistema apresentar uma sugestão.'
})

function aplicarSugestaoFiscal(forcar = false) {
  const sugestao = sugestoesFiscais[form.regime_apuracao]
  if (!sugestao) return

  const csosnInvalido = !codigosCsosn.value.includes(String(form.icms_csosn_padrao || ''))
  const cstInvalido = !codigosCstIcms.value.includes(String(form.icms_cst_padrao || ''))

  if (forcar || csosnInvalido || !usaCsosn.value) {
    form.icms_csosn_padrao = sugestao.icms_csosn_padrao
  }

  if (forcar || cstInvalido || usaCsosn.value) {
    form.icms_cst_padrao = sugestao.icms_cst_padrao
  }

  if (forcar || !String(form.pis_cst_padrao || '').trim()) {
    form.pis_cst_padrao = sugestao.pis_cst_padrao
  }

  if (forcar || !String(form.cofins_cst_padrao || '').trim()) {
    form.cofins_cst_padrao = sugestao.cofins_cst_padrao
  }

  form.regime_fiscal_confirmado = false
}
const usaCsosn = computed(() => ['mei','simples_nacional','simples_excesso'].includes(form.regime_apuracao))
watch(
  () => form.regime_apuracao,
  (novo, anterior) => {
    if (!novo || novo === anterior) return
    aplicarSugestaoFiscal(false)
  },
)
const logoUrl = computed(() => {
  const url = form.logo_url || (form.logo?.startsWith('http') ? form.logo : null)
  if (!url) return null

  const separator = url.includes('?') ? '&' : '?'
  return `${url}${separator}v=${logoCacheKey.value}`
})

function aplicar(dados={}) {
  Object.keys(form).forEach(k => {
    if (dados[k] !== undefined && dados[k] !== null) form[k] = dados[k]
  })
  form.segmentos = Array.isArray(dados.segmentos) ? dados.segmentos : []
  form.inscricoes_substitutas = Array.isArray(dados.inscricoes_substitutas) ? dados.inscricoes_substitutas : []
  logoFalhou.value = false
}
async function carregar(){ try { const {data}=await api.get('/empresa/perfil'); aplicar(data.data) } catch(e){ toast.error(e.response?.data?.message || 'Não foi possível carregar os dados da empresa.') } }
async function salvar(){ salvando.value=true; try { const {data}=await api.put('/empresa/perfil', form); aplicar(data.data); toast.success(data.message) } catch(e){ toast.error(primeiroErro(e) || 'Falha ao salvar os dados.') } finally { salvando.value=false } }
async function consultarCep(){ const cep=(form.cep||'').replace(/\D/g,''); if(cep.length!==8){toast.warning('Informe um CEP com 8 dígitos.');return} consultandoCep.value=true; try{const {data}=await api.get(`/empresa/consultar-cep/${cep}`); const d=data.data||data; form.cep=mascaraCep(d.cep||cep); form.logradouro=d.logradouro||form.logradouro; form.complemento=d.complemento||form.complemento; form.bairro=d.bairro||form.bairro; form.cidade=d.cidade||form.cidade; form.estado=d.estado||form.estado; form.ibge=d.ibge||form.ibge; form.dados_fonte=d.fonte||form.dados_fonte}catch{toast.error('CEP não encontrado.')}finally{consultandoCep.value=false}}
async function consultarCnpj(){ const cnpj=(form.cnpj||'').replace(/\D/g,''); if(cnpj.length!==14){toast.warning('Informe um CNPJ com 14 dígitos.');return} consultandoCnpj.value=true; try{const {data}=await api.get(`/empresa/consultar-cnpj/${cnpj}`); const d=data.data||data; aplicar({...form, razao_social:d.razao_social||form.razao_social, nome_fantasia:d.nome_fantasia||form.nome_fantasia, cnpj:mascaraCnpj(d.cnpj||cnpj), ie:d.ie||form.ie, cnae:d.cnae_principal||d.cnae||form.cnae, atividade_principal:d.atividade_principal||form.atividade_principal, porte_empresa:d.porte||form.porte_empresa, cep:mascaraCep(d.cep||form.cep), logradouro:d.logradouro||form.logradouro, numero:d.numero||form.numero, complemento:d.complemento||form.complemento, bairro:d.bairro||form.bairro, cidade:d.cidade||d.municipio||form.cidade, estado:d.uf||form.estado, telefone:d.telefone||form.telefone, email:d.email||form.email, dados_fonte:d.fonte||form.dados_fonte}); toast.success(`Dados empresariais carregados por ${d.fonte||'fonte pública'}. Revise e salve.`)}catch(e){toast.error(e.response?.data?.message||'Não foi possível consultar o CNPJ.')}finally{consultandoCnpj.value=false}}
function adicionarInscricao(){form.inscricoes_substitutas.push({uf:'',inscricao_estadual:''})}
async function enviarLogo(event) {
  const file = event.target.files?.[0]
  if (!file) return

  if (file.size > 4 * 1024 * 1024) {
    toast.warning('A logo deve possuir no máximo 4 MB.')
    event.target.value = ''
    return
  }

  enviandoLogo.value = true
  const fd = new FormData()
  fd.append('logo', file)

  try {
    const { data } = await api.post('/empresa/logo', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    aplicar(data.data)
    logoCacheKey.value = Date.now()
    logoFalhou.value = false
    toast.success(data.message)
  } catch (e) {
    toast.error(primeiroErro(e) || e.response?.data?.message || 'Falha ao enviar a logo.')
  } finally {
    enviandoLogo.value = false
    event.target.value = ''
  }
}
async function removerLogo() {
  try {
    await api.delete('/empresa/logo')
    form.logo = null
    form.logo_url = null
    logoFalhou.value = false
    logoCacheKey.value = Date.now()
    toast.success('Logo removida.')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha ao remover a logo.')
  }
}
function mascaraCnpj(v=''){const n=v.replace(/\D/g,'').slice(0,14);return n.replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d)/,'$1-$2')}
function mascaraCep(v=''){const n=String(v).replace(/\D/g,'').slice(0,8);return n.replace(/(\d{5})(\d)/,'$1-$2')}
function primeiroErro(e){const erros=e.response?.data?.errors;return erros?Object.values(erros).flat()[0]:null}
onMounted(carregar)
</script>

<style scoped>
.section-title{font-size:.95rem;font-weight:700;color:var(--text-primary);padding-bottom:.65rem;border-bottom:1px solid var(--border-subtle)}
.label{display:block;font-size:.75rem;font-weight:600;color:var(--text-secondary);margin-bottom:.4rem}
.check-card{display:flex;align-items:center;gap:.5rem;padding:.65rem .8rem;border:1px solid var(--border);background:var(--bg-elevated);border-radius:.6rem;font-size:.8rem;color:var(--text-secondary)}
.cadastro-pendente-banner{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;border:1px solid rgba(99,102,241,.45);border-radius:12px;background:rgba(79,70,229,.10)}
.cadastro-pendente-banner strong{display:block;color:var(--text-primary);font-size:.9rem}
.cadastro-pendente-banner p{margin-top:4px;color:var(--text-muted);font-size:.78rem}
.lookup-hint{margin-top:5px;color:var(--text-muted);font-size:.68rem;line-height:1.35}

.logo-panel{display:flex;flex-wrap:wrap;align-items:center;gap:1.25rem;padding:1rem;border:1px solid var(--border);border-radius:.85rem;background:var(--bg-elevated)}
.logo-preview{width:220px;height:120px;display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px dashed var(--border);border-radius:.75rem;background:#fff;padding:.75rem}
.logo-image{display:block;max-width:100%;max-height:100%;object-fit:contain}
.logo-placeholder{display:flex;flex-direction:column;align-items:center;gap:.5rem;text-align:center;color:var(--text-muted);font-size:.72rem}
.logo-placeholder-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:.65rem;background:rgba(79,70,229,.12);color:#818cf8;font-weight:800}
.logo-actions{min-width:240px;flex:1}
.logo-help{margin-top:.4rem;color:var(--text-muted);font-size:.7rem;line-height:1.35}

.regime-confirmacao{display:flex;align-items:flex-start;gap:.65rem;padding:.85rem 1rem;border:1px solid rgba(245,158,11,.4);border-radius:.7rem;background:rgba(245,158,11,.08);font-size:.78rem;color:var(--text-secondary)}
.assistente-fiscal{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem;border:1px solid rgba(59,130,246,.35);border-radius:.8rem;background:rgba(59,130,246,.08)}
.assistente-fiscal strong{display:block;margin-bottom:.25rem}
.assistente-fiscal p{margin:.15rem 0;font-size:.82rem;color:var(--text-secondary)}
.assistente-fiscal small,.ajuda-fiscal{display:block;margin-top:.3rem;font-size:.7rem;color:var(--text-muted)}
.btn-secondary{border:1px solid var(--border-color);border-radius:.65rem;padding:.65rem .9rem;background:var(--surface-elevated);color:var(--text-primary);font-weight:600;white-space:nowrap}
.btn-secondary:hover{filter:brightness(1.05)}
@media(max-width:760px){.assistente-fiscal{align-items:flex-start;flex-direction:column}.btn-secondary{width:100%}}
</style>
