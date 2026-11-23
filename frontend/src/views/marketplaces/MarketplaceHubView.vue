<template>
  <div class="marketplace-hub">
    <div class="page-header">
      <div>
        <h1 class="page-title">Central de Extensões e Marketplaces</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">
          Conectores para marketplaces, lojas virtuais, logística, pagamentos, marketing, ERP, fiscal e automação.
        </p>
      </div>
      <div class="flex gap-2">
        <button class="btn-secondary" @click="carregar">Atualizar</button>
        <button class="btn-primary">Configurações</button>
      </div>
    </div>

    <div class="hub-layout">
      <aside class="hub-sidebar card">
        <div class="sidebar-title">Categorias</div>

        <button
          :class="['category-item', !categoriaAtiva && 'active']"
          @click="categoriaAtiva = ''; aplicarFiltros()"
        >
          <span>Todos os conectores</span>
          <strong>{ resumo.total }</strong>
        </button>

        <button
          v-for="cat in categoriasOrdenadas"
          :key="cat.categoria"
          :class="['category-item', categoriaAtiva === cat.categoria && 'active']"
          @click="categoriaAtiva = cat.categoria; aplicarFiltros()"
        >
          <span>{ labelCategoria(cat.categoria) }</span>
          <strong>{ cat.total }</strong>
        </button>

        <div class="sidebar-title mt-5">Status</div>
        <button :class="['category-item', statusAtivo === 'conectado' && 'active']" @click="statusAtivo = statusAtivo === 'conectado' ? '' : 'conectado'; aplicarFiltros()">
          <span>Conectados</span>
          <strong>{ resumo.conectados }</strong>
        </button>
        <button :class="['category-item', statusAtivo === 'desconectado' && 'active']" @click="statusAtivo = statusAtivo === 'desconectado' ? '' : 'desconectado'; aplicarFiltros()">
          <span>Não conectados</span>
          <strong>{ resumo.disponiveis }</strong>
        </button>
      </aside>

      <main class="hub-content">
        <div class="card p-4 results-toolbar">
          <div class="search-box">
            <MagnifyingGlassIcon class="w-5 h-5"/>
            <input v-model="busca" placeholder="Pesquisar integração..." @input="aplicarFiltros"/>
          </div>

          <div class="filter-tabs mt-3">
            <button :class="!categoriaAtiva && 'active'" @click="categoriaAtiva=''; aplicarFiltros()">Todos</button>
            <button v-for="cat in categoriasDestaque" :key="cat" :class="categoriaAtiva===cat && 'active'" @click="categoriaAtiva=cat; aplicarFiltros()">
              { labelCategoria(cat) }
            </button>
          </div>
        </div>

        <div class="summary-grid">
          <div class="summary-card"><span>Conectores</span><strong>{ resumo.total }</strong><small>disponíveis no hub</small></div>
          <div class="summary-card"><span>Conectados</span><strong>{ resumo.conectados }</strong><small>ativos nesta empresa</small></div>
          <div class="summary-card"><span>Categorias</span><strong>{ resumo.categorias }</strong><small>grupos de integração</small></div>
          <div class="summary-card"><span>Disponíveis</span><strong>{ resumo.disponiveis }</strong><small>prontos para configurar</small></div>
        </div>

        <div v-if="loading" class="card p-10 text-center text-sm text-[var(--text-muted)]">
          Carregando conectores...
        </div>

        <div v-else-if="!filtrados.length" class="card p-10 text-center text-sm text-[var(--text-muted)]">
          Nenhum conector encontrado.
        </div>

        <div v-else class="connectors-grid results-scroll">
          <article v-for="connector in filtrados" :key="connector.slug" class="connector-card">
            <div class="connector-head">
              <div class="connector-logo">{ iniciais(connector.nome) }</div>
              <span :class="['badge', connector.conectado ? 'badge-success' : 'badge-gray']">
                { connector.conectado ? 'Conectado' : 'Disponível' }
              </span>
            </div>

            <h3>{ connector.nome }</h3>
            <p>{ connector.descricao }</p>

            <div class="connector-meta">
              <span>{ labelCategoria(connector.categoria) }</span>
              <span v-if="connector.nome_conta">{ connector.nome_conta }</span>
            </div>

            <div class="connector-actions">
              <button v-if="connector.conectado" class="btn-secondary btn-sm" @click="abrirConector(connector)">Gerenciar</button>
              <button v-else class="btn-primary btn-sm" @click="abrirConector(connector)">Configurar</button>
              <button class="btn-ghost btn-sm">Detalhes</button>
            </div>
          </article>
        </div>
      </main>
    </div>

    <div v-if="modalConnector" class="modal-backdrop">
      <div class="card modal-card">
        <div class="modal-title">
          <div>
            <h3>{ modalConnector.nome }</h3>
            <p>{ modalConnector.descricao }</p>
          </div>
          <button @click="modalConnector=null">×</button>
        </div>

        <div class="notice">
          A estrutura deste conector já está cadastrada. A autenticação e sincronização real serão habilitadas nas próximas fases específicas.
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
          <div class="info-box"><span>Categoria</span><strong>{ labelCategoria(modalConnector.categoria) }</strong></div>
          <div class="info-box"><span>Status</span><strong>Disponível para implementação</strong></div>
        </div>

        <button class="btn-primary w-full mt-5" @click="modalConnector=null">Entendi</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline'
