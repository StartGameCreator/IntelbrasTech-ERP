<template>
  <div class="space-y-4 animate-fade-in">
    <div class="page-header sticky top-0 z-20 bg-[var(--bg-primary)] py-3">
      <div class="flex items-center gap-3">
        <button @click="$router.back()" class="btn-ghost btn-icon"><ArrowLeftIcon class="w-5 h-5"/></button>
        <div>
          <h1 class="page-title">{{ isEdit ? form.nome || 'Editar Produto' : 'Novo Produto' }}</h1>
          <p class="text-xs text-[var(--text-muted)]">Cadastro completo preparado para estoque, NF-e e marketplaces.</p>
        </div>
      </div>
      <div class="flex gap-2">
        <button type="button" class="btn-secondary" @click="$router.back()">Cancelar</button>
        <button class="btn-primary" :disabled="salvando" @click="salvar">
          <ArrowPathIcon v-if="salvando" class="w-4 h-4 animate-spin"/>
          {{ salvando ? 'Salvando...' : 'Salvar produto' }}
        </button>
      </div>
    </div>

    <div class="card p-5">
      <div class="grid gap-4 lg:grid-cols-[220px_1fr]">
        <div class="product-cover">
          <img v-if="imagemPrincipal" :src="imagemPrincipal.url" :alt="form.nome" />
          <PhotoIcon v-else class="h-14 w-14 text-[var(--text-muted)]"/>
          <span>{{ isEdit ? 'Imagem principal' : 'Salve para adicionar imagens' }}</span>
        </div>

        <div class="space-y-4">
          <div>
            <label class="label">Nome *</label>
            <input v-model="form.nome" class="input text-base font-semibold" required />
            <p v-if="erros.nome" class="form-error">{{ erros.nome[0] }}</p>
          </div>

          <div class="grid gap-3 md:grid-cols-4">
            <div><label class="label">Código / SKU</label><input v-model="form.codigo" class="input"/></div>
            <div><label class="label">Formato</label><select v-model="form.formato" class="select"><option value="simples">Simples</option><option value="variacao">Com variações</option><option value="kit">Kit</option><option value="composto">Composto</option></select></div>
            <div><label class="label">Tipo</label><select v-model="form.tipo" class="select"><option value="simples">Produto</option><option value="servico">Serviço</option><option value="digital">Digital</option><option value="kit">Kit</option><option value="composto">Composto</option></select></div>
            <div><label class="label">Condição</label><select v-model="form.condicao" class="select"><option value="novo">Novo</option><option value="usado">Usado</option><option value="recondicionado">Recondicionado</option></select></div>
          </div>

          <div class="grid gap-3 md:grid-cols-4">
            <label class="switch-line"><input type="checkbox" v-model="form.ativo"/> Ativado</label>
            <div><label class="label">Preço de venda *</label><input v-model.number="form.preco_venda" type="number" step="0.01" class="input"/></div>
            <div><label class="label">Unidade</label><select v-model="form.unidade_id" class="select"><option :value="null">Selecione</option><option v-for="u in unidades" :key="u.id" :value="u.id">{{ u.sigla }}</option></select></div>
            <div><label class="label">Marca</label><select v-model="form.marca_id" class="select"><option :value="null">Selecione</option><option v-for="m in marcas" :key="m.id" :value="m.id">{{ m.nome }}</option></select></div>
          </div>
        </div>
      </div>
    </div>

    <div class="tabs-bar">
      <button v-for="t in tabs" :key="t.id" :class="['tab-btn', aba === t.id && 'active']" @click="aba=t.id">{{ t.label }}</button>
    </div>

    <form @submit.prevent="salvar" class="space-y-4">
      <section v-show="aba==='caracteristicas'" class="space-y-4">
        <div class="card p-5 space-y-4">
          <h3 class="section-title">Características</h3>
          <div class="grid gap-3 md:grid-cols-4">
            <div><label class="label">Produção</label><select v-model="form.producao" class="select"><option value="propria">Própria</option><option value="terceiros">Terceiros</option></select></div>
            <div><label class="label">Data de validade</label><input v-model="form.data_validade" type="date" class="input"/></div>
            <div><label class="label">Frete grátis</label><select v-model="form.frete_gratis" class="select"><option :value="false">Não</option><option :value="true">Sim</option></select></div>
            <div><label class="label">Categoria</label><select v-model="form.categoria_id" class="select"><option :value="null">Selecione</option><option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nome }}</option></select></div>
          </div>

          <div class="grid gap-3 md:grid-cols-4">
            <div><label class="label">Peso líquido</label><input v-model.number="form.peso" type="number" step="0.001" class="input"/></div>
            <div><label class="label">Peso bruto</label><input v-model.number="form.peso_bruto" type="number" step="0.001" class="input"/></div>
            <div><label class="label">Largura</label><input v-model.number="form.largura" type="number" step="0.01" class="input"/></div>
            <div><label class="label">Altura</label><input v-model.number="form.altura" type="number" step="0.01" class="input"/></div>
            <div><label class="label">Profundidade</label><input v-model.number="form.comprimento" type="number" step="0.01" class="input"/></div>
            <div><label class="label">Volumes</label><input v-model.number="form.volumes" type="number" min="1" class="input"/></div>
            <div><label class="label">Itens por caixa</label><input v-model.number="form.itens_caixa" type="number" step="0.001" class="input"/></div>
            <div><label class="label">Unidade da dimensão</label><select v-model="form.unidade_dimensao" class="select"><option value="cm">Centímetros</option><option value="mm">Milímetros</option><option value="m">Metros</option></select></div>
          </div>

          <div class="grid gap-3 md:grid-cols-3">
            <div><label class="label">GTIN / EAN</label><input v-model="form.ean" class="input"/></div>
            <div><label class="label">GTIN / EAN tributário</label><input v-model="form.ean_tributario" class="input"/></div>
            <div><label class="label">Código de barras</label><input v-model="form.codigo_barras" class="input"/></div>
          </div>
        </div>

        <div class="card p-5 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="section-title">Conteúdo comercial</h3>
            <div class="flex gap-2">
              <button type="button" class="btn-secondary btn-sm" :disabled="!isEdit || gerandoIa" @click="gerarIa('titulo')"><SparklesIcon class="w-4 h-4"/> Melhorar título</button>
              <button type="button" class="btn-secondary btn-sm" :disabled="!isEdit || gerandoIa" @click="gerarIa('descricao')"><SparklesIcon class="w-4 h-4"/> Gerar descrição</button>
              <button type="button" class="btn-secondary btn-sm" :disabled="!isEdit || gerandoIa" @click="gerarIa('tags')"><SparklesIcon class="w-4 h-4"/> Sugerir tags</button>
            </div>
          </div>

          <div><label class="label">Descrição curta</label><textarea v-model="form.descricao_curta" class="input min-h-28" maxlength="5000"/></div>
          <div>
            <label class="label">Descrição principal (HTML permitido)</label>
            <textarea v-model="form.descricao" class="input min-h-44 font-mono text-xs"/>
          </div>
          <div>
            <label class="label">Descrição complementar</label>
            <textarea v-model="form.descricao_complementar" class="input min-h-44 font-mono text-xs"/>
          </div>

          <div class="grid gap-3 md:grid-cols-2">
            <div><label class="label">Link externo</label><input v-model="form.link_externo" class="input" placeholder="https://..."/></div>
            <div><label class="label">Vídeo</label><input v-model="form.video_url" class="input" placeholder="https://youtube.com/..."/></div>
          </div>
          <div><label class="label">Observações</label><textarea v-model="form.observacoes" class="input min-h-24"/></div>
          <div><label class="label">Tags (separadas por vírgula)</label><input v-model="tagsTexto" class="input" placeholder="Intelbras, segurança, fechadura digital"/></div>
        </div>
      </section>

      <section v-show="aba==='imagens'" class="card p-5 space-y-4">
        <h3 class="section-title">Imagens</h3>
        <div v-if="!isEdit" class="notice">Salve o produto primeiro para incluir imagens.</div>
        <template v-else>
          <div class="grid gap-4 lg:grid-cols-2">
            <label class="upload-box">
              <input type="file" class="hidden" accept="image/*" multiple @change="enviarImagem"/>
              <ArrowUpTrayIcon class="w-8 h-8"/>
              <strong>Enviar imagem local</strong>
              <span>Selecione várias imagens — PNG, JPG ou WEBP, até 10 MB cada</span>
            </label>
            <div class="space-y-2 rounded-xl border border-[var(--border)] p-4">
              <label class="label">Adicionar por URL</label>
              <input v-model="novaImagemUrl" class="input" placeholder="https://site.com/imagem.png"/>
              <button type="button" class="btn-primary w-full" :disabled="!novaImagemUrl" @click="adicionarUrl">Adicionar imagem por link</button>
            </div>
          </div>

          <div class="image-grid">
            <article v-for="(img, index) in imagens" :key="img.id" class="image-card" draggable="true" @dragstart="arrastarImagem(index)" @dragover.prevent @drop="soltarImagem(index)">
              <img :src="img.url" :alt="img.alt_text || form.nome"/>
              <div class="image-meta">
                <span>{{ img.origem === 'upload' ? 'Local' : 'URL' }}</span>
                <span>{{ img.largura && img.altura ? `${img.largura} × ${img.altura}` : img.extensao || 'Imagem' }}</span>
              </div>
              <div class="flex gap-2">
                <button type="button" class="btn-secondary btn-sm flex-1" :disabled="img.principal" @click="definirPrincipal(img)">{{ img.principal ? 'Principal' : 'Tornar principal' }}</button>
                <button type="button" class="btn-danger btn-sm" @click="removerImagem(img)">Excluir</button>
              </div>
            </article>
          </div>
        </template>
      </section>

      <section v-show="aba==='estoque'" class="card p-5 space-y-4">
        <h3 class="section-title">Estoque</h3>
        <label class="switch-line"><input type="checkbox" v-model="form.controla_estoque"/> Controlar estoque</label>
        <div class="grid gap-3 md:grid-cols-3">
          <div><label class="label">Estoque mínimo</label><input v-model.number="form.estoque_minimo" type="number" step="0.001" class="input"/></div>
          <div><label class="label">Estoque máximo</label><input v-model.number="form.estoque_maximo" type="number" step="0.001" class="input"/></div>
          <div><label class="label">Ponto de reposição</label><input v-model.number="form.ponto_reposicao" type="number" step="0.001" class="input"/></div>
        </div>
        <div class="notice">Saldos por depósito, reservas, lotes e séries continuarão sendo controlados no módulo Estoque.</div>
      </section>

      <section v-show="aba==='fornecedores'" class="card p-5 space-y-4">
        <h3 class="section-title">Fornecedores</h3>
        <div class="notice">A estrutura para múltiplos fornecedores já foi criada. A gestão completa será liberada no próximo patch.</div>
        <div><label class="label">Fornecedor principal</label><select v-model="form.fornecedor_id" class="select max-w-lg"><option :value="null">Selecione</option><option v-for="f in fornecedores" :key="f.id" :value="f.id">{{ f.nome_fantasia || f.razao_social }}</option></select></div>
      </section>

      <section v-show="aba==='tributacao'" class="space-y-4">
        <div class="card p-5 space-y-4">
          <h3 class="section-title">Dados da nota fiscal</h3>
          <div class="grid gap-3 md:grid-cols-4">
            <div><label class="label">Origem</label><select v-model.number="form.origem" class="select"><option v-for="n in 9" :key="n-1" :value="n-1">{{ n-1 }}</option></select></div>
            <div><label class="label">NCM</label><input v-model="form.ncm" class="input"/></div>
            <div><label class="label">CEST</label><input v-model="form.cest" class="input"/></div>
            <div><label class="label">CFOP</label><input v-model="form.cfop" class="input"/></div>
            <div><label class="label">Tipo do item</label><input v-model="form.tipo_item" class="input"/></div>
            <div><label class="label">% Tributos</label><input v-model.number="form.percentual_tributos" type="number" step="0.0001" class="input"/></div>
            <div class="md:col-span-2"><label class="label">Grupo de produtos</label><input v-model="form.grupo_produtos" class="input"/></div>
          </div>
        </div>
        <div class="card p-5 space-y-4">
          <h3 class="section-title">ICMS / IPI / PIS / COFINS</h3>
          <div class="grid gap-3 md:grid-cols-4">
            <div><label class="label">CST ICMS</label><input v-model="form.icms_cst" class="input"/></div>
            <div><label class="label">ICMS %</label><input v-model.number="form.icms_aliquota" type="number" step="0.01" class="input"/></div>
            <div><label class="label">Base ICMS ST</label><input v-model.number="form.icms_st_base" type="number" step="0.01" class="input"/></div>
            <div><label class="label">Valor ICMS ST</label><input v-model.number="form.icms_st_valor" type="number" step="0.01" class="input"/></div>
            <div><label class="label">CST IPI</label><input v-model="form.ipi_cst" class="input"/></div>
            <div><label class="label">IPI %</label><input v-model.number="form.ipi_aliquota" type="number" step="0.01" class="input"/></div>
            <div><label class="label">CST PIS</label><input v-model="form.pis_cst" class="input"/></div>
            <div><label class="label">PIS %</label><input v-model.number="form.pis_aliquota" type="number" step="0.01" class="input"/></div>
            <div><label class="label">CST COFINS</label><input v-model="form.cofins_cst" class="input"/></div>
            <div><label class="label">COFINS %</label><input v-model.number="form.cofins_aliquota" type="number" step="0.01" class="input"/></div>
          </div>
          <div><label class="label">Informações adicionais</label><textarea v-model="form.informacoes_adicionais" class="input min-h-28"/></div>
        </div>
      </section>

      <section v-show="aba==='ia'" class="space-y-4">
        <div class="card p-5 space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h3 class="section-title">Assistente inteligente de produto</h3>
              <p class="mt-1 text-xs text-[var(--text-muted)]">Gere rascunhos, revise e aplique apenas o conteúdo aprovado.</p>
            </div>
            <button type="button" class="btn-secondary btn-sm" :disabled="!isEdit || gerandoIa" @click="carregarHistoricoIa">
              Atualizar histórico
            </button>
          </div>

          <div class="grid gap-3 md:grid-cols-3">
            <button type="button" class="ia-action" @click="gerarIa('titulo')"><SparklesIcon class="w-5 h-5"/><span><strong>Título comercial</strong><small>Nome curto e objetivo</small></span></button>
            <button type="button" class="ia-action" @click="gerarIa('descricao')"><SparklesIcon class="w-5 h-5"/><span><strong>Descrição comercial</strong><small>Benefícios e argumentos</small></span></button>
            <button type="button" class="ia-action" @click="gerarIa('descricao_tecnica')"><SparklesIcon class="w-5 h-5"/><span><strong>Ficha técnica</strong><small>Dados estruturados</small></span></button>
            <button type="button" class="ia-action" @click="gerarIa('tags')"><SparklesIcon class="w-5 h-5"/><span><strong>Tags</strong><small>Classificação e busca</small></span></button>
            <button type="button" class="ia-action" @click="gerarIa('seo')"><SparklesIcon class="w-5 h-5"/><span><strong>SEO</strong><small>Título, descrição e palavras-chave</small></span></button>
            <button type="button" class="ia-action" @click="gerarMarketplace"><SparklesIcon class="w-5 h-5"/><span><strong>Marketplace</strong><small>Rascunho por canal</small></span></button>
          </div>

          <div v-if="previewIa" class="rounded-xl border border-[var(--border)] bg-[var(--bg-input)] p-4">
            <div class="mb-3 flex items-center justify-between">
              <strong class="text-sm text-[var(--text-primary)]">Prévia do conteúdo</strong>
              <button type="button" class="btn-primary btn-sm" :disabled="!previewRegistroId" @click="aplicarPreviewIa">Aplicar ao produto</button>
            </div>
            <pre class="ia-preview">{{ formatarPreviewIa(previewIa) }}</pre>
          </div>
        </div>

        <div class="card p-5 space-y-3">
          <h3 class="section-title">Histórico de conteúdo</h3>
          <div v-if="!historicoIa.length" class="notice">Nenhum rascunho gerado ainda.</div>
          <article v-for="item in historicoIa" :key="item.id" class="history-item">
            <div>
              <strong>{{ item.tipo }}<span v-if="item.canal"> · {{ item.canal }}</span></strong>
              <p>{{ new Date(item.created_at).toLocaleString('pt-BR') }} · {{ item.fonte }}</p>
            </div>
            <span class="badge" :class="item.aplicado ? 'badge-success' : 'badge-gray'">{{ item.aplicado ? 'Aplicado' : 'Rascunho' }}</span>
          </article>
        </div>
      </section>

      <section v-show="aba==='codigos'" class="space-y-4">
        <div class="card p-5 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="section-title">Códigos adicionais e DUN</h3>
            <button type="button" class="btn-secondary btn-sm" @click="adicionarCodigo">Adicionar código</button>
          </div>

          <div v-if="!codigosAdicionais.length" class="notice">Nenhum código adicional cadastrado.</div>

          <div v-for="(item, index) in codigosAdicionais" :key="index" class="grid gap-2 rounded-lg border border-[var(--border)] p-3 md:grid-cols-[140px_1fr_140px_90px_80px]">
            <select v-model="item.tipo" class="select">
              <option value="dun">DUN</option><option value="ean">EAN</option><option value="gtin">GTIN</option>
              <option value="sku">SKU</option><option value="interno">Interno</option><option value="outro">Outro</option>
            </select>
            <input v-model="item.codigo" class="input" placeholder="Código"/>
            <input v-model.number="item.quantidade" type="number" step="0.001" class="input" placeholder="Quantidade"/>
            <label class="switch-line"><input type="checkbox" v-model="item.principal"/> Principal</label>
            <button type="button" class="btn-danger btn-sm" @click="codigosAdicionais.splice(index,1)">Excluir</button>
          </div>

          <button type="button" class="btn-primary" :disabled="!isEdit" @click="salvarCodigos">Salvar códigos</button>
        </div>

        <div class="card p-5 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="section-title">Listas de preço</h3>
            <button type="button" class="btn-secondary btn-sm" @click="adicionarLista">Adicionar lista</button>
          </div>

          <div v-if="!listasPreco.length" class="notice">Nenhuma lista de preço vinculada.</div>

          <div v-for="(item, index) in listasPreco" :key="index" class="grid gap-2 rounded-lg border border-[var(--border)] p-3 md:grid-cols-[1fr_180px_180px_90px_80px]">
            <select v-model="item.tabela_preco_id" class="select">
              <option :value="null">Selecione</option>
              <option v-for="t in tabelasPreco" :key="t.id" :value="t.id">{{ t.nome }}</option>
            </select>
            <input v-model.number="item.preco" type="number" step="0.01" class="input" placeholder="Preço"/>
            <input v-model.number="item.preco_minimo" type="number" step="0.01" class="input" placeholder="Preço mínimo"/>
            <label class="switch-line"><input type="checkbox" v-model="item.ativo"/> Ativa</label>
            <button type="button" class="btn-danger btn-sm" @click="listasPreco.splice(index,1)">Excluir</button>
          </div>

          <button type="button" class="btn-primary" :disabled="!isEdit" @click="salvarListas">Salvar listas</button>
        </div>
      </section>

      <section v-show="aba==='variacoes'" class="space-y-4">
        <div class="card p-5 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="section-title">Variações</h3>
            <button type="button" class="btn-secondary btn-sm" @click="adicionarVariacao">Adicionar variação</button>
          </div>

          <div v-if="!variacoesProduto.length" class="notice">Use variações para cor, tamanho, modelo ou acabamento.</div>

          <div v-for="(item, index) in variacoesProduto" :key="item.id || index" class="rounded-lg border border-[var(--border)] p-3 space-y-2">
            <div class="grid gap-2 md:grid-cols-4">
              <input v-model="item.nome" class="input" placeholder="Nome: Preto / 110 V"/>
              <input v-model="item.sku" class="input" placeholder="SKU"/>
              <input v-model="item.codigo_barras" class="input" placeholder="Código de barras"/>
              <label class="switch-line"><input type="checkbox" v-model="item.ativo"/> Ativa</label>
            </div>
            <div class="grid gap-2 md:grid-cols-4">
              <input v-model.number="item.preco_venda" type="number" step="0.01" class="input" placeholder="Preço venda"/>
              <input v-model.number="item.preco_custo" type="number" step="0.01" class="input" placeholder="Preço custo"/>
              <input v-model.number="item.peso" type="number" step="0.001" class="input" placeholder="Peso"/>
              <input v-model="item.atributosTexto" class="input" placeholder="Atributos: cor=preto;voltagem=110"/>
            </div>
            <button type="button" class="btn-danger btn-sm" @click="variacoesProduto.splice(index,1)">Remover</button>
          </div>

          <button type="button" class="btn-primary" :disabled="!isEdit" @click="salvarVariacoes">Salvar variações</button>
        </div>

        <div class="card p-5 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="section-title">Kit ou produto composto</h3>
              <p class="text-xs text-[var(--text-muted)]">Vincule produtos componentes e suas quantidades.</p>
            </div>
            <button type="button" class="btn-secondary btn-sm" @click="adicionarComponente">Adicionar componente</button>
          </div>

          <div v-for="(item,index) in composicaoProduto" :key="index" class="grid gap-2 rounded-lg border border-[var(--border)] p-3 md:grid-cols-[1fr_160px_80px]">
            <select v-model="item.produto_id" class="select">
              <option :value="null">Selecione o produto</option>
              <option v-for="p in produtosBusca.filter(p=>p.id!==Number(route.params.id))" :key="p.id" :value="p.id">{{ p.nome }} — {{ p.sku || p.codigo }}</option>
            </select>
            <input v-model.number="item.quantidade" type="number" step="0.001" min="0.001" class="input"/>
            <button type="button" class="btn-danger btn-sm" @click="composicaoProduto.splice(index,1)">Excluir</button>
          </div>

          <button type="button" class="btn-primary" :disabled="!isEdit" @click="salvarComposicao">Salvar composição</button>
        </div>
      </section>

      <section v-show="aba==='documentos'" class="card p-5 space-y-4">
        <h3 class="section-title">Documentos e mídia</h3>
        <div class="grid gap-3 md:grid-cols-2">
          <div><label class="label">Tipo</label><select v-model="documento.tipo" class="select"><option value="manual">Manual</option><option value="ficha_tecnica">Ficha técnica</option><option value="certificado">Certificado</option><option value="garantia">Garantia</option><option value="video">Vídeo</option><option value="outro">Outro</option></select></div>
          <div><label class="label">Título</label><input v-model="documento.titulo" class="input"/></div>
          <div><label class="label">URL externa</label><input v-model="documento.url" class="input" placeholder="https://..."/></div>
          <div><label class="label">Arquivo local</label><input type="file" class="input" @change="documento.arquivo=$event.target.files?.[0] || null"/></div>
        </div>
        <button type="button" class="btn-primary" :disabled="!isEdit || !documento.titulo" @click="adicionarDocumento">Adicionar documento</button>

        <div v-if="!documentosProduto.length" class="notice">Nenhum documento cadastrado.</div>
        <div v-for="doc in documentosProduto" :key="doc.id" class="flex items-center justify-between rounded-lg border border-[var(--border)] p-3">
          <div><strong class="text-sm">{{ doc.titulo }}</strong><p class="text-xs text-[var(--text-muted)]">{{ doc.tipo }} · {{ doc.origem }}</p></div>
          <div class="flex gap-2"><a :href="doc.url" target="_blank" class="btn-secondary btn-sm">Abrir</a><button type="button" class="btn-danger btn-sm" @click="removerDocumento(doc)">Excluir</button></div>
        </div>
      </section>

      <section v-show="aba==='marketplaces'" class="card p-5 space-y-4">
        <h3 class="section-title">Marketplaces e Nuvemshop</h3>
        <div class="notice">Estrutura de canais criada para Mercado Livre, Shopee, Amazon, Magalu, Nuvemshop e outros. A publicação será ativada por integração nos próximos patches.</div>
        <div class="channel-grid">
          <div v-for="c in canais" :key="c.id" class="channel-card">
            <span>{{ c.label }}</span>
            <button type="button" class="btn-secondary btn-sm" :disabled="!isEdit || gerandoIa" @click="gerarIa('marketplace', c.id)">Gerar rascunho</button>
          </div>
        </div>
      </section>

      <div class="flex justify-end gap-2 pb-8">
        <button type="button" class="btn-secondary" @click="$router.back()">Cancelar</button>
        <button type="submit" class="btn-primary" :disabled="salvando">{{ salvando ? 'Salvando...' : 'Salvar produto' }}</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import { ArrowLeftIcon, ArrowPathIcon, PhotoIcon, ArrowUpTrayIcon, SparklesIcon } from '@heroicons/vue/24/outline'

