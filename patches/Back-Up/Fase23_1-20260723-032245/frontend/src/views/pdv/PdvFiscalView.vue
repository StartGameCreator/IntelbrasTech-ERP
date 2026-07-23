<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">NFC-e do PDV</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">
          Fluxo integrado ao módulo Fiscal, certificado, CSC e SEFAZ.
        </p>
      </div>
      <RouterLink to="/pdv/vendas" class="btn-secondary">Voltar</RouterLink>
    </div>

    <div class="card p-5 space-y-4">
      <div class="grid gap-3 md:grid-cols-2">
        <div>
          <label class="label">Venda do PDV</label>
          <select v-model="vendaId" class="select" :disabled="carregandoVendas">
            <option value="">Selecione uma venda</option>
            <option v-for="v in vendas" :key="v.id" :value="String(v.id)">
              #{{ v.numero }} · {{ cliente(v) }} · {{ moeda(v.total) }} · {{ labelStatus(v.nfce_status) }}
            </option>
          </select>
        </div>

        <div class="config-box">
          <span>Configuração automática</span>
          <strong v-if="validando">Validando venda...</strong>
          <strong v-else>{{ validacao?.configuracao?.ambiente_nome || 'Selecione uma venda' }}</strong>
          <small v-if="validacao?.configuracao">
            Série {{ validacao.configuracao.serie || '—' }} · UF {{ validacao.configuracao.uf || '—' }}
          </small>
          <small v-else>Ambiente, série, CSC e certificado serão verificados automaticamente.</small>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <button class="btn-secondary" :disabled="!vendaId || validando || processando" @click="validar">
          {{ validando ? 'Validando...' : 'Validar novamente' }}
        </button>

        <button class="btn-secondary" :disabled="!validacao?.valido || processando || validando" @click="preparar">
          Preparar no Fiscal
        </button>

        <button class="btn-primary" :disabled="!validacao?.valido || processando || validando" @click="emitir">
          {{ processando ? 'Processando...' : 'Emitir na SEFAZ' }}
        </button>

        <RouterLink v-if="documento?.status === 'autorizada'" :to="`/pdv/devolucoes?venda=${vendaId}`" class="btn-secondary">Gerar devolução</RouterLink>

        <button v-if="documento" class="btn-secondary" @click="baixarDanfe">
          Baixar DANFE
        </button>

        <button
          v-if="documento?.status === 'autorizada'"
          class="btn-danger"
          :disabled="processando"
          @click="cancelarNota"
        >
          Cancelar nota fiscal
        </button>

        <button
          v-if="vendaSelecionada && vendaSelecionada.status !== 'cancelada'"
          class="btn-danger"
          :disabled="processando"
          @click="cancelarVenda"
        >
          Cancelar venda
        </button>
      </div>
    </div>

    <div v-if="erroCarregamento" class="card p-5 error-box">
      <strong>Falha ao validar a venda</strong>
      <p class="mt-2 text-xs whitespace-pre-wrap">{{ erroCarregamento }}</p>
      <button class="btn-secondary mt-3" @click="validar">Tentar novamente</button>
    </div>

    <div v-if="validacao" class="card p-5">
      <div v-if="validacao.valido" class="ok">
        Venda apta para o fluxo fiscal da NFC-e.
      </div>

      <div v-if="validacao.erros?.length" class="mt-3">
        <h3 class="text-xs font-semibold text-danger">Pendências</h3>
        <p v-for="erro in validacao.erros" :key="erro" class="mt-1 text-xs">• {{ erro }}</p>
      </div>

      <div v-if="validacao.avisos?.length" class="mt-3">
        <h3 class="text-xs font-semibold text-warning">Avisos</h3>
        <p v-for="aviso in validacao.avisos" :key="aviso" class="mt-1 text-xs">• {{ aviso }}</p>
      </div>
    </div>

    <div v-if="documento" class="card p-5">
      <h2 class="text-sm font-semibold">Documento fiscal vinculado à venda</h2>

      <div class="mt-3 grid gap-3 md:grid-cols-4 text-xs">
        <div>Status: <strong>{{ labelStatus(documento.status) }}</strong></div>
        <div>Ambiente: <strong>{{ documento.ambiente_nome || documento.ambiente }}</strong></div>
        <div>Série: <strong>{{ documento.serie }}</strong></div>
        <div>Número: <strong>{{ documento.numero }}</strong></div>
      </div>

      <div v-if="documento.chave_acesso" class="mt-3 text-xs">
        Chave: <strong class="break-all">{{ documento.chave_acesso }}</strong>
      </div>

      <div v-if="documento.protocolo" class="mt-2 text-xs">
        Protocolo: <strong>{{ documento.protocolo }}</strong>
      </div>

      <div v-if="documento.informacoes_fisco" class="mt-3 error-box">
        {{ documento.informacoes_fisco }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast = useToast()
const route = useRoute()
const router = useRouter()

const vendaId = ref(route.query.venda ? String(route.query.venda) : '')
const validacao = ref(null)
const documento = ref(null)
const vendas = ref([])
const processando = ref(false)
const validando = ref(false)
const carregandoVendas = ref(false)
const erroCarregamento = ref('')
const vendaSelecionada = ref(null)

const moeda = value =>
  new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(Number(value || 0))

const cliente = venda =>
  venda.cliente?.nome_fantasia ||
  venda.cliente?.razao_social ||
  venda.cliente?.nome ||
  'Consumidor final'

const labelStatus = value =>
  ({
    nao_emitida: 'Não emitida',
    preparada: 'Preparada',
    processando: 'Processando',
    rascunho: 'Preparada',
    autorizada: 'Autorizada',
    emitida: 'Autorizada',
    rejeitada: 'Rejeitada',
    cancelada: 'Cancelada',
  }[value] || value || '—')

function mensagemErro(error) {
  const response = error?.response?.data

  if (response?.message) {
    return response.message
  }

  if (response?.errors) {
    return Object.values(response.errors).flat().join('\n')
  }

  if (error?.message) {
    return error.message
  }

  return 'Não foi possível concluir a validação.'
}

async function carregarVendas() {
  carregandoVendas.value = true
  erroCarregamento.value = ''

  try {
    const { data } = await api.get('/pdv/vendas', {
      params: { per_page: 100 },
    })

    vendas.value = data.data || data || []
    vendaSelecionada.value = vendas.value.find(v => String(v.id) === String(vendaId.value)) || null

    if (!vendaId.value && vendas.value.length) {
      vendaId.value = String(vendas.value[0].id)
    }
  } catch (error) {
    erroCarregamento.value = mensagemErro(error)
    toast.error('Falha ao carregar as vendas do PDV.')
  } finally {
    carregandoVendas.value = false
  }
}

async function validar() {
  if (!vendaId.value || validando.value) return

  validando.value = true
  erroCarregamento.value = ''
  validacao.value = null

  try {
    const validationResponse = await api.get(`/pdv/nfce/${vendaId.value}/validar`)
    validacao.value = validationResponse.data

    try {
      const documentResponse = await api.get(`/pdv/nfce/${vendaId.value}/documento`)
      documento.value = documentResponse.data?.data || null
    } catch {
      documento.value = null
    }
  } catch (error) {
    erroCarregamento.value = mensagemErro(error)
    toast.error(erroCarregamento.value)
  } finally {
    validando.value = false
  }
}

async function preparar() {
  processando.value = true
  erroCarregamento.value = ''

  try {
    const { data } = await api.post(`/pdv/nfce/${vendaId.value}/gerar`)
    documento.value = data.documento
    toast.success(data.message)
    await validar()
  } catch (error) {
    validacao.value = error.response?.data?.validacoes || validacao.value
    erroCarregamento.value = mensagemErro(error)
    toast.error(erroCarregamento.value)
  } finally {
    processando.value = false
  }
}

async function emitir() {
  if (!confirm('Confirmar transmissão da NFC-e para a SEFAZ no ambiente configurado?')) return

  processando.value = true
  erroCarregamento.value = ''

  try {
    const { data } = await api.post(`/pdv/nfce/${vendaId.value}/emitir`)
    documento.value = data.documento
    toast.success(data.message)
    await carregarVendas()
    await validar()
  } catch (error) {
    validacao.value = error.response?.data?.validacoes || validacao.value
    erroCarregamento.value = mensagemErro(error)
    toast.error(erroCarregamento.value)
  } finally {
    processando.value = false
  }
}

async function cancelarNota() {
  if (!vendaId.value || documento.value?.status !== 'autorizada') return

  const justificativa = window.prompt(
    'Informe a justificativa do cancelamento fiscal (mínimo de 15 caracteres):',
  )

  if (!justificativa) return
  if (justificativa.trim().length < 15) {
    toast.error('A justificativa deve ter pelo menos 15 caracteres.')
    return
  }

  if (!window.confirm('Transmitir o cancelamento deste documento fiscal para a SEFAZ?')) return

  processando.value = true
  try {
    const { data } = await api.post(`/pdv/nfce/${vendaId.value}/cancelar`, {
      justificativa: justificativa.trim(),
    })
    toast.success(data.message)
    await carregarVendas()
    await validar()
  } catch (error) {
    toast.error(mensagemErro(error))
  } finally {
    processando.value = false
  }
}

async function cancelarVenda() {
  if (!vendaId.value || vendaSelecionada.value?.status === 'cancelada') return

  const motivo = window.prompt(
    'Informe o motivo do cancelamento da venda (mínimo de 10 caracteres):',
  )

  if (!motivo) return
  if (motivo.trim().length < 10) {
    toast.error('O motivo deve ter pelo menos 10 caracteres.')
    return
  }

  if (!window.confirm('Cancelar a venda e estornar estoque e caixa?')) return

  processando.value = true
  try {
    const { data } = await api.post(`/pdv/vendas/${vendaId.value}/cancelar`, {
      motivo: motivo.trim(),
    })
    toast.success(data.message)
    await carregarVendas()
    await validar()
  } catch (error) {
    toast.error(mensagemErro(error))
  } finally {
    processando.value = false
  }
}

async function baixarDanfe() {
  if (!vendaId.value) {
    toast.error('Selecione uma venda antes de baixar o DANFE.')
    return
  }

  try {
    const response = await api.get(
      `/pdv/nfce/${vendaId.value}/pdf`,
      {
        responseType: 'blob',
        headers: {
          Accept: 'application/pdf',
        },
      },
    )

    const contentType =
      response.headers?.['content-type'] ||
      'application/pdf'

    const blob = new Blob(
      [response.data],
      { type: contentType },
    )

    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    const disposition =
      response.headers?.['content-disposition'] || ''

    const utf8Name =
      disposition.match(/filename\*=UTF-8''([^;]+)/i)

    const simpleName =
      disposition.match(/filename="?([^";]+)"?/i)

    link.href = url
    link.download = utf8Name
      ? decodeURIComponent(utf8Name[1])
      : simpleName
        ? simpleName[1]
        : `NFCe-${documento.value?.numero || vendaId.value}.pdf`

    document.body.appendChild(link)
    link.click()
    link.remove()

    window.setTimeout(() => {
      URL.revokeObjectURL(url)
    }, 1000)

    toast.success('DANFE baixado com sucesso.')
  } catch (error) {
    let mensagem = 'Não foi possível baixar o DANFE.'

    const dados = error?.response?.data

    if (dados instanceof Blob) {
      try {
        const texto = await dados.text()
        const json = JSON.parse(texto)
        mensagem = json?.message || mensagem
      } catch {
        // Mantém a mensagem padrão.
      }
    } else {
      mensagem =
        dados?.message ||
        error?.message ||
        mensagem
    }

    toast.error(mensagem)
  }
}

watch(vendaId, async newValue => {
  validacao.value = null
  documento.value = null
  erroCarregamento.value = ''

  if (!newValue) { vendaSelecionada.value = null; return }

  vendaSelecionada.value = vendas.value.find(v => String(v.id) === String(newValue)) || null

  await router.replace({
    path: route.path,
    query: { ...route.query, venda: newValue },
  })

  await validar()
})

onMounted(async () => {
  await carregarVendas()

  if (vendaId.value) {
    await validar()
  }
})
</script>

<style scoped>
.ok {
  padding: 12px;
  border: 1px solid rgba(34, 197, 94, .35);
  border-radius: 9px;
  background: rgba(34, 197, 94, .08);
  font-size: .75rem;
  color: #4ade80;
}

.config-box {
  display: flex;
  flex-direction: column;
  justify-content: center;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: var(--bg-input);
  padding: 10px;
}

.config-box span,
.config-box small {
  font-size: .56rem;
  color: var(--text-muted);
}

.config-box strong {
  font-size: .72rem;
  margin: 2px 0;
}

.error-box {
  border: 1px solid rgba(239, 68, 68, .35);
  border-radius: 8px;
  background: rgba(239, 68, 68, .08);
  padding: 10px;
  color: #f87171;
}
</style>