import api from '@/services/api'

const router = useRouter()
const loading = ref(false)
const connectors = ref([])
const categorias = ref([])
const resumo = ref({ total:0, conectados:0, disponiveis:0, categorias:0 })
const busca = ref('')
const categoriaAtiva = ref('')
const statusAtivo = ref('')
const modalConnector = ref(null)

const categoriasDestaque = ['marketplace','ecommerce','logistica','pagamentos','erp','marketing','fiscal','automacao','ia']

const fallback = [{"slug": "mercado_livre", "nome": "Mercado Livre", "categoria": "marketplace", "descricao": "Marketplace líder na América Latina"}, {"slug": "shopee", "nome": "Shopee", "categoria": "marketplace", "descricao": "Marketplace e social commerce"}, {"slug": "amazon", "nome": "Amazon", "categoria": "marketplace", "descricao": "Marketplace global"}, {"slug": "magalu", "nome": "Magalu", "categoria": "marketplace", "descricao": "Marketplace Magazine Luiza"}, {"slug": "americanas", "nome": "Americanas", "categoria": "marketplace", "descricao": "Marketplace Americanas"}, {"slug": "carrefour", "nome": "Carrefour", "categoria": "marketplace", "descricao": "Marketplace Carrefour"}, {"slug": "casas_bahia", "nome": "Casas Bahia", "categoria": "marketplace", "descricao": "Marketplace Via"}, {"slug": "ponto", "nome": "Ponto", "categoria": "marketplace", "descricao": "Marketplace Via"}, {"slug": "extra", "nome": "Extra", "categoria": "marketplace", "descricao": "Marketplace Via"}, {"slug": "madeira_madeira", "nome": "MadeiraMadeira", "categoria": "marketplace", "descricao": "Casa, móveis e decoração"}, {"slug": "leroy_merlin", "nome": "Leroy Merlin", "categoria": "marketplace", "descricao": "Casa e construção"}, {"slug": "kabum", "nome": "KaBuM!", "categoria": "marketplace", "descricao": "Tecnologia e eletrônicos"}, {"slug": "netshoes", "nome": "Netshoes", "categoria": "marketplace", "descricao": "Esporte e moda"}, {"slug": "zattini", "nome": "Zattini", "categoria": "marketplace", "descricao": "Moda e acessórios"}, {"slug": "dafiti", "nome": "Dafiti", "categoria": "marketplace", "descricao": "Moda e lifestyle"}, {"slug": "centauro", "nome": "Centauro", "categoria": "marketplace", "descricao": "Esporte"}, {"slug": "decathlon", "nome": "Decathlon", "categoria": "marketplace", "descricao": "Esporte e lazer"}, {"slug": "olist", "nome": "Olist", "categoria": "marketplace", "descricao": "Hub de marketplaces"}, {"slug": "b2w", "nome": "B2W Legacy", "categoria": "marketplace", "descricao": "Integração legada"}, {"slug": "tiktok_shop", "nome": "TikTok Shop", "categoria": "marketplace", "descricao": "Social commerce"}, {"slug": "facebook_shop", "nome": "Facebook Shop", "categoria": "marketplace", "descricao": "Loja no Facebook"}, {"slug": "instagram_shopping", "nome": "Instagram Shopping", "categoria": "marketplace", "descricao": "Catálogo no Instagram"}, {"slug": "google_merchant", "nome": "Google Merchant Center", "categoria": "marketplace", "descricao": "Catálogo no Google Shopping"}, {"slug": "ifood", "nome": "iFood", "categoria": "marketplace", "descricao": "Pedidos e delivery"}, {"slug": "rappi", "nome": "Rappi", "categoria": "marketplace", "descricao": "Pedidos e delivery"}, {"slug": "enjoei", "nome": "Enjoei", "categoria": "marketplace", "descricao": "Moda e usados"}, {"slug": "elo7", "nome": "Elo7", "categoria": "marketplace", "descricao": "Artesanato e personalizados"}, {"slug": "shein", "nome": "SHEIN Marketplace", "categoria": "marketplace", "descricao": "Moda e lifestyle"}, {"slug": "aliexpress", "nome": "AliExpress", "categoria": "marketplace", "descricao": "Marketplace global"}, {"slug": "ebay", "nome": "eBay", "categoria": "marketplace", "descricao": "Marketplace global"}, {"slug": "walmart", "nome": "Walmart Marketplace", "categoria": "marketplace", "descricao": "Marketplace internacional"}, {"slug": "etsy", "nome": "Etsy", "categoria": "marketplace", "descricao": "Produtos artesanais"}, {"slug": "temu", "nome": "Temu", "categoria": "marketplace", "descricao": "Marketplace global"}, {"slug": "nuvemshop", "nome": "Nuvemshop", "categoria": "ecommerce", "descricao": "Loja virtual"}, {"slug": "shopify", "nome": "Shopify", "categoria": "ecommerce", "descricao": "Plataforma de e-commerce"}, {"slug": "woocommerce", "nome": "WooCommerce", "categoria": "ecommerce", "descricao": "Loja WordPress"}, {"slug": "vtex", "nome": "VTEX", "categoria": "ecommerce", "descricao": "Digital commerce enterprise"}, {"slug": "tray", "nome": "Tray", "categoria": "ecommerce", "descricao": "Loja virtual"}, {"slug": "loja_integrada", "nome": "Loja Integrada", "categoria": "ecommerce", "descricao": "Loja virtual"}, {"slug": "bagy", "nome": "Bagy", "categoria": "ecommerce", "descricao": "Social commerce"}, {"slug": "yampi", "nome": "Yampi", "categoria": "ecommerce", "descricao": "Checkout e e-commerce"}, {"slug": "wake", "nome": "Wake Commerce", "categoria": "ecommerce", "descricao": "Plataforma enterprise"}, {"slug": "linx_commerce", "nome": "Linx Commerce", "categoria": "ecommerce", "descricao": "Omnichannel"}, {"slug": "jet", "nome": "JET e-commerce", "categoria": "ecommerce", "descricao": "Plataforma de loja"}, {"slug": "irroba", "nome": "iSET / Irroba", "categoria": "ecommerce", "descricao": "Loja virtual"}, {"slug": "wbuy", "nome": "WBuy", "categoria": "ecommerce", "descricao": "Loja virtual"}, {"slug": "dooca", "nome": "Dooca Commerce", "categoria": "ecommerce", "descricao": "Loja virtual"}, {"slug": "cartpanda", "nome": "CartPanda", "categoria": "ecommerce", "descricao": "Checkout e loja"}, {"slug": "wix", "nome": "Wix Stores", "categoria": "ecommerce", "descricao": "Loja virtual"}, {"slug": "prestashop", "nome": "PrestaShop", "categoria": "ecommerce", "descricao": "Plataforma open source"}, {"slug": "opencart", "nome": "OpenCart", "categoria": "ecommerce", "descricao": "Plataforma open source"}, {"slug": "magento", "nome": "Adobe Commerce / Magento", "categoria": "ecommerce", "descricao": "E-commerce enterprise"}, {"slug": "bling", "nome": "Bling", "categoria": "erp", "descricao": "ERP e integrações"}, {"slug": "tiny", "nome": "Tiny ERP", "categoria": "erp", "descricao": "ERP e e-commerce"}, {"slug": "omie", "nome": "Omie", "categoria": "erp", "descricao": "ERP empresarial"}, {"slug": "conta_azul", "nome": "Conta Azul", "categoria": "erp", "descricao": "Gestão financeira"}, {"slug": "totvs", "nome": "TOTVS", "categoria": "erp", "descricao": "ERP corporativo"}, {"slug": "senior", "nome": "Senior Sistemas", "categoria": "erp", "descricao": "ERP corporativo"}, {"slug": "sap", "nome": "SAP", "categoria": "erp", "descricao": "ERP corporativo"}, {"slug": "oracle_netsuite", "nome": "Oracle NetSuite", "categoria": "erp", "descricao": "ERP em nuvem"}, {"slug": "protheus", "nome": "Protheus", "categoria": "erp", "descricao": "ERP TOTVS"}, {"slug": "linx_erp", "nome": "Linx ERP", "categoria": "erp", "descricao": "Varejo e PDV"}, {"slug": "vhsys", "nome": "vhsys", "categoria": "erp", "descricao": "Gestão empresarial"}, {"slug": "marketup", "nome": "MarketUP", "categoria": "erp", "descricao": "ERP e PDV"}, {"slug": "gestao_click", "nome": "GestãoClick", "categoria": "erp", "descricao": "Gestão empresarial"}, {"slug": "agendor", "nome": "Agendor", "categoria": "crm", "descricao": "CRM de vendas"}, {"slug": "rd_station", "nome": "RD Station", "categoria": "crm", "descricao": "Marketing e CRM"}, {"slug": "pipedrive", "nome": "Pipedrive", "categoria": "crm", "descricao": "CRM comercial"}, {"slug": "hubspot", "nome": "HubSpot", "categoria": "crm", "descricao": "CRM e marketing"}, {"slug": "salesforce", "nome": "Salesforce", "categoria": "crm", "descricao": "CRM enterprise"}, {"slug": "melhor_envio", "nome": "Melhor Envio", "categoria": "logistica", "descricao": "Fretes e etiquetas"}, {"slug": "frenet", "nome": "Frenet", "categoria": "logistica", "descricao": "Cálculo de frete"}, {"slug": "kangu", "nome": "Kangu", "categoria": "logistica", "descricao": "Pontos de postagem"}, {"slug": "correios", "nome": "Correios", "categoria": "logistica", "descricao": "Postagem e rastreio"}, {"slug": "jadlog", "nome": "Jadlog", "categoria": "logistica", "descricao": "Transportadora"}, {"slug": "loggi", "nome": "Loggi", "categoria": "logistica", "descricao": "Entregas rápidas"}, {"slug": "azul_cargo", "nome": "Azul Cargo", "categoria": "logistica", "descricao": "Carga aérea"}, {"slug": "latam_cargo", "nome": "LATAM Cargo", "categoria": "logistica", "descricao": "Carga aérea"}, {"slug": "total_express", "nome": "Total Express", "categoria": "logistica", "descricao": "Transportadora"}, {"slug": "sequoia", "nome": "Sequoia", "categoria": "logistica", "descricao": "Transportadora"}, {"slug": "jamef", "nome": "Jamef", "categoria": "logistica", "descricao": "Transportadora"}, {"slug": "braspress", "nome": "Braspress", "categoria": "logistica", "descricao": "Transportadora"}, {"slug": "rodonaves", "nome": "Rodonaves", "categoria": "logistica", "descricao": "Transportadora"}, {"slug": "loggi_envios", "nome": "Loggi Envios", "categoria": "logistica", "descricao": "Envios integrados"}, {"slug": "lalamove", "nome": "Lalamove", "categoria": "logistica", "descricao": "Entregas locais"}, {"slug": "uber_direct", "nome": "Uber Direct", "categoria": "logistica", "descricao": "Entregas sob demanda"}, {"slug": "borzo", "nome": "Borzo", "categoria": "logistica", "descricao": "Entregas rápidas"}, {"slug": "intelipost", "nome": "Intelipost", "categoria": "logistica", "descricao": "TMS e logística"}, {"slug": "frete_rapido", "nome": "Frete Rápido", "categoria": "logistica", "descricao": "Cotação e gestão de fretes"}, {"slug": "shipay", "nome": "Shipay", "categoria": "logistica", "descricao": "Orquestração de entregas"}, {"slug": "mercado_pago", "nome": "Mercado Pago", "categoria": "pagamentos", "descricao": "Pagamentos"}, {"slug": "pagseguro", "nome": "PagBank / PagSeguro", "categoria": "pagamentos", "descricao": "Pagamentos"}, {"slug": "asaas", "nome": "Asaas", "categoria": "pagamentos", "descricao": "Cobranças e PIX"}, {"slug": "stripe", "nome": "Stripe", "categoria": "pagamentos", "descricao": "Pagamentos globais"}, {"slug": "paypal", "nome": "PayPal", "categoria": "pagamentos", "descricao": "Pagamentos globais"}, {"slug": "pagarme", "nome": "Pagar.me", "categoria": "pagamentos", "descricao": "Gateway de pagamento"}, {"slug": "stone", "nome": "Stone", "categoria": "pagamentos", "descricao": "Adquirência e TEF"}, {"slug": "cielo", "nome": "Cielo", "categoria": "pagamentos", "descricao": "Adquirência"}, {"slug": "rede", "nome": "Rede", "categoria": "pagamentos", "descricao": "Adquirência"}, {"slug": "getnet", "nome": "Getnet", "categoria": "pagamentos", "descricao": "Adquirência"}, {"slug": "safrapay", "nome": "SafraPay", "categoria": "pagamentos", "descricao": "Adquirência"}, {"slug": "sumup", "nome": "SumUp", "categoria": "pagamentos", "descricao": "Pagamentos"}, {"slug": "infinitepay", "nome": "InfinitePay", "categoria": "pagamentos", "descricao": "Pagamentos"}, {"slug": "openpix", "nome": "OpenPix", "categoria": "pagamentos", "descricao": "PIX"}, {"slug": "efi", "nome": "Efí / Gerencianet", "categoria": "pagamentos", "descricao": "Cobranças e PIX"}, {"slug": "iugu", "nome": "Iugu", "categoria": "pagamentos", "descricao": "Cobranças"}, {"slug": "zoop", "nome": "Zoop", "categoria": "pagamentos", "descricao": "Banking e pagamentos"}, {"slug": "adyen", "nome": "Adyen", "categoria": "pagamentos", "descricao": "Pagamentos enterprise"}, {"slug": "ebanx", "nome": "EBANX", "categoria": "pagamentos", "descricao": "Pagamentos internacionais"}, {"slug": "picpay", "nome": "PicPay", "categoria": "pagamentos", "descricao": "Carteira e pagamentos"}, {"slug": "whatsapp_business", "nome": "WhatsApp Business", "categoria": "comunicacao", "descricao": "Mensagens e atendimento"}, {"slug": "telegram", "nome": "Telegram", "categoria": "comunicacao", "descricao": "Mensagens"}, {"slug": "gmail", "nome": "Gmail", "categoria": "comunicacao", "descricao": "E-mail"}, {"slug": "outlook", "nome": "Microsoft Outlook", "categoria": "comunicacao", "descricao": "E-mail e calendário"}, {"slug": "mailchimp", "nome": "Mailchimp", "categoria": "marketing", "descricao": "E-mail marketing"}, {"slug": "active_campaign", "nome": "ActiveCampaign", "categoria": "marketing", "descricao": "Automação de marketing"}, {"slug": "sendgrid", "nome": "SendGrid", "categoria": "marketing", "descricao": "E-mail transacional"}, {"slug": "brevo", "nome": "Brevo", "categoria": "marketing", "descricao": "Marketing e automação"}, {"slug": "meta_ads", "nome": "Meta Ads", "categoria": "marketing", "descricao": "Anúncios Facebook e Instagram"}, {"slug": "google_ads", "nome": "Google Ads", "categoria": "marketing", "descricao": "Publicidade"}, {"slug": "tiktok_ads", "nome": "TikTok Ads", "categoria": "marketing", "descricao": "Publicidade"}, {"slug": "google_analytics", "nome": "Google Analytics", "categoria": "marketing", "descricao": "Métricas"}, {"slug": "google_tag_manager", "nome": "Google Tag Manager", "categoria": "marketing", "descricao": "Tags e eventos"}, {"slug": "hotjar", "nome": "Hotjar", "categoria": "marketing", "descricao": "Comportamento do usuário"}, {"slug": "criteo", "nome": "Criteo", "categoria": "marketing", "descricao": "Retargeting"}, {"slug": "ramper", "nome": "Ramper", "categoria": "marketing", "descricao": "Prospecção"}, {"slug": "leadlovers", "nome": "LeadLovers", "categoria": "marketing", "descricao": "Automação de marketing"}, {"slug": "nfe_io", "nome": "NFe.io", "categoria": "fiscal", "descricao": "Emissão fiscal"}, {"slug": "focus_nfe", "nome": "Focus NFe", "categoria": "fiscal", "descricao": "Emissão fiscal"}, {"slug": "enotas", "nome": "eNotas", "categoria": "fiscal", "descricao": "Emissão fiscal"}, {"slug": "plugnotas", "nome": "PlugNotas", "categoria": "fiscal", "descricao": "Emissão fiscal"}, {"slug": "webmania", "nome": "WebmaniaBR", "categoria": "fiscal", "descricao": "NF-e e NFC-e"}, {"slug": "tecnospeed", "nome": "TecnoSpeed", "categoria": "fiscal", "descricao": "Documentos fiscais"}, {"slug": "nuvem_fiscal", "nome": "Nuvem Fiscal", "categoria": "fiscal", "descricao": "Documentos fiscais"}, {"slug": "arquivei", "nome": "Arquivei", "categoria": "fiscal", "descricao": "Gestão de documentos fiscais"}, {"slug": "zendesk", "nome": "Zendesk", "categoria": "atendimento", "descricao": "Suporte e atendimento"}, {"slug": "freshdesk", "nome": "Freshdesk", "categoria": "atendimento", "descricao": "Help desk"}, {"slug": "intercom", "nome": "Intercom", "categoria": "atendimento", "descricao": "Atendimento"}, {"slug": "jivochat", "nome": "JivoChat", "categoria": "atendimento", "descricao": "Chat omnichannel"}, {"slug": "tawkto", "nome": "Tawk.to", "categoria": "atendimento", "descricao": "Chat"}, {"slug": "zapi", "nome": "Z-API", "categoria": "automacao", "descricao": "WhatsApp API"}, {"slug": "evolution_api", "nome": "Evolution API", "categoria": "automacao", "descricao": "WhatsApp API"}, {"slug": "make", "nome": "Make", "categoria": "automacao", "descricao": "Automação"}, {"slug": "zapier", "nome": "Zapier", "categoria": "automacao", "descricao": "Automação"}, {"slug": "n8n", "nome": "n8n", "categoria": "automacao", "descricao": "Automação"}, {"slug": "power_automate", "nome": "Power Automate", "categoria": "automacao", "descricao": "Automação Microsoft"}, {"slug": "openai", "nome": "OpenAI", "categoria": "ia", "descricao": "Inteligência artificial"}, {"slug": "gemini", "nome": "Google Gemini", "categoria": "ia", "descricao": "Inteligência artificial"}, {"slug": "anthropic", "nome": "Anthropic Claude", "categoria": "ia", "descricao": "Inteligência artificial"}, {"slug": "azure_ai", "nome": "Azure AI", "categoria": "ia", "descricao": "Serviços de IA"}]

