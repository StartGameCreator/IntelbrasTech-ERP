<template>
  <div class="space-y-5 animate-fade-in">
    <div class="page-header">
      <div>
        <button @click="router.push('/produtos')" class="back-link">
          <ArrowLeftIcon class="w-4 h-4" /> Voltar
        </button>
        <h1 class="page-title">{{ produto?.nome || 'Produto' }}</h1>
        <p class="text-xs text-[var(--text-muted)] mt-0.5">
          {{ produto?.sku || produto?.codigo || '—' }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2" v-if="produto">
        <span class="badge" :class="produto.ativo ? 'badge-success' : 'badge-gray'">
          {{ produto.ativo ? 'Ativo' : 'Inativo' }}
        </span>
        <router-link :to="`/produtos/${produto.id}/editar`" class="btn-primary btn-sm">
          <PencilIcon class="w-4 h-4" /> Editar cadastro completo
        </router-link>
      </div>
    </div>

    <div v-if="loading" class="skeleton h-52 rounded-xl" />

    <template v-else-if="produto">
      <div class="product-hero">
        <div class="main-image">
          <img v-if="imagemPrincipal" :src="imagemPrincipal.url" :alt="produto.nome" />
          <PhotoIcon v-else class="w-16 h-16 text-[var(--text-muted)]" />
        </div>

        <div class="hero-info">
          <div class="grid gap-4 md:grid-cols-3">
            <Info label="Categoria" :value="produto.categoria?.nome" />
            <Info label="Marca" :value="produto.marca?.nome" />
            <Info label="Fornecedor" :value="produto.fornecedor?.nome_fantasia || produto.fornecedor?.razao_social" />
            <Info label="Unidade" :value="produto.unidade?.sigla" />
            <Info label="Formato" :value="produto.formato" />
            <Info label="Condição" :value="produto.condicao" />
          </div>

          <div class="price-grid">
            <Info label="Preço de custo" :value="moeda(produto.preco_custo)" strong />
            <Info label="Preço de venda" :value="moeda(produto.preco_venda)" strong />
            <Info label="Margem" :value="percentual(produto.margem_lucro)" strong />
            <Info label="Preço mínimo" :value="moeda(produto.preco_venda_minimo)" strong />
          </div>
        </div>
      </div>

      <div class="tabs-bar">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          :class="['tab-btn', aba === tab.id && 'active']"
          @click="aba = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <section v-show="aba === 'caracteristicas'" class="space-y-4">
        <div class="card p-5">
          <h3 class="section-title">Características</h3>
          <div class="details-grid">
            <Info label="Produção" :value="produto.producao" />
            <Info label="Data de validade" :value="produto.data_validade" />
            <Info label="Frete grátis" :value="produto.frete_gratis ? 'Sim' : 'Não'" />
            <Info label="Peso líquido" :value="produto.peso ? `${produto.peso} kg` : null" />
            <Info label="Peso bruto" :value="produto.peso_bruto ? `${produto.peso_bruto} kg` : null" />
            <Info label="Largura" :value="medida(produto.largura)" />
            <Info label="Altura" :value="medida(produto.altura)" />
            <Info label="Profundidade" :value="medida(produto.comprimento)" />
            <Info label="Volumes" :value="produto.volumes" />
            <Info label="Itens por caixa" :value="produto.itens_caixa" />
            <Info label="GTIN/EAN" :value="produto.ean" />
            <Info label="GTIN/EAN tributário" :value="produto.ean_tributario" />
          </div>
        </div>

        <div class="card p-5 space-y-4">
          <h3 class="section-title">Conteúdo comercial</h3>
          <ContentBlock title="Descrição curta" :content="produto.descricao_curta" />
          <ContentBlock title="Descrição principal" :content="produto.descricao" html />
          <ContentBlock title="Descrição complementar" :content="produto.descricao_complementar" html />

          <div class="details-grid">
            <Info label="Link externo" :value="produto.link_externo" link />
            <Info label="Vídeo" :value="produto.video_url" link />
            <Info label="Tags" :value="(produto.tags || []).join(', ')" />
          </div>
        </div>
      </section>

      <section v-show="aba === 'imagens'" class="card p-5 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="section-title">Imagens do produto</h3>
          <router-link :to="`/produtos/${produto.id}/editar`" class="btn-secondary btn-sm">
            Gerenciar imagens
          </router-link>
        </div>

        <div v-if="!produto.imagens?.length" class="empty-state">
          <p class="text-xs text-[var(--text-muted)]">Nenhuma imagem cadastrada</p>
        </div>

        <div v-else class="gallery">
          <article v-for="img in produto.imagens" :key="img.id" class="gallery-card">
            <img :src="img.url" :alt="img.alt_text || produto.nome" />
            <div class="gallery-meta">
              <span>{{ img.origem === 'upload' ? 'Arquivo local' : 'URL externa' }}</span>
              <span>{{ img.principal ? 'Principal' : `Ordem ${img.ordem}` }}</span>
            </div>
          </article>
        </div>
      </section>

      <section v-show="aba === 'estoque'" class="card p-5 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="section-title">Estoque por depósito</h3>
          <span v-if="produto.abaixo_minimo" class="badge badge-warning">Abaixo do mínimo</span>
        </div>

        <div class="details-grid">
          <Info label="Estoque mínimo" :value="decimal(produto.estoque_minimo, 3)" />
          <Info label="Estoque máximo" :value="decimal(produto.estoque_maximo, 3)" />
          <Info label="Ponto de reposição" :value="decimal(produto.ponto_reposicao, 3)" />
          <Info label="Controla estoque" :value="produto.controla_estoque ? 'Sim' : 'Não'" />
        </div>

        <div v-if="!produto.estoque?.length" class="empty-state">
          <p class="text-xs text-[var(--text-muted)]">Sem movimentação de estoque para este produto</p>
        </div>

        <table v-else class="table">
          <thead>
            <tr>
              <th>Depósito</th>
              <th class="text-right">Quantidade</th>
              <th class="text-right">Reservado</th>
              <th class="text-right">Disponível</th>
              <th class="text-right">Custo médio</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="e in produto.estoque" :key="e.id">
              <td>{{ e.deposito?.nome || '—' }}</td>
              <td class="text-right">{{ decimal(e.quantidade, 3) }}</td>
              <td class="text-right">{{ decimal(e.quantidade_reservada, 3) }}</td>
              <td class="text-right font-medium">{{ decimal(e.quantidade_disponivel, 3) }}</td>
              <td class="text-right">{{ moeda(e.custo_medio) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-show="aba === 'fornecedores'" class="card p-5 space-y-4">
        <h3 class="section-title">Fornecedores vinculados</h3>

        <div v-if="!produto.fornecedores_produto?.length" class="empty-state">
          <p class="text-xs text-[var(--text-muted)]">Nenhum fornecedor adicional vinculado</p>
        </div>

        <table v-else class="table">
          <thead>
            <tr>
              <th>Fornecedor</th>
              <th>Código</th>
              <th class="text-right">Custo</th>
              <th class="text-right">Qtd. mínima</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="f in produto.fornecedores_produto" :key="f.id">
              <td>{{ f.fornecedor?.nome_fantasia || f.fornecedor?.razao_social || `Fornecedor #${f.fornecedor_id}` }}</td>
              <td>{{ f.codigo_fornecedor || '—' }}</td>
              <td class="text-right">{{ moeda(f.custo) }}</td>
              <td class="text-right">{{ decimal(f.quantidade_minima, 3) }}</td>
              <td><span class="badge" :class="f.principal ? 'badge-success' : 'badge-gray'">{{ f.principal ? 'Principal' : 'Alternativo' }}</span></td>
            </tr>
          </tbody>
        </table>
      </section>

      <section v-show="aba === 'tributacao'" class="space-y-4">
        <div class="card p-5">
          <h3 class="section-title">Dados fiscais</h3>
          <div class="details-grid">
            <Info label="Origem" :value="produto.origem" />
            <Info label="NCM" :value="produto.ncm" />
            <Info label="CEST" :value="produto.cest" />
            <Info label="CFOP" :value="produto.cfop" />
            <Info label="Tipo do item" :value="produto.tipo_item" />
            <Info label="Grupo de produtos" :value="produto.grupo_produtos" />
            <Info label="% Tributos" :value="percentual(produto.percentual_tributos)" />
            <Info label="CST ICMS" :value="produto.icms_cst" />
            <Info label="ICMS" :value="percentual(produto.icms_aliquota)" />
            <Info label="ICMS ST base" :value="moeda(produto.icms_st_base)" />
            <Info label="ICMS ST valor" :value="moeda(produto.icms_st_valor)" />
            <Info label="IPI" :value="percentual(produto.ipi_aliquota)" />
            <Info label="PIS" :value="percentual(produto.pis_aliquota)" />
            <Info label="COFINS" :value="percentual(produto.cofins_aliquota)" />
          </div>
        </div>

        <div v-if="produto.informacoes_adicionais" class="card p-5">
          <h3 class="section-title">Informações adicionais da nota fiscal</h3>
          <p class="content-text">{{ produto.informacoes_adicionais }}</p>
        </div>
      </section>

      <section v-show="aba === 'ia'" class="space-y-4">
        <div class="card p-5">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="section-title">Conteúdo inteligente</h3>
              <p class="mt-1 text-xs text-[var(--text-muted)]">
                Status: {{ produto.conteudo_ia_status || 'não gerado' }}
              </p>
            </div>
            <router-link :to="`/produtos/${produto.id}/editar`" class="btn-primary btn-sm">
              <SparklesIcon class="w-4 h-4" /> Gerar conteúdo
            </router-link>
          </div>
        </div>

        <div class="card p-5">
          <h3 class="section-title">Histórico</h3>
          <div v-if="!produto.conteudos_ia?.length" class="empty-state">
            <p class="text-xs text-[var(--text-muted)]">Nenhum rascunho gerado</p>
          </div>

          <div v-else class="history-list">
            <article v-for="item in produto.conteudos_ia" :key="item.id" class="history-item">
              <div>
                <strong>{{ item.tipo }}<span v-if="item.canal"> · {{ item.canal }}</span></strong>
                <p>{{ dataHora(item.created_at) }} · {{ item.fonte }}</p>
              </div>
              <span class="badge" :class="item.aplicado ? 'badge-success' : 'badge-gray'">
                {{ item.aplicado ? 'Aplicado' : 'Rascunho' }}
              </span>
            </article>
          </div>
        </div>
      </section>

      <section v-show="aba === 'marketplaces'" class="card p-5 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="section-title">Marketplaces e canais</h3>
          <router-link :to="`/produtos/${produto.id}/editar`" class="btn-secondary btn-sm">
            Preparar anúncios
          </router-link>
        </div>

        <div class="channel-grid">
          <article v-for="canal in canais" :key="canal.id" class="channel-card">
            <div>
              <strong>{{ canal.label }}</strong>
              <p>{{ canalData(canal.id)?.titulo || 'Nenhum rascunho preparado' }}</p>
            </div>
            <span class="badge" :class="canalData(canal.id) ? 'badge-warning' : 'badge-gray'">
              {{ canalData(canal.id)?.status || 'Não publicado' }}
            </span>
          </article>
        </div>
      </section>

      <section v-if="produto.variacoes?.length" class="card p-5">
        <h3 class="section-title">Variações</h3>
        <table class="table">
          <thead><tr><th>SKU</th><th>Código de barras</th><th class="text-right">Preço</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="v in produto.variacoes" :key="v.id">
              <td>{{ v.sku || '—' }}</td>
              <td>{{ v.codigo_barras || '—' }}</td>
              <td class="text-right">{{ moeda(v.preco_venda) }}</td>
              <td><span class="badge" :class="v.ativo ? 'badge-success' : 'badge-gray'">{{ v.ativo ? 'Ativa' : 'Inativa' }}</span></td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>

    <div v-else class="empty-state">
      <p class="text-sm text-[var(--text-muted)]">Produto não encontrado</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useFormato } from '@/composables/useFormato'
import { ArrowLeftIcon, PencilIcon, PhotoIcon, SparklesIcon } from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const { moeda, percentual, decimal } = useFormato()

const produto = ref(null)
const loading = ref(false)
const aba = ref('caracteristicas')

const tabs = [
  { id: 'caracteristicas', label: 'Características' },
  { id: 'imagens', label: 'Imagens' },
  { id: 'estoque', label: 'Estoque' },
  { id: 'fornecedores', label: 'Fornecedores' },
  { id: 'tributacao', label: 'Tributação' },
  { id: 'ia', label: 'Conteúdo IA' },
  { id: 'marketplaces', label: 'Marketplaces' },
]

const canais = [
  { id: 'mercado_livre', label: 'Mercado Livre' },
  { id: 'shopee', label: 'Shopee' },
  { id: 'amazon', label: 'Amazon' },
  { id: 'magalu', label: 'Magalu' },
  { id: 'nuvemshop', label: 'Nuvemshop' },
  { id: 'loja_propria', label: 'Loja própria' },
]

const imagemPrincipal = computed(() =>
  produto.value?.imagens?.find(img => img.principal) || produto.value?.imagens?.[0]
)

const Info = defineComponent({
  props: {
    label: String,
    value: [String, Number],
    strong: Boolean,
    link: Boolean,
  },
  setup(props) {
    return () => h('div', { class: 'info-item' }, [
      h('dt', { class: 'info-label' }, props.label),
      props.link && props.value
        ? h('a', { href: props.value, target: '_blank', rel: 'noopener', class: 'info-link' }, props.value)
        : h('dd', { class: props.strong ? 'info-value strong' : 'info-value' }, props.value ?? '—'),
    ])
  },
})

const ContentBlock = defineComponent({
  props: { title: String, content: String, html: Boolean },
  setup(props) {
    return () => h('div', { class: 'content-block' }, [
      h('h4', { class: 'content-title' }, props.title),
      props.content
        ? (props.html
          ? h('div', { class: 'rich-content', innerHTML: props.content })
          : h('p', { class: 'content-text' }, props.content))
        : h('p', { class: 'content-empty' }, 'Não informado'),
    ])
  },
})

function medida(value) {
  if (!value) return '—'
  return `${value} ${produto.value?.unidade_dimensao || 'cm'}`
}

function dataHora(value) {
  return value ? new Date(value).toLocaleString('pt-BR') : '—'
}

function canalData(canal) {
  return produto.value?.canais?.find(item => item.canal === canal)
}

async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get(`/produtos/${route.params.id}`)
    produto.value = data
  } catch {
    produto.value = null
  } finally {
    loading.value = false
  }
}

