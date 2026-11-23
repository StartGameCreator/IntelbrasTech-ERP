<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Financeiro e Fiscal Omnichannel</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">
          Conciliação financeira e preparação fiscal dos pedidos de marketplace.
        </p>
      </div>
      <RouterLink to="/marketplaces/automacao" class="btn-secondary">
        Automação Omnichannel
      </RouterLink>
    </div>

    <div class="summary-grid">
      <Summary label="Financeiro pendente" :value="resumo.financeiro_pendente"/>
      <Summary label="Financeiro conciliado" :value="resumo.financeiro_conciliado"/>
      <Summary label="Fiscal pronto" :value="resumo.fiscal_pronto"/>
      <Summary label="Fiscal pendente" :value="resumo.fiscal_pendente"/>
    </div>

    <div class="card p-5">
      <h2 class="text-sm font-semibold">Preparar pedido</h2>

      <div class="mt-3 grid gap-3 md:grid-cols-5">
        <input v-model="form.order_id" class="input" placeholder="ID do pedido importado"/>
        <select v-model="form.documento_tipo" class="select">
          <option value="nfe">NF-e</option>
          <option value="nfce">NFC-e</option>
        </select>
        <select v-model="form.ambiente" class="select">
          <option value="homologacao">Homologação</option>
          <option value="producao">Produção</option>
        </select>
        <input v-model.number="form.serie" type="number" min="1" max="999" class="input"/>
        <button class="btn-primary" :disabled="!form.order_id" @click="preparar">
          Preparar
        </button>
      </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
      <section class="card overflow-hidden results-scroll-card">
        <div class="section-title">
          <div>
            <h2>Conciliação financeira</h2>
            <p>Valores brutos, taxas e líquido estimado.</p>
          </div>
        </div>

        <div v-if="!financeiro.length" class="empty">
          Nenhum lançamento preparado.
        </div>

        <div v-else class="results-scroll">
          <table class="table">
            <thead>
              <tr>
                <th>Pedido</th>
                <th>Status</th>
                <th class="text-right">Bruto</th>
                <th class="text-right">Taxas</th>
                <th class="text-right">Líquido</th>
                <th class="text-right">Ação</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in financeiro" :key="item.id">
                <td>#{{ item.marketplace_order_id }}</td>
                <td>
                  <span :class="['badge', item.status === 'conciliado' ? 'badge-success' : 'badge-warning']">
                    {{ item.status }}
                  </span>
                </td>
                <td class="text-right">{{ moeda(item.valor_bruto) }}</td>
                <td class="text-right">{{ moeda(item.taxas) }}</td>
                <td class="text-right font-semibold">{{ moeda(item.valor_liquido) }}</td>
                <td class="text-right">
                  <button
                    v-if="item.status !== 'conciliado'"
                    class="btn-secondary btn-sm"
                    @click="conciliar(item.id)"
                  >
                    Conciliar
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="card overflow-hidden results-scroll-card">
        <div class="section-title">
          <div>
            <h2>Fila fiscal</h2>
            <p>Pedidos prontos ou com pendências fiscais.</p>
          </div>
        </div>

        <div v-if="!fiscal.length" class="empty">
          Nenhum pedido na fila fiscal.
        </div>

        <div v-else class="results-scroll">
          <table class="table">
            <thead>
              <tr>
                <th>Pedido</th>
                <th>Documento</th>
                <th>Ambiente</th>
                <th>Status</th>
                <th>Validações</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in fiscal" :key="item.id">
                <td>#{{ item.marketplace_order_id }}</td>
                <td>{{ item.documento_tipo.toUpperCase() }}</td>
                <td>{{ item.ambiente }}</td>
                <td>
                  <span :class="['badge', item.status === 'pronto' ? 'badge-success' : 'badge-danger']">
                    {{ item.status }}
                  </span>
                </td>
                <td>
                  <button class="btn-ghost btn-sm" @click="abrirValidacoes(item)">
                    Ver detalhes
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <div v-if="modal" class="modal-backdrop">
      <div class="card modal-card">
        <div class="flex justify-between gap-4">
          <div>
            <h2 class="text-base font-bold">Validações fiscais</h2>
            <p class="text-xs text-[var(--text-muted)]">
              Pedido #{{ selecionado.marketplace_order_id }}
            </p>
          </div>
          <button class="text-xl" @click="modal=false">×</button>
        </div>

        <div v-if="validacoes.valido" class="success-box mt-4">
          Pedido fiscalmente pronto.
        </div>

        <div v-if="validacoes.erros?.length" class="mt-4">
          <h3 class="text-xs font-semibold text-danger">Pendências</h3>
          <p v-for="erro in validacoes.erros" :key="erro" class="mt-1 text-xs">• {{ erro }}</p>
        </div>

        <div v-if="validacoes.avisos?.length" class="mt-4">
          <h3 class="text-xs font-semibold text-warning">Avisos</h3>
          <p v-for="aviso in validacoes.avisos" :key="aviso" class="mt-1 text-xs">• {{ aviso }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, defineComponent, h, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'

const toast = useToast()
const resumo = ref({
  financeiro_pendente: 0,
  financeiro_conciliado: 0,
  fiscal_pronto: 0,
  fiscal_pendente: 0,
})
const financeiro = ref([])
const fiscal = ref([])
const modal = ref(false)
const selecionado = ref({})
const validacoes = ref({})
const form = ref({
  order_id: '',
  documento_tipo: 'nfe',
  ambiente: 'homologacao',
  serie: 1,
})

const Summary = defineComponent({
  props: { label: String, value: [String, Number] },
  setup(props) {
    return () => h('div', { class: 'summary-card' }, [
      h('span', props.label),
      h('strong', props.value ?? 0),
    ])
  },
})

const moeda = value => new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
}).format(Number(value || 0))

