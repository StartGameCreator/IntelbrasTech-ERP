<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Pedidos Mercado Livre</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">
          Importe e acompanhe pedidos, pagamentos e entregas.
        </p>
      </div>

      <div class="flex gap-2">
        <RouterLink to="/marketplaces/mercado-livre" class="btn-secondary">
          Conta
        </RouterLink>
        <RouterLink to="/marketplaces/mercado-livre/catalogo" class="btn-secondary">
          Catálogo
        </RouterLink>
        <button class="btn-primary" :disabled="importando" @click="importar">
          {{ importando ? 'Importando...' : 'Importar pedidos' }}
        </button>
      </div>
    </div>

    <div class="card p-4 results-toolbar">
      <div class="grid gap-3 md:grid-cols-6">
        <input
          v-model="filtros.busca"
          class="input md:col-span-2"
          placeholder="Pedido, comprador ou documento"
        />
        <input v-model="filtros.data_inicio" type="date" class="input"/>
        <input v-model="filtros.data_fim" type="date" class="input"/>
        <select v-model="filtros.status" class="select">
          <option value="">Todos os status</option>
          <option value="paid">Pago</option>
          <option value="confirmed">Confirmado</option>
          <option value="cancelled">Cancelado</option>
        </select>
        <button class="btn-secondary" @click="carregar(1)">Pesquisar</button>
      </div>
    </div>

    <div class="card overflow-hidden results-scroll-card">
      <div v-if="loading" class="p-10 text-center text-sm text-[var(--text-muted)]">
        Carregando pedidos...
      </div>

      <div v-else-if="!pedidos.length" class="p-10 text-center text-sm text-[var(--text-muted)]">
        Nenhum pedido importado.
      </div>

      <div v-else class="results-scroll">
        <table class="table">
          <thead>
            <tr>
              <th>Pedido</th>
              <th>Data</th>
              <th>Comprador</th>
              <th>Pagamento</th>
              <th>Envio</th>
              <th class="text-right">Total</th>
              <th class="text-right">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pedido in pedidos" :key="pedido.id">
              <td>
                <strong>{{ pedido.numero_externo || pedido.external_id }}</strong>
                <span class="block text-[10px] text-[var(--text-muted)]">
                  {{ pedido.external_id }}
                </span>
              </td>
              <td>{{ dataHora(pedido.data_venda) }}</td>
              <td>
                {{ pedido.comprador_nome || 'Não informado' }}
                <span v-if="pedido.comprador_documento" class="block text-[10px] text-[var(--text-muted)]">
                  {{ pedido.comprador_documento }}
                </span>
              </td>
              <td>
                <span :class="['badge', badgePagamento(pedido.status_pagamento)]">
                  {{ labelStatus(pedido.status_pagamento) }}
                </span>
              </td>
              <td>
                <span :class="['badge', badgeEnvio(pedido.status_envio)]">
                  {{ labelStatus(pedido.status_envio) }}
                </span>
              </td>
              <td class="text-right font-semibold">{{ moeda(pedido.total) }}</td>
              <td class="text-right">
                <div class="inline-flex gap-2">
                  <button class="btn-secondary btn-sm" @click="abrir(pedido.id)">
                    Detalhes
                  </button>
                  <button class="btn-secondary btn-sm" @click="atualizar(pedido.id)">
                    Atualizar
                  </button>
                </div>
                <p v-if="pedido.ultimo_erro" class="mt-1 text-[10px] text-danger">
                  {{ pedido.ultimo_erro }}
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-if="meta.last_page > 1"
        class="flex items-center justify-between border-t border-[var(--border)] p-4"
      >
        <button
          class="btn-secondary btn-sm"
          :disabled="meta.current_page <= 1"
          @click="carregar(meta.current_page - 1)"
        >
          Anterior
        </button>

        <span class="text-xs text-[var(--text-muted)]">
          Página {{ meta.current_page }} de {{ meta.last_page }}
        </span>

        <button
          class="btn-secondary btn-sm"
          :disabled="meta.current_page >= meta.last_page"
          @click="carregar(meta.current_page + 1)"
        >
          Próxima
        </button>
      </div>
    </div>

    <div v-if="modal" class="modal-backdrop">
      <div class="card modal-card">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h2 class="text-base font-bold">
              Pedido {{ detalhe.numero_externo || detalhe.external_id }}
            </h2>
            <p class="text-xs text-[var(--text-muted)]">
              {{ dataHora(detalhe.data_venda) }}
            </p>
          </div>
          <button class="text-xl text-[var(--text-muted)]" @click="modal=false">×</button>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-3">
          <Info label="Comprador" :value="detalhe.comprador_nome"/>
          <Info label="Pagamento" :value="labelStatus(detalhe.status_pagamento)"/>
          <Info label="Envio" :value="labelStatus(detalhe.status_envio)"/>
        </div>

        <div class="mt-5">
          <h3 class="text-sm font-semibold">Itens</h3>
          <div class="mt-2 space-y-2">
            <div v-for="(item, index) in detalhe.itens || []" :key="index" class="item-card">
              <div>
                <strong>{{ item.titulo }}</strong>
                <span>{{ item.sku || item.external_item_id }}</span>
              </div>
              <div class="text-right">
                <strong>{{ item.quantidade }} × {{ moeda(item.preco_unitario) }}</strong>
              </div>
            </div>
          </div>
        </div>

        <div v-if="detalhe.endereco_entrega" class="mt-5">
          <h3 class="text-sm font-semibold">Entrega</h3>
          <pre class="json-box">{{ JSON.stringify(detalhe.endereco_entrega, null, 2) }}</pre>
        </div>

        <div class="mt-5 flex justify-end">
          <button class="btn-primary" @click="modal=false">Fechar</button>
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
const loading = ref(false)
const importando = ref(false)
const pedidos = ref([])
const meta = ref({ current_page: 1, last_page: 1 })
const modal = ref(false)
const detalhe = ref({})
const filtros = ref({
  busca: '',
  data_inicio: '',
  data_fim: '',
  status: '',
})

