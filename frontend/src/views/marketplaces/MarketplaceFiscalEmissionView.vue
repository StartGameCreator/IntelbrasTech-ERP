<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Emissão Fiscal Omnichannel</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">
          Valide pedidos, gere o XML e acompanhe a fila de assinatura.
        </p>
      </div>
      <RouterLink to="/marketplaces/financeiro-fiscal" class="btn-secondary">
        Financeiro e Fiscal
      </RouterLink>
    </div>

    <div class="summary-grid">
      <Summary label="XML gerado" :value="resumo.rascunhos"/>
      <Summary label="Aguardando assinatura" :value="resumo.aguardando_assinatura"/>
      <Summary label="Autorizadas" :value="resumo.autorizadas"/>
      <Summary label="Erros" :value="resumo.erros"/>
    </div>

    <div class="card p-5">
      <h2 class="text-sm font-semibold">Nova emissão</h2>

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
        <div class="flex gap-2">
          <button class="btn-secondary flex-1" :disabled="!form.order_id" @click="validar">
            Validar
          </button>
          <button class="btn-primary flex-1" :disabled="!validacao?.valido" @click="gerar">
            Gerar XML
          </button>
        </div>
      </div>

      <div v-if="validacao" class="mt-4">
        <div v-if="validacao.valido" class="success-box">
          Pedido pronto para geração do XML.
        </div>

        <div v-if="validacao.erros?.length" class="error-box">
          <strong>Pendências</strong>
          <p v-for="erro in validacao.erros" :key="erro">• {{ erro }}</p>
        </div>

        <div v-if="validacao.avisos?.length" class="warning-box">
          <strong>Avisos</strong>
          <p v-for="aviso in validacao.avisos" :key="aviso">• {{ aviso }}</p>
        </div>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div class="section-title">
        <div>
          <h2>Emissões fiscais</h2>
          <p>XMLs gerados e fila de assinatura/transmissão.</p>
        </div>
        <button class="btn-secondary btn-sm" @click="carregar">Atualizar</button>
      </div>

      <div v-if="!items.length" class="empty">
        Nenhuma emissão criada.
      </div>

      <div v-else class="overflow-auto">
        <table class="table">
          <thead>
            <tr>
              <th>Pedido</th>
              <th>Documento</th>
              <th>Série/Número</th>
              <th>Ambiente</th>
              <th>Status</th>
              <th>Mensagem</th>
              <th class="text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td>#{{ item.marketplace_order_id }}</td>
              <td>{{ item.documento_tipo.toUpperCase() }}</td>
              <td>{{ item.serie }}/{{ item.numero || '—' }}</td>
              <td>{{ item.ambiente }}</td>
              <td>
                <span :class="['badge', badge(item.status)]">
                  {{ item.status }}
                </span>
              </td>
              <td class="max-w-xs text-xs">{{ item.mensagem_status || '—' }}</td>
              <td class="text-right">
                <div class="inline-flex gap-2">
                  <a
                    v-if="item.xml_rascunho"
                    :href="xmlUrl(item.id)"
                    target="_blank"
                    class="btn-secondary btn-sm"
                  >
                    Baixar XML
                  </a>
                  <button
                    v-if="item.status === 'xml_gerado'"
                    class="btn-primary btn-sm"
                    @click="enfileirar(item.id)"
                  >
                    Enfileirar
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card p-5">
      <h2 class="text-sm font-semibold">Status desta fase</h2>
      <div class="mt-3 notice">
        Esta etapa gera o XML de rascunho e organiza a fila. A assinatura com certificado,
        transmissão à SEFAZ, protocolo e DANFE serão ativados quando as credenciais fiscais
        e o certificado válido estiverem configurados.
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
const items = ref([])
const resumo = ref({
  rascunhos: 0,
  aguardando_assinatura: 0,
  autorizadas: 0,
  erros: 0,
})
const validacao = ref(null)
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

async function carregar() {
  const { data } = await api.get('/marketplace/emissao-fiscal')
  items.value = data.items || []
  resumo.value = data.resumo
}

async function validar() {
  validacao.value = null

  try {
    const { data } = await api.get(
      `/marketplace/emissao-fiscal/pedidos/${form.value.order_id}/validar`
    )
    validacao.value = data
  } catch (error) {
    toast.error(error.response?.data?.message || 'Falha na validação.')
  }
}

async function gerar() {
  try {
    const { data } = await api.post(
      `/marketplace/emissao-fiscal/pedidos/${form.value.order_id}/gerar`,
      {
        documento_tipo: form.value.documento_tipo,
        ambiente: form.value.ambiente,
        serie: form.value.serie,
      }
    )

    toast.success(data.message)
    await carregar()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Falha ao gerar XML.')
  }
}

async function enfileirar(id) {
  try {
    const { data } = await api.post(`/marketplace/emissao-fiscal/${id}/enfileirar`)
    toast.success(data.message)
    await carregar()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Falha ao enfileirar.')
  }
}

function xmlUrl(id) {
  const base = String(api.defaults.baseURL || '').replace(/\/$/, '')
  return `${base}/marketplace/emissao-fiscal/${id}/xml`
}

function badge(status) {
  if (status === 'autorizado') return 'badge-success'
  if (status === 'erro') return 'badge-danger'
  return 'badge-warning'
}

onMounted(carregar)
</script>

<style scoped>
.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.summary-card{border:1px solid var(--border);border-radius:10px;background:var(--bg-card);padding:14px}
.summary-card span{display:block;font-size:.65rem;color:var(--text-muted)}
.summary-card strong{display:block;margin-top:5px;font-size:1.5rem;color:var(--text-primary)}
.section-title{display:flex;align-items:center;justify-content:space-between;padding:15px;border-bottom:1px solid var(--border)}
.section-title h2{font-size:.82rem;font-weight:800}
.section-title p{margin-top:2px;font-size:.66rem;color:var(--text-muted)}
.empty{padding:36px;text-align:center;font-size:.72rem;color:var(--text-muted)}
.success-box,.warning-box,.error-box,.notice{margin-top:10px;border-radius:9px;padding:12px;font-size:.72rem}
.success-box{border:1px solid rgba(34,197,94,.35);background:rgba(34,197,94,.08);color:#4ade80}
.warning-box{border:1px solid rgba(245,158,11,.35);background:rgba(245,158,11,.08)}
.error-box{border:1px solid rgba(239,68,68,.35);background:rgba(239,68,68,.08);color:#f87171}
.notice{border:1px solid rgba(59,130,246,.35);background:rgba(59,130,246,.08);color:var(--text-secondary)}
@media(max-width:900px){.summary-grid{grid-template-columns:repeat(2,1fr)}}
</style>