function parseJson(value) {
  if (!value) return {}
  if (typeof value === 'object') return value

  try {
    return JSON.parse(value)
  } catch {
    return {}
  }
}

async function carregar() {
  const { data } = await api.get('/marketplace/financeiro-fiscal/overview')
  resumo.value = data.resumo
  financeiro.value = data.financeiro || []
  fiscal.value = data.fiscal || []
}

async function preparar() {
  try {
    const { data } = await api.post(
      `/marketplace/financeiro-fiscal/pedidos/${form.value.order_id}/preparar`,
      {
        documento_tipo: form.value.documento_tipo,
        ambiente: form.value.ambiente,
        serie: form.value.serie,
      }
    )

    toast.success(data.message)
    await carregar()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Falha ao preparar o pedido.')
  }
}

async function conciliar(id) {
  try {
    const { data } = await api.post(`/marketplace/financeiro-fiscal/${id}/conciliar`)
    toast.success(data.message)
    await carregar()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Falha ao conciliar.')
  }
}

function abrirValidacoes(item) {
  selecionado.value = item
  validacoes.value = parseJson(item.validacoes)
  modal.value = true
}

onMounted(carregar)
</script>

<style scoped>
.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.summary-card{border:1px solid var(--border);border-radius:10px;background:var(--bg-card);padding:14px}
.summary-card span{display:block;font-size:.65rem;color:var(--text-muted)}
.summary-card strong{display:block;margin-top:5px;font-size:1.5rem;color:var(--text-primary)}
.section-title{padding:15px;border-bottom:1px solid var(--border)}
.section-title h2{font-size:.82rem;font-weight:800}
.section-title p{margin-top:2px;font-size:.66rem;color:var(--text-muted)}
.empty{padding:36px;text-align:center;font-size:.72rem;color:var(--text-muted)}
.modal-backdrop{position:fixed;inset:0;z-index:120;background:rgba(0,0,0,.72);display:flex;align-items:center;justify-content:center;padding:16px}
.modal-card{width:min(650px,100%);max-height:90vh;overflow:auto;padding:18px}
.success-box{border:1px solid rgba(34,197,94,.35);background:rgba(34,197,94,.08);border-radius:9px;padding:12px;font-size:.72rem;color:#4ade80}
@media(max-width:900px){.summary-grid{grid-template-columns:repeat(2,1fr)}}
</style>