onMounted(carregar)
</script>

<style scoped>
.back-link{display:flex;align-items:center;gap:4px;margin-bottom:5px;font-size:.72rem;color:var(--text-muted)}
.product-hero{display:grid;grid-template-columns:280px 1fr;gap:16px}
.main-image,.hero-info{border:1px solid var(--border);border-radius:12px;background:var(--bg-card)}
.main-image{min-height:270px;display:flex;align-items:center;justify-content:center;overflow:hidden}
.main-image img{width:100%;height:270px;object-fit:contain;background:white}
.hero-info{padding:18px}
.price-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:18px;padding-top:18px;border-top:1px solid var(--border)}
.tabs-bar{display:flex;gap:4px;overflow:auto;border-bottom:1px solid var(--border)}
.tab-btn{padding:10px 14px;font-size:.78rem;color:var(--text-muted);border-bottom:2px solid transparent;white-space:nowrap}
.tab-btn.active{color:var(--text-primary);border-color:var(--primary)}
.section-title{font-size:.9rem;font-weight:700;color:var(--text-primary);margin-bottom:14px}
.details-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}
.info-label{font-size:.68rem;color:var(--text-muted);margin-bottom:3px}
.info-value{font-size:.8rem;color:var(--text-secondary);text-transform:none}
.info-value.strong{font-weight:700;color:var(--text-primary)}
.info-link{font-size:.76rem;color:var(--primary);word-break:break-all}
.content-block{padding-top:12px;border-top:1px solid var(--border)}
.content-block:first-of-type{padding-top:0;border-top:0}
.content-title{font-size:.75rem;font-weight:700;color:var(--text-primary);margin-bottom:7px}
.content-text,.rich-content{font-size:.78rem;line-height:1.6;color:var(--text-secondary)}
.content-empty{font-size:.75rem;color:var(--text-muted)}
.rich-content :deep(img){max-width:100%;height:auto}
.gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px}
.gallery-card{border:1px solid var(--border);border-radius:10px;padding:10px;background:var(--bg-input)}
.gallery-card img{width:100%;height:210px;object-fit:contain;background:white;border-radius:7px}
.gallery-meta{display:flex;justify-content:space-between;padding-top:8px;font-size:.67rem;color:var(--text-muted)}
.history-list{display:flex;flex-direction:column;gap:8px}
.history-item{display:flex;align-items:center;justify-content:space-between;padding:12px;border:1px solid var(--border);border-radius:9px;background:var(--bg-input)}
.history-item strong{font-size:.76rem;color:var(--text-primary)}
.history-item p{font-size:.66rem;color:var(--text-muted);margin-top:2px}
.channel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}
.channel-card{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px;border:1px solid var(--border);border-radius:10px;background:var(--bg-input)}
.channel-card strong{font-size:.78rem;color:var(--text-primary)}
.channel-card p{max-width:300px;margin-top:3px;font-size:.67rem;color:var(--text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
@media(max-width:900px){.product-hero{grid-template-columns:1fr}.details-grid,.price-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