const router = useRouter(), route = useRoute(), toast = useToast()
const isEdit = computed(() => !!route.params.id)
const aba = ref('caracteristicas')
const salvando = ref(false), gerandoIa = ref(false), erros = ref({})
const categorias = ref([]), marcas = ref([]), unidades = ref([]), fornecedores = ref([])
const imagens = ref([]), novaImagemUrl = ref('')
const codigosAdicionais = ref([])
const listasPreco = ref([])
const tabelasPreco = ref([])
const variacoesProduto = ref([])
const composicaoProduto = ref([])
const produtosBusca = ref([])
const documentosProduto = ref([])
const documento = ref({ tipo:'manual', titulo:'', url:'', arquivo:null, publico:true })
const historicoIa = ref([]), previewIa = ref(null), previewRegistroId = ref(null), canalIa = ref('nuvemshop')
const indiceArrastado = ref(null)
const tabs = [
  {id:'caracteristicas',label:'Características'},{id:'imagens',label:'Imagens'},
  {id:'estoque',label:'Estoque'},{id:'fornecedores',label:'Fornecedores'},
  {id:'tributacao',label:'Tributação'},{id:'codigos',label:'Códigos e preços'},{id:'variacoes',label:'Variações e kits'},{id:'documentos',label:'Documentos'},{id:'ia',label:'Conteúdo IA'},{id:'marketplaces',label:'Marketplaces'},
]
const canais = [
  {id:'mercado_livre',label:'Mercado Livre'},
  {id:'shopee',label:'Shopee'},
  {id:'amazon',label:'Amazon'},
  {id:'magalu',label:'Magalu'},
  {id:'nuvemshop',label:'Nuvemshop'},
  {id:'loja_propria',label:'Loja própria'},
]