const filtrados = computed(() => connectors.value.filter(c => {
  const termo = busca.value.trim().toLowerCase()
  const bateBusca = !termo || c.nome.toLowerCase().includes(termo) || (c.descricao||'').toLowerCase().includes(termo)
  const bateCategoria = !categoriaAtiva.value || c.categoria === categoriaAtiva.value
  const bateStatus = !statusAtivo.value || (statusAtivo.value === 'conectado' ? c.conectado : !c.conectado)
  return bateBusca && bateCategoria && bateStatus
}))

const categoriasOrdenadas = computed(() => [...categorias.value].sort((a,b)=>a.categoria.localeCompare(b.categoria)))

function aplicarFiltros() {}
function iniciais(nome) { return nome.split(/\s+/).slice(0,2).map(p=>p[0]).join('').toUpperCase() }
function abrirConector(connector) {
  if (connector.slug === 'nuvemshop') { router.push({ name: 'nuvemshop-dropshipping' }); return }
  modalConnector.value = connector
}
function labelCategoria(cat) {
  return {
    marketplace:'Marketplaces', ecommerce:'Lojas virtuais', erp:'ERP e gestão', crm:'CRM',
    logistica:'Logística', pagamentos:'Pagamentos', comunicacao:'Comunicação',
    marketing:'Marketing', fiscal:'Fiscal', atendimento:'Atendimento',
    automacao:'Automação', ia:'Inteligência Artificial'
  }[cat] || cat
}

