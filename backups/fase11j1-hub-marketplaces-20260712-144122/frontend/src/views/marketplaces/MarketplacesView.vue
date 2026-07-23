<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Marketplaces</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">Conecte e sincronize suas contas de vendas, logística, certificados digitais e CSV</p>
      </div>
      <button @click="carregarTudo" class="btn-secondary btn-sm" :disabled="loading">
        <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
        Atualizar
      </button>
    </div>

    <div class="card p-2 flex flex-wrap gap-2">
      <button
        v-for="aba in abas"
        :key="aba.id"
        @click="abaAtiva = aba.id"
        class="btn-sm"
        :class="abaAtiva === aba.id ? 'btn-primary' : 'btn-secondary'"
      >
        {{ aba.label }}
      </button>
    </div>

    <div v-if="loading" class="skeleton h-40 rounded-xl" />

    <div v-else-if="abaAtiva === 'marketplaces'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div v-for="plataforma in plataformas" :key="plataforma.tipo" class="card p-5">
        <div class="flex items-start justify-between mb-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold" :style="{ background: plataforma.cor }">
              {{ plataforma.nome[0] }}
            </div>
            <div>
              <h3 class="text-sm font-semibold">{{ plataforma.nome }}</h3>
              <span v-if="integracaoDe(plataforma.tipo)" class="badge" :class="statusBadge(integracaoDe(plataforma.tipo).status)">
                {{ statusLabel[integracaoDe(plataforma.tipo).status] || integracaoDe(plataforma.tipo).status }}
              </span>
              <span v-else class="badge badge-gray">Não configurado</span>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-3 mb-4">
          <input
            v-for="campo in plataforma.campos"
            :key="campo"
            v-model="credenciaisMarketplace[plataforma.tipo][campo]"
            class="input"
            :placeholder="labelCampo(campo)"
            :type="campoProtegido(campo) ? 'password' : 'text'"
          />
        </div>

        <template v-if="integracaoDe(plataforma.tipo) && integracaoDe(plataforma.tipo).status === 'ativo'">
          <div class="grid grid-cols-2 gap-3 mb-4 text-sm">
            <div>
              <p class="text-xs text-[var(--text-muted)]">Anúncios sincronizados</p>
              <p class="font-medium">{{ integracaoDe(plataforma.tipo).marketplace_produtos_count ?? 0 }}</p>
            </div>
            <div>
              <p class="text-xs text-[var(--text-muted)]">Pedidos importados</p>
              <p class="font-medium">{{ integracaoDe(plataforma.tipo).marketplace_pedidos_count ?? 0 }}</p>
            </div>
          </div>
          <p class="text-xs text-[var(--text-muted)] mb-4">
            Último sync: {{ integracaoDe(plataforma.tipo).ultimo_sync ? dataHora(integracaoDe(plataforma.tipo).ultimo_sync) : 'nunca' }}
          </p>
        </template>

        <div class="flex flex-wrap gap-2">
          <button @click="salvarMarketplace(plataforma)" class="btn-primary btn-sm" :disabled="processando">
            Salvar API
          </button>
          <button @click="testarMarketplace(plataforma)" class="btn-secondary btn-sm" :disabled="processando">
            Testar rota
          </button>
          <button
            v-if="integracaoDe(plataforma.tipo) && integracaoDe(plataforma.tipo).status === 'ativo'"
            @click="sincronizar(plataforma.tipo, 'pedidos')"
            class="btn-secondary btn-sm"
            :disabled="processando"
          >
            <ShoppingBagIcon class="w-4 h-4" /> Pedidos
          </button>
          <button
            v-if="integracaoDe(plataforma.tipo) && integracaoDe(plataforma.tipo).status === 'ativo'"
            @click="sincronizar(plataforma.tipo, 'produtos')"
            class="btn-secondary btn-sm"
            :disabled="processando"
          >
            <CubeIcon class="w-4 h-4" /> Anúncios
          </button>
          <button v-if="integracaoDe(plataforma.tipo)" @click="desconectar(plataforma.tipo)" class="btn-ghost btn-sm text-danger" :disabled="processando">
            <XCircleIcon class="w-4 h-4" /> Desconectar
          </button>
        </div>
      </div>
    </div>

    <div v-else-if="abaAtiva === 'logistica'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div v-for="transportadora in transportadorasCatalogo" :key="transportadora.api_tipo" class="card p-5">
        <div class="flex items-start justify-between mb-4">
          <div>
            <h3 class="text-sm font-semibold">{{ transportadora.nome }}</h3>
            <span class="badge" :class="transportadoraSalva(transportadora.api_tipo) ? 'badge-success' : 'badge-gray'">
              {{ transportadoraSalva(transportadora.api_tipo) ? 'Configurada' : 'Não configurada' }}
            </span>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-3 mb-4">
          <input
            v-for="campo in transportadora.campos"
            :key="campo"
            v-model="credenciaisTransportadora[transportadora.api_tipo][campo]"
            class="input"
            :placeholder="labelCampo(campo)"
            :type="campoProtegido(campo) ? 'password' : 'text'"
          />
        </div>

        <div class="flex flex-wrap gap-2">
          <button @click="salvarTransportadora(transportadora)" class="btn-primary btn-sm" :disabled="processando">Salvar API</button>
          <button @click="testarTransportadora(transportadora)" class="btn-secondary btn-sm" :disabled="processando">Testar rota</button>
        </div>
      </div>
    </div>

    <div v-else-if="abaAtiva === 'certificados'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div class="card p-5">
        <h3 class="text-sm font-semibold mb-4">Upload de Certificado Digital</h3>
        <div class="space-y-3">
          <select v-model="certificado.tipo" class="select">
            <option value="A1">A1 (.pfx/.p12)</option>
            <option value="A3">A3</option>
          </select>
          <input type="file" accept=".pfx,.p12" @change="e => certificado.arquivo = e.target.files[0]" class="input" />
          <input v-model="certificado.senha" type="password" class="input" placeholder="Senha do certificado" />
          <input v-model="certificado.validade" type="date" class="input" />
          <input v-model="certificado.serie" class="input" placeholder="Série / observação" />
          <button @click="enviarCertificado" class="btn-primary btn-sm" :disabled="processando">Enviar certificado</button>
        </div>
      </div>

      <div class="card p-5">
        <h3 class="text-sm font-semibold mb-3">Certificados cadastrados</h3>
        <div v-if="!certificados.length" class="empty-state py-8">
          <p class="text-xs text-[var(--text-muted)]">Nenhum certificado cadastrado.</p>
        </div>
        <div v-for="item in certificados" :key="item.id" class="flex items-center justify-between border-b border-[var(--border-subtle)] py-3 last:border-0">
          <div>
            <p class="text-sm font-medium">{{ item.tipo }} — {{ item.serie || 'Sem série' }}</p>
            <p class="text-xs text-[var(--text-muted)]">Validade: {{ item.validade || 'não informada' }}</p>
          </div>
          <button @click="removerCertificado(item.id)" class="btn-ghost btn-sm text-danger">Remover</button>
        </div>
      </div>
    </div>

    <div v-else-if="abaAtiva === 'csv'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <div class="card p-5">
        <h3 class="text-sm font-semibold mb-4">Cadastro de CSV</h3>
        <div class="space-y-3">
          <select v-model="csv.tipo" class="select">
            <option value="clientes">Clientes</option>
            <option value="produtos">Produtos</option>
            <option value="estoque">Estoque</option>
            <option value="pedidos">Pedidos</option>
            <option value="fornecedores">Fornecedores</option>
            <option value="marketplace">Marketplace</option>
          </select>
          <input type="file" accept=".csv,.txt" @change="e => csv.arquivo = e.target.files[0]" class="input" />
          <button @click="enviarCsv" class="btn-primary btn-sm" :disabled="processando">Cadastrar CSV</button>
          <p class="text-xs text-[var(--text-muted)]">Nesta etapa o arquivo é salvo e contado. O processamento/mapeamento completo fica preparado para a próxima fase.</p>
        </div>
      </div>

      <div class="card p-5">
        <h3 class="text-sm font-semibold mb-3">CSVs cadastrados</h3>
        <div v-if="!csvs.length" class="empty-state py-8">
          <p class="text-xs text-[var(--text-muted)]">Nenhum CSV cadastrado.</p>
        </div>
        <div v-for="item in csvs" :key="item.id" class="border-b border-[var(--border-subtle)] py-3 last:border-0">
          <p class="text-sm font-medium">{{ item.nome_original }}</p>
          <p class="text-xs text-[var(--text-muted)]">Tipo: {{ item.tipo }} · Linhas: {{ item.total_linhas }} · Status: {{ item.status }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowPathIcon, ShoppingBagIcon, CubeIcon, XCircleIcon } from '@heroicons/vue/24/outline'