const form = ref({
  nome:'',tipo:'simples',formato:'simples',condicao:'novo',producao:'terceiros',
  codigo:'',sku:'',codigo_barras:'',ean:'',ean_tributario:'',
  categoria_id:null,marca_id:null,fornecedor_id:null,unidade_id:null,
  descricao:'',descricao_curta:'',descricao_complementar:'',link_externo:'',video_url:'',observacoes:'',tags:[],
  peso:0,peso_bruto:0,largura:0,altura:0,comprimento:0,volumes:1,itens_caixa:1,unidade_dimensao:'cm',
  data_validade:null,frete_gratis:false,preco_custo:0,preco_venda:0,preco_venda_minimo:0,seo_titulo:'',seo_descricao:'',seo_palavras_chave:[],
  ncm:'',cest:'',cfop:'',origem:0,tipo_item:'',grupo_produtos:'',percentual_tributos:0,
  icms_cst:'',icms_aliquota:0,icms_st_base:0,icms_st_valor:0,ipi_cst:'',ipi_aliquota:0,
  pis_cst:'',pis_aliquota:0,cofins_cst:'',cofins_aliquota:0,informacoes_adicionais:'',
  controla_estoque:true,estoque_minimo:0,estoque_maximo:0,ponto_reposicao:0,ativo:true,
})

