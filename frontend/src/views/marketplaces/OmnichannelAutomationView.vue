<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <h1 class="page-title">Automação Omnichannel</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">
          Converta pedidos importados em pedidos internos e baixe o estoque.
        </p>
      </div>
      <RouterLink to="/marketplaces/mercado-livre/pedidos" class="btn-secondary">
        Pedidos Mercado Livre
      </RouterLink>
    </div>

    <div class="summary-grid">
      <div class="summary-card">
        <span>Pendentes</span>
        <strong>{{ resumo.pendentes }}</strong>
        <small>aguardando processamento</small>
      </div>
      <div class="summary-card">
        <span>Processados</span>
        <strong>{{ resumo.processados }}</strong>
        <small>pedido interno criado</small>
      </div>
      <div class="summary-card">
        <span>Com erro</span>
        <strong>{{ resumo.com_erro }}</strong>
        <small>precisam de correção</small>
      </div>
    </div>

    <div class="card p-5">
      <div class="grid gap-3 md:grid-cols-3">
        <div>
          <label class="label">ID do pedido importado</label>
          <input v-model="orderId" class="input" placeholder="Ex.: 15"/>
        </div>
        <div>
          <label class="label">Depósito para baixa</label>
          <select v-model="depositoId" class="select">
            <option value="">Selecione</option>
            <option v-for="deposito in depositos" :key="deposito.id" :value="deposito.id">
              {{ deposito.nome }}
            </option>
          </select>
        </div>
        <div class="flex items-end gap-2">
          <button class="btn-secondary flex-1" :disabled="!orderId" @click="validar">
            Validar
          </button>
          <button
            class="btn-primary flex-1"
            :disabled="!orderId || !depositoId || !validacao?.valido"
            @click="processar"
          >
            Processar
          </button>
        </div>
      </div>
    </div>

    <div v-if="validacao" class="grid gap-4 lg:grid-cols-2">
      <section class="card p-5">
        <h2 class="text-sm font-semibold">Validação</h2>

        <div v-if="validacao.valido" class="success-box mt-3">
          Todos os itens foram localizados no cadastro de produtos.
        </div>

        <div v-if="validacao.erros?.length" class="mt-4">
          <h3 class="text-xs font-semibold text-danger">Pendências</h3>
          <p v-for="erro in validacao.erros" :key="erro" class="mt-1 text-xs">
            • {{ erro }}
          </p>
        </div>

        <div v-if="validacao.avisos?.length" class="mt-4">
          <h3 class="text-xs font-semibold text-warning">Avisos</h3>
          <p v-for="aviso in validacao.avisos" :key="aviso" class="mt-1 text-xs">
            • {{ aviso }}
          </p>
        </div>
      </section>

      <section class="card p-5">
        <h2 class="text-sm font-semibold">Mapeamento dos itens</h2>

        <div class="mt-3 space-y-2">
          <div
            v-for="item in validacao.mapeamentos || []"
            :key="item.index"
            class="mapping-row"
          >
            <div>
              <strong>{{ item.sku || item.external_item_id || 'Sem referência' }}</strong>
              <span>{{ item.produto_nome || 'Produto não localizado' }}</span>
            </div>
            <span :class="['badge', item.status === 'localizado' ? 'badge-success' : 'badge-danger']">
              {{ item.status }}
            </span>
          </div>
        </div>
      </section>
    </div>

    <div v-if="resultado" class="card p-5">
      <div class="success-box">
        <strong>{{ resultado.message }}</strong>
        <p class="mt-1">
          Pedido interno {{ resultado.pedido.numero }} — {{ moeda(resultado.pedido.total) }}
        </p>
      </div>
    </div>

    <div class="card p-5">
      <h2 class="text-sm font-semibold">O que esta automação executa</h2>
      <div class="features mt-4">
        <Feature title="Mapeamento por SKU" text="Localiza o produto interno pelo SKU, código ou item externo."/>
        <Feature title="Pedido interno" text="Cria o pedido de venda com canal Mercado Livre."/>
        <Feature title="Baixa de estoque" text="Registra a saída no depósito selecionado."/>
        <Feature title="Proteção contra duplicidade" text="Não cria outro pedido interno para a mesma venda."/>
        <Feature title="Rastreabilidade" text="Vincula o pedido interno ao pedido do marketplace."/>
        <Feature title="Preparação fiscal" text="Deixa o pedido pronto para o fluxo fiscal seguinte."/>
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
const resumo = ref({ pendentes: 0, processados: 0, com_erro: 0 })
const depositos = ref([])
const orderId = ref('')
const depositoId = ref('')
const validacao = ref(null)
const resultado = ref(null)

const Feature = defineComponent({
  props: { title: String, text: String },
  setup(props) {
    return () => h('div', { class: 'feature-card' }, [
      h('strong', props.title),
      h('p', props.text),
    ])
  },
})

const moeda = value => new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
}).format(Number(value || 0))

async function carregar() {
  const { data } = await api.get('/marketplace/automacao/overview')
  resumo.value = data.resumo
  depositos.value = data.depositos
}

async function validar() {
  resultado.value = null

  try {
    const { data } = await api.get(`/marketplace/automacao/pedidos/${orderId.value}/validar`)
    validacao.value = data
  } catch (error) {
    toast.error(error.response?.data?.message || 'Falha na validação.')
  }
}

async function processar() {
  try {
    const { data } = await api.post(
      `/marketplace/automacao/pedidos/${orderId.value}/processar`,
      { deposito_id: depositoId.value }
    )

    resultado.value = data
    toast.success(data.message)
    await carregar()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Falha no processamento.')
  }
}

onMounted(carregar)
</script>

<style scoped>
.summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.summary-card{border:1px solid var(--border);border-radius:10px;background:var(--bg-card);padding:14px}
.summary-card span,.summary-card small{display:block;font-size:.65rem;color:var(--text-muted)}
.summary-card strong{display:block;font-size:1.5rem;margin:5px 0;color:var(--text-primary)}
.success-box{border:1px solid rgba(34,197,94,.35);background:rgba(34,197,94,.08);border-radius:9px;padding:12px;font-size:.72rem;color:#4ade80}
.mapping-row{display:flex;justify-content:space-between;gap:12px;border:1px solid var(--border);border-radius:9px;padding:11px}
.mapping-row strong{display:block;font-size:.73rem}
.mapping-row span:not(.badge){display:block;margin-top:3px;font-size:.65rem;color:var(--text-muted)}
.features{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.feature-card{border:1px solid var(--border);border-radius:9px;padding:12px}
.feature-card strong{font-size:.75rem;color:var(--text-primary)}
.feature-card p{margin-top:4px;font-size:.67rem;line-height:1.45;color:var(--text-muted)}
@media(max-width:800px){.summary-grid,.features{grid-template-columns:1fr}}
</style>