async function carregar() {
  loading.value = true
  try {
    const { data } = await api.get('/marketplace-hub')
    connectors.value = data.connectors || []
    categorias.value = data.categorias || []
    resumo.value = data.resumo || resumo.value
  } catch {
    connectors.value = fallback.map((c, i) => ({...c, id:i+1, conectado:false, status:'desconectado'}))
    const map = {}
    connectors.value.forEach(c => map[c.categoria] = (map[c.categoria]||0)+1)
    categorias.value = Object.entries(map).map(([categoria,total]) => ({categoria,total}))
    resumo.value = {
      total:connectors.value.length,
      conectados:0,
      disponiveis:connectors.value.length,
      categorias:categorias.value.length
    }
  } finally {
    loading.value = false
  }
}

onMounted(carregar)
</script>

<style scoped>
.marketplace-hub{display:flex;flex-direction:column;gap:16px}
.hub-layout{display:grid;grid-template-columns:240px minmax(0,1fr);gap:14px}
.hub-sidebar{padding:12px;height:max-content;position:sticky;top:12px}
.sidebar-title{font-size:.67rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--text-muted);padding:6px 8px}
.category-item{width:100%;display:flex;justify-content:space-between;align-items:center;border-radius:8px;padding:9px 10px;font-size:.72rem;color:var(--text-secondary)}
.category-item:hover,.category-item.active{background:var(--bg-elevated);color:var(--text-primary)}
.category-item strong{font-size:.65rem;color:var(--text-muted)}
.hub-content{display:flex;flex-direction:column;gap:14px;min-width:0}
.search-box{display:flex;align-items:center;gap:9px;border:1px solid var(--border);border-radius:9px;padding:10px 12px;background:var(--bg-input);color:var(--text-muted)}
.search-box input{flex:1;background:transparent;outline:none;color:var(--text-primary);font-size:.8rem}
.filter-tabs{display:flex;gap:6px;flex-wrap:wrap}
.filter-tabs button{border:1px solid var(--border);border-radius:999px;padding:5px 10px;font-size:.66rem;color:var(--text-secondary)}
.filter-tabs button.active,.filter-tabs button:hover{border-color:var(--primary);color:var(--text-primary);background:var(--bg-elevated)}
.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.summary-card{border:1px solid var(--border);border-radius:10px;background:var(--bg-card);padding:14px}
.summary-card span,.summary-card small{display:block;font-size:.65rem;color:var(--text-muted)}
.summary-card strong{display:block;font-size:1.5rem;color:var(--text-primary);margin:5px 0}
.connectors-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(235px,1fr));gap:10px}
.connector-card{border:1px solid var(--border);border-radius:11px;background:var(--bg-card);padding:13px;display:flex;flex-direction:column;min-height:210px;transition:.18s}
.connector-card:hover{transform:translateY(-2px);border-color:var(--primary)}
.connector-head{display:flex;justify-content:space-between;align-items:center}
.connector-logo{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--primary),#7c3aed);color:white;font-weight:900;font-size:.78rem}
.connector-card h3{margin-top:12px;font-size:.82rem;font-weight:800;color:var(--text-primary)}
.connector-card p{margin-top:4px;font-size:.68rem;color:var(--text-muted);line-height:1.45;flex:1}
.connector-meta{display:flex;justify-content:space-between;gap:8px;margin-top:10px;font-size:.62rem;color:var(--text-muted)}
.connector-actions{display:flex;gap:6px;margin-top:12px}
.modal-backdrop{position:fixed;inset:0;z-index:120;background:rgba(0,0,0,.72);display:flex;align-items:center;justify-content:center;padding:16px}
.modal-card{width:min(560px,100%);padding:18px}
.modal-title{display:flex;justify-content:space-between;gap:15px}
.modal-title h3{font-weight:800;color:var(--text-primary)}
.modal-title p{font-size:.7rem;color:var(--text-muted);margin-top:3px}
.modal-title button{font-size:1.4rem;color:var(--text-muted)}
.notice{margin-top:16px;border:1px solid rgba(59,130,246,.35);background:rgba(59,130,246,.08);border-radius:9px;padding:12px;font-size:.72rem;color:var(--text-secondary)}
.info-box{border:1px solid var(--border);border-radius:9px;padding:12px}
.info-box span{display:block;font-size:.65rem;color:var(--text-muted)}
.info-box strong{display:block;margin-top:4px;font-size:.75rem;color:var(--text-primary)}
@media(max-width:900px){.hub-layout{grid-template-columns:1fr}.hub-sidebar{position:static}.summary-grid{grid-template-columns:repeat(2,1fr)}}
</style>