const tagsTexto = computed({
  get: () => (form.value.tags || []).join(', '),
  set: v => form.value.tags = String(v).split(',').map(x=>x.trim()).filter(Boolean),
})
const imagemPrincipal = computed(() => imagens.value.find(i=>i.principal) || imagens.value[0])

async function carregarAuxiliares() {
  const reqs = await Promise.allSettled([
    api.get('/categorias'),api.get('/marcas'),api.get('/unidades-medida'),api.get('/fornecedores',{params:{per_page:200}}),api.get('/tabelas-preco',{params:{per_page:200}}),api.get('/produtos',{params:{per_page:200}})
  ])
  const val = i => reqs[i].status === 'fulfilled' ? (reqs[i].value.data.data ?? reqs[i].value.data) : []
  categorias.value=val(0); marcas.value=val(1); unidades.value=val(2); fornecedores.value=val(3); tabelasPreco.value=val(4); produtosBusca.value=val(5)
}

async function carregarProduto() {
  const {data} = await api.get(`/produtos/${route.params.id}`)
  Object.assign(form.value, data)
  form.value.tags = Array.isArray(data.tags) ? data.tags : []
  imagens.value = data.imagens || []
  codigosAdicionais.value = data.codigos || []
  listasPreco.value = data.listas_preco || []
  variacoesProduto.value = (data.variacoes || []).map(v=>({...v,atributosTexto:Object.entries(v.atributos||{}).map(([k,val])=>`${k}=${val}`).join(';')}))
  composicaoProduto.value = (data.composicao || []).map(item => ({
    produto_id: item.produto_filho_id,
    quantidade: item.quantidade,
    nome: item.produto_filho?.nome,
  }))
  documentosProduto.value = data.documentos || []
}