const toast = useToast()
const { dataHora } = useFormato()

const abas = [
  { id: 'marketplaces', label: 'Marketplaces' },
  { id: 'logistica', label: 'Logística / Transportes' },
  { id: 'certificados', label: 'Certificados Digitais' },
  { id: 'csv', label: 'CSV' },
]
const abaAtiva = ref('marketplaces')

const cores = {
  mercadolivre: '#FFE600',
  shopee: '#EE4D2D',
  magalu: '#0086FF',
  amazon: '#FF9900',
  olist: '#00A868',
  aliexpress: '#E52B20',
  americanas: '#E60014',
  casas_bahia: '#0033A0',
  carrefour: '#004E9F',
  enjoei: '#EC2D83',
  petlove: '#7B2CBF',
  tiktok_shop: '#111827',
}

const plataformasPadrao = [
  { tipo: 'mercadolivre', nome: 'Mercado Livre', cor: cores.mercadolivre, rota: 'mercadolivre', campos: ['seller_id', 'client_id', 'client_secret', 'access_token', 'refresh_token', 'marketplace_id'] },
  { tipo: 'shopee', nome: 'Shopee', cor: cores.shopee, rota: 'shopee', campos: ['partner_id', 'partner_key', 'shop_id', 'access_token', 'refresh_token'] },
]

