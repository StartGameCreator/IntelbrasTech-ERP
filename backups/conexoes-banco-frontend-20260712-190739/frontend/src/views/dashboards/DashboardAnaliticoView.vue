<template>
  <div class="dashboards-page">
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
    cards: (remoto.cards || selected.cards || []).map((card,index)=>({
      ...card,
      icon: selected.cards?.[index]?.icon || icons.grafico,
    })),
    serie: remoto.serie || selected.serie || baseSerie,
    ranking: remoto.ranking || selected.ranking || baseRanking,
    indicadores: remoto.indicadores || selected.indicadores || baseIndicadores,
    acoes: selected.acoes || baseAcoes,
    colunas: remoto.colunas || selected.colunas || baseColunas,
    linhas: remoto.linhas || selected.linhas || baseLinhas,
  }
})

async function atualizar(mostrarMensagem=true){
  carregando.value=true

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
  () => [route.meta.dashboardKey, periodo.value],
  () => atualizar(false),
)

onMounted(() => atualizar(false))
</script>

<style scoped>
.dashboards-page{display:flex;flex-direction:column;gap:16px}.dashboard-breadcrumb{display:flex;align-items:center;gap:6px;font-size:.62rem;color:var(--text-muted);margin-bottom:4px}.header-actions{display:flex;gap:8px;align-items:center}.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.summary-card{display:flex;align-items:center;gap:12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-card);padding:14px}.summary-icon{width:38px;height:38px;border-radius:9px;display:grid;place-items:center;background:rgba(59,130,246,.12);color:#60a5fa}.summary-card span,.summary-card strong,.summary-card small{display:block}.summary-card span{font-size:.61rem;color:var(--text-muted)}.summary-card strong{font-size:1.05rem;margin-top:3px}.summary-card small{font-size:.55rem;margin-top:2px;color:var(--text-muted)}.summary-card small.up{color:#4ade80}.summary-card small.down{color:#f87171}.dashboard-grid{display:grid;grid-template-columns:2fr 1fr;gap:10px}.chart-card{padding:14px}.chart-card header,.dashboard-table-card>header{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.chart-card h2,.dashboard-table-card h2{font-size:.76rem;font-weight:800}.chart-card header p,.dashboard-table-card header p{font-size:.58rem;color:var(--text-muted);margin-top:2px}.status-live{display:flex;align-items:center;gap:5px;font-size:.54rem;color:#4ade80}.status-live i{width:6px;height:6px;border-radius:50%;background:#22c55e}.bar-chart{height:245px;display:flex;align-items:flex-end;gap:12px;padding-top:30px}.bar-column{flex:1;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:5px}.bar-value{font-size:.52rem;color:var(--text-muted)}.bar-track{width:100%;max-width:34px;height:175px;border-radius:7px;background:var(--bg-elevated);display:flex;align-items:flex-end;overflow:hidden}.bar-fill{width:100%;border-radius:7px 7px 0 0;background:linear-gradient(180deg,#60a5fa,#2563eb)}.bar-column>span{font-size:.55rem;color:var(--text-muted)}.ranking-list{display:flex;flex-direction:column;gap:11px;margin-top:15px}.ranking-row{display:flex;align-items:center;gap:9px}.ranking-position{width:21px;height:21px;border-radius:50%;display:grid;place-items:center;background:var(--bg-elevated);font-size:.55rem}.ranking-copy{flex:1}.ranking-copy strong,.ranking-copy span{display:block}.ranking-copy strong{font-size:.63rem}.ranking-copy span{font-size:.53rem;color:var(--text-muted)}.ranking-row>strong{font-size:.61rem}.ranking-progress{height:3px;background:var(--bg-elevated);border-radius:4px;margin-top:4px;overflow:hidden}.ranking-progress i{display:block;height:100%;background:#22c55e}.indicator-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:14px}.indicator-grid div{border:1px solid var(--border);border-radius:8px;padding:10px}.indicator-grid span,.indicator-grid strong,.indicator-grid small{display:block}.indicator-grid span{font-size:.55rem;color:var(--text-muted)}.indicator-grid strong{font-size:.82rem;margin-top:3px}.indicator-grid small{font-size:.5rem;color:var(--text-muted);margin-top:2px}.action-list{display:flex;flex-direction:column;gap:7px;margin-top:12px}.action-list a{display:flex;align-items:center;gap:9px;border:1px solid var(--border);border-radius:8px;padding:9px}.action-list a:hover{border-color:#3b82f6;background:var(--bg-elevated)}.action-list a div{flex:1}.action-list strong,.action-list span{display:block}.action-list strong{font-size:.61rem}.action-list span{font-size:.52rem;color:var(--text-muted)}.dashboard-table-card{overflow:hidden}.dashboard-table-card>header{padding:14px;border-bottom:1px solid var(--border)}@media(max-width:1000px){.summary-grid{grid-template-columns:repeat(2,1fr)}.dashboard-grid{grid-template-columns:1fr}}@media(max-width:650px){.summary-grid{grid-template-columns:1fr}.header-actions{flex-wrap:wrap}.bar-chart{gap:5px}}
</style>