async function salvar() {
  salvando.value=true; erros.value={}
  try {
    const payload = {...form.value}
    const {data} = isEdit.value
      ? await api.put(`/produtos/${route.params.id}`, payload)
      : await api.post('/produtos', payload)
    toast.success(data.message || 'Produto salvo.')
    if (!isEdit.value) router.replace(`/produtos/${data.produto.id}/editar`)
    else await carregarProduto()
  } catch (e) {
    erros.value=e.response?.data?.errors || {}
    toast.error(e.response?.data?.message || 'Erro ao salvar produto.')
  } finally { salvando.value=false }
}

async function enviarImagem(event) {
  const files = Array.from(event.target.files || [])
  if (!files.length) return

  const fd = new FormData()
  files.forEach(file => fd.append('imagens[]', file))
  fd.append('principal', imagens.value.length ? '0' : '1')

  try {
    await api.post(`/produtos/${route.params.id}/imagens`, fd, {
      headers: {'Content-Type':'multipart/form-data'},
    })
    await carregarProduto()
    toast.success(files.length > 1 ? 'Imagens enviadas.' : 'Imagem enviada.')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Falha no envio.')
  }

  event.target.value = ''
}

async function adicionarUrl() {
  try {
    await api.post(`/produtos/${route.params.id}/imagens-url`,{url:novaImagemUrl.value,principal:!imagens.value.length})
    novaImagemUrl.value=''; await carregarProduto(); toast.success('Imagem adicionada.')
  } catch(e){ toast.error(e.response?.data?.message || 'URL inválida.') }
}
async function definirPrincipal(img){ await api.post(`/produtos/${route.params.id}/imagens/${img.id}/principal`); await carregarProduto() }
async function removerImagem(img){ if(!confirm('Remover esta imagem?')) return; await api.delete(`/produtos/${route.params.id}/imagens/${img.id}`); await carregarProduto() }

