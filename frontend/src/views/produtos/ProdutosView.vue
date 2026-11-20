<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Produtos</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">{{ total }} registros</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="carregar" class="btn-secondary btn-sm" :disabled="loading">
          <ArrowPathIcon class="w-4 h-4" :class="loading && 'animate-spin'" />
        </button>
        <button class="btn-secondary btn-sm" @click="abrirImportacao">
          <ArrowUpTrayIcon class="w-4 h-4" />
          Importar tabela Intelbras
        </button>
        <router-link to="/produtos/novo" class="btn-primary btn-sm">
          <PlusIcon class="w-4 h-4" /> Novo Produto
        </router-link>
      </div>
    </div>

    <div class="card p-4">
      <div class="flex flex-wrap gap-3">
        <div class="flex-1 min-w-[220px] relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--text-muted)]" />
          <input v-model="busca" type="text" placeholder="Buscar por nome, código, SKU ou código de barras..." class="input pl-9" @input="debounceBusca" />
        </div>
        <select v-model="filtroTipo" class="input w-44" @change="carregar">
          <option value="">Todos os tipos</option>
          <option value="simples">Simples</option>
          <option value="composto">Composto</option>
          <option value="kit">Kit</option>
          <option value="servico">Serviço</option>
          <option value="digital">Digital</option>
        </select>
        <label class="flex items-center gap-2 text-xs text-[var(--text-muted)] px-2">
          <input type="checkbox" v-model="filtroEstoqueBaixo" @change="carregar" /> Estoque baixo
        </label>
      </div>
    </div>

    <div class="table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Produto</th><th>Categoria</th><th>SKU/Código</th>
            <th class="text-right">Custo de compra</th><th class="text-right">Preço de venda</th><th class="text-right">Markup</th>
            <th>Status</th><th class="text-right">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="8"><div class="skeleton h-8 rounded m-2" /></td></tr>
          <tr v-else-if="!items.length"><td colspan="8">
            <div class="empty-state"><p class="text-sm text-[var(--text-muted)]">Nenhum produto encontrado</p></div>
          </td></tr>
          <tr v-else v-for="item in items" :key="item.id" class="cursor-pointer" @click="verItem(item.id)">
            <td class="text-sm font-medium text-[var(--text-primary)]">{{ item.nome }}</td>
            <td class="text-xs text-[var(--text-muted)]">{{ item.categoria?.nome || '—' }}</td>
            <td class="text-xs">{{ item.sku || item.codigo || '—' }}</td>
            <td class="text-sm text-right font-medium">{{ moeda(item.preco_custo) }}</td>
            <td class="text-sm text-right font-medium">{{ moeda(item.preco_venda) }}</td>
            <td class="text-xs text-right text-[var(--text-muted)]">{{ item.modo_preco_venda === 'markup' ? percentual(item.markup_venda) : 'Manual' }}</td>
            <td><span class="badge" :class="item.ativo ? 'badge-success' : 'badge-gray'">{{ item.ativo ? 'Ativo' : 'Inativo' }}</span></td>
            <td class="text-right" @click.stop>
              <router-link :to="`/produtos/${item.id}/editar`" class="btn-ghost btn-icon"><PencilIcon class="w-4 h-4" /></router-link>
              <button @click="verItem(item.id)" class="btn-ghost btn-icon"><EyeIcon class="w-4 h-4" /></button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div ref="listSentinel" class="product-load-more">
      <button v-if="pagina < ultimaPagina" class="btn-secondary btn-sm" :disabled="loadingMore" @click="carregarMais">
        {{ loadingMore ? 'Carregando...' : 'Carregar mais produtos' }}
      </button>
      <span v-else-if="items.length">Todos os {{ total }} produtos foram carregados.</span>
    </div>

    <div v-if="modalImportacao" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div class="card w-full max-w-2xl overflow-hidden">
        <div class="flex items-center justify-between border-b border-[var(--border)] px-5 py-4">
          <div>
            <h2 class="text-sm font-semibold text-[var(--text-primary)]">Importar tabela de preços Intelbras</h2>
            <p class="mt-1 text-xs text-[var(--text-muted)]">
              O catálogo e os preços por UF serão atualizados sem duplicar produtos.
            </p>
          </div>
          <button class="btn-ghost btn-icon" :disabled="importando" @click="fecharImportacao">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-4 p-5">
          <label class="import-drop">
            <input
              class="hidden"
              type="file"
              accept=".xlsb,.xlsx,.xls"
              :disabled="importando"
              @change="selecionarArquivo"
            />
            <ArrowUpTrayIcon class="h-8 w-8 text-[var(--primary)]" />
            <strong>{{ arquivo?.name || 'Selecionar tabela Intelbras' }}</strong>
            <span>Formatos aceitos: XLSB, XLSX e XLS</span>
          </label>

          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="label">UF preferencial para o preço principal</label>
              <select v-model="ufPreferencial" class="input">
                <option v-for="uf in ufs" :key="uf" :value="uf">{{ uf }}</option>
              </select>
            </div>
            <div>
              <label class="label">Planilha identificada</label>
              <div class="input flex items-center">{{ resumo.aba || 'Aguardando arquivo' }}</div>
            </div>
          </div>

          <div v-if="resumo.produtos" class="grid grid-cols-3 gap-3">
            <div class="import-kpi">
              <strong>{{ numero(resumo.produtos) }}</strong>
              <span>Produtos</span>
            </div>
            <div class="import-kpi">
              <strong>{{ numero(resumo.precos) }}</strong>
              <span>Preços por UF</span>
            </div>
            <div class="import-kpi">
              <strong>{{ numero(resumo.familias) }}</strong>
              <span>Famílias</span>
            </div>
          </div>

          <div v-if="importando || progresso > 0" class="space-y-2">
            <div class="flex justify-between text-xs text-[var(--text-muted)]">
              <span>{{ etapa }}</span>
              <span>{{ progresso }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-[var(--bg-input)]">
              <div class="h-full rounded-full bg-[var(--primary)] transition-all" :style="{ width: progresso + '%' }" />
            </div>
          </div>

          <div v-if="resultado" class="rounded-lg border border-success/30 bg-success/10 p-3 text-xs">
            <strong class="text-success">Importação concluída</strong>
            <p class="mt-1 text-[var(--text-muted)]">
              {{ resultado.produtos_criados }} novos, {{ resultado.produtos_atualizados }} atualizados e
              {{ resultado.precos_gravados }} preços gravados.
            </p>
          </div>

          <div class="rounded-lg border border-[var(--border)] bg-[var(--bg-input)]/40 p-3 text-xs text-[var(--text-muted)]">
            O arquivo de preços não contém saldo físico. Os produtos serão criados com estoque zerado e
            prontos para receber entradas, compras, inventários e ajustes no módulo Estoque.
          </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-[var(--border)] px-5 py-4">
          <button class="btn-secondary" :disabled="importando" @click="fecharImportacao">Fechar</button>
          <button class="btn-primary" :disabled="!arquivo || importando || !resumo.produtos" @click="importar">
            {{ importando ? 'Importando...' : 'Iniciar importação' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onBeforeUnmount, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import * as XLSX from 'xlsx'
import {
  ArrowPathIcon,
  MagnifyingGlassIcon,
  EyeIcon,
  PencilIcon,
  PlusIcon,
  ArrowUpTrayIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const toast = useToast()
const { moeda, percentual } = useFormato()

const items = ref([])
const total = ref(0)
const loading = ref(false)
const loadingMore = ref(false)
const pagina = ref(1)
const ultimaPagina = ref(1)
const listSentinel = ref(null)
const busca = ref('')
const filtroTipo = ref('')
const filtroEstoqueBaixo = ref(false)
const modalImportacao = ref(false)
const arquivo = ref(null)
const produtosImportacao = ref([])
const precosImportacao = ref([])
const resumo = ref({})
const ufPreferencial = ref('GO')
const importando = ref(false)
const progresso = ref(0)
const etapa = ref('')
const resultado = ref(null)
let timer = null

const ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO']

async function carregar(append = false) {
  if (append ? loadingMore.value : loading.value) return
  if (append) loadingMore.value = true
  else { loading.value = true; pagina.value = 1 }
  try {
    const { data } = await api.get('/produtos', {
      params: {
        busca: busca.value,
        tipo: filtroTipo.value,
        estoque_baixo: filtroEstoqueBaixo.value ? 1 : undefined,
        per_page: 30,
        page: pagina.value,
      },
    })
    const recebidos = data.data ?? data
    items.value = append ? [...items.value, ...recebidos] : recebidos
    total.value = data.total ?? items.value.length
    ultimaPagina.value = data.last_page ?? 1
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

async function carregarMais() {
  if (pagina.value >= ultimaPagina.value || loadingMore.value) return
  pagina.value += 1
  try { await carregar(true) } catch (error) { pagina.value -= 1; throw error }
}

function debounceBusca() {
  clearTimeout(timer)
  timer = setTimeout(carregar, 400)
}

function verItem(id) {
  router.push('/produtos/' + id)
}

function numero(valor) {
  return new Intl.NumberFormat('pt-BR').format(valor || 0)
}

function abrirImportacao() {
  modalImportacao.value = true
}

function fecharImportacao() {
  if (importando.value) return
  modalImportacao.value = false
  arquivo.value = null
  produtosImportacao.value = []
  precosImportacao.value = []
  resumo.value = {}
  resultado.value = null
  progresso.value = 0
  etapa.value = ''
}

function normalizarCabecalho(valor) {
  return String(valor ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toLowerCase()
}

function boolSim(valor) {
  return ['sim', 's', 'yes', 'true', '1'].includes(
    normalizarCabecalho(valor)
  )
}

function numeroSeguro(valor) {
  if (valor === null || valor === undefined || valor === '') return null
  const numero = Number(valor)
  return Number.isFinite(numero) ? numero : null
}

function codigoProduto(valor) {
  if (valor === null || valor === undefined || valor === '') return ''
  return String(valor).replace(/\.0$/, '').trim()
}

function precoPrincipal(linha, mapa) {
  const tipo = String(linha[mapa['preco referencia']] ?? '').trim().toUpperCase()
  const campos = {
    PV: mapa.pv,
    CUSTO: mapa.custo,
    PP: mapa.pp,
    PSD: mapa.psd,
    PSCF: mapa.pscf,
  }

  const preferido = numeroSeguro(linha[campos[tipo]])
  if (preferido !== null) return preferido

  for (const coluna of [mapa.pscf, mapa.psd, mapa.pv, mapa.pp, mapa.custo]) {
    const valor = numeroSeguro(linha[coluna])
    if (valor !== null) return valor
  }

  return 0
}

async function selecionarArquivo(event) {
  const selecionado = event.target.files?.[0]
  if (!selecionado) return

  arquivo.value = selecionado
  resultado.value = null
  progresso.value = 3
  etapa.value = 'Lendo a planilha...'

  const buffer = await selecionado.arrayBuffer()
  const workbook = XLSX.read(buffer, { type: 'array', cellDates: true })
  const aba = workbook.SheetNames.includes('Tabela')
    ? 'Tabela'
    : workbook.SheetNames.find(nome => normalizarCabecalho(nome).includes('tabela'))

  if (!aba) {
    throw new Error('A aba Tabela não foi encontrada no arquivo.')
  }

  const linhas = XLSX.utils.sheet_to_json(workbook.Sheets[aba], {
    header: 1,
    defval: null,
    raw: true,
  })

  const indiceCabecalho = linhas.findIndex(linha => {
    const valores = linha.map(normalizarCabecalho)
    return valores.includes('codigo produto') && valores.includes('uf destino')
  })

  if (indiceCabecalho < 0) {
    throw new Error('O cabeçalho da tabela Intelbras não foi reconhecido.')
  }

  const cabecalhos = linhas[indiceCabecalho].map(normalizarCabecalho)
  const mapa = Object.fromEntries(cabecalhos.map((nome, indice) => [nome, indice]))
  const produtos = new Map()
  const precos = []
  const familias = new Set()

  for (const linha of linhas.slice(indiceCabecalho + 1)) {
    const codigo = codigoProduto(linha[mapa['codigo produto']])
    const nome = String(linha[mapa['descricao do produto']] ?? '').trim()
    const ufDestino = String(linha[mapa['uf destino']] ?? '').trim().toUpperCase()

    if (!codigo || !nome || ufDestino.length !== 2) continue

    const preco = {
      codigo,
      tabela: String(linha[mapa.tabela] ?? 'Distribuição').trim(),
      uf_origem: String(linha[mapa['uf origem']] ?? '').trim(),
      uf_destino: ufDestino,
      ipi: numeroSeguro(linha[mapa.ipi]) ?? 0,
      icms: numeroSeguro(linha[mapa.icms]) ?? 0,
      pv: numeroSeguro(linha[mapa.pv]),
      custo: numeroSeguro(linha[mapa.custo]),
      pp: numeroSeguro(linha[mapa.pp]),
      psd: numeroSeguro(linha[mapa.psd]),
      pscf: numeroSeguro(linha[mapa.pscf]),
      multiplo: numeroSeguro(linha[mapa['qtd. multipla']]) ?? 1,
      preco_referencia: String(linha[mapa['preco referencia']] ?? '').trim(),
    }

    precos.push(preco)

    const existente = produtos.get(codigo)
    const usarLinha = !existente || ufDestino === ufPreferencial.value

    if (usarLinha) {
      const familia = String(linha[mapa.familia] ?? '').trim()
      if (familia) familias.add(familia)

      produtos.set(codigo, {
        codigo,
        nome,
        descricao: nome,
        resumo: String(linha[mapa['produto/resumo']] ?? '').trim(),
        bu: String(linha[mapa.bu] ?? '').trim(),
        segmento: String(linha[mapa.segmento] ?? '').trim(),
        familia,
        multiplo: preco.multiplo,
        preco_referencia: preco.preco_referencia,
        // A tabela do fabricante informa custo de aquisição, não preço de revenda.
        preco_venda: 0,
        preco_custo: precoPrincipal(linha, mapa),
        ipi: preco.ipi,
        icms: preco.icms,
        bndes: boolSim(linha[mapa['disp. bndes']]),
        segmento_portfolio: String(linha[mapa['segm. portfolio']] ?? '').trim(),
        showroom: boolSim(linha[mapa.showroom]),
        item_consumo: boolSim(linha[mapa['item consumo']]),
        casa_inteligente: boolSim(linha[mapa['casa inteligente']]),
        observacoes: String(linha[mapa.observacoes] ?? '').trim(),
        _uf: ufDestino,
      })
    }
  }

  produtosImportacao.value = Array.from(produtos.values()).map(({ _uf, ...item }) => item)
  precosImportacao.value = precos
  resumo.value = {
    aba,
    produtos: produtosImportacao.value.length,
    precos: precosImportacao.value.length,
    familias: familias.size,
  }
  progresso.value = 8
  etapa.value = 'Arquivo pronto para importar.'
}

function lotes(lista, tamanho) {
  const saida = []
  for (let i = 0; i < lista.length; i += tamanho) {
    saida.push(lista.slice(i, i + tamanho))
  }
  return saida
}

async function importar() {
  importando.value = true
  resultado.value = null

  try {
    etapa.value = 'Criando a importação...'
    progresso.value = 10

    const { data: inicio } = await api.post('/produtos/importar-intelbras/iniciar', {
      arquivo: arquivo.value.name,
      uf_preferencial: ufPreferencial.value,
    })

    const uuid = inicio.importacao.uuid
    const lotesProdutos = lotes(produtosImportacao.value, 50)
    const lotesPrecos = lotes(precosImportacao.value, 300)
    let processados = 0
    const totalLotes = lotesProdutos.length + lotesPrecos.length

    for (const lote of lotesProdutos) {
      etapa.value = `Cadastrando produtos — lote ${processados + 1} de ${totalLotes}...`
      const { data: loteResultado } = await api.post(`/produtos/importar-intelbras/${uuid}/produtos`, {
        produtos: lote,
      })

      if (loteResultado.erros?.length) {
        console.warn('Itens rejeitados na importação:', loteResultado.erros)
      }

      processados++
      progresso.value = 10 + Math.round((processados / totalLotes) * 82)
    }

    for (const lote of lotesPrecos) {
      etapa.value = 'Gravando preços por Estado...'
      await api.post(`/produtos/importar-intelbras/${uuid}/precos`, {
        precos: lote,
      })
      processados++
      progresso.value = 10 + Math.round((processados / totalLotes) * 82)
    }

    etapa.value = 'Finalizando...'
    const { data: fim } = await api.post(
      `/produtos/importar-intelbras/${uuid}/finalizar`
    )

    resultado.value = fim.importacao
    progresso.value = 100
    etapa.value = 'Importação concluída.'
    await carregar()
  } catch (error) {
    const resposta = error.response?.data
    const detalhes = resposta?.errors
      ? Object.values(resposta.errors).flat().join(' | ')
      : resposta?.message

    etapa.value = `Importação interrompida: ${detalhes || error.message || 'erro desconhecido'}`
    toast.error(detalhes || resposta?.message || 'A importação foi interrompida.')
  } finally {
    importando.value = false
  }
}

let listObserver
onMounted(() => {
  carregar()
  listObserver = new IntersectionObserver(entries => {
    if (entries[0]?.isIntersecting) carregarMais()
  }, { rootMargin: '240px' })
  if (listSentinel.value) listObserver.observe(listSentinel.value)
})
onBeforeUnmount(() => listObserver?.disconnect())

function normalizarTextoImportacao(valor, limite = 50000) {
  if (valor === null || valor === undefined) return ''
  return String(valor).replace(/\u0000/g, '').trim().slice(0, limite)
}
</script>

<style scoped>
.import-drop {
  display: flex;
  min-height: 145px;
  cursor: pointer;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 7px;
  border: 1px dashed var(--border);
  border-radius: 12px;
  background: color-mix(in srgb, var(--bg-input) 75%, transparent);
  color: var(--text-muted);
  transition: border-color .15s ease, background .15s ease;
}

.import-drop:hover {
  border-color: var(--primary);
  background: color-mix(in srgb, var(--bg-input) 84%, var(--primary) 16%);
}

.import-drop strong {
  color: var(--text-primary);
  font-size: .86rem;
}

.import-drop span {
  font-size: .7rem;
}

.import-kpi {
  display: flex;
  min-height: 72px;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--bg-input);
}

.import-kpi strong {
  color: var(--text-primary);
  font-size: 1.05rem;
}
.product-load-more{min-height:54px;display:flex;align-items:center;justify-content:center;padding:8px;color:var(--text-muted);font-size:.7rem}.product-load-more button{min-height:40px}

.import-kpi span {
  margin-top: 3px;
  color: var(--text-muted);
  font-size: .68rem;
  text-transform: uppercase;
}
</style>
