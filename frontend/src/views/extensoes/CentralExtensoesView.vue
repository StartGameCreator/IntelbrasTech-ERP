<template>
  <div class="extensions-page">
    <header class="extensions-header">
      <div>
        <div class="breadcrumb">
          <Squares2X2Icon class="w-4 h-4"/>
          <span>Central de Extensões</span>
        </div>
        <h1>Central de Extensões IntelbrasTech</h1>
        <p>Descubra, instale e gerencie integrações, serviços e módulos do seu ERP.</p>
      </div>

      <div class="header-actions">
        <div class="global-search">
          <MagnifyingGlassIcon class="w-5 h-5"/>
          <input v-model="busca" placeholder="Pesquisar integrações, aplicativos e serviços..."/>
        </div>
        <button class="btn-secondary" @click="aba='integracoes'">
          <CodeBracketIcon class="w-4 h-4"/>
          Área do integrador
        </button>
      </div>
    </header>

    <nav class="main-tabs">
      <button :class="{active:aba==='descobrir'}" @click="aba='descobrir'">
        <HomeIcon class="w-4 h-4"/>Descobrir
      </button>
      <button :class="{active:aba==='integracoes'}" @click="aba='integracoes'">
        <PuzzlePieceIcon class="w-4 h-4"/>Integrações
      </button>
      <button :class="{active:aba==='servicos'}" @click="aba='servicos'">
        <WrenchScrewdriverIcon class="w-4 h-4"/>Serviços IntelbrasTech
      </button>
      <button :class="{active:aba==='instalacoes'}" @click="aba='instalacoes'">
        <ArchiveBoxArrowDownIcon class="w-4 h-4"/>Minhas instalações
      </button>
    </nav>

    <div class="extensions-body">
      <aside class="extensions-sidebar">
        <template v-if="aba==='integracoes'">
          <div class="sidebar-section">
            <h3>Vendas online</h3>
            <button :class="{active:categoria==='marketplace'}" @click="categoria='marketplace'">Marketplace</button>
            <button :class="{active:categoria==='ecommerce'}" @click="categoria='ecommerce'">Plataforma de e-commerce</button>
            <button :class="{active:categoria==='erp'}" @click="categoria='erp'">Hub e ERP</button>
            <button :class="{active:categoria==='logistica'}" @click="categoria='logistica'">Delivery e logística</button>
            <button :class="{active:categoria==='marketing'}" @click="categoria='marketing'">Social commerce</button>
          </div>
          <div class="sidebar-section">
            <h3>Gestão financeira e custos</h3>
            <button :class="{active:categoria==='pagamentos'}" @click="categoria='pagamentos'">Pagamentos</button>
          </div>
          <div class="sidebar-section">
            <h3>Inteligência e análises</h3>
            <button @click="categoria='todos'">Dashboards e BI</button>
            <button @click="categoria='todos'">Soluções em IA</button>
          </div>
        </template>

        <template v-else-if="aba==='descobrir'">
          <div class="sidebar-section">
            <h3>Descobrir</h3>
            <button class="active">Nosso ecossistema</button>
            <button @click="rolarPara('destaques')">Categorias em destaque</button>
            <button @click="rolarPara('parceiros')">Plataformas parceiras</button>
            <button @click="rolarPara('servicos')">Serviços</button>
          </div>
        </template>

        <template v-else-if="aba==='servicos'">
          <div class="sidebar-section">
            <h3>Serviços IntelbrasTech</h3>
            <button :class="{active:filtroServico==='todos'}" @click="filtroServico='todos'">Todos os serviços</button>
            <button :class="{active:filtroServico==='implantacao'}" @click="filtroServico='implantacao'">Implantação</button>
            <button :class="{active:filtroServico==='suporte'}" @click="filtroServico='suporte'">Suporte</button>
            <button :class="{active:filtroServico==='fiscal'}" @click="filtroServico='fiscal'">Fiscal</button>
            <button :class="{active:filtroServico==='ia'}" @click="filtroServico='ia'">Inteligência Artificial</button>
            <button :class="{active:filtroServico==='infra'}" @click="filtroServico='infra'">Infraestrutura</button>
          </div>
        </template>

        <template v-else>
          <div class="sidebar-section">
            <h3>Minhas instalações</h3>
            <button class="active">Todas</button>
            <button>Integrações ativas</button>
            <button>Serviços contratados</button>
            <button>Módulos internos</button>
          </div>
        </template>
      </aside>

      <main class="extensions-content">
        <!-- DESCOBRIR -->
        <template v-if="aba==='descobrir'">
          <section class="ecosystem-hero">
            <div>
              <span class="eyebrow">NOSSO ECOSSISTEMA</span>
              <h2>Conecte seu ERP a tudo que movimenta o seu negócio</h2>
              <p>Marketplaces, lojas virtuais, logística, pagamentos, fiscal, IA e serviços especializados.</p>
            </div>
            <div class="hero-modules">
              <article v-for="item in ecossistema" :key="item.nome" @click="item.acao()">
                <component :is="item.icon" class="w-5 h-5"/>
                <div><strong>{{ item.nome }}</strong><span>{{ item.desc }}</span></div>
              </article>
            </div>
          </section>

          <section id="destaques" class="content-section">
            <div class="section-title"><StarIcon class="w-5 h-5"/><div><h2>Categorias em destaque</h2><p>Os canais mais usados para acelerar suas vendas.</p></div></div>
            <div class="featured-grid">
              <article v-for="item in destaques" :key="item.tipo" class="featured-card" :style="{'--accent':item.cor}" @click="abrirConector(item)">
                <div class="featured-brand"><img :src="item.icone" @error="imagemFalhou($event)"/><span>{{ iniciais(item.nome) }}</span></div>
                <strong>{{ item.nome }}</strong>
                <p>{{ item.descricao }}</p>
                <span class="recommended">Recomendado</span>
              </article>
            </div>
          </section>

          <section id="parceiros" class="content-section">
            <div class="section-title"><LinkIcon class="w-5 h-5"/><div><h2>Plataformas parceiras</h2><p>Soluções complementares para sua operação.</p></div></div>
            <div class="partner-grid">
              <article v-for="item in parceiros" :key="item.nome">
                <div class="mini-logo">{{ iniciais(item.nome) }}</div>
                <div><strong>{{ item.nome }}</strong><span>{{ item.desc }}</span></div>
              </article>
            </div>
          </section>

          <section id="servicos" class="content-section">
            <div class="section-title"><WrenchScrewdriverIcon class="w-5 h-5"/><div><h2>Serviços IntelbrasTech</h2><p>Serviços profissionais para implantação e evolução do ERP.</p></div></div>
            <div class="service-preview-grid">
              <article v-for="s in servicos.slice(0,4)" :key="s.id">
                <component :is="s.icon" class="w-5 h-5"/>
                <strong>{{ s.nome }}</strong>
                <p>{{ s.desc }}</p>
                <button class="btn-secondary btn-sm" @click="aba='servicos'">Ver serviço</button>
              </article>
            </div>
          </section>
        </template>

        <!-- INTEGRAÇÕES -->
        <template v-else-if="aba==='integracoes'">
          <section class="content-section no-top">
            <div class="section-title">
              <BuildingStorefrontIcon class="w-5 h-5"/>
              <div><h2>{{ categoriaTitulo }}</h2><p>{{ categoriaDescricao }}</p></div>
            </div>
            <div class="integration-grid">
              <article v-for="item in integracoesFiltradas" :key="item.tipo" class="integration-card" @click="abrirConector(item)">
                <div class="integration-logo" :style="{'--brand':item.cor}">
                  <img :src="item.icone" @error="imagemFalhou($event)"/>
                  <span>{{ iniciais(item.nome) }}</span>
                </div>
                <div class="integration-copy"><strong>{{ item.nome }}</strong><span>{{ item.descricao }}</span></div>
                <span v-if="item.status==='ativo'" class="status active">Conectado</span>
                <span v-else class="status">Disponível</span>
              </article>
            </div>
          </section>
        </template>

        <!-- SERVIÇOS -->
        <template v-else-if="aba==='servicos'">
          <section class="content-section no-top">
            <div class="section-title"><WrenchScrewdriverIcon class="w-5 h-5"/><div><h2>Serviços IntelbrasTech</h2><p>Contrate serviços especializados diretamente pelo ERP.</p></div></div>
            <div class="services-grid">
              <article v-for="s in servicosFiltrados" :key="s.id" class="service-card">
                <div class="service-icon"><component :is="s.icon" class="w-5 h-5"/></div>
                <span v-if="s.destaque" class="promo">Destaque</span>
                <h3>{{ s.nome }}</h3>
                <p>{{ s.desc }}</p>
                <div class="service-footer">
                  <strong>{{ s.preco }}</strong>
                  <button class="btn-secondary btn-sm" @click="contratarServico(s)">Solicitar</button>
                </div>
              </article>
            </div>
          </section>
        </template>

        <!-- INSTALAÇÕES -->
        <template v-else>
          <section class="content-section no-top">
            <div class="section-title"><ArchiveBoxArrowDownIcon class="w-5 h-5"/><div><h2>Minhas instalações</h2><p>Integrações e módulos ativos no seu ambiente.</p></div></div>

            <div v-if="!instalacoes.length" class="empty-card">
              <ArchiveBoxArrowDownIcon class="w-10 h-10"/>
              <strong>Nenhuma extensão instalada</strong>
              <span>Configure uma integração ou solicite um serviço para vê-lo aqui.</span>
            </div>

            <div v-else class="install-groups">
              <section v-for="grupo in instalacoesAgrupadas" :key="grupo.categoria">
                <h3>{{ grupo.label }}</h3>
                <div class="installed-grid">
                  <article v-for="item in grupo.items" :key="item.tipo">
                    <div class="integration-logo" :style="{'--brand':item.cor}">
                      <img :src="item.icone" @error="imagemFalhou($event)"/>
                      <span>{{ iniciais(item.nome) }}</span>
                    </div>
                    <div><strong>{{ item.nome_conta || item.nome }}</strong><span>{{ statusTexto(item.status) }}</span></div>
                    <button class="icon-menu" @click="abrirConector(item)">⋮</button>
                  </article>
                </div>
              </section>
            </div>
          </section>
        </template>
      </main>
    </div>

    <div v-if="modalConector" class="modal-backdrop" @click.self="modalConector=null">
      <div class="card connector-modal">
        <header>
          <div class="integration-logo large" :style="{'--brand':modalConector.cor}">
            <img :src="modalConector.icone" @error="imagemFalhou($event)"/>
            <span>{{ iniciais(modalConector.nome) }}</span>
          </div>
          <div><h2>{{ modalConector.nome }}</h2><p>{{ modalConector.descricao }}</p></div>
          <button @click="modalConector=null">×</button>
        </header>

        <div class="modal-fields">
          <label v-for="campo in modalConector.campos || []" :key="campo">
            <span>{{ labelCampo(campo) }}</span>
            <input v-model="credenciais[campo]" class="input" :type="campoProtegido(campo)?'password':'text'"/>
          </label>
          <label class="wide"><span>Nome da conta</span><input v-model="nomeConta" class="input" :placeholder="`${modalConector.nome} - Conta principal`"/></label>
        </div>

        <footer>
          <button class="btn-secondary" @click="testarConector">Testar rota</button>
          <button class="btn-primary" :disabled="salvando" @click="salvarConector">Salvar configuração</button>
        </footer>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, markRaw } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import {
  Squares2X2Icon, MagnifyingGlassIcon, CodeBracketIcon, HomeIcon, PuzzlePieceIcon,
  WrenchScrewdriverIcon, ArchiveBoxArrowDownIcon, StarIcon, LinkIcon,
  BuildingStorefrontIcon, TruckIcon, ShieldCheckIcon, SparklesIcon,
  CloudArrowUpIcon, AcademicCapIcon, ChatBubbleLeftRightIcon, ServerStackIcon,
  CurrencyDollarIcon, DocumentTextIcon, ChartBarIcon, CubeIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()
const aba = ref('descobrir')
const busca = ref('')
const categoria = ref('marketplace')
const filtroServico = ref('todos')
const conectores = ref([])
const modalConector = ref(null)
const credenciais = ref({})
const nomeConta = ref('')
const salvando = ref(false)

const ecossistema = [
  {nome:'Hub Omnichannel',desc:'Todos os marketplaces',icon:markRaw(BuildingStorefrontIcon),acao:()=>{aba.value='integracoes';categoria.value='marketplace'}},
  {nome:'Expedição',desc:'Picking, packing e etiquetas',icon:markRaw(TruckIcon),acao:()=>window.location.href='/expedicao'},
  {nome:'Fiscal',desc:'NF-e, NFC-e e certificados',icon:markRaw(DocumentTextIcon),acao:()=>window.location.href='/fiscal/nfe'},
  {nome:'IA IntelbrasTech',desc:'Automação e inteligência',icon:markRaw(SparklesIcon),acao:()=>{aba.value='servicos';filtroServico.value='ia'}},
  {nome:'Transportadoras',desc:'Frete e rastreamento',icon:markRaw(TruckIcon),acao:()=>window.location.href='/logistica/transportadoras'},
  {nome:'Backup em nuvem',desc:'Proteção do ambiente',icon:markRaw(CloudArrowUpIcon),acao:()=>{aba.value='servicos';filtroServico.value='infra'}},
  {nome:'Suporte premium',desc:'Atendimento especializado',icon:markRaw(ChatBubbleLeftRightIcon),acao:()=>{aba.value='servicos';filtroServico.value='suporte'}},
  {nome:'Treinamentos',desc:'Capacitação da equipe',icon:markRaw(AcademicCapIcon),acao:()=>{aba.value='servicos';filtroServico.value='implantacao'}},
]

const servicos = [
  {id:'implantacao-premium',nome:'Implantação Premium',desc:'Configuração completa do ERP, cadastros, permissões e treinamento inicial.',categoria:'implantacao',preco:'Sob consulta',icon:markRaw(AcademicCapIcon),destaque:true},
  {id:'implantacao-multiempresa',nome:'Implantação Multiempresa',desc:'Estruturação de múltiplos CNPJs, depósitos e regras operacionais.',categoria:'implantacao',preco:'Sob consulta',icon:markRaw(BuildingStorefrontIcon)},
  {id:'migracao-erp',nome:'Migração de ERP',desc:'Importação assistida de clientes, produtos, estoque e financeiro.',categoria:'implantacao',preco:'Sob consulta',icon:markRaw(ServerStackIcon)},
  {id:'suporte-premium',nome:'Suporte Premium',desc:'Canal prioritário para atendimento técnico e operacional.',categoria:'suporte',preco:'Plano mensal',icon:markRaw(ChatBubbleLeftRightIcon),destaque:true},
  {id:'configuracao-fiscal',nome:'Configuração Fiscal',desc:'NF-e, NFC-e, séries, CSC, certificado digital e regras tributárias.',categoria:'fiscal',preco:'Sob consulta',icon:markRaw(ShieldCheckIcon)},
  {id:'certificado-digital',nome:'Certificado Digital A1',desc:'Orientação, instalação e validação do certificado no ERP.',categoria:'fiscal',preco:'Sob consulta',icon:markRaw(ShieldCheckIcon)},
  {id:'ia-comercial',nome:'IA Comercial',desc:'Prospecção, descrição de produtos, CRM e análise de oportunidades.',categoria:'ia',preco:'Plano mensal',icon:markRaw(SparklesIcon),destaque:true},
  {id:'ia-fiscal',nome:'IA Fiscal',desc:'Sugestões de NCM, validações e apoio ao cadastro tributário.',categoria:'ia',preco:'Plano mensal',icon:markRaw(SparklesIcon)},
  {id:'ia-estoque',nome:'IA de Estoque',desc:'Previsão de demanda, ruptura e sugestão de compras.',categoria:'ia',preco:'Plano mensal',icon:markRaw(ChartBarIcon)},
  {id:'backup-cloud',nome:'Backup em Nuvem',desc:'Rotinas de backup, retenção e restauração monitorada.',categoria:'infra',preco:'Plano mensal',icon:markRaw(CloudArrowUpIcon)},
  {id:'monitoramento',nome:'Monitoramento 24h',desc:'Monitoramento de disponibilidade e serviços essenciais.',categoria:'infra',preco:'Plano mensal',icon:markRaw(ServerStackIcon)},
  {id:'sob-medida',nome:'Desenvolvimento Sob Medida',desc:'Novos módulos, relatórios e integrações específicas.',categoria:'suporte',preco:'Sob consulta',icon:markRaw(CodeBracketIcon)},
]

const parceiros = [
  {nome:'Nuvemshop',desc:'Loja virtual integrada'},
  {nome:'Shopify',desc:'E-commerce global'},
  {nome:'WooCommerce',desc:'Loja WordPress'},
  {nome:'Melhor Envio',desc:'Fretes e etiquetas'},
  {nome:'Mercado Pago',desc:'Pagamentos e conciliação'},
  {nome:'Correios',desc:'Postagem e rastreio'},
  {nome:'Bling',desc:'Integração com ERP externo'},
  {nome:'Omie',desc:'Integração empresarial'},
]

const categoriaNomes = {
  marketplace:'Marketplace',
  ecommerce:'Plataforma de e-commerce',
  erp:'Hub e ERP',
  logistica:'Delivery e logística',
  pagamentos:'Pagamentos',
  marketing:'Social commerce',
}
const categoriaDescricoes = {
  marketplace:'Plataformas para reunir diversos vendedores em um só lugar.',
  ecommerce:'Lojas virtuais e plataformas próprias de venda.',
  erp:'Sistemas de gestão e hubs integradores.',
  logistica:'Fretes, etiquetas, entregas e rastreamento.',
  pagamentos:'Gateways, adquirentes e conciliação financeira.',
  marketing:'Redes sociais, campanhas e relacionamento.',
}

const filtradosBusca = computed(()=>conectores.value.filter(c=>{
  const q=busca.value.trim().toLowerCase()
  return !q || `${c.nome} ${c.descricao} ${c.categoria}`.toLowerCase().includes(q)
}))
const integracoesFiltradas = computed(()=>filtradosBusca.value.filter(c=>categoria.value==='todos'||c.categoria===categoria.value))
const destaques = computed(()=>['tiktok_shop','mercadolivre','shopee','amazon'].map(t=>conectores.value.find(c=>c.tipo===t)).filter(Boolean))
const categoriaTitulo = computed(()=>categoriaNomes[categoria.value]||'Integrações')
const categoriaDescricao = computed(()=>categoriaDescricoes[categoria.value]||'Conectores disponíveis.')
const servicosFiltrados = computed(()=>servicos.filter(s=>filtroServico.value==='todos'||s.categoria===filtroServico.value))
const instalacoes = computed(()=>conectores.value.filter(c=>c.status==='ativo'||c.status==='pendente'))
const instalacoesAgrupadas = computed(()=>{
  const labels={marketplace:'Marketplaces',ecommerce:'Plataformas de e-commerce',erp:'Hubs e ERPs',logistica:'Logística',pagamentos:'Pagamentos',marketing:'Marketing'}
  return Object.entries(labels).map(([categoria,label])=>({categoria,label,items:instalacoes.value.filter(i=>i.categoria===categoria)})).filter(g=>g.items.length)
})

function iniciais(n){return String(n||'').split(/\s+/).slice(0,2).map(x=>x[0]).join('').toUpperCase()}
function imagemFalhou(e){e.target.style.display='none'}
function statusTexto(s){return({ativo:'Conectado',pendente:'Configuração salva',inativo:'Inativo',desconectado:'Desconectado'})[s]||s}
function campoProtegido(c){return /(secret|token|password|senha|key)/i.test(c)}
function labelCampo(c){return c.replaceAll('_',' ').replace(/\b\w/g,m=>m.toUpperCase())}
function abrirConector(item){modalConector.value=item;credenciais.value={};nomeConta.value=item.nome_conta||''}
function rolarPara(id){document.getElementById(id)?.scrollIntoView({behavior:'smooth'})}
function contratarServico(s){toast.success(`Solicitação registrada: ${s.nome}.`)}

async function carregar(){
  try{
    const {data}=await api.get('/marketplaces/catalogo')
    conectores.value=data.data||[]
  }catch(e){
    toast.error('Não foi possível carregar o catálogo de integrações.')
  }
}
async function salvarConector(){
  salvando.value=true
  try{
    const {data}=await api.post(`/marketplaces/${modalConector.value.tipo}/configurar`,{
      nome:nomeConta.value||modalConector.value.nome,
      status:'pendente',
      credenciais:credenciais.value,
      config:{origem:'central_extensoes'},
    })
    toast.success(data.message)
    modalConector.value=null
    await carregar()
  }catch(e){toast.error(e.response?.data?.message||'Falha ao salvar configuração.')}
  finally{salvando.value=false}
}
async function testarConector(){
  try{
    const {data}=await api.get(`/marketplaces/${modalConector.value.tipo}/testar`)
    toast[data.ok?'success':'warning'](data.message)
  }catch(e){toast.error(e.response?.data?.message||'Falha ao testar integração.')}
}
onMounted(carregar)
</script>

<style scoped>
.extensions-page{min-height:100%;display:flex;flex-direction:column;background:var(--bg-main)}.extensions-header{display:flex;justify-content:space-between;gap:20px;padding:18px 20px 14px;border-bottom:1px solid var(--border)}.breadcrumb{display:flex;align-items:center;gap:7px;font-size:.65rem;color:var(--text-muted)}.extensions-header h1{font-size:1.15rem;font-weight:800;margin-top:5px}.extensions-header p{font-size:.68rem;color:var(--text-muted);margin-top:4px}.header-actions{display:flex;align-items:center;gap:10px}.global-search{width:min(420px,38vw);display:flex;align-items:center;gap:8px;border:1px solid var(--border);border-radius:8px;background:var(--bg-input);padding:0 10px}.global-search input{height:36px;flex:1;background:transparent;outline:none;font-size:.68rem}
.main-tabs{display:flex;align-items:center;gap:22px;padding:0 20px;border-bottom:1px solid var(--border)}.main-tabs button{display:flex;align-items:center;gap:6px;padding:11px 0 9px;border-bottom:2px solid transparent;font-size:.67rem;color:var(--text-secondary)}.main-tabs button.active{color:#4ade80;border-color:#22c55e}
.extensions-body{display:grid;grid-template-columns:190px 1fr;min-height:calc(100vh - 180px)}.extensions-sidebar{border-right:1px solid var(--border);background:var(--bg-card);padding:12px 8px}.sidebar-section{padding:7px 0 12px}.sidebar-section h3{font-size:.64rem;font-weight:800;padding:5px 8px;color:var(--text-primary)}.sidebar-section button{width:100%;text-align:left;border-radius:7px;padding:7px 9px;font-size:.62rem;color:var(--text-secondary)}.sidebar-section button:hover,.sidebar-section button.active{background:rgba(34,197,94,.12);color:#4ade80}
.extensions-content{padding:16px 18px;overflow:auto}.ecosystem-hero{border:1px solid var(--border);border-radius:12px;background:radial-gradient(circle at 90% 20%,rgba(34,197,94,.12),transparent 32%),var(--bg-card);padding:20px}.eyebrow{font-size:.58rem;font-weight:800;letter-spacing:.12em;color:#4ade80}.ecosystem-hero h2{font-size:1.25rem;font-weight:800;margin-top:5px}.ecosystem-hero p{font-size:.68rem;color:var(--text-muted);margin-top:4px}.hero-modules{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:16px}.hero-modules article{display:flex;align-items:center;gap:9px;border:1px solid var(--border);border-radius:8px;background:var(--bg-surface);padding:10px;cursor:pointer}.hero-modules article:hover{border-color:#22c55e}.hero-modules strong,.hero-modules span{display:block}.hero-modules strong{font-size:.66rem}.hero-modules span{font-size:.57rem;color:var(--text-muted)}
.content-section{margin-top:18px}.content-section.no-top{margin-top:0}.section-title{display:flex;align-items:flex-start;gap:8px;border-bottom:1px solid var(--border);padding-bottom:9px;margin-bottom:10px}.section-title h2{font-size:.82rem;font-weight:800}.section-title p{font-size:.61rem;color:var(--text-muted);margin-top:2px}.featured-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.featured-card{border:1px solid var(--border);border-radius:9px;background:var(--bg-card);overflow:hidden;cursor:pointer}.featured-card:hover{transform:translateY(-2px);border-color:var(--accent)}.featured-brand{height:56px;display:grid;place-items:center;background:color-mix(in srgb,var(--accent) 75%,#111)}.featured-brand img{width:36px;height:36px;object-fit:contain;grid-area:1/1}.featured-brand span{grid-area:1/1;font-weight:900}.featured-card>strong,.featured-card>p,.featured-card>.recommended{margin-left:10px;margin-right:10px}.featured-card>strong{display:block;font-size:.68rem;margin-top:9px}.featured-card>p{font-size:.58rem;color:var(--text-muted);margin-top:3px;min-height:30px}.recommended{display:inline-block;margin-top:7px;margin-bottom:10px;border-radius:999px;background:#0284c7;padding:2px 7px;font-size:.52rem;color:#fff}
.partner-grid,.integration-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.partner-grid article,.integration-card{display:flex;align-items:center;gap:9px;border:1px solid var(--border);border-radius:8px;background:var(--bg-card);padding:10px}.partner-grid strong,.partner-grid span,.integration-copy strong,.integration-copy span{display:block}.partner-grid strong,.integration-copy strong{font-size:.65rem}.partner-grid span,.integration-copy span{font-size:.56rem;color:var(--text-muted);margin-top:2px}.mini-logo{width:34px;height:34px;border-radius:7px;display:grid;place-items:center;background:var(--bg-elevated);font-size:.57rem;font-weight:800}.integration-card{cursor:pointer;min-height:70px}.integration-card:hover{border-color:#22c55e}.integration-logo{width:36px;height:36px;border:1px solid var(--border);border-radius:8px;background:color-mix(in srgb,var(--brand) 12%,var(--bg-elevated));display:grid;place-items:center;overflow:hidden;flex-shrink:0}.integration-logo.large{width:48px;height:48px}.integration-logo img{width:24px;height:24px;object-fit:contain;grid-area:1/1;z-index:2}.integration-logo span{grid-area:1/1;font-size:.58rem;font-weight:900;color:var(--brand)}.integration-copy{flex:1}.status{font-size:.51rem;border-radius:999px;padding:3px 6px;background:var(--bg-elevated);color:var(--text-muted)}.status.active{background:rgba(34,197,94,.12);color:#4ade80}
.service-preview-grid,.services-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.service-preview-grid article,.service-card{position:relative;border:1px solid var(--border);border-top:4px solid #22c55e;border-radius:8px;background:var(--bg-card);padding:14px}.service-preview-grid strong,.service-card h3{display:block;font-size:.68rem;margin-top:9px}.service-preview-grid p,.service-card p{font-size:.57rem;color:var(--text-muted);margin-top:5px;min-height:38px}.service-preview-grid button{margin-top:10px}.service-icon{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:rgba(34,197,94,.16);color:#4ade80}.promo{position:absolute;right:10px;top:10px;border-radius:999px;background:#7c3aed;padding:2px 6px;font-size:.5rem;color:#fff}.service-footer{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:12px}.service-footer strong{font-size:.61rem;color:#4ade80}
.empty-card{display:flex;flex-direction:column;align-items:center;gap:8px;padding:50px;border:1px dashed var(--border);border-radius:10px;color:var(--text-muted)}.empty-card strong{font-size:.75rem}.empty-card span{font-size:.62rem}.install-groups section{margin-bottom:18px}.install-groups h3{font-size:.7rem;font-weight:800;margin-bottom:8px}.installed-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.installed-grid article{display:flex;align-items:center;gap:9px;border:1px solid var(--border);border-radius:8px;background:var(--bg-card);padding:10px}.installed-grid strong,.installed-grid span{display:block}.installed-grid strong{font-size:.65rem}.installed-grid span{font-size:.55rem;color:var(--text-muted);margin-top:2px}.icon-menu{margin-left:auto;font-size:1rem}
.modal-backdrop{position:fixed;inset:0;z-index:180;background:rgba(0,0,0,.75);display:flex;align-items:center;justify-content:center;padding:16px}.connector-modal{width:min(760px,100%);max-height:90vh;overflow:auto;padding:0}.connector-modal header{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;padding:16px;border-bottom:1px solid var(--border)}.connector-modal header h2{font-size:.9rem;font-weight:800}.connector-modal header p{font-size:.61rem;color:var(--text-muted);margin-top:2px}.connector-modal header button{font-size:1.4rem}.modal-fields{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;padding:16px}.modal-fields label span{display:block;font-size:.59rem;color:var(--text-muted);margin-bottom:4px}.modal-fields .wide{grid-column:1/-1}.connector-modal footer{display:flex;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid var(--border)}
@media(max-width:1100px){.hero-modules,.featured-grid,.partner-grid,.integration-grid,.service-preview-grid,.services-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:760px){.extensions-header{flex-direction:column}.header-actions{flex-direction:column;align-items:stretch}.global-search{width:100%}.extensions-body{grid-template-columns:1fr}.extensions-sidebar{display:none}.hero-modules,.featured-grid,.partner-grid,.integration-grid,.service-preview-grid,.services-grid,.installed-grid{grid-template-columns:1fr}.modal-fields{grid-template-columns:1fr}.main-tabs{overflow:auto}}
</style>