async function gerarIa(acao, canal = null) {
  if (!isEdit.value) {
    toast.info('Salve o produto antes de usar o assistente.')
    return
  }

  gerandoIa.value = true

  try {
    const {data} = await api.post(`/produtos/${route.params.id}/assistente-conteudo`, {
      acao,
      canal,
    })

    previewIa.value = data.conteudo
    previewRegistroId.value = data.registro_id
    await carregarHistoricoIa()
    toast.success(data.message)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Não foi possível gerar conteúdo.')
  } finally {
    gerandoIa.value = false
  }
}

async function gerarMarketplace() {
  await gerarIa('marketplace', canalIa.value)
}

async function carregarHistoricoIa() {
  if (!isEdit.value) return
  const {data} = await api.get(`/produtos/${route.params.id}/assistente-conteudo/historico`)
  historicoIa.value = data.data || []
}

async function aplicarPreviewIa() {
  if (!previewRegistroId.value) return

  const {data} = await api.post(
    `/produtos/${route.params.id}/assistente-conteudo/${previewRegistroId.value}/aplicar`
  )

  Object.assign(form.value, data.produto || {})
  await carregarProduto()
  await carregarHistoricoIa()
  toast.success(data.message)
}

function formatarPreviewIa(valor) {
  return typeof valor === 'string'
    ? valor
    : JSON.stringify(valor, null, 2)
}