const plataformas = ref([...plataformasPadrao])
const integracoes = ref([])
const transportadorasCatalogo = ref([])
const transportadoras = ref([])
const certificados = ref([])
const csvs = ref([])
const credenciaisMarketplace = ref(Object.fromEntries(plataformasPadrao.map(p => [p.tipo, {}])))
const credenciaisTransportadora = ref({})
const certificado = ref({ tipo: 'A1', arquivo: null, senha: '', validade: '', serie: '' })
const csv = ref({ tipo: 'clientes', arquivo: null })
const loading = ref(false)
const processando = ref(false)

const statusLabel = { ativo: 'Conectado', pendente: 'Pendente', inativo: 'Inativo', desconectado: 'Desconectado' }
const statusBadge = s => ({ ativo: 'badge-success', pendente: 'badge-warning', inativo: 'badge-gray', desconectado: 'badge-gray' }[s] || 'badge-gray')
const integracaoDe = tipo => integracoes.value.find(i => i.tipo === tipo)
const transportadoraSalva = tipo => transportadoras.value.find(t => t.api_tipo === tipo)
const campoProtegido = campo => ['secret', 'senha', 'token', 'key', 'password'].some(item => campo.toLowerCase().includes(item))

function labelCampo(campo) {
  const labels = {
    seller_id: 'Seller ID',
    client_id: 'Client ID',
    client_secret: 'Client Secret',
    access_token: 'Access Token',
    refresh_token: 'Refresh Token',
    marketplace_id: 'Marketplace ID',
    partner_id: 'Partner ID',
    partner_key: 'Partner Key',
    shop_id: 'Shop ID',
    app_key: 'App Key',
    app_secret: 'App Secret',
    api_key: 'API Key',
    api_token: 'API Token',
    usuario: 'Usuário',
    senha: 'Senha',
    contrato: 'Contrato',
    cnpj: 'CNPJ',
    codigo_cliente: 'Código do Cliente',
  }
  return labels[campo] || campo.replaceAll('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
}

function prepararObjetos() {
  plataformas.value.forEach(p => { credenciaisMarketplace.value[p.tipo] ||= {} })
  transportadorasCatalogo.value.forEach(t => { credenciaisTransportadora.value[t.api_tipo] ||= {} })
}

async function carregarTudo() {
  loading.value = true
  try {
    const [integracoesRes, marketplaceCatalogoRes, transportadorasRes, transportadorasCatalogoRes, certificadosRes, csvRes] = await Promise.all([
      api.get('/integracoes'),
      api.get('/marketplaces/catalogo'),
      api.get('/transportadoras'),
      api.get('/transportadoras/catalogo'),
      api.get('/certificados-digitais'),
      api.get('/csv-importacoes'),
    ])

    integracoes.value = integracoesRes.data

    const extras = marketplaceCatalogoRes.data.data
      .filter(p => !['mercadolivre', 'shopee'].includes(p.tipo))
      .map(p => ({ ...p, cor: cores[p.tipo] || '#6366f1', rota: p.tipo }))

    plataformas.value = [...plataformasPadrao, ...extras]
    transportadoras.value = transportadorasRes.data.data
    transportadorasCatalogo.value = transportadorasCatalogoRes.data.data
    certificados.value = certificadosRes.data.data
    csvs.value = csvRes.data.data
    prepararObjetos()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível carregar as integrações.')
  } finally {
    loading.value = false
  }
}

async function salvarMarketplace(plataforma) {
  processando.value = true
  try {
    const { data } = await api.post(`/marketplaces/${plataforma.tipo}/configurar`, {
      nome: plataforma.nome,
      status: 'pendente',
      credenciais: credenciaisMarketplace.value[plataforma.tipo],
      config: {},
    })
    toast.success(data.message)
    await carregarTudo()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível salvar o marketplace.')
  } finally {
    processando.value = false
  }
}

async function testarMarketplace(plataforma) {
  try {
    const { data } = await api.get(`/marketplaces/${plataforma.tipo}/testar`)
    toast[data.ok ? 'success' : 'warning'](data.message)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha no teste da rota.')
  }
}

async function sincronizar(tipo, recurso) {
  processando.value = true
  const integracao = integracaoDe(tipo)
  const plataforma = plataformas.value.find(p => p.tipo === tipo)
  try {
    const { data } = await api.post(`/marketplace/${plataforma.rota}/${integracao.id}/sincronizar-${recurso}`)
    toast.success(data.message)
    await carregarTudo()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível sincronizar.')
  } finally {
    processando.value = false
  }
}

async function desconectar(tipo) {
  const integracao = integracaoDe(tipo)
  if (!integracao) return
  processando.value = true
  try {
    const { data } = await api.delete(`/integracoes/${integracao.id}`)
    toast.success(data.message)
    await carregarTudo()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível desconectar.')
  } finally {
    processando.value = false
  }
}

async function salvarTransportadora(transportadora) {
  processando.value = true
  try {
    const { data } = await api.post(`/transportadoras/${transportadora.api_tipo}/configurar`, credenciaisTransportadora.value[transportadora.api_tipo])
    toast.success(data.message)
    await carregarTudo()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível salvar a transportadora.')
  } finally {
    processando.value = false
  }
}

async function testarTransportadora(transportadora) {
  try {
    const { data } = await api.get(`/transportadoras/${transportadora.api_tipo}/testar`)
    toast[data.ok ? 'success' : 'warning'](data.message)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha no teste da rota.')
  }
}

async function enviarCertificado() {
  if (!certificado.value.arquivo && certificado.value.tipo === 'A1') return toast.warning('Selecione o arquivo .pfx ou .p12.')
  processando.value = true
  try {
    const form = new FormData()
    Object.entries(certificado.value).forEach(([k, v]) => { if (v) form.append(k, v) })
    const { data } = await api.post('/certificados-digitais', form, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success(data.message)
    certificado.value = { tipo: 'A1', arquivo: null, senha: '', validade: '', serie: '' }
    await carregarTudo()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível enviar o certificado.')
  } finally {
    processando.value = false
  }
}

async function removerCertificado(id) {
  processando.value = true
  try {
    const { data } = await api.delete(`/certificados-digitais/${id}`)
    toast.success(data.message)
    await carregarTudo()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível remover o certificado.')
  } finally {
    processando.value = false
  }
}

async function enviarCsv() {
  if (!csv.value.arquivo) return toast.warning('Selecione um arquivo CSV.')
  processando.value = true
  try {
    const form = new FormData()
    form.append('tipo', csv.value.tipo)
    form.append('arquivo', csv.value.arquivo)
    const { data } = await api.post('/csv-importacoes', form, { headers: { 'Content-Type': 'multipart/form-data' } })
    toast.success(data.message)
    csv.value = { tipo: 'clientes', arquivo: null }
    await carregarTudo()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível cadastrar o CSV.')
  } finally {
    processando.value = false
  }
}

onMounted(carregarTudo)
</script>