const Info = defineComponent({
  props: { label: String, value: [String, Number] },
  setup(props) {
    return () => h('div', { class: 'info-card' }, [
      h('span', props.label),
      h('strong', props.value || '—'),
    ])
  },
})

const moeda = value => new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
}).format(Number(value || 0))

const dataHora = value => value
  ? new Date(value).toLocaleString('pt-BR')
  : '—'

function labelStatus(value) {
  return {
    approved: 'Aprovado',
    paid: 'Pago',
    pending: 'Pendente',
    confirmed: 'Confirmado',
    cancelled: 'Cancelado',
    ready_to_ship: 'Pronto para envio',
    shipped: 'Enviado',
    delivered: 'Entregue',
    not_delivered: 'Não entregue',
  }[value] || value || 'Não informado'
}

function badgePagamento(value) {
  if (['approved', 'paid'].includes(value)) return 'badge-success'
  if (['cancelled', 'rejected'].includes(value)) return 'badge-danger'
  return 'badge-warning'
}

function badgeEnvio(value) {
  if (['delivered'].includes(value)) return 'badge-success'
  if (['not_delivered', 'cancelled'].includes(value)) return 'badge-danger'
  return 'badge-warning'
}

async function carregar(page = 1) {
  loading.value = true

  try {
    const { data } = await api.get('/marketplace/mercado-livre/pedidos', {
      params: { ...filtros.value, page },
    })

    pedidos.value = data.data || []
    meta.value = {
      current_page: data.current_page || 1,
      last_page: data.last_page || 1,
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Não foi possível carregar os pedidos.')
  } finally {
    loading.value = false
  }
}

async function importar() {
  importando.value = true

  try {
    const { data } = await api.post('/marketplace/mercado-livre/pedidos/importar', {
      data_inicio: filtros.value.data_inicio || null,
      data_fim: filtros.value.data_fim || null,
    })

    toast.success(
      `${data.message} Novos: ${data.importados}. Atualizados: ${data.atualizados}.`
    )

    await carregar()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Falha ao importar pedidos.')
  } finally {
    importando.value = false
  }
}

async function abrir(id) {
  const { data } = await api.get(`/marketplace/mercado-livre/pedidos/${id}`)
  detalhe.value = data.data
  modal.value = true
}

async function atualizar(id) {
  try {
    const { data } = await api.post(`/marketplace/mercado-livre/pedidos/${id}/atualizar`)
    toast.success(data.message)
    await carregar(meta.value.current_page)
  } catch (error) {
    toast.error(error.response?.data?.message || 'Falha ao atualizar pedido.')
  }
}

onMounted(() => carregar())
</script>

<style scoped>
.modal-backdrop{position:fixed;inset:0;z-index:120;background:rgba(0,0,0,.72);display:flex;align-items:center;justify-content:center;padding:16px}
.modal-card{width:min(860px,100%);max-height:92vh;overflow:auto;padding:18px}
.info-card{border:1px solid var(--border);border-radius:9px;padding:11px}
.info-card span{display:block;font-size:.64rem;color:var(--text-muted)}
.info-card strong{display:block;margin-top:4px;font-size:.75rem;color:var(--text-primary)}
.item-card{display:flex;justify-content:space-between;gap:15px;border:1px solid var(--border);border-radius:9px;padding:11px}
.item-card strong{display:block;font-size:.74rem}
.item-card span{display:block;margin-top:3px;font-size:.64rem;color:var(--text-muted)}
.json-box{margin-top:8px;max-height:250px;overflow:auto;border:1px solid var(--border);border-radius:9px;background:var(--bg-input);padding:12px;font-size:.65rem}
</style>