function arrastarImagem(index) {
  indiceArrastado.value = index
}

async function soltarImagem(index) {
  const origem = indiceArrastado.value
  if (origem === null || origem === index) return

  const copia = [...imagens.value]
  const [movida] = copia.splice(origem, 1)
  copia.splice(index, 0, movida)
  imagens.value = copia
  indiceArrastado.value = null

  await api.put(`/produtos/${route.params.id}/imagens-ordem`, {
    ids: copia.map(item => item.id),
  })
  toast.success('Ordem das imagens atualizada.')
}


function adicionarCodigo(){ codigosAdicionais.value.push({tipo:'dun',codigo:'',quantidade:1,principal:false}) }
async function salvarCodigos(){
  const {data}=await api.put(`/produtos/${route.params.id}/codigos`,{codigos:codigosAdicionais.value})
  codigosAdicionais.value=data.data||[]; toast.success(data.message)
}

function adicionarLista(){ listasPreco.value.push({tabela_preco_id:null,preco:form.value.preco_venda,preco_minimo:form.value.preco_venda_minimo,ativo:true}) }
async function salvarListas(){
  const {data}=await api.put(`/produtos/${route.params.id}/listas-preco`,{listas:listasPreco.value})
  listasPreco.value=data.data||[]; toast.success(data.message)
}

