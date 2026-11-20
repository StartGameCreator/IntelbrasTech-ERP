<template>
  <div class="dashboards-page" :class="{'sales-dashboard': ehVendas}">
    <header class="page-header">
      <div>
        <div class="dashboard-breadcrumb">
          <ChartBarIcon class="w-4 h-4"/>
          <span>Dashboards</span>
          <ChevronRightIcon class="w-3 h-3"/>
          <strong>{{ painel.titulo }}</strong>
        </div>
        <h1 class="page-title">{{ painel.titulo }}</h1>
        <p class="mt-1 text-xs text-[var(--text-muted)]">{{ painel.descricao }}</p>
      </div>

      <div class="header-actions">
        <select v-model="periodo" class="select">
          <option value="hoje">Hoje</option>
          <option value="7d">Últimos 7 dias</option>
          <option value="30d">Últimos 30 dias</option>
          <option value="mes">Mês atual</option>
          <option value="12m">Últimos 12 meses</option>
          <option value="24m">Últimos 24 meses</option>
        </select>
        <button class="btn-secondary" @click="atualizar">
          <ArrowPathIcon class="w-4 h-4" :class="carregando && 'animate-spin'"/>
          Atualizar
        </button>
        <button class="btn-primary" @click="exportar">
          <ArrowDownTrayIcon class="w-4 h-4"/>
          Exportar
        </button>
      </div>
    </header>

    <section class="summary-grid">
      <article v-for="card in painel.cards" :key="card.label" class="summary-card">
        <div class="summary-icon"><component :is="card.icon" class="w-5 h-5"/></div>
        <div>
          <span>{{ card.label }}</span>
          <strong>{{ card.valor }}</strong>
          <small :class="card.variacao?.startsWith('-') ? 'down' : 'up'">
            {{ card.variacao || 'Atualizado agora' }}
          </small>
        </div>
      </article>
    </section>

    <section class="dashboard-grid">
      <article class="card chart-card chart-main">
        <header>
          <div>
            <h2>{{ painel.graficoTitulo }}</h2>
            <p>{{ painel.graficoDescricao }}</p>
          </div>
          <span class="status-live"><i></i> Atualização automática</span>
        </header>

        <div class="bar-chart">
          <div v-for="(item,index) in painel.serie" :key="`${item.label}-${index}`" class="bar-column">
            <div class="bar-value">{{ item.valorLabel }}</div>
            <div class="bar-track">
              <div class="bar-fill" :style="{height:`${item.percentual}%`}"></div>
            </div>
            <span>{{ item.label }}</span>
          </div>
        </div>
      </article>

      <article class="card chart-card">
        <header>
          <div><h2>{{ painel.rankingTitulo }}</h2><p>{{ painel.rankingDescricao }}</p></div>
        </header>
        <div class="ranking-list">
          <div v-for="(item,index) in painel.ranking" :key="item.nome" class="ranking-row">
            <span class="ranking-position">{{ index+1 }}</span>
            <div class="ranking-copy">
              <strong>{{ item.nome }}</strong>
              <span>{{ item.detalhe }}</span>
              <div class="ranking-progress"><i :style="{width:`${item.percentual}%`}"></i></div>
            </div>
            <strong>{{ item.valor }}</strong>
          </div>
        </div>
      </article>

      <article class="card chart-card">
        <header>
          <div><h2>Indicadores operacionais</h2><p>Pontos que merecem atenção.</p></div>
        </header>
        <div class="indicator-grid">
          <div v-for="item in painel.indicadores" :key="item.label">
            <span>{{ item.label }}</span>
            <strong>{{ item.valor }}</strong>
            <small>{{ item.detalhe }}</small>
          </div>
        </div>
      </article>

      <article class="card chart-card">
        <header>
          <div><h2>Próximas ações</h2><p>Atalhos recomendados pelo painel.</p></div>
        </header>
        <div class="action-list">
          <RouterLink v-for="acao in painel.acoes" :key="acao.to" :to="acao.to">
            <component :is="acao.icon" class="w-5 h-5"/>
            <div><strong>{{ acao.label }}</strong><span>{{ acao.descricao }}</span></div>
            <ChevronRightIcon class="w-4 h-4"/>
          </RouterLink>
        </div>
      </article>
    </section>

    <section class="card dashboard-table-card">
      <header>
        <div>
          <h2>{{ painel.tabelaTitulo }}</h2>
          <p>{{ painel.tabelaDescricao }}</p>
        </div>
        <button class="btn-secondary btn-sm">Ver relatório completo</button>
      </header>

      <div class="overflow-auto">
        <table class="table">
          <thead>
            <tr>
              <th v-for="coluna in painel.colunas" :key="coluna">{{ coluna }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(linha,index) in painel.linhas" :key="index">
              <td v-for="(valor,key) in linha" :key="key">{{ valor }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, markRaw, onMounted, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import {
  ChartBarIcon, ChevronRightIcon, ArrowPathIcon, ArrowDownTrayIcon,
  CurrencyDollarIcon, ShoppingBagIcon, UserGroupIcon, CubeIcon,
  ArchiveBoxIcon, ShoppingCartIcon, UsersIcon, ClockIcon,
  PresentationChartLineIcon, TruckIcon, BanknotesIcon
} from '@heroicons/vue/24/outline'

const route = useRoute()
const toast = useToast()
const periodo = ref(route.meta.dashboardPeriodo || '30d')
const carregando = ref(false)
const dadosApi = ref(null)
const ehVendas = computed(() => route.meta.dashboardKey === 'vendas')

const icons = {
  vendas: markRaw(CurrencyDollarIcon),
  pedidos: markRaw(ShoppingBagIcon),
  vendedor: markRaw(UserGroupIcon),
  produtos: markRaw(CubeIcon),
  estoque: markRaw(ArchiveBoxIcon),
  compras: markRaw(ShoppingCartIcon),
  clientes: markRaw(UsersIcon),
  tempo: markRaw(ClockIcon),
  grafico: markRaw(PresentationChartLineIcon),
  entrega: markRaw(TruckIcon),
  financeiro: markRaw(BanknotesIcon),
}

const paineis = {
  geral: {
    titulo:'Dashboard', descricao:'Visão consolidada dos principais indicadores do ERP.',
    cards:[
      {label:'Faturamento',valor:'R$ 248.930,00',variacao:'+12,4%',icon:icons.vendas},
      {label:'Pedidos',valor:'1.284',variacao:'+8,1%',icon:icons.pedidos},
      {label:'Ticket médio',valor:'R$ 193,87',variacao:'+3,6%',icon:icons.financeiro},
      {label:'Clientes ativos',valor:'842',variacao:'+5,2%',icon:icons.clientes},
    ],
    graficoTitulo:'Evolução do faturamento',graficoDescricao:'Comparativo dos últimos períodos.',
    rankingTitulo:'Canais de venda',rankingDescricao:'Participação no faturamento.',
    tabelaTitulo:'Resumo operacional',tabelaDescricao:'Indicadores recentes por área.',
  },
  mensal: {
    titulo:'Dashboard Mensal',descricao:'Consolidação mensal de vendas, margens e operações.',
    cards:[
      {label:'Faturamento do mês',valor:'R$ 248.930,00',variacao:'+12,4%',icon:icons.vendas},
      {label:'Margem estimada',valor:'31,8%',variacao:'+2,1%',icon:icons.grafico},
      {label:'Pedidos no mês',valor:'1.284',variacao:'+8,1%',icon:icons.pedidos},
      {label:'Despesas',valor:'R$ 74.320,00',variacao:'-4,3%',icon:icons.financeiro},
    ],
    graficoTitulo:'Fechamento mensal',graficoDescricao:'Receita acumulada por semana.',
    rankingTitulo:'Categorias no mês',rankingDescricao:'Produtos com maior participação.',
    tabelaTitulo:'Resultados mensais',tabelaDescricao:'Resumo por centro de resultado.',
  },
  diario: {
    titulo:'Dashboard Diário',descricao:'Acompanhamento em tempo real da operação do dia.',
    cards:[
      {label:'Vendas hoje',valor:'R$ 18.430,00',variacao:'+9,8%',icon:icons.vendas},
      {label:'Pedidos hoje',valor:'96',variacao:'+7,2%',icon:icons.pedidos},
      {label:'Separações pendentes',valor:'14',variacao:'-3',icon:icons.estoque},
      {label:'Entregas postadas',valor:'38',variacao:'+11',icon:icons.entrega},
    ],
    graficoTitulo:'Vendas por hora',graficoDescricao:'Movimento comercial ao longo do dia.',
    rankingTitulo:'Produtos do dia',rankingDescricao:'Itens mais vendidos hoje.',
    tabelaTitulo:'Atividade do dia',tabelaDescricao:'Últimos eventos registrados.',
  },
  vendas: {
    titulo:'Dashboard de Vendas',descricao:'Análise comercial completa por período, canal e carteira.',
    cards:[
      {label:'Receita de vendas',valor:'R$ 248.930,00',variacao:'+12,4%',icon:icons.vendas},
      {label:'Pedidos aprovados',valor:'1.216',variacao:'+8,1%',icon:icons.pedidos},
      {label:'Conversão',valor:'26,4%',variacao:'+1,9%',icon:icons.grafico},
      {label:'Ticket médio',valor:'R$ 204,71',variacao:'+3,6%',icon:icons.financeiro},
    ],
    graficoTitulo:'Curva de vendas',graficoDescricao:'Evolução da receita no período.',
    rankingTitulo:'Ranking comercial',rankingDescricao:'Canais e vendedores com melhor resultado.',
    tabelaTitulo:'Vendas recentes',tabelaDescricao:'Pedidos mais recentes do período.',
  },
  pedidos: {
    titulo:'Pedidos de Venda',descricao:'Indicadores de volume, status e atendimento dos pedidos.',
    cards:[
      {label:'Pedidos abertos',valor:'184',variacao:'+14',icon:icons.pedidos},
      {label:'Aprovados',valor:'1.216',variacao:'+8,1%',icon:icons.grafico},
      {label:'Cancelados',valor:'31',variacao:'-2,4%',icon:icons.tempo},
      {label:'Prazo médio',valor:'1,8 dias',variacao:'-0,3 dia',icon:icons.entrega},
    ],
    graficoTitulo:'Pedidos por status',graficoDescricao:'Distribuição ao longo do período.',
    rankingTitulo:'Origem dos pedidos',rankingDescricao:'Canais com maior volume.',
    tabelaTitulo:'Pedidos em atenção',tabelaDescricao:'Pedidos com atraso ou pendências.',
  },
  vendedor: {
    titulo:'Venda por Vendedor',descricao:'Desempenho individual e metas da equipe comercial.',
    cards:[
      {label:'Vendedores ativos',valor:'12',variacao:'+1',icon:icons.vendedor},
      {label:'Meta atingida',valor:'78,6%',variacao:'+5,2%',icon:icons.grafico},
      {label:'Comissões',valor:'R$ 18.420,00',variacao:'+9,1%',icon:icons.financeiro},
      {label:'Novos clientes',valor:'96',variacao:'+14,3%',icon:icons.clientes},
    ],
    graficoTitulo:'Vendas da equipe',graficoDescricao:'Resultado por vendedor.',
    rankingTitulo:'Ranking de vendedores',rankingDescricao:'Receita e conversão individual.',
    tabelaTitulo:'Detalhamento por vendedor',tabelaDescricao:'Metas, vendas e carteira.',
  },
  canal: {
    titulo:'Venda Canal (24 meses)',descricao:'Histórico de vendas por canal nos últimos 24 meses.',
    cards:[
      {label:'Receita 24 meses',valor:'R$ 5,82 mi',variacao:'+21,6%',icon:icons.vendas},
      {label:'Marketplaces',valor:'46,8%',variacao:'+7,2%',icon:icons.grafico},
      {label:'Loja própria',valor:'31,5%',variacao:'+3,1%',icon:icons.pedidos},
      {label:'PDV',valor:'21,7%',variacao:'-1,8%',icon:icons.financeiro},
    ],
    graficoTitulo:'Histórico por canal',graficoDescricao:'Comparativo dos últimos 24 meses.',
    rankingTitulo:'Canais com maior crescimento',rankingDescricao:'Variação acumulada.',
    tabelaTitulo:'Desempenho dos canais',tabelaDescricao:'Receita, pedidos e ticket médio.',
  },
  previsao: {
    titulo:'Previsão de Estoque',descricao:'Projeção de ruptura e cobertura baseada no consumo.',
    cards:[
      {label:'Risco de ruptura',valor:'27 itens',variacao:'-6',icon:icons.estoque},
      {label:'Cobertura média',valor:'42 dias',variacao:'+4 dias',icon:icons.grafico},
      {label:'Estoque projetado',valor:'R$ 812 mil',variacao:'+2,8%',icon:icons.financeiro},
      {label:'Compras sugeridas',valor:'R$ 96.430',variacao:'18 pedidos',icon:icons.compras},
    ],
    graficoTitulo:'Cobertura projetada',graficoDescricao:'Dias de cobertura por período.',
    rankingTitulo:'Produtos críticos',rankingDescricao:'Maior risco de ruptura.',
    tabelaTitulo:'Projeção de reposição',tabelaDescricao:'Itens com necessidade de compra.',
  },
  produtos: {
    titulo:'Dashboard de Produtos',descricao:'Desempenho comercial e operacional do catálogo.',
    cards:[
      {label:'Produtos ativos',valor:'8.426',variacao:'+126',icon:icons.produtos},
      {label:'Com vendas',valor:'4.812',variacao:'+8,4%',icon:icons.grafico},
      {label:'Margem média',valor:'29,7%',variacao:'+1,3%',icon:icons.financeiro},
      {label:'Sem cadastro fiscal',valor:'84',variacao:'-21',icon:icons.tempo},
    ],
    graficoTitulo:'Performance do catálogo',graficoDescricao:'Produtos vendidos por período.',
    rankingTitulo:'Produtos líderes',rankingDescricao:'Receita e quantidade vendida.',
    tabelaTitulo:'Saúde do catálogo',tabelaDescricao:'Produtos com cadastro incompleto ou pendências.',
  },
  estoque: {
    titulo:'Estoque de Produtos',descricao:'Posição de estoque, valor e disponibilidade por depósito.',
    cards:[
      {label:'Valor em estoque',valor:'R$ 812.450',variacao:'+2,8%',icon:icons.financeiro},
      {label:'Itens disponíveis',valor:'38.214',variacao:'+1.420',icon:icons.estoque},
      {label:'Abaixo do mínimo',valor:'146',variacao:'-18',icon:icons.tempo},
      {label:'Depósitos',valor:'5',variacao:'Atualizado',icon:icons.produtos},
    ],
    graficoTitulo:'Valor do estoque',graficoDescricao:'Evolução do capital armazenado.',
    rankingTitulo:'Maior valor em estoque',rankingDescricao:'Produtos com maior capital imobilizado.',
    tabelaTitulo:'Posição por produto',tabelaDescricao:'Saldo, reserva e disponibilidade.',
  },
  compras: {
    titulo:'Sugestão de Compras',descricao:'Reposição sugerida com base em giro, estoque e previsão.',
    cards:[
      {label:'Itens sugeridos',valor:'128',variacao:'+23',icon:icons.compras},
      {label:'Valor previsto',valor:'R$ 96.430',variacao:'+7,1%',icon:icons.financeiro},
      {label:'Fornecedores',valor:'34',variacao:'+4',icon:icons.entrega},
      {label:'Urgentes',valor:'19',variacao:'-3',icon:icons.tempo},
    ],
    graficoTitulo:'Necessidade de compra',graficoDescricao:'Valor sugerido por semana.',
    rankingTitulo:'Fornecedores prioritários',rankingDescricao:'Maior volume sugerido.',
    tabelaTitulo:'Lista de compra sugerida',tabelaDescricao:'Produtos e quantidades recomendadas.',
  },
  semMovimento: {
    titulo:'Produtos sem Movimentação',descricao:'Itens parados e capital sem giro.',
    cards:[
      {label:'Sem movimento 90 dias',valor:'326',variacao:'-18',icon:icons.tempo},
      {label:'Capital parado',valor:'R$ 184.200',variacao:'-3,6%',icon:icons.financeiro},
      {label:'Acima de 180 dias',valor:'104',variacao:'-9',icon:icons.estoque},
      {label:'Sugestão de liquidação',valor:'72',variacao:'+12',icon:icons.grafico},
    ],
    graficoTitulo:'Tempo sem movimento',graficoDescricao:'Distribuição por faixa de dias.',
    rankingTitulo:'Maior capital parado',rankingDescricao:'Itens sem giro com maior valor.',
    tabelaTitulo:'Produtos sem movimentação',tabelaDescricao:'Saldo, custo e última saída.',
  },
  clientes: {
    titulo:'Dashboard de Clientes',descricao:'Base ativa, comportamento e valor da carteira.',
    cards:[
      {label:'Clientes ativos',valor:'842',variacao:'+5,2%',icon:icons.clientes},
      {label:'Novos no período',valor:'96',variacao:'+14,3%',icon:icons.vendedor},
      {label:'Ticket médio',valor:'R$ 284,20',variacao:'+6,4%',icon:icons.financeiro},
      {label:'Inativos',valor:'138',variacao:'-12',icon:icons.tempo},
    ],
    graficoTitulo:'Evolução da carteira',graficoDescricao:'Novos clientes e recorrência.',
    rankingTitulo:'Clientes por faturamento',rankingDescricao:'Maiores compradores do período.',
    tabelaTitulo:'Carteira de clientes',tabelaDescricao:'Atividade e potencial comercial.',
  },
  recorrentes: {
    titulo:'Clientes Novos x Recorrentes',descricao:'Comparativo de aquisição e retenção da carteira.',
    cards:[
      {label:'Novos clientes',valor:'96',variacao:'+14,3%',icon:icons.vendedor},
      {label:'Recorrentes',valor:'746',variacao:'+4,1%',icon:icons.clientes},
      {label:'Taxa de recompra',valor:'58,7%',variacao:'+3,8%',icon:icons.grafico},
      {label:'Receita recorrente',valor:'R$ 162.430',variacao:'+9,6%',icon:icons.financeiro},
    ],
    graficoTitulo:'Aquisição x recorrência',graficoDescricao:'Composição mensal da carteira.',
    rankingTitulo:'Segmentos recorrentes',rankingDescricao:'Perfis com maior recompra.',
    tabelaTitulo:'Comportamento da carteira',tabelaDescricao:'Clientes e frequência de compra.',
  },
  carteira: {
    titulo:'Atividade da Carteira',descricao:'Interações, oportunidades e clientes sem contato.',
    cards:[
      {label:'Contatos no período',valor:'428',variacao:'+18,2%',icon:icons.vendedor},
      {label:'Oportunidades abertas',valor:'86',variacao:'+11',icon:icons.grafico},
      {label:'Sem contato 30 dias',valor:'174',variacao:'-22',icon:icons.tempo},
      {label:'Potencial estimado',valor:'R$ 384 mil',variacao:'+7,4%',icon:icons.financeiro},
    ],
    graficoTitulo:'Atividade comercial',graficoDescricao:'Contatos e oportunidades por período.',
    rankingTitulo:'Carteiras mais ativas',rankingDescricao:'Vendedores e clientes movimentados.',
    tabelaTitulo:'Clientes para abordagem',tabelaDescricao:'Carteira com prioridade de contato.',
  },
}

const baseSerie = [
  {label:'Jan',percentual:42,valorLabel:'42k'}, {label:'Fev',percentual:55,valorLabel:'55k'},
  {label:'Mar',percentual:48,valorLabel:'48k'}, {label:'Abr',percentual:66,valorLabel:'66k'},
  {label:'Mai',percentual:71,valorLabel:'71k'}, {label:'Jun',percentual:84,valorLabel:'84k'},
  {label:'Jul',percentual:76,valorLabel:'76k'}, {label:'Ago',percentual:92,valorLabel:'92k'},
]
const baseRanking = [
  {nome:'Mercado Livre',detalhe:'Marketplace',valor:'R$ 86.420',percentual:88},
  {nome:'Loja própria',detalhe:'E-commerce',valor:'R$ 62.180',percentual:68},
  {nome:'PDV',detalhe:'Loja física',valor:'R$ 48.930',percentual:54},
  {nome:'Shopee',detalhe:'Marketplace',valor:'R$ 31.400',percentual:38},
]
const baseIndicadores = [
  {label:'Conversão',valor:'26,4%',detalhe:'Meta: 25%'},
  {label:'Prazo médio',valor:'1,8 dia',detalhe:'Pedido até expedição'},
  {label:'Ruptura',valor:'2,7%',detalhe:'27 itens críticos'},
  {label:'Margem',valor:'31,8%',detalhe:'Estimativa gerencial'},
]
const baseAcoes = [
  {to:'/pedidos',label:'Revisar pedidos',descricao:'Pedidos com pendências',icon:icons.pedidos},
  {to:'/estoque',label:'Conferir estoque',descricao:'Itens abaixo do mínimo',icon:icons.estoque},
  {to:'/compras',label:'Gerar compras',descricao:'Sugestões de reposição',icon:icons.compras},
  {to:'/crm',label:'Ativar carteira',descricao:'Clientes sem contato',icon:icons.clientes},
]
const baseColunas = ['Indicador','Resultado','Meta','Status']
const baseLinhas = [
  ['Faturamento','R$ 248.930','R$ 230.000','Acima da meta'],
  ['Pedidos','1.284','1.200','Acima da meta'],
  ['Margem','31,8%','30%','Acima da meta'],
  ['Prazo médio','1,8 dia','2 dias','Dentro da meta'],
]

const painel = computed(()=>{
  const key = route.meta.dashboardKey || 'geral'
  const selected = paineis[key] || paineis.geral
  const remoto = dadosApi.value || {}

  return {
    ...selected,
    cards: (remoto.cards || []).map((card,index)=>({
      ...card,
      icon: selected.cards?.[index]?.icon || icons.grafico,
    })),
    serie: remoto.serie || [],
    ranking: remoto.ranking || [],
    indicadores: remoto.indicadores || [],
    acoes: selected.acoes || baseAcoes,
    colunas: remoto.colunas || [],
    linhas: remoto.linhas || [],
  }
})

async function atualizar(mostrarMensagem=true){
  carregando.value=true
  dadosApi.value=null

  try {
    const key = route.meta.dashboardKey || 'geral'
    const {data} = await api.get(`/dashboard/analytics/${key}`, {
      params: {periodo: periodo.value},
    })
    dadosApi.value = data

    if (mostrarMensagem) toast.success('Dashboard atualizado com dados do banco.')
  } catch (error) {
    toast.error(error.response?.data?.message || 'Não foi possível carregar os dados do dashboard.')
  } finally {
    carregando.value=false
  }
}
function exportar(){
  toast.success('Relatório preparado para exportação.')
}

watch(
  () => route.meta.dashboardKey,
  () => {
    const periodoPadrao = route.meta.dashboardPeriodo || '30d'
    if (periodo.value !== periodoPadrao) {
      periodo.value = periodoPadrao
      return
    }
    atualizar(false)
  },
)

watch(periodo, () => atualizar(false))

onMounted(() => atualizar(false))
</script>

<style scoped>
.dashboards-page{
  --dash-cyan:#25f4d0;--dash-pink:#ff4fa3;--dash-purple:#9b5cff;--dash-yellow:#f7e64b;
  position:relative;isolation:isolate;display:flex;flex-direction:column;gap:16px;
  min-height:calc(100vh - 110px);margin:-20px;padding:22px;
  color:#f8f4ff;background:
    radial-gradient(circle at 12% 8%,rgba(133,31,255,.2),transparent 27%),
    radial-gradient(circle at 88% 35%,rgba(255,31,139,.11),transparent 26%),
    linear-gradient(145deg,#0b0016 0%,#160025 48%,#090010 100%);
}
.dashboards-page:before{content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;opacity:.18;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:32px 32px}
.page-header{padding:18px 20px;border:1px solid rgba(186,115,255,.18);border-radius:16px;background:linear-gradient(100deg,rgba(44,2,72,.92),rgba(19,1,35,.86));box-shadow:0 18px 45px rgba(0,0,0,.28)}
.dashboard-breadcrumb{display:flex;align-items:center;gap:6px;font-size:.64rem;letter-spacing:.08em;text-transform:uppercase;color:#b89bc9;margin-bottom:6px}.dashboard-breadcrumb strong{color:var(--dash-cyan)}
.page-title{color:#fff;font-size:1.28rem}.header-actions{display:flex;gap:8px;align-items:center}.header-actions :deep(.select){border-color:rgba(176,94,255,.35);background:#180329;color:#fff}.header-actions :deep(.btn-secondary){border-color:rgba(176,94,255,.4);background:rgba(89,22,130,.25);color:#eadcff}.header-actions :deep(.btn-primary){border:0;background:linear-gradient(110deg,#8d35ff,#e834b5);box-shadow:0 7px 22px rgba(174,47,255,.28)}
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.summary-card{position:relative;overflow:hidden;display:flex;align-items:center;gap:13px;min-height:112px;border:1px solid rgba(190,108,255,.18);border-radius:14px;background:linear-gradient(145deg,rgba(47,5,73,.94),rgba(20,2,35,.96));padding:16px;box-shadow:0 13px 32px rgba(0,0,0,.24);transition:.2s ease}.summary-card:hover{transform:translateY(-3px);border-color:rgba(70,244,218,.35);box-shadow:0 16px 38px rgba(0,0,0,.34)}.summary-card:after{content:"";position:absolute;right:-24px;bottom:-36px;width:110px;height:110px;border-radius:50%;background:radial-gradient(circle,rgba(155,92,255,.2),transparent 66%)}
.summary-icon{position:relative;width:58px;height:58px;flex:none;border-radius:50%;display:grid;place-items:center;color:var(--dash-cyan);background:radial-gradient(circle at center,#180428 53%,transparent 55%),conic-gradient(var(--dash-cyan) 0 72%,rgba(37,244,208,.12) 72%);filter:drop-shadow(0 0 8px rgba(37,244,208,.22))}.summary-card:nth-child(2) .summary-icon{color:var(--dash-pink);background:radial-gradient(circle at center,#180428 53%,transparent 55%),conic-gradient(var(--dash-pink) 0 64%,rgba(255,79,163,.12) 64%)}.summary-card:nth-child(3) .summary-icon{color:var(--dash-yellow);background:radial-gradient(circle at center,#180428 53%,transparent 55%),conic-gradient(var(--dash-yellow) 0 79%,rgba(247,230,75,.12) 79%)}.summary-card:nth-child(4) .summary-icon{color:var(--dash-purple);background:radial-gradient(circle at center,#180428 53%,transparent 55%),conic-gradient(var(--dash-purple) 0 68%,rgba(155,92,255,.12) 68%)}
.summary-card span,.summary-card strong,.summary-card small{display:block}.summary-card span{font-size:.62rem;letter-spacing:.06em;text-transform:uppercase;color:#a990ba}.summary-card strong{font-size:1.12rem;color:#fff;margin-top:4px}.summary-card small{font-size:.57rem;margin-top:4px;color:#a990ba}.summary-card small.up{color:#48f3c7}.summary-card small.down{color:#ff70a9}
.dashboard-grid{display:grid;grid-template-columns:minmax(0,1.75fr) minmax(280px,1fr);gap:12px}.dashboards-page :deep(.card){border:1px solid rgba(188,108,255,.17);border-radius:14px;background:linear-gradient(150deg,rgba(40,4,63,.94),rgba(15,2,27,.97));box-shadow:0 14px 34px rgba(0,0,0,.24)}.chart-card{padding:17px}.chart-card header,.dashboard-table-card>header{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.chart-card h2,.dashboard-table-card h2{font-size:.78rem;font-weight:800;letter-spacing:.045em;text-transform:uppercase;color:#f9f4ff}.chart-card header p,.dashboard-table-card header p{font-size:.59rem;color:#9b82ab;margin-top:3px}.status-live{display:flex;align-items:center;gap:6px;font-size:.55rem;color:#48f3c7}.status-live i{width:7px;height:7px;border-radius:50%;background:#48f3c7;box-shadow:0 0 10px #48f3c7;animation:pulse 1.7s infinite}
.bar-chart{height:260px;display:flex;align-items:flex-end;gap:12px;padding:30px 5px 0;border-bottom:1px solid rgba(187,112,255,.14);background:repeating-linear-gradient(to bottom,transparent 0,transparent 50px,rgba(190,113,255,.07) 51px)}.bar-column{flex:1;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:6px}.bar-value{font-size:.53rem;color:#b49bc3}.bar-track{width:100%;max-width:38px;height:182px;border-radius:7px 7px 2px 2px;background:rgba(120,62,151,.16);display:flex;align-items:flex-end;overflow:hidden}.bar-fill{width:100%;border-radius:7px 7px 2px 2px;background:linear-gradient(180deg,var(--dash-cyan),#7055ff 58%,#dc3fa8);box-shadow:0 0 16px rgba(37,244,208,.24)}.bar-column:nth-child(even) .bar-fill{background:linear-gradient(180deg,var(--dash-pink),#8f45ff)}.bar-column>span{font-size:.56rem;color:#9d85ac}
.ranking-list{display:flex;flex-direction:column;gap:12px;margin-top:16px}.ranking-row{display:flex;align-items:center;gap:10px}.ranking-position{width:24px;height:24px;border:1px solid rgba(37,244,208,.28);border-radius:50%;display:grid;place-items:center;background:rgba(37,244,208,.08);color:var(--dash-cyan);font-size:.57rem}.ranking-copy{flex:1}.ranking-copy strong,.ranking-copy span{display:block}.ranking-copy strong{font-size:.64rem;color:#f7f0fb}.ranking-copy span{font-size:.53rem;color:#8f789e}.ranking-row>strong{font-size:.61rem;color:#e6d9ed}.ranking-progress{height:4px;background:rgba(126,74,151,.18);border-radius:4px;margin-top:5px;overflow:hidden}.ranking-progress i{display:block;height:100%;border-radius:4px;background:linear-gradient(90deg,var(--dash-pink),var(--dash-cyan));box-shadow:0 0 8px rgba(37,244,208,.28)}
.indicator-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:9px;margin-top:15px}.indicator-grid div{position:relative;border:1px solid rgba(175,94,255,.2);border-radius:11px;padding:13px;background:rgba(31,5,48,.62)}.indicator-grid div:before{content:"";position:absolute;left:0;top:12px;bottom:12px;width:2px;border-radius:4px;background:linear-gradient(var(--dash-pink),var(--dash-cyan))}.indicator-grid span,.indicator-grid strong,.indicator-grid small{display:block}.indicator-grid span{font-size:.56rem;color:#a48caf}.indicator-grid strong{font-size:.9rem;color:#fff;margin-top:4px}.indicator-grid small{font-size:.51rem;color:#8b7398;margin-top:3px}
.action-list{display:flex;flex-direction:column;gap:8px;margin-top:13px}.action-list a{display:flex;align-items:center;gap:10px;border:1px solid rgba(180,102,255,.17);border-radius:10px;padding:10px;color:#d9cae3;background:rgba(44,8,65,.45);transition:.2s}.action-list a:hover{transform:translateX(3px);border-color:rgba(37,244,208,.38);background:rgba(50,13,75,.75);color:var(--dash-cyan)}.action-list a div{flex:1}.action-list strong,.action-list span{display:block}.action-list strong{font-size:.62rem}.action-list span{font-size:.53rem;color:#8e779b}.dashboard-table-card{overflow:hidden}.dashboard-table-card>header{padding:16px;border-bottom:1px solid rgba(183,105,255,.15)}.dashboard-table-card :deep(.table){--table-border:rgba(181,102,255,.12)}.dashboard-table-card :deep(th){background:rgba(55,9,79,.7);color:#bda7ca}.dashboard-table-card :deep(td){border-color:rgba(181,102,255,.1);color:#dfd3e7}.dashboard-table-card :deep(tr:hover td){background:rgba(111,39,154,.14)}
@keyframes pulse{50%{opacity:.45;transform:scale(.75)}}
@media(max-width:1150px){.summary-grid{grid-template-columns:repeat(2,1fr)}.dashboard-grid{grid-template-columns:1fr}}
@media(max-width:700px){.dashboards-page{margin:-12px;padding:14px}.page-header{align-items:flex-start}.summary-grid{grid-template-columns:1fr}.header-actions{width:100%;flex-wrap:wrap}.header-actions>*{flex:1}.bar-chart{gap:5px}.summary-card{min-height:96px}}

/* Identidade exclusiva do Dashboard de Vendas: grafite + vermelho/coral */
.sales-dashboard{
  --dash-cyan:#ff4f63;--dash-pink:#ff334f;--dash-purple:#c51f3b;--dash-yellow:#ff8b5c;
  background:
    radial-gradient(circle at 16% 3%,rgba(225,28,66,.17),transparent 27%),
    radial-gradient(circle at 90% 42%,rgba(255,69,84,.09),transparent 25%),
    linear-gradient(145deg,#090b12 0%,#10111b 48%,#080910 100%);
}
.sales-dashboard:before{opacity:.1;background-size:26px 26px}
.sales-dashboard .page-header{border-color:rgba(255,61,91,.22);background:linear-gradient(100deg,rgba(28,12,23,.96),rgba(11,12,20,.92));box-shadow:inset 0 -1px rgba(255,52,80,.28),0 18px 45px rgba(0,0,0,.3)}
.sales-dashboard .dashboard-breadcrumb strong{color:#ff5068}.sales-dashboard .header-actions :deep(.select){border-color:rgba(255,70,98,.28);background:#15121b}.sales-dashboard .header-actions :deep(.btn-secondary){border-color:rgba(255,70,98,.32);background:rgba(129,24,46,.18);color:#ffdce2}.sales-dashboard .header-actions :deep(.btn-primary){background:linear-gradient(110deg,#d51f43,#ff5360);box-shadow:0 7px 22px rgba(229,37,69,.24)}
.sales-dashboard .summary-grid{grid-template-columns:1.15fr repeat(3,1fr);gap:8px}.sales-dashboard .summary-card{min-height:94px;border-color:rgba(255,67,93,.17);border-radius:6px;background:linear-gradient(150deg,rgba(25,20,30,.98),rgba(12,13,20,.98));box-shadow:inset 0 1px rgba(255,255,255,.02),0 10px 26px rgba(0,0,0,.25)}.sales-dashboard .summary-card:first-child{border-left:3px solid #ff3654;background:linear-gradient(135deg,rgba(86,18,39,.38),rgba(14,14,22,.98))}.sales-dashboard .summary-card:hover{border-color:rgba(255,76,100,.45)}
.sales-dashboard .summary-icon{width:48px;height:48px;color:#ff5268;background:radial-gradient(circle at center,#14111a 53%,transparent 55%),conic-gradient(#ff3d5c 0 76%,rgba(255,61,92,.12) 76%);filter:drop-shadow(0 0 8px rgba(255,54,84,.2))}.sales-dashboard .summary-card:nth-child(2) .summary-icon,.sales-dashboard .summary-card:nth-child(3) .summary-icon,.sales-dashboard .summary-card:nth-child(4) .summary-icon{color:#ff6b68;background:radial-gradient(circle at center,#14111a 53%,transparent 55%),conic-gradient(#ff4b61 0 68%,rgba(255,61,92,.1) 68%)}
.sales-dashboard .summary-card span{color:#b798a1}.sales-dashboard .summary-card small.up{color:#ff7a83}.sales-dashboard .summary-card small.down{color:#ffb05f}
.sales-dashboard .dashboard-grid{grid-template-columns:minmax(0,1.45fr) minmax(260px,.85fr);gap:8px}.sales-dashboard :deep(.card){border-color:rgba(255,67,93,.14);border-radius:6px;background:linear-gradient(150deg,rgba(24,20,29,.97),rgba(10,11,17,.99));box-shadow:0 10px 28px rgba(0,0,0,.25)}.sales-dashboard .chart-card{padding:13px}.sales-dashboard .chart-card h2,.sales-dashboard .dashboard-table-card h2{color:#ff5a70}.sales-dashboard .chart-card header p,.sales-dashboard .dashboard-table-card header p{color:#8e7a82}.sales-dashboard .status-live{color:#ff6878}.sales-dashboard .status-live i{background:#ff3c59;box-shadow:0 0 10px #ff3c59}
.sales-dashboard .bar-chart{height:218px;gap:9px;padding-top:24px;border-bottom-color:rgba(255,65,91,.15);background:repeating-linear-gradient(to bottom,transparent 0,transparent 41px,rgba(255,63,91,.065) 42px)}.sales-dashboard .bar-track{height:147px;max-width:none;border-radius:2px;background:rgba(255,69,91,.07)}.sales-dashboard .bar-fill,.sales-dashboard .bar-column:nth-child(even) .bar-fill{border-radius:2px 2px 0 0;background:linear-gradient(180deg,#ff5a65,#d92349);box-shadow:0 0 9px rgba(255,45,77,.18)}
.sales-dashboard .ranking-position{border-color:rgba(255,72,96,.3);background:rgba(255,54,84,.09);color:#ff6476}.sales-dashboard .ranking-progress i{background:linear-gradient(90deg,#b91f3e,#ff5862);box-shadow:0 0 7px rgba(255,50,79,.2)}.sales-dashboard .indicator-grid div{border-color:rgba(255,69,94,.16);border-radius:5px;background:rgba(32,19,27,.58)}.sales-dashboard .indicator-grid div:before{background:linear-gradient(#ff3857,#ff8a59)}
.sales-dashboard .action-list a{border-color:rgba(255,68,93,.14);border-radius:5px;background:rgba(38,20,28,.42)}.sales-dashboard .action-list a:hover{border-color:rgba(255,74,97,.42);background:rgba(86,24,39,.34);color:#ff6374}.sales-dashboard .dashboard-table-card>header{border-bottom-color:rgba(255,65,91,.14)}.sales-dashboard .dashboard-table-card :deep(th){background:rgba(71,19,34,.52);color:#d6adb7}.sales-dashboard .dashboard-table-card :deep(td){border-color:rgba(255,67,92,.08);color:#e0d2d6}.sales-dashboard .dashboard-table-card :deep(tr:hover td){background:rgba(132,28,51,.12)}
@media(max-width:1150px){.sales-dashboard .summary-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:700px){.sales-dashboard .summary-grid{grid-template-columns:1fr}}
</style>
