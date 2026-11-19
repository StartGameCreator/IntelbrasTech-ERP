<template>
  <section class="space-y-6">
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-[0.18em] text-[var(--text-muted)]">Configurações</p>
        <h1 class="mt-1 text-xl font-semibold text-[var(--text-primary)]">{{ titulo }}</h1>
        <p class="mt-1 text-sm text-[var(--text-secondary)]">{{ descricao }}</p>
      </div>
      <span class="badge badge-info">Central de preferências</span>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="item in itens"
        :key="item.titulo"
        class="card p-5 border border-[var(--border)] hover:border-brand-500/40 transition-colors"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-sm font-semibold text-[var(--text-primary)]">{{ item.titulo }}</h2>
            <p class="mt-2 text-xs leading-5 text-[var(--text-secondary)]">{{ item.descricao }}</p>
          </div>
          <span class="w-8 h-8 rounded-lg bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-bold">IT</span>
        </div>

        <RouterLink
          v-if="item.rota"
          :to="item.rota"
          class="btn-secondary mt-5 inline-flex items-center justify-center"
        >
          Abrir
        </RouterLink>
        <button v-else type="button" class="btn-secondary mt-5" disabled>
          Em configuração
        </button>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'

const route = useRoute()

const modulos = {
  empresa: {
    titulo: 'Empresa',
    descricao: 'Dados cadastrais, unidades, endereço, identidade visual e informações institucionais.',
    itens: [
      { titulo: 'Dados da empresa', descricao: 'Razão social, nome fantasia, CNPJ, contatos e endereço.' },
      { titulo: 'Unidades e filiais', descricao: 'Organize matriz, filiais e centros de operação.' },
      { titulo: 'Identidade visual', descricao: 'Logotipo, cores e informações exibidas em documentos.' },
    ],
  },
  sistema: {
    titulo: 'Sistema',
    descricao: 'Preferências gerais, segurança, usuários, permissões e parâmetros operacionais.',
    itens: [
      { titulo: 'Usuários e permissões', descricao: 'Perfis de acesso, funções e regras por módulo.' },
      { titulo: 'Preferências gerais', descricao: 'Idioma, fuso horário, numeração e padrões do sistema.' },
      { titulo: 'Auditoria e segurança', descricao: 'Logs, sessões, políticas de senha e rastreabilidade.' },
    ],
  },
  cadastros: {
    titulo: 'Cadastros',
    descricao: 'Atalhos para os principais cadastros mestres do ERP.',
    itens: [
      { titulo: 'Clientes', descricao: 'Cadastro e manutenção da base de clientes.', rota: '/clientes' },
      { titulo: 'Fornecedores', descricao: 'Cadastro e manutenção da base de fornecedores.', rota: '/fornecedores' },
      { titulo: 'Produtos', descricao: 'Catálogo, variações, preços e informações fiscais.', rota: '/produtos' },
    ],
  },
  suprimentos: {
    titulo: 'Suprimentos',
    descricao: 'Compras, fornecedores, estoque e abastecimento.',
    itens: [
      { titulo: 'Compras', descricao: 'Pedidos de compra e acompanhamento de recebimentos.', rota: '/compras' },
      { titulo: 'Estoque', descricao: 'Saldos, movimentações e inventário.', rota: '/estoque' },
      { titulo: 'Fornecedores', descricao: 'Gestão de parceiros de fornecimento.', rota: '/fornecedores' },
    ],
  },
  vendas: {
    titulo: 'Vendas',
    descricao: 'Pedidos, clientes, CRM e operação comercial.',
    itens: [
      { titulo: 'Pedidos', descricao: 'Pedidos de venda e acompanhamento de status.', rota: '/pedidos' },
      { titulo: 'CRM', descricao: 'Funil, oportunidades e prospecção.', rota: '/crm' },
      { titulo: 'Clientes', descricao: 'Base de clientes e histórico comercial.', rota: '/clientes' },
    ],
  },
  certificado: {
    titulo: 'Certificado Digital',
    descricao: 'Gestão de certificados A1/A3 usados em documentos fiscais.',
    itens: [
      { titulo: 'Certificados cadastrados', descricao: 'Upload, validade, ambiente e empresa vinculada.' },
      { titulo: 'Alertas de vencimento', descricao: 'Avisos preventivos para renovação de certificados.' },
      { titulo: 'Testar certificado', descricao: 'Validação de senha, cadeia e disponibilidade.' },
    ],
  },
  logistica: {
    titulo: 'Logística',
    descricao: 'Transportadoras, fretes, etiquetas, rastreamento e expedição.',
    itens: [
      { titulo: 'Transportadoras', descricao: 'Correios, Jadlog, Loggi, Azul Cargo e demais parceiros.' },
      { titulo: 'Expedição', descricao: 'Separação, etiquetas e despacho de pedidos.' },
      { titulo: 'Rastreamento', descricao: 'Acompanhamento de entregas e ocorrências.' },
    ],
  },
  contabilidade: {
    titulo: 'Contabilidade',
    descricao: 'Parâmetros contábeis, centros de custo e exportações.',
    itens: [
      { titulo: 'Plano de contas', descricao: 'Estrutura contábil e vínculos financeiros.' },
      { titulo: 'Centros de custo', descricao: 'Classificação por unidade, projeto ou departamento.' },
      { titulo: 'Exportações contábeis', descricao: 'Arquivos e relatórios para o escritório contábil.' },
    ],
  },
  importacoes: {
    titulo: 'Importações de Dados',
    descricao: 'Importação assistida por CSV, Excel e integrações externas.',
    itens: [
      { titulo: 'Clientes e leads', descricao: 'Importe contatos e oportunidades para o CRM.' },
      { titulo: 'Produtos', descricao: 'Importe catálogo, estoque e preços.' },
      { titulo: 'Pedidos e documentos', descricao: 'Importe dados operacionais em lote.' },
    ],
  },
  backup: {
    titulo: 'Backup e Exportação de Dados',
    descricao: 'Proteção, cópia e portabilidade das informações do ERP.',
    itens: [
      { titulo: 'Gerar backup', descricao: 'Crie uma cópia completa do banco e dos arquivos.' },
      { titulo: 'Restaurar backup', descricao: 'Restaure uma cópia previamente validada.' },
      { titulo: 'Exportar dados', descricao: 'Exporte cadastros e movimentações em formatos abertos.' },
    ],
  },
}

const modulo = computed(() => modulos[route.meta.modulo] || modulos.sistema)
const titulo = computed(() => modulo.value.titulo)
const descricao = computed(() => modulo.value.descricao)
const itens = computed(() => modulo.value.itens)
</script>