function adicionarVariacao(){ variacoesProduto.value.push({nome:'',sku:'',codigo_barras:'',preco_venda:form.value.preco_venda,preco_custo:form.value.preco_custo,peso:form.value.peso,atributosTexto:'',ativo:true}) }
function parseAtributos(texto){
  return Object.fromEntries(String(texto||'').split(';').map(x=>x.trim()).filter(Boolean).map(x=>{const [k,...r]=x.split('=');return [k.trim(),r.join('=').trim()]}))
}
async function salvarVariacoes(){
  const payload=variacoesProduto.value.map(v=>({...v,atributos:parseAtributos(v.atributosTexto)}))
  const {data}=await api.put(`/produtos/${route.params.id}/variacoes-completas`,{variacoes:payload})
  variacoesProduto.value=(data.data||[]).map(v=>({...v,atributosTexto:Object.entries(v.atributos||{}).map(([k,val])=>`${k}=${val}`).join(';')}))
  toast.success(data.message)
}

function adicionarComponente(){ composicaoProduto.value.push({produto_id:null,quantidade:1}) }
async function salvarComposicao(){
  const {data}=await api.put(`/produtos/${route.params.id}/composicao`,{itens:composicaoProduto.value})
  composicaoProduto.value=(data.data||[]).map(i=>({produto_id:i.produto_filho_id,quantidade:i.quantidade,nome:i.produto_filho?.nome}))
  toast.success(data.message)
}

async function adicionarDocumento(){
  const fd=new FormData()
  fd.append('tipo',documento.value.tipo); fd.append('titulo',documento.value.titulo)
  fd.append('publico',documento.value.publico?'1':'0')
  if(documento.value.url) fd.append('url',documento.value.url)
  if(documento.value.arquivo) fd.append('arquivo',documento.value.arquivo)
  const {data}=await api.post(`/produtos/${route.params.id}/documentos`,fd,{headers:{'Content-Type':'multipart/form-data'}})
  documentosProduto.value.push(data.data); documento.value={tipo:'manual',titulo:'',url:'',arquivo:null,publico:true}
  toast.success(data.message)
}
async function removerDocumento(doc){
  if(!confirm('Remover este documento?')) return
  const {data}=await api.delete(`/produtos/${route.params.id}/documentos/${doc.id}`)
  documentosProduto.value=documentosProduto.value.filter(x=>x.id!==doc.id); toast.success(data.message)
}

onMounted(async()=>{ await carregarAuxiliares(); if(isEdit.value){ await carregarProduto(); await carregarHistoricoIa() } })
</script>

<style scoped>
.tabs-bar{display:flex;gap:4px;overflow:auto;border-bottom:1px solid var(--border)}
.tab-btn{padding:10px 14px;font-size:.78rem;color:var(--text-muted);border-bottom:2px solid transparent;white-space:nowrap}
.tab-btn.active{color:var(--text-primary);border-color:var(--primary)}
.section-title{font-size:.9rem;font-weight:700;color:var(--text-primary)}
.product-cover{min-height:180px;border:1px solid var(--border);border-radius:12px;background:var(--bg-input);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;overflow:hidden;color:var(--text-muted);font-size:.72rem}
.product-cover img{width:100%;height:180px;object-fit:contain;background:white}
.switch-line{display:flex;align-items:center;gap:8px;font-size:.8rem;color:var(--text-secondary)}
.notice{padding:12px;border:1px solid var(--border);border-radius:9px;background:var(--bg-input);font-size:.75rem;color:var(--text-muted)}
.upload-box{min-height:150px;border:1px dashed var(--border);border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;cursor:pointer;color:var(--text-muted)}
.upload-box strong{color:var(--text-primary);font-size:.85rem}
.upload-box span{font-size:.7rem}
.image-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px}
.image-card{border:1px solid var(--border);border-radius:10px;padding:10px;background:var(--bg-input)}
.image-card img{width:100%;height:160px;object-fit:contain;background:white;border-radius:7px}
.image-meta{display:flex;justify-content:space-between;font-size:.65rem;color:var(--text-muted);padding:7px 0}
.channel-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
.channel-card{display:flex;align-items:center;justify-content:space-between;padding:12px;border:1px solid var(--border);border-radius:9px;background:var(--bg-input);font-size:.78rem}
.ia-action{display:flex;align-items:center;gap:10px;padding:14px;border:1px solid var(--border);border-radius:10px;background:var(--bg-input);text-align:left;color:var(--text-secondary)}
.ia-action:hover{border-color:var(--primary);color:var(--text-primary)}
.ia-action span{display:flex;flex-direction:column;gap:2px}
.ia-action strong{font-size:.78rem}
.ia-action small{font-size:.66rem;color:var(--text-muted)}
.ia-preview{max-height:360px;overflow:auto;white-space:pre-wrap;word-break:break-word;font-size:.72rem;color:var(--text-secondary)}
.history-item{display:flex;align-items:center;justify-content:space-between;padding:11px;border:1px solid var(--border);border-radius:9px;background:var(--bg-input)}
.history-item strong{font-size:.75rem;color:var(--text-primary)}
.history-item p{margin-top:2px;font-size:.65rem;color:var(--text-muted)}
.image-card{cursor:grab}
.image-card:active{cursor:grabbing}
</style>
