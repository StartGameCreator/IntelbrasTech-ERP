import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior: () => ({ top: 0 }),
  routes: [
    {
      path: '/',
      component: () => import('@/components/portal/PortalLayout.vue'),
      meta: { public: true },
      children: [
        { path: '', name: 'portal.home', component: () => import('@/views/portal/PortalHomeView.vue'), meta: { title: 'Tecnologia e Energia' } },
        { path: 'energia-solar', name: 'portal.solar', component: () => import('@/views/portal/PortalSolarView.vue'), meta: { title: 'Energia Solar' } },
        { path: 'solucoes/:slug', name: 'portal.solution', component: () => import('@/views/portal/PortalSolutionView.vue'), meta: { title: 'Soluções' } },
        { path: 'assistencia-tecnica', name: 'portal.assistance', component: () => import('@/views/portal/PortalAssistanceView.vue'), meta: { title: 'Assistência Técnica' } },
        { path: 'projetos', name: 'portal.projects', component: () => import('@/views/portal/PortalProjectsView.vue'), meta: { title: 'Projetos' } },
        { path: 'sobre', name: 'portal.about', component: () => import('@/views/portal/PortalAboutView.vue'), meta: { title: 'Sobre' } },
        { path: 'contato', name: 'portal.contact', component: () => import('@/views/portal/PortalContactView.vue'), meta: { title: 'Contato' } },
        { path: 'atendimento', name: 'portal.atendimento', component: () => import('@/views/portal/PortalAtendimentoView.vue'), meta: { title: 'Abrir chamado' } },
        { path: 'atendimento/:token', name: 'portal.atendimento.acompanhar', component: () => import('@/views/portal/PortalChamadoAcompanhamentoView.vue'), meta: { title: 'Acompanhar chamado' } },
        { path: 'privacidade', name: 'portal.privacy', component: () => import('@/views/portal/PortalPrivacyView.vue'), meta: { title: 'Privacidade' } },
      ],
    },
    {
      path: '/cadastre-se',
      name: 'marketplace.cadastre-se',
      component: () => import('@/views/marketplace-tecnicos/CadastroMarketplaceView.vue'),
      meta: { title: 'Cadastre-se â€” IntelbrasTech', public: true },
    },
    { path: '/solicite-um-tecnico', redirect: '/atendimento' },
    
    // Ã¢â€â‚¬Ã¢â€â‚¬ Auth Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { guest: true, title: 'Login' },
    },
    {
      path: '/esqueci-senha',
      name: 'esqueci-senha',
      component: () => import('@/views/auth/EsqueciSenhaView.vue'),
      meta: { guest: true, title: 'Redefinir senha' },
    },
    {
      path: '/auth/social/callback',
      name: 'auth.social.callback',
      component: () => import('@/views/auth/SocialCallbackView.vue'),
      meta: { guest: true, title: 'Conectando conta' },
    },

    // Ã¢â€â‚¬Ã¢â€â‚¬ App Layout Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
    {
      path: '/',
      component: () => import('@/components/layout/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '',        redirect: '/pdv' },
        // â”€â”€ Dashboards analÃ­ticos
        {
          path: 'dashboards',
          name: 'dashboards',
          redirect: '/dashboard',
        },
        {
          path: 'dashboards/mensal',
          name: 'dashboards.mensal',
          redirect: { path: '/dashboard', query: { secao: 'mensal' } },
        },
        {
          path: 'dashboards/diario',
          name: 'dashboards.diario',
          redirect: { path: '/dashboard', query: { secao: 'diario' } },
        },
        {
          path: 'dashboards/vendas',
          name: 'dashboards.vendas',
          redirect: { path: '/dashboard', query: { secao: 'vendas' } },
        },
        {
          path: 'dashboards/pedidos-venda',
          name: 'dashboards.pedidos',
          redirect: { path: '/dashboard', query: { secao: 'pedidos' } },
        },
        {
          path: 'dashboards/venda-vendedor',
          name: 'dashboards.vendedor',
          redirect: { path: '/dashboard', query: { secao: 'vendedor' } },
        },
        {
          path: 'dashboards/venda-canal-24-meses',
          name: 'dashboards.canal',
          redirect: { path: '/dashboard', query: { secao: 'canal' } },
        },
        {
          path: 'dashboards/previsao-estoque',
          name: 'dashboards.previsao',
          redirect: { path: '/dashboard', query: { secao: 'previsao' } },
        },
        {
          path: 'dashboards/produtos',
          name: 'dashboards.produtos',
          redirect: { path: '/dashboard', query: { secao: 'produtos' } },
        },
        {
          path: 'dashboards/estoque-produtos',
          name: 'dashboards.estoque',
          redirect: { path: '/dashboard', query: { secao: 'estoque' } },
        },
        {
          path: 'dashboards/sugestao-compras',
          name: 'dashboards.compras',
          redirect: { path: '/dashboard', query: { secao: 'compras' } },
        },
        {
          path: 'dashboards/sem-movimentacao',
          name: 'dashboards.sem-movimento',
          redirect: { path: '/dashboard', query: { secao: 'semMovimento' } },
        },
        {
          path: 'dashboards/clientes',
          name: 'dashboards.clientes',
          redirect: { path: '/dashboard', query: { secao: 'clientes' } },
        },
        {
          path: 'dashboards/novos-recorrentes',
          name: 'dashboards.recorrentes',
          redirect: { path: '/dashboard', query: { secao: 'recorrentes' } },
        },
        {
          path: 'dashboards/atividade-carteira',
          name: 'dashboards.carteira',
          redirect: { path: '/dashboard', query: { secao: 'carteira' } },
        },
        {
          path: 'dashboard',
          name: 'dashboard',
          component: () => import('@/views/dashboard/DashboardUnificadoView.vue'),
          meta: { title: 'Dashboard Executivo', icon: 'ChartBarIcon' },
        },

        // Ã¢â€â‚¬Ã¢â€â‚¬ Clientes Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        
        {
          path: 'comercial',
          name: 'comercial',
          component: () => import('@/views/comercial/ComercialView.vue'),
          meta: { title: 'Central Comercial' },
        },

        {
          path: 'pdv',
          name: 'pdv',
          component: () => import('@/views/pdv/PdvView.vue'),
          meta: { title: 'Frente de Caixa' },
        },

        {
          path: 'pdv/vendas',
          name: 'pdv-historico',
          component: () => import('@/views/pdv/PdvHistoricoView.vue'),
          meta: { title: 'Vendas do PDV' },
        },

        {
          path: 'pdv/devolucoes',
          name: 'pdv-devolucoes',
          component: () => import('@/views/pdv/PdvDevolucoesView.vue'),
          meta: { title: 'Devoluções e Pós-venda' },
        },

        {
          path: 'pdv/fiscal',
          name: 'pdv-fiscal',
          component: () => import('@/views/pdv/PdvFiscalView.vue'),
          meta: { title: 'NFC-e do PDV' },
        },
        {
          path: 'clientes',
          name: 'clientes',
          component: () => import('@/views/clientes/ClientesView.vue'),
          meta: { title: 'Clientes', permissao: 'clientes.view' },
        },
        {
          path: 'clientes/novo',
          name: 'clientes.novo',
          component: () => import('@/views/clientes/ClienteFormView.vue'),
          meta: { title: 'Novo Cliente', permissao: 'clientes.create' },
        },
        {
          path: 'clientes/:id',
          name: 'clientes.show',
          component: () => import('@/views/clientes/ClienteShowView.vue'),
          meta: { title: 'Cliente', permissao: 'clientes.view' },
        },
        {
          path: 'clientes/:id/editar',
          name: 'clientes.editar',
          component: () => import('@/views/clientes/ClienteFormView.vue'),
          meta: { title: 'Editar Cliente', permissao: 'clientes.edit' },
        },
        {
          path: 'clientes/mapa',
          name: 'clientes.mapa',
          component: () => import('@/views/clientes/ClienteMapaView.vue'),
          meta: { title: 'Mapa de Clientes', permissao: 'clientes.view' },
        },

        // Ã¢â€â‚¬Ã¢â€â‚¬ Fornecedores Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'fornecedores',
          name: 'fornecedores',
          component: () => import('@/views/fornecedores/FornecedoresView.vue'),
          meta: { title: 'Fornecedores', permissao: 'fornecedores.view' },
        },
        {
          path: 'fornecedores/novo',
          name: 'fornecedores.novo',
          component: () => import('@/views/fornecedores/FornecedorFormView.vue'),
          meta: { title: 'Novo Fornecedor', permissao: 'fornecedores.create' },
        },
        {
          path: 'fornecedores/:id',
          name: 'fornecedores.show',
          component: () => import('@/views/fornecedores/FornecedorShowView.vue'),
          meta: { title: 'Fornecedor', permissao: 'fornecedores.view' },
        },
        {
          path: 'fornecedores/:id/editar',
          name: 'fornecedores.editar',
          component: () => import('@/views/fornecedores/FornecedorFormView.vue'),
          meta: { title: 'Editar Fornecedor', permissao: 'fornecedores.edit' },
        },

        // Ã¢â€â‚¬Ã¢â€â‚¬ Produtos Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'produtos',
          name: 'produtos',
          component: () => import('@/views/produtos/ProdutosView.vue'),
          meta: { title: 'Produtos', permissao: 'produtos.view' },
        },
        {
          path: 'produtos/novo',
          name: 'produtos.novo',
          component: () => import('@/views/produtos/ProdutoFormView.vue'),
          meta: { title: 'Novo Produto', permissao: 'produtos.create' },
        },
        {
          path: 'produtos/:id',
          name: 'produtos.show',
          component: () => import('@/views/produtos/ProdutoShowView.vue'),
          meta: { title: 'Produto', permissao: 'produtos.view' },
        },
        {
          path: 'produtos/:id/editar',
          name: 'produtos.editar',
          component: () => import('@/views/produtos/ProdutoFormView.vue'),
          meta: { title: 'Editar Produto', permissao: 'produtos.edit' },
        },

        // Ã¢â€â‚¬Ã¢â€â‚¬ Pedidos Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'pedidos',
          name: 'pedidos',
          component: () => import('@/views/pedidos/PedidosView.vue'),
          meta: { title: 'Pedidos', permissao: 'pedidos.view' },
        },
        {
          path: 'pedidos/novo',
          name: 'pedidos.novo',
          component: () => import('@/views/pedidos/PedidoFormView.vue'),
          meta: { title: 'Novo Pedido', permissao: 'pedidos.create' },
        },
        {
          path: 'pedidos/:id',
          name: 'pedidos.show',
          component: () => import('@/views/pedidos/PedidoShowView.vue'),
          meta: { title: 'Pedido', permissao: 'pedidos.view' },
        },
        {
          path: 'pedidos/kanban',
          name: 'pedidos.kanban',
          component: () => import('@/views/pedidos/PedidoKanbanView.vue'),
          meta: { title: 'Kanban de Pedidos', permissao: 'pedidos.view' },
        },

        // Ã¢â€â‚¬Ã¢â€â‚¬ Estoque Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'estoque',
          name: 'estoque',
          component: () => import('@/views/estoque/EstoqueView.vue'),
          meta: { title: 'Estoque', permissao: 'estoque.view' },
        },
        {
          path: 'estoque/movimentacoes',
          name: 'estoque.movimentacoes',
          component: () => import('@/views/estoque/MovimentacoesView.vue'),
          meta: { title: 'Movimentações', permissao: 'estoque.view' },
        },
        {
          path: 'estoque/inventario',
          name: 'estoque.inventario',
          component: () => import('@/views/estoque/InventarioView.vue'),
          meta: { title: 'Inventário', permissao: 'estoque.edit' },
        },

        // Ã¢â€â‚¬Ã¢â€â‚¬ Compras Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'compras',
          name: 'compras',
          component: () => import('@/views/compras/ComprasView.vue'),
          meta: { title: 'Compras', permissao: 'compras.view' },
        },
        {
          path: 'compras/novo',
          name: 'compras.novo',
          component: () => import('@/views/compras/CompraFormView.vue'),
          meta: { title: 'Novo Pedido de Compra', permissao: 'compras.create' },
        },
        {
          path: 'compras/:id',
          name: 'compras.show',
          component: () => import('@/views/compras/CompraShowView.vue'),
          meta: { title: 'Pedido de Compra', permissao: 'compras.view' },
        },

        // Ã¢â€â‚¬Ã¢â€â‚¬ Financeiro Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'financeiro/contas-receber',
          name: 'financeiro.receber',
          component: () => import('@/views/financeiro/ContasReceberView.vue'),
          meta: { title: 'Contas a Receber', permissao: 'financeiro.view' },
        },
        {
          path: 'financeiro/contas-pagar',
          name: 'financeiro.pagar',
          component: () => import('@/views/financeiro/ContasPagarView.vue'),
          meta: { title: 'Contas a Pagar', permissao: 'financeiro.view' },
        },
        {
          path: 'financeiro/fluxo-caixa',
          name: 'financeiro.fluxo',
          component: () => import('@/views/financeiro/FluxoCaixaView.vue'),
          meta: { title: 'Fluxo de Caixa', permissao: 'financeiro.view' },
        },
        {
          path: 'financeiro/dre',
          name: 'financeiro.dre',
          component: () => import('@/views/financeiro/DREView.vue'),
          meta: { title: 'DRE', permissao: 'financeiro.view' },
        },

        // Ã¢â€â‚¬Ã¢â€â‚¬ Fiscal Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'fiscal/certificados',
          name: 'fiscal.certificados',
          component: () => import('@/views/fiscal/CertificadoDigitalView.vue'),
          meta: { title: 'Certificado Digital', permissao: 'fiscal.view' },
        },
        {
          path: 'fiscal/contador',
          name: 'fiscal.contador',
          component: () => import('@/views/fiscal/ContadorView.vue'),
          meta: { title: 'Espaço do Contador', permissao: 'fiscal.view' },
        },
        {
          path: 'fiscal/central',
          name: 'fiscal.central',
          component: () => import('@/views/fiscal/CentralFiscalView.vue'),
          meta: { title: 'Central Fiscal', permissao: 'fiscal.view' },
        },
        {
          path: 'fiscal/nfe',
          name: 'fiscal.nfe',
          component: () => import('@/views/fiscal/NFeView.vue'),
          meta: { title: 'NF-e', permissao: 'fiscal.view' },
        },
        {
          path: 'fiscal/nfse',
          name: 'fiscal.nfse',
          component: () => import('@/views/fiscal/NfseView.vue'),
          meta: { title: 'NFS-e de Serviços', permissao: 'fiscal.view' },
        },
        {
          path: 'fiscal/nfe/configuracoes',
          name: 'fiscal.nfe.configuracoes',
          component: () => import('@/views/fiscal/NFeConfiguracoesView.vue'),
          meta: { title: 'Configurações NF-e e NFC-e', permissao: 'fiscal.view' },
        },        {
          path: 'fiscal/nfe/:id',
          name: 'fiscal.nfe.show',
          component: () => import('@/views/fiscal/NFeShowView.vue'),
          meta: { title: 'NF-e', permissao: 'fiscal.view' },
        },

        // Ã¢â€â‚¬Ã¢â€â‚¬ CRM Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'crm/busca-cnpj',
          name: 'crm.cnpj',
          component: () => import('@/views/crm/CnpjBuscaView.vue'),
          meta: { title: 'Busca CNPJ', permissao: 'crm.view' },
        },
        {
          path: 'crm/google-empresas',
          name: 'crm.google-empresas',
          component: () => import('@/views/crm/GoogleBusinessBuscaView.vue'),
          meta: { title: 'Busca Google Empresas', permissao: 'crm.view' },
        },
                  {
                    path: 'crm',
          name: 'crm',
          component: () => import('@/views/crm/CRMView.vue'),
          meta: { title: 'CRM', permissao: 'crm.view' },
        },

        {
          path: 'marketplace-tecnicos',
          name: 'marketplace-tecnicos',
          redirect: { name: 'gestao-equipe', query: { aba: 'cobertura' } },
          meta: { title: 'Gestão de Equipe' },
        },
        {
          path: 'gestao-equipe',
          name: 'gestao-equipe',
          component: () => import('@/views/gestao/GestaoEquipeView.vue'),
          meta: { title: 'Gestão de Equipe' },
        },
        {
          path: 'tecnico/operacao',
          name: 'tecnico.operacao',
          component: () => import('@/views/tecnico/OperacaoTecnicaView.vue'),
          meta: { title: 'Operação Técnica', tecnicoOnly: true },
        },
        // Ã¢â€â‚¬Ã¢â€â‚¬ AssistÃƒÂªncia TÃƒÂ©cnica Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'os',
          name: 'os',
          component: () => import('@/views/os/OSView.vue'),
          meta: { title: 'Assistência Técnica', permissao: 'os.view' },
        },
        {
          path: 'chamados-clientes',
          name: 'chamados-clientes',
          component: () => import('@/views/os/ChamadosClientesView.vue'),
          meta: { title: 'Chamados de Clientes', permissao: 'os.view' },
        },
        {
          path: 'os/nova',
          name: 'os.nova',
          component: () => import('@/views/os/OSFormView.vue'),
          meta: { title: 'Nova OS', permissao: 'os.create' },
        },
        {
          path: 'os/:id',
          name: 'os.show',
          component: () => import('@/views/os/OSShowView.vue'),
          meta: { title: 'Ordem de Serviço', permissao: 'os.view' },
        },
        {
          path: 'marketplace-catalogo',
          redirect: '/marketplaces/mercado-livre/catalogo',
          meta: { title: 'Catálogo Mercado Livre' },
        },

        {
          path: 'marketplaces/mercado-livre/catalogo',
          name: 'mercado-livre-catalogo',
          component: () => import('@/views/marketplaces/MercadoLivreCatalogoView.vue'),
          meta: { title: 'Catálogo Mercado Livre' },
        },

        {
          path: 'marketplaces/emissao-fiscal',
          name: 'marketplace-emissao-fiscal',
          component: () => import('@/views/marketplaces/MarketplaceFiscalEmissionView.vue'),
          meta: { title: 'Emissão Fiscal Omnichannel' },
        },

        {
          path: 'marketplaces/financeiro-fiscal',
          name: 'marketplace-financeiro-fiscal',
          component: () => import('@/views/marketplaces/MarketplaceFinanceFiscalView.vue'),
          meta: { title: 'Financeiro e Fiscal Omnichannel' },
        },

        {
          path: 'marketplaces/automacao',
          name: 'automacao-omnichannel',
          component: () => import('@/views/marketplaces/OmnichannelAutomationView.vue'),
          meta: { title: 'Automação Omnichannel' },
        },

        {
          path: 'marketplaces/mercado-livre/pedidos',
          name: 'mercado-livre-pedidos',
          component: () => import('@/views/marketplaces/MercadoLivrePedidosView.vue'),
          meta: { title: 'Pedidos Mercado Livre' },
        },

        {
          path: 'marketplaces/mercado-livre',
          name: 'mercado-livre',
          component: () => import('@/views/marketplaces/MercadoLivreView.vue'),
          meta: { title: 'Mercado Livre' },
        },

        {
          path: 'marketplaces/nuvemshop',
          name: 'nuvemshop-dropshipping',
          component: () => import('@/views/marketplaces/NuvemshopDropshippingView.vue'),
          meta: { title: 'Nuvemshop e Dropshipping' },
        },
        {
          path: 'marketplace-hub',
          name: 'marketplace-hub',
          component: () => import('@/views/marketplaces/MarketplaceHubView.vue'),
          meta: { title: 'Central de Extensões e Marketplaces' },
        },

        {
          path: 'os/:id/editar',
          name: 'os.editar',
          component: () => import('@/views/os/OSFormView.vue'),
          meta: { title: 'Editar OS', permissao: 'os.edit' },
        },

        {
          path: 'logistica/transportadoras',
          name: 'transportadoras',
          component: () => import('@/views/logistica/TransportadorasView.vue'),
          meta: { title: 'Central de Transportadoras' },
        },

        {
          path: 'expedicao',
          name: 'expedicao',
          component: () => import('@/views/expedicao/ExpedicaoView.vue'),
          meta: { title: 'Expedição Inteligente' },
        },

        // â”€â”€ Central de ExtensÃµes IntelbrasTech
        {
          path: 'extensoes',
          name: 'extensoes',
          component: () => import('@/views/extensoes/CentralExtensoesView.vue'),
          meta: { title: 'Central de Extensões IntelbrasTech', permissao: 'integracoes.view' },
        },

        // Ã¢â€â‚¬Ã¢â€â‚¬ Marketplaces Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'marketplaces',
          name: 'marketplaces',
          component: () => import('@/views/marketplaces/MarketplacesView.vue'),
          meta: { title: 'Marketplaces' },
        },
        {
          path: 'marketplaces/callback/:plataforma',
          name: 'marketplaces.callback',
          component: () => import('@/views/marketplaces/MarketplaceCallbackView.vue'),
          meta: { title: 'Conectando...' },
        },

        // Ã¢â€â‚¬Ã¢â€â‚¬ Mapa GPS Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'mapa',
          name: 'mapa',
          component: () => import('@/views/dashboard/MapaView.vue'),
          meta: { title: 'Mapa ONS' },
        },
        {
          path: 'mapa-aneel',
          name: 'mapa.aneel',
          component: () => import('@/views/dashboard/MapaAneelView.vue'),
          meta: { title: 'Mapa ANEEL' },
        },

        // Ã¢â€â‚¬Ã¢â€â‚¬ ConfiguraÃƒÂ§ÃƒÂµes / APIs Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'configuracoes/apis',
          name: 'configuracoes.apis',
          component: () => import('@/views/configuracoes/ApiIntegrationsView.vue'),
          meta: { title: 'Central de APIs', permissao: 'integracoes.view' },
        },
        {
          path: 'configuracoes/logs',
          name: 'configuracoes.logs',
          component: () => import('@/views/configuracoes/SystemLogsView.vue'),
          meta: { title: 'Logs do Sistema', adminOnly: true },
        },
        {
          path: 'configuracoes/site',
          name: 'configuracoes.site',
          component: () => import('@/views/configuracoes/MeuSiteView.vue'),
          meta: { title: 'Meu Site' },
        },
        {
          path: 'configuracoes/site/carrossel',
          name: 'configuracoes.portal.carrossel',
          component: () => import('@/views/configuracoes/PortalCarouselView.vue'),
          meta: { title: 'Conteúdo do Carrossel' },
        },
        {
          path: 'configuracoes/site/seo',
          name: 'configuracoes.portal.seo',
          component: () => import('@/views/configuracoes/PortalSeoView.vue'),
          meta: { title: 'SEO e Rastreamento' },
        },
        {
          path: 'configuracoes/site/email',
          name: 'configuracoes.site.email',
          component: () => import('@/views/configuracoes/EmailMarketingView.vue'),
          meta: { title: 'Meu E-mail' },
        },
        { path: 'configuracoes/portal/carrossel', redirect: '/configuracoes/site/carrossel' },
        { path: 'configuracoes/portal/seo', redirect: '/configuracoes/site/seo' },

        // Ã¢â€â‚¬Ã¢â€â‚¬ 404 Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
        {
          path: 'configuracoes/empresa',
          name: 'configuracoes.empresa',
          component: () => import('@/views/configuracoes/EmpresaView.vue'),
          meta: { title: 'Dados da Empresa' },
        },
        { path: ':pathMatch(.*)*', name: 'not-found',
          component: () => import('@/views/NotFoundView.vue') },
      ],
    },
  ],
})

// Guards
router.beforeEach(async (to) => {
  const auth = useAuthStore()

  document.title = to.meta.title ? `${to.meta.title} — IntelbrasTech` : 'IntelbrasTech'

  if (to.meta.requiresAuth && !auth.logado) return { name: 'login' }
  if (to.meta.guest && auth.logado) return { name: auth.usuario?.perfil_operacional === 'tecnico' ? 'tecnico.operacao' : 'pdv' }

  if (to.meta.requiresAuth && auth.logado && !auth.usuario) {
    await auth.carregarUsuario()
  }

  if (to.meta.tecnicoOnly && auth.usuario?.perfil_operacional !== 'tecnico') {
    return { name: 'gestao-equipe' }
  }

  if (to.meta.adminOnly && !auth.isAdmin) {
    return { name: 'pdv' }
  }

  if (to.meta.permissao && !auth.pode(to.meta.permissao)) {
    return { name: 'pdv' }
  }
})

export default router
