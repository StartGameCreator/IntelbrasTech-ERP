<template>
  <div class="pdv-shell">
    <header class="pdv-header">
      <div>
        <h1>Frente de Caixa</h1>
        <p v-if="caixa">Caixa {{ caixa.numero }} · {{ caixa.deposito?.nome || 'Sem depósito' }}</p>
        <p v-else>Nenhum caixa aberto</p>
      </div>

      <div class="pdv-header-actions">
        <button class="btn-secondary btn-sm" @click="abrirMovimento('suprimento')" :disabled="!caixa">Suprimento</button>
        <button class="btn-secondary btn-sm" @click="abrirMovimento('sangria')" :disabled="!caixa">Sangria</button>
        <button class="btn-secondary btn-sm" @click="modalResumo = true" :disabled="!caixa">Resumo</button>
        <button class="btn-danger btn-sm" @click="modalFechamento = true" :disabled="!caixa">Fechar caixa</button>
      </div>
    </header>

    <div v-if="loading" class="card p-8 text-center">Carregando PDV...</div>

    <template v-else>
      <div v-if="!caixa" class="open-box">
        <div class="card p-6 max-w-xl w-full">
          <h2 class="text-lg font-bold text-[var(--text-primary)]">Abrir caixa</h2>
          <p class="mt-1 text-xs text-[var(--text-muted)]">Informe o depósito e o valor inicial em dinheiro.</p>

          <div class="mt-5 space-y-4">
            <div>
              <label class="label">Depósito</label>
              <select v-model="abertura.deposito_id" class="select">
                <option :value="null">Selecione</option>
                <option v-for="d in depositos" :key="d.id" :value="d.id">{{ d.nome }}</option>
              </select>
            </div>

            <div>
              <label class="label">Troco inicial</label>
              <input v-model.number="abertura.valor_abertura" type="number" step="0.01" min="0" class="input"/>
            </div>

            <div>
              <label class="label">Observações</label>
              <textarea v-model="abertura.observacoes" class="input min-h-24"/>
            </div>

            <button class="btn-primary w-full" @click="abrirCaixa">Abrir caixa</button>
          </div>
        </div>
      </div>

      <div v-else class="pdv-grid" :class="`mobile-panel-${mobilePanel}`">
        <section class="catalog-panel" :aria-hidden="mobilePanel !== 'catalogo'">
          <nav class="sale-steps" aria-label="Etapas da venda">
            <button class="active" @click="searchInput?.focus()"><span>1</span> Produto <kbd>F2</kbd></button>
            <button @click="modalCliente=true"><span>2</span> Cliente <kbd>F3</kbd></button>
            <button @click="abrirPagamento" :disabled="!carrinho.length"><span>3</span> Pagamento <kbd>F5</kbd></button>
          </nav>
          <div class="catalog-tools">
            <label class="price-list"><span>Lista de preço</span><select class="select"><option>Preço padrão</option></select></label>
            <label class="barcode-toggle"><span>Leitor de código de barras</span><input type="checkbox" checked><b>Ativado</b></label>
          </div>
          <div class="search-row">
            <MagnifyingGlassIcon class="w-5 h-5"/>
            <input
              ref="searchInput"
              v-model="busca"
              class="pdv-search"
              placeholder="Código de barras, SKU, código ou nome do produto"
              @input="debouncedSearch"
              @keydown.enter.prevent="adicionarPrimeiroResultado"
            />
            <kbd>F2</kbd>
          </div>

          <div v-if="buscando" class="catalog-empty">Buscando...</div>
          <div v-else-if="busca && !resultados.length" class="catalog-empty">Nenhum produto encontrado.</div>
          <div v-else-if="!busca" class="catalog-empty">
            Digite um código, SKU, EAN ou nome para localizar produtos.
          </div>

          <div v-else class="product-results">
            <button
              v-for="produto in resultados"
              :key="produto.id"
              class="product-result"
              @click="adicionarProduto(produto)"
            >
              <div class="product-image">
                <img v-if="produto.imagem" :src="produto.imagem" :alt="produto.nome"/>
                <CubeIcon v-else class="w-8 h-8"/>
              </div>
              <div class="product-copy">
                <strong>{{ produto.nome }}</strong>
                <span>{{ produto.sku || produto.codigo || produto.ean || 'Sem código' }}</span>
                <small>Estoque: {{ numero(produto.estoque_disponivel, 3) }} {{ produto.unidade }}</small>
              </div>
              <div class="product-price">{{ Number(produto.preco) > 0 ? moeda(produto.preco) : 'Definir no caixa' }}</div>
            </button>
            <div ref="pdvProductSentinel" class="pdv-load-more">
              <button v-if="paginaProdutos < ultimaPaginaProdutos" :disabled="loadingMoreProducts" @click="carregarMaisProdutos">
                {{ loadingMoreProducts ? 'Carregando...' : 'Carregar mais produtos' }}
              </button>
              <span v-else>Todos os resultados foram carregados.</span>
            </div>
          </div>
          <div class="catalog-footer">
            <button class="btn-secondary" @click="limparCarrinho" :disabled="!carrinho.length">Excluir venda</button>
            <button class="btn-primary" @click="abrirPagamento" :disabled="!carrinho.length">Finalizar venda <kbd>F5</kbd></button>
          </div>
        </section>

        <aside class="cart-panel" :aria-hidden="mobilePanel !== 'carrinho'">
          <div class="cart-top">
            <div>
              <h2>Produtos da venda</h2>
              <p>{{ carrinho.length }} item(ns)</p>
            </div>
            <button class="btn-ghost btn-sm" @click="limparCarrinho" :disabled="!carrinho.length">Limpar</button>
          </div>

          <div class="client-box">
            <div>
              <span>Cliente</span>
              <strong>{{ clienteSelecionado?.nome_fantasia || clienteSelecionado?.razao_social || 'Consumidor final' }}</strong>
            </div>
            <button class="btn-secondary btn-sm" @click="modalCliente = true">Selecionar <kbd>F3</kbd></button>
          </div>

          <div class="fiscal-box">
            <div class="fiscal-title">
              <div><span>Documento fiscal</span><strong>{{ fiscalLabel }}</strong></div>
              <span class="fiscal-status" :class="fiscalTipo">{{ fiscalStatus }}</span>
            </div>
            <label class="fiscal-profile">
              <span>Perfil de emissÃ£o</span>
              <select v-model="fiscalPerfil" class="select" @change="aplicarPerfilFiscal(true)">
                <option value="automatico">AutomÃ¡tico por UF (recomendado)</option>
                <option value="nfce">NFC-e â€” modelo 65</option>
                <option value="nfe">NF-e â€” modelo 55</option>
                <option value="nenhum">Sem documento fiscal</option>
              </select>
            </label>
            <div class="fiscal-options">
              <button :class="{active:fiscalPerfil==='automatico'}" @click="selecionarFiscal('automatico')">AutomÃ¡tico</button>
              <button :class="{active:fiscalPerfil==='nfce'}" @click="selecionarFiscal('nfce')">NFC-e <kbd>F6</kbd></button>
              <button :class="{active:fiscalPerfil==='nfe'}" @click="selecionarFiscal('nfe')">NF-e <kbd>F7</kbd></button>
              <button :class="{active:fiscalPerfil==='nenhum'}" @click="selecionarFiscal('nenhum')">Sem nota</button>
            </div>
            <p v-if="fiscalPerfil==='automatico'" class="fiscal-rule">Regra ativa: mesma UF usa modelo 65; outra UF usa modelo 55 automaticamente.</p>
            <p v-if="fiscalTipo==='nfe' && !clienteSelecionado" class="fiscal-warning">Selecione um cliente com CPF/CNPJ para emitir a NF-e.</p>
            <p v-else-if="fiscalTipo==='nfce'" class="fiscal-hint">Consumidor final. O CPF/CNPJ pode ser identificado pelo botão Selecionar.</p>
          </div>

          <div class="cart-items">
            <div v-if="!carrinho.length" class="cart-empty">
              <div class="empty-store"><ShoppingCartIcon class="w-12 h-12"/></div>
              <strong>Nenhum produto adicionado</strong>
              <p>Pesquise ou leia o código de barras para iniciar a venda.</p>
            </div>

            <article v-for="(item, index) in carrinho" :key="item.produto_id" class="cart-item">
              <div class="cart-item-main">
                <strong>{{ item.nome }}</strong>
                <span>{{ item.codigo }}</span>
              </div>

              <div class="cart-item-controls">
                <button @click="alterarQuantidade(index, -1)">−</button>
                <input v-model.number="item.quantidade" type="number" min="0.001" step="1" @change="normalizarQuantidade(item)"/>
                <button @click="alterarQuantidade(index, 1)">+</button>
              </div>

              <div class="cart-item-values">
                <input v-model.number="item.preco_unitario" type="number" step="0.01" min="0"/>
                <strong>{{ moeda(totalItem(item)) }}</strong>
                <button class="remove-item" @click="removerItem(index)">×</button>
              </div>
            </article>
          </div>

          <div class="cart-summary">
            <div><span>Subtotal</span><strong>{{ moeda(subtotal) }}</strong></div>
            <div class="summary-input"><span>Desconto <kbd>F4</kbd></span><input ref="discountInput" v-model.number="desconto" type="number" min="0" step="0.01"/></div>
            <div class="summary-input"><span>Acréscimo</span><input v-model.number="acrescimo" type="number" min="0" step="0.01"/></div>
            <div class="grand-total"><span>Total</span><strong>{{ moeda(total) }}</strong></div>
          </div>

          <div class="total-dock">
            <span>Total da venda</span>
            <strong>{{ moeda(total) }}</strong>
            <button class="finish-sale" :disabled="!carrinho.length" @click="abrirPagamento"><BanknotesIcon class="w-5 h-5"/> Receber <kbd>F5</kbd></button>
          </div>
        </aside>
      </div>

      <nav v-if="caixa" class="mobile-pdv-nav" aria-label="Navegação do frente de caixa">
        <button :class="{ active: mobilePanel === 'catalogo' }" @click="mobilePanel = 'catalogo'; focarBuscaMobile()">
          <MagnifyingGlassIcon class="w-5 h-5"/>
          <span>Produtos</span>
        </button>
        <button :class="{ active: mobilePanel === 'carrinho' }" @click="mobilePanel = 'carrinho'">
          <span class="mobile-cart-icon">
            <ShoppingCartIcon class="w-5 h-5"/>
            <b v-if="carrinho.length">{{ carrinho.length }}</b>
          </span>
          <span>Carrinho</span>
        </button>
        <button class="mobile-receive" :disabled="!carrinho.length" @click="abrirPagamento">
          <BanknotesIcon class="w-5 h-5"/>
          <span>Receber</span>
          <strong>{{ moeda(total) }}</strong>
        </button>
      </nav>
    </template>

    <!-- Cliente -->
    <div v-if="modalCliente" class="modal-backdrop">
      <div class="card modal-card">
        <div class="modal-title"><h3>Selecionar cliente</h3><button @click="modalCliente=false">×</button></div>
        <button class="new-client-button" @click="mostrarCadastroCliente=!mostrarCadastroCliente">
          <span>{{ mostrarCadastroCliente ? '−' : '+' }}</span>
          {{ mostrarCadastroCliente ? 'Voltar para a busca' : 'Cadastrar cliente' }}
        </button>

        <form v-if="mostrarCadastroCliente" class="quick-client-form" @submit.prevent="cadastrarClienteRapido">
          <div class="type-switch">
            <button type="button" :class="{active:novoCliente.tipo==='F'}" @click="novoCliente.tipo='F'">Pessoa física</button>
            <button type="button" :class="{active:novoCliente.tipo==='J'}" @click="novoCliente.tipo='J'">Pessoa jurídica</button>
          </div>
          <label><span>{{ novoCliente.tipo==='J' ? 'Razão social' : 'Nome completo' }} *</span><input v-model="novoCliente.razao_social" class="input" required></label>
          <div class="quick-grid">
            <label><span>{{ novoCliente.tipo==='J' ? 'CNPJ' : 'CPF' }}</span><div class="lookup-field"><input :value="novoCliente.cpf_cnpj" class="input" :maxlength="novoCliente.tipo==='J' ? 18 : 14" :placeholder="novoCliente.tipo==='J' ? '00.000.000/0000-00' : '000.000.000-00'" @input="formatarDocumentoCliente" @keydown.enter.prevent="novoCliente.tipo==='J' && consultarCnpj()"><button v-if="novoCliente.tipo==='J'" type="button" :disabled="consultandoCnpj" @click="consultarCnpj">{{ consultandoCnpj ? 'Buscando...' : 'Buscar CNPJ' }}</button></div></label>
            <label><span>Telefone</span><input v-model="novoCliente.telefone" class="input"></label>
            <label class="wide"><span>E-mail</span><input v-model="novoCliente.email" type="email" class="input"></label>
            <label><span>CEP</span><div class="lookup-field"><input :value="novoCliente.cep" class="input" maxlength="10" placeholder="00.000-000" @input="formatarCepCliente" @blur="consultarCepAutomatico" @keydown.enter.prevent="consultarCep"><button type="button" :disabled="consultandoCep" @click="consultarCep">{{ consultandoCep ? 'Buscando...' : 'Buscar CEP' }}</button></div></label>
            <label><span>UF</span><input v-model="novoCliente.estado" maxlength="2" class="input uppercase"></label>
            <label class="wide"><span>Endereço</span><input v-model="novoCliente.logradouro" class="input"></label>
            <label><span>Número</span><input v-model="novoCliente.numero" class="input"></label>
            <label><span>Bairro</span><input v-model="novoCliente.bairro" class="input"></label>
            <label><span>Cidade</span><input v-model="novoCliente.cidade" class="input"></label>
          </div>
          <button class="btn-primary w-full" type="submit" :disabled="salvandoCliente">{{ salvandoCliente ? 'Cadastrando...' : 'Cadastrar e selecionar cliente' }}</button>
        </form>

        <template v-else>
        <input v-model="buscaCliente" class="input" placeholder="Nome, CPF/CNPJ ou telefone" @input="buscarClientes"/>
        <button class="client-option" @click="selecionarCliente(null)">
          <strong>Consumidor final</strong><span>Venda sem cliente identificado</span>
        </button>
        <button v-for="c in clientes" :key="c.id" class="client-option" @click="selecionarCliente(c)">
          <strong>{{ c.nome_fantasia || c.razao_social }}</strong>
          <span>{{ c.cpf_cnpj || 'Sem documento' }} · {{ c.telefone || 'Sem telefone' }}</span>
        </button>
        </template>
      </div>
    </div>

    <!-- Pagamento -->
    <div v-if="modalPagamento" class="modal-backdrop">
      <div class="card modal-card payment-modal">
        <div class="modal-title"><h3>Recebimento</h3><button @click="modalPagamento=false">×</button></div>

        <div class="payment-total">{{ moeda(total) }}</div>

        <div class="payment-fiscal">
          <div><span>Documento</span><strong>{{ fiscalLabel }}</strong></div>
          <div><span>Destinatário</span><strong>{{ clienteSelecionado?.nome_fantasia || clienteSelecionado?.razao_social || 'Consumidor final' }}</strong></div>
          <label v-if="fiscalTipo==='nfe'"><input v-model="emitirAutomaticamente" type="checkbox"> Emitir e transmitir para a SEFAZ após receber</label>
        </div>

        <label class="label mt-3">Observações da venda <kbd>F8</kbd></label>
        <textarea ref="notesInput" v-model="observacoes" class="input min-h-16" placeholder="Informações adicionais do cupom ou da nota"/>

        <div class="payment-buttons">
          <button v-for="forma in formasRapidas" :key="forma.tipo" @click="adicionarPagamento(forma)">
            <component :is="forma.icon" class="w-5 h-5"/>
            <span>{{ forma.label }}</span>
          </button>
        </div>

        <div class="payment-list">
          <article v-for="(p,index) in pagamentos" :key="index">
            <select v-model="p.tipo" class="select">
              <option value="dinheiro">Dinheiro</option>
              <option value="pix">PIX</option>
              <option value="cartao_debito">Cartão de débito</option>
              <option value="cartao_credito">Cartão de crédito</option>
              <option value="transferencia">Transferência</option>
              <option value="crediario">Crediário</option>
              <option value="vale">Vale</option>
              <option value="outro">Outro</option>
            </select>
            <input v-model.number="p.valor" type="number" step="0.01" class="input"/>
            <input v-if="p.tipo==='cartao_credito'" v-model.number="p.parcelas" type="number" min="1" max="24" class="input" placeholder="Parcelas"/>
            <button class="btn-danger btn-sm" @click="pagamentos.splice(index,1)">Excluir</button>
          </article>
        </div>

        <div class="payment-balance">
          <div><span>Recebido</span><strong>{{ moeda(valorRecebido) }}</strong></div>
          <div><span>{{ saldoPagamento >= 0 ? 'Troco' : 'Falta' }}</span><strong :class="saldoPagamento < 0 ? 'text-danger' : 'text-success'">{{ moeda(Math.abs(saldoPagamento)) }}</strong></div>
        </div>

        <button class="btn-primary w-full" :disabled="saldoPagamento < 0 || finalizando" @click="concluirVenda">
          {{ finalizando ? 'Finalizando...' : 'Concluir venda' }}
        </button>
      </div>
    </div>


    <!-- Pós-venda -->
    <div v-if="modalPosVenda" class="modal-backdrop">
      <div class="card modal-card pos-sale-modal">
        <div class="sale-success">
          <CheckCircleIcon class="sale-success-icon"/>
          <div>
            <h3>Venda finalizada com sucesso</h3>
            <p>Venda #{{ ultimaVenda?.venda?.numero }} · {{ moeda(ultimaVenda?.venda?.total) }}</p>
          </div>
          <button class="close-sale-modal" @click="fecharPosVenda">×</button>
        </div>

        <div class="flow-line">
          <strong>Venda Finalizada</strong>
          <span>›</span>
          <strong>Escolha o documento</strong>
          <span>›</span>
          <strong>PDF ou papel físico</strong>
        </div>

        <div class="document-choice">
          <article v-if="ultimaVenda?.fiscalTipo !== 'nenhum'" class="document-card fiscal-document">
            <DocumentTextIcon/>
            <div>
              <strong>{{ ultimaVenda?.fiscalTipo === 'nfe' ? 'Nota Fiscal NF-e' : 'Nota Fiscal NFC-e' }}</strong>
              <span>{{ ultimaVenda?.fiscalMensagem || 'Documento fiscal da venda' }}</span>
            </div>
            <div class="output-buttons">
              <button class="btn-secondary" :disabled="documentoProcessando" @click="emitirSaida('fiscal','pdf')">
                <ArrowDownTrayIcon/> PDF
              </button>
              <button class="btn-primary" :disabled="documentoProcessando" @click="emitirSaida('fiscal','papel')">
                <PrinterIcon/> Papel físico
              </button>
            </div>
          </article>

          <article class="document-card receipt-document">
            <ReceiptPercentIcon/>
            <div>
              <strong>Recibo não fiscal</strong>
              <span>Comprovante de pagamento sem validade fiscal.</span>
            </div>
            <div class="output-buttons">
              <button class="btn-secondary" :disabled="documentoProcessando" @click="emitirSaida('recibo','pdf')">
                <ArrowDownTrayIcon/> PDF
              </button>
              <button class="btn-primary" :disabled="documentoProcessando" @click="emitirSaida('recibo','papel')">
                <PrinterIcon/> Papel físico
              </button>
            </div>
          </article>
        </div>

        <p v-if="ultimaVenda?.falhaFiscal" class="post-sale-warning">
          A venda foi concluída, porém o documento fiscal possui pendência: {{ ultimaVenda.falhaFiscal }}
        </p>

        <div class="post-sale-footer">
          <button class="btn-secondary" @click="fecharPosVenda">Nova venda sem imprimir</button>
          <span v-if="documentoProcessando">Preparando documento...</span>
        </div>
      </div>
    </div>

    <!-- Movimento -->
    <div v-if="modalMovimento" class="modal-backdrop">
      <div class="card modal-card">
        <div class="modal-title"><h3>{{ movimento.tipo === 'sangria' ? 'Sangria' : 'Suprimento' }}</h3><button @click="modalMovimento=false">×</button></div>
        <label class="label">Valor</label>
        <input v-model.number="movimento.valor" type="number" min="0.01" step="0.01" class="input"/>
        <label class="label mt-3">Descrição</label>
        <textarea v-model="movimento.descricao" class="input min-h-24"/>
        <button class="btn-primary w-full mt-4" @click="registrarMovimento">Registrar</button>
      </div>
    </div>

    <!-- Resumo -->
    <div v-if="modalResumo" class="modal-backdrop">
      <div class="card modal-card">
        <div class="modal-title"><h3>Resumo do caixa</h3><button @click="modalResumo=false">×</button></div>
        <div class="summary-lines">
          <div><span>Abertura</span><strong>{{ moeda(resumo?.valor_abertura) }}</strong></div>
          <div><span>Vendas</span><strong>{{ moeda(resumo?.vendas_total) }}</strong></div>
          <div><span>Suprimentos</span><strong>{{ moeda(resumo?.suprimentos) }}</strong></div>
          <div><span>Sangrias</span><strong>{{ moeda(resumo?.sangrias) }}</strong></div>
          <div><span>Vendas concluídas</span><strong>{{ resumo?.quantidade_vendas || 0 }}</strong></div>
          <div class="grand-total"><span>Dinheiro calculado</span><strong>{{ moeda(resumo?.saldo_dinheiro_calculado) }}</strong></div>
        </div>
      </div>
    </div>

    <!-- Fechamento -->
    <div v-if="modalFechamento" class="modal-backdrop">
      <div class="card modal-card">
        <div class="modal-title"><h3>Fechar caixa</h3><button @click="modalFechamento=false">×</button></div>
        <p class="text-xs text-[var(--text-muted)]">Dinheiro calculado: {{ moeda(resumo?.saldo_dinheiro_calculado) }}</p>
        <label class="label mt-4">Valor contado em dinheiro</label>
        <input v-model.number="fechamento.valor_informado" type="number" min="0" step="0.01" class="input"/>
        <label class="label mt-3">Observações</label>
        <textarea v-model="fechamento.observacoes" class="input min-h-24"/>
        <button class="btn-danger w-full mt-4" @click="fecharCaixa">Confirmar fechamento</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onBeforeUnmount, onMounted, watch } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/services/api'
import {
  ArrowDownTrayIcon, BanknotesIcon, CheckCircleIcon, CreditCardIcon, CubeIcon,
  DocumentTextIcon, MagnifyingGlassIcon, PrinterIcon, QrCodeIcon,
  ReceiptPercentIcon, ShoppingCartIcon
} from '@heroicons/vue/24/outline'

const toast = useToast()
const loading = ref(true)
const caixa = ref(null)
const empresaUf = ref('')
const resumo = ref(null)
const depositos = ref([])
const formasPagamento = ref([])
const busca = ref('')
const resultados = ref([])
const buscando = ref(false)
const loadingMoreProducts = ref(false)
const paginaProdutos = ref(1)
const ultimaPaginaProdutos = ref(1)
const pdvProductSentinel = ref(null)
const searchInput = ref(null)
const discountInput = ref(null)
const notesInput = ref(null)
const carrinho = ref([])
const mobilePanel = ref('catalogo')
const clienteSelecionado = ref(null)
const clientes = ref([])
const buscaCliente = ref('')
const mostrarCadastroCliente = ref(false)
const salvandoCliente = ref(false)
const consultandoCnpj = ref(false)
const consultandoCep = ref(false)
const novoCliente = ref(clienteVazio())
const desconto = ref(0)
const acrescimo = ref(0)
const pagamentos = ref([])
const finalizando = ref(false)
const fiscalTipo = ref('nfce')
const fiscalPerfil = ref('automatico')
const emitirAutomaticamente = ref(true)
const observacoes = ref('')
const modalCliente = ref(false)
const modalPagamento = ref(false)
const modalPosVenda = ref(false)
const ultimaVenda = ref(null)
const documentoProcessando = ref(false)
const modalMovimento = ref(false)
const modalResumo = ref(false)
const modalFechamento = ref(false)
const movimento = ref({ tipo: 'sangria', valor: 0, descricao: '' })
const fechamento = ref({ valor_informado: 0, observacoes: '' })
const abertura = ref({ deposito_id: null, valor_abertura: 0, observacoes: '' })
let searchTimer = null
let clientTimer = null

const formasRapidas = [
  { tipo:'dinheiro', label:'Dinheiro', icon:BanknotesIcon },
  { tipo:'pix', label:'PIX', icon:QrCodeIcon },
  { tipo:'cartao_debito', label:'Débito', icon:CreditCardIcon },
  { tipo:'cartao_credito', label:'Crédito', icon:CreditCardIcon },
]

const subtotal = computed(() => carrinho.value.reduce((s,item)=>s+totalItem(item),0))
const total = computed(() => Math.max(0, subtotal.value - Number(desconto.value||0) + Number(acrescimo.value||0)))
const valorRecebido = computed(() => pagamentos.value.reduce((s,p)=>s+Number(p.valor||0),0))
const saldoPagamento = computed(() => Number((valorRecebido.value-total.value).toFixed(2)))
const fiscalLabel = computed(() => fiscalTipo.value === 'nfce' ? 'Cupom fiscal NFC-e' : fiscalTipo.value === 'nfe' ? 'Nota fiscal NF-e' : 'Comprovante não fiscal')
const fiscalStatus = computed(() => fiscalTipo.value === 'nfe' ? 'SEFAZ' : fiscalTipo.value === 'nfce' ? 'CONSUMIDOR' : 'NÃO FISCAL')

function moeda(v){ return new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(v||0)) }
function numero(v,c=2){ return new Intl.NumberFormat('pt-BR',{minimumFractionDigits:c,maximumFractionDigits:c}).format(Number(v||0)) }
function totalItem(item){ return Math.max(0, Number(item.quantidade||0)*Number(item.preco_unitario||0)-Number(item.desconto||0)) }

async function carregar(){
  loading.value=true
  try{
    const {data}=await api.get('/pdv/bootstrap')
    caixa.value=data.caixa
    empresaUf.value=String(data.empresa?.uf||'').trim().toUpperCase()
    resumo.value=data.resumo
    depositos.value=data.depositos||[]
    formasPagamento.value=data.formas_pagamento||[]
    if(!abertura.value.deposito_id) abertura.value.deposito_id=depositos.value[0]?.id||null
  }finally{loading.value=false; nextTick(()=>searchInput.value?.focus())}
}

async function abrirCaixa(){
  try{
    const {data}=await api.post('/pdv/caixas/abrir',abertura.value)
    caixa.value=data.caixa
    toast.success(data.message)
    await carregar()
  }catch(e){toast.error(e.response?.data?.message||'Não foi possível abrir o caixa.')}
}

function debouncedSearch(){
  clearTimeout(searchTimer)
  searchTimer=setTimeout(()=>buscarProdutos(false),250)
}
async function buscarProdutos(append=false){
  if(!busca.value.trim()){resultados.value=[];paginaProdutos.value=1;ultimaPaginaProdutos.value=1;return}
  if(append ? loadingMoreProducts.value : buscando.value)return
  if(append)loadingMoreProducts.value=true
  else{buscando.value=true;paginaProdutos.value=1}
  try{
    const {data}=await api.get('/pdv/produtos',{params:{q:busca.value,deposito_id:caixa.value?.deposito_id,page:paginaProdutos.value,per_page:30}})
    const recebidos=data.data||[]
    resultados.value=append?[...resultados.value,...recebidos]:recebidos
    ultimaPaginaProdutos.value=data.last_page||1
    if(!append&&resultados.value.length===1 && [resultados.value[0].ean,resultados.value[0].sku,resultados.value[0].codigo].includes(busca.value.trim())){
      adicionarProduto(resultados.value[0])
    }
  }finally{buscando.value=false;loadingMoreProducts.value=false}
}
async function carregarMaisProdutos(){
  if(paginaProdutos.value>=ultimaPaginaProdutos.value||loadingMoreProducts.value)return
  paginaProdutos.value+=1
  try{await buscarProdutos(true)}catch(error){paginaProdutos.value-=1;throw error}
}
function adicionarPrimeiroResultado(){ if(resultados.value[0]) adicionarProduto(resultados.value[0]) }
function focarBuscaMobile(){ nextTick(()=>searchInput.value?.focus()) }
function adicionarProduto(produto){
  if(produto.controla_estoque && produto.estoque_disponivel<=0){toast.warning('Produto sem estoque disponível.');return}
  const existente=carrinho.value.find(i=>i.produto_id===produto.id)
  if(existente) existente.quantidade+=1
  else carrinho.value.push({
    produto_id:produto.id,nome:produto.nome,codigo:produto.sku||produto.codigo||produto.ean||'',
    quantidade:1,preco_unitario:Number(produto.preco||0),desconto:0,
    estoque_disponivel:Number(produto.estoque_disponivel||0),controla_estoque:produto.controla_estoque
  })
  busca.value=''; resultados.value=[]; nextTick(()=>searchInput.value?.focus())
}
function alterarQuantidade(index,delta){
  const item=carrinho.value[index]
  item.quantidade=Math.max(0.001,Number(item.quantidade||0)+delta)
}
function normalizarQuantidade(item){
  item.quantidade=Math.max(0.001,Number(item.quantidade||0))
  if(item.controla_estoque && item.quantidade>item.estoque_disponivel){
    item.quantidade=item.estoque_disponivel
    toast.warning('Quantidade ajustada ao estoque disponível.')
  }
}
function removerItem(index){carrinho.value.splice(index,1)}
function limparCarrinho(){if(confirm('Limpar o cupom atual?')) carrinho.value=[]}

function selecionarFiscal(tipo){
  fiscalPerfil.value=tipo
  aplicarPerfilFiscal(true)
  tipo=fiscalTipo.value
  if(tipo==='nfe' && !clienteSelecionado.value){
    modalCliente.value=true
    toast.info('Selecione o cliente que receberá a NF-e.')
  }
}

function aplicarPerfilFiscal(notificar=false){
  const perfil=fiscalPerfil.value
  if(perfil==='automatico'){
    const ufCliente=String(clienteSelecionado.value?.estado||'').trim().toUpperCase()
    fiscalTipo.value=clienteSelecionado.value && ufCliente && empresaUf.value && ufCliente!==empresaUf.value ? 'nfe' : 'nfce'
    if(notificar && fiscalTipo.value==='nfe') toast.info(`DestinatÃ¡rio de ${ufCliente}: perfil alterado automaticamente para NF-e modelo 55.`)
    return
  }
  fiscalTipo.value=perfil
}

function abrirPagamento(){
  aplicarPerfilFiscal(false)
  if(fiscalTipo.value==='nfe'){
    const documento=String(clienteSelecionado.value?.cpf_cnpj||'').replace(/\D/g,'')
    if(!clienteSelecionado.value || ![11,14].includes(documento.length)){
      modalCliente.value=true
      toast.warning('A NF-e exige cliente identificado com CPF ou CNPJ válido.')
      return
    }
  }
  pagamentos.value=[{tipo:'dinheiro',valor:Number(total.value.toFixed(2)),parcelas:1}]
  modalPagamento.value=true
}
function adicionarPagamento(forma){
  const restante=Math.max(0,total.value-valorRecebido.value)
  pagamentos.value.push({tipo:forma.tipo,valor:Number(restante.toFixed(2)),parcelas:1})
}

async function concluirVenda(){
  finalizando.value=true
  const fiscalSelecionado=fiscalTipo.value
  try{
    const payload={
      cliente_id:clienteSelecionado.value?.id||null,
      deposito_id:caixa.value.deposito_id||depositos.value[0]?.id,
      desconto:Number(desconto.value||0),acrescimo:Number(acrescimo.value||0),
      itens:carrinho.value.map(i=>({produto_id:i.produto_id,quantidade:i.quantidade,preco_unitario:i.preco_unitario,desconto:i.desconto||0})),
      observacoes:observacoes.value,
      pagamentos:pagamentos.value.map(p=>({...p,forma_pagamento_id:formaPagamentoId(p.tipo)}))
    }

    const {data}=await api.post('/pdv/vendas',payload)
    let mensagem=`${data.message} Troco: ${moeda(data.troco)}`
    let falhaFiscal=''
    let notaFiscalId=null

    try{
      if(fiscalSelecionado==='nfe'){
        const notaCriada=await api.post('/nfe',{pedido_id:data.pedido.id})
        notaFiscalId=notaCriada.data.data.id

        if(emitirAutomaticamente.value){
          const emissao=await api.post(`/nfe/${notaFiscalId}/emitir`)
          mensagem=`Venda concluída e NF-e ${emissao.data.data?.status==='autorizada' ? 'autorizada pela SEFAZ' : 'processada'}.`
        }else{
          mensagem='Venda concluída e NF-e salva como rascunho.'
        }
      }else if(fiscalSelecionado==='nfce'){
        await api.post(`/pdv/vendas/${data.venda.id}/preparar-nfce`)
        mensagem='Venda concluída. NFC-e pronta para geração e impressão.'
      }
    }catch(e){
      falhaFiscal=e.response?.data?.message||'Não foi possível concluir o documento fiscal.'
    }

    if(falhaFiscal) toast.warning(`A venda foi concluída, mas o fiscal ficou pendente: ${falhaFiscal}`)
    else toast.success(mensagem)

    ultimaVenda.value={
      venda:data.venda,
      pedido:data.pedido,
      troco:data.troco,
      fiscalTipo:fiscalSelecionado,
      notaFiscalId,
      reciboId:null,
      fiscalMensagem:mensagem,
      falhaFiscal,
      pagamentos:[...pagamentos.value],
      observacoes:observacoes.value
    }

    resumo.value=data.resumo_caixa
    carrinho.value=[]
    clienteSelecionado.value=null
    desconto.value=0
    acrescimo.value=0
    pagamentos.value=[]
    observacoes.value=''
    fiscalPerfil.value='automatico'
    fiscalTipo.value='nfce'
    modalPagamento.value=false
    modalPosVenda.value=true
  }catch(e){
    toast.error(e.response?.data?.message||'Erro ao concluir a venda.')
  }finally{
    finalizando.value=false
  }
}

async function emitirSaida(documento,formato){
  if(!ultimaVenda.value) return

  documentoProcessando.value=true
  try{
    if(documento==='recibo'){
      await prepararRecibo()

      if(formato==='pdf'){
        const {data}=await api.get(`/recibos/${ultimaVenda.value.reciboId}/pdf`)
        abrirArquivo(data.url,false)
      }else{
        await imprimirComprovanteNaoFiscal()
      }
      return
    }

    if(ultimaVenda.value.fiscalTipo==='nfe'){
      await prepararNfeParaImpressao()
      const response=await api.get(`/nfe/${ultimaVenda.value.notaFiscalId}/danfe-arquivo`,{responseType:'blob'})
      const url=URL.createObjectURL(new Blob([response.data],{type:'application/pdf'}))

      if(formato==='pdf'){
        const a=document.createElement('a')
        a.href=url
        a.download=`NFe-${ultimaVenda.value.notaFiscalId}.pdf`
        a.click()
        setTimeout(()=>URL.revokeObjectURL(url),30000)
      }else{
        abrirArquivo(url,true,true)
      }
      return
    }

    if(ultimaVenda.value.fiscalTipo==='nfce'){
      await prepararNfceParaImpressao()
      const response=await api.get(`/pdv/nfce/${ultimaVenda.value.venda.id}/pdf`,{responseType:'blob'})
      const url=URL.createObjectURL(new Blob([response.data],{type:'application/pdf'}))

      if(formato==='pdf'){
        const a=document.createElement('a')
        a.href=url
        a.download=`NFCe-venda-${ultimaVenda.value.venda.numero}.pdf`
        a.click()
        setTimeout(()=>URL.revokeObjectURL(url),30000)
      }else{
        abrirArquivo(url,true,true)
      }
      return
    }

    toast.info('A venda foi concluída sem documento fiscal. Escolha o recibo não fiscal.')
  }catch(e){
    toast.error(e.response?.data?.message||'Não foi possível preparar o documento.')
  }finally{
    documentoProcessando.value=false
  }
}

async function prepararRecibo(){
  if(ultimaVenda.value.reciboId) return

  const forma=ultimaVenda.value.pagamentos.map(p=>p.tipo).join(', ')
  const {data}=await api.post('/recibos',{
    pedido_id:ultimaVenda.value.pedido.id,
    forma_pagamento:forma,
    observacoes:ultimaVenda.value.observacoes||null
  })
  ultimaVenda.value.reciboId=data.recibo.id
}

async function prepararNfeParaImpressao(){
  if(!ultimaVenda.value.notaFiscalId){
    const criada=await api.post('/nfe',{pedido_id:ultimaVenda.value.pedido.id})
    ultimaVenda.value.notaFiscalId=criada.data.data.id
  }

  try{
    await api.get(`/nfe/${ultimaVenda.value.notaFiscalId}/danfe`)
  }catch(e){
    if(e.response?.status===422){
      await api.post(`/nfe/${ultimaVenda.value.notaFiscalId}/emitir`)
    }else{
      throw e
    }
  }

  ultimaVenda.value.falhaFiscal=''
  ultimaVenda.value.fiscalMensagem='NF-e autorizada e DANFE preparado com sucesso.'
}

async function prepararNfceParaImpressao(){
  const vendaId=ultimaVenda.value.venda.id
  const validacao=await api.get(`/pdv/nfce/${vendaId}/validar`)

  if(!validacao.data.valido){
    throw new Error(validacao.data.erros?.join(' ')||'Existem pendências fiscais na NFC-e.')
  }

  let documento=(await api.get(`/pdv/nfce/${vendaId}/documento`)).data.data
  if(!documento){
    documento=(await api.post(`/pdv/nfce/${vendaId}/gerar`)).data.documento
  }

  if(documento?.status!=='autorizada'){
    documento=(await api.post(`/pdv/nfce/${vendaId}/emitir`)).data.documento
  }

  if(documento?.status!=='autorizada' || !documento?.chave_acesso || !documento?.protocolo){
    throw new Error('A NFC-e ainda não foi autorizada pela SEFAZ. O PDF fiscal não pode ser liberado.')
  }
}

async function imprimirComprovanteNaoFiscal(){
  const {data}=await api.get(`/pdv/vendas/${ultimaVenda.value.venda.id}/comprovante`)
  const c=data.data
  const itens=(c.itens||[]).map(i=>`
    <div class="item">
      <strong>${escapeHtml(i.descricao)}</strong>
      <div><span>${numero(i.quantidade,3)} ${escapeHtml(i.unidade)} x ${moeda(i.preco_unitario)}</span><b>${moeda(i.total)}</b></div>
    </div>`).join('')
  const pagamentos=(c.pagamentos||[]).map(p=>`<div><span>${escapeHtml(p.tipo)}</span><b>${moeda(p.valor)}</b></div>`).join('')

  const janela=window.open('','_blank','width=420,height=760')
  if(!janela) throw new Error('O navegador bloqueou a janela de impressão.')

  janela.document.write(`<!doctype html><html><head><title>Recibo ${escapeHtml(c.numero)}</title>
    <style>
      body{font:12px Arial,sans-serif;color:#111;margin:0;padding:12px}
      main{width:80mm;margin:auto}.center{text-align:center}.line{border-top:1px dashed #333;margin:10px 0}
      main>div,.item>div{display:flex;justify-content:space-between;gap:10px;margin:5px 0}
      .item{margin:9px 0}.total{font-size:16px;font-weight:bold}
      @page{size:80mm auto;margin:3mm}
    </style></head><body><main>
      <section class="center"><h2>${escapeHtml(c.empresa.nome_fantasia||c.empresa.razao_social||'IntelbrasTech')}</h2>
      <div>${escapeHtml(c.empresa.razao_social||'')}</div><div>CNPJ: ${escapeHtml(c.empresa.cnpj||'')}</div></section>
      <div class="line"></div><div><span>Venda</span><b>#${escapeHtml(c.numero)}</b></div>
      <div><span>Data</span><span>${escapeHtml(c.data)}</span></div>
      <div><span>Cliente</span><span>${escapeHtml(c.cliente.nome)}</span></div>
      <div class="line"></div>${itens}<div class="line"></div>
      <div><span>Subtotal</span><b>${moeda(c.subtotal)}</b></div>
      <div class="total"><span>Total</span><b>${moeda(c.total)}</b></div>
      <div class="line"></div>${pagamentos}
      <div><span>Troco</span><b>${moeda(c.troco)}</b></div>
      <div class="line"></div><section class="center"><strong>RECIBO NÃO FISCAL</strong>
      <p>${escapeHtml(c.codigo)}</p><p>Obrigado pela preferência!</p></section>
    </main></body></html>`)
  janela.document.close()
  janela.focus()
  setTimeout(()=>janela.print(),400)
}

function abrirArquivo(url,imprimir=false,revogar=false){
  const janela=window.open(url,'_blank')
  if(!janela) throw new Error('O navegador bloqueou a abertura do documento.')

  if(imprimir){
    janela.addEventListener('load',()=>setTimeout(()=>{
      try{janela.print()}catch{}
      if(revogar) setTimeout(()=>URL.revokeObjectURL(url),30000)
    },700))
  }
}

function escapeHtml(valor){
  return String(valor??'').replace(/[&<>"']/g,c=>({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[c]))
}

function fecharPosVenda(){
  modalPosVenda.value=false
  ultimaVenda.value=null
  nextTick(()=>searchInput.value?.focus())
}

function formaPagamentoId(tipo){return formasPagamento.value.find(f=>f.tipo===tipo)?.id||null}

function clienteVazio(){
  return {tipo:'F',razao_social:'',nome_fantasia:'',cpf_cnpj:'',telefone:'',email:'',cep:'',logradouro:'',numero:'',complemento:'',bairro:'',cidade:'',estado:'',ibge:''}
}

function mascaraDocumento(valor,tipo){
  const numeros=String(valor||'').replace(/\D/g,'').slice(0,tipo==='J'?14:11)
  if(tipo==='J') return numeros.replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3').replace(/\.(\d{3})(\d)/,'.$1/$2').replace(/(\d{4})(\d)/,'$1-$2')
  return numeros.replace(/^(\d{3})(\d)/,'$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/,'$1.$2.$3').replace(/(\d{3})(\d{1,2})$/,'$1-$2')
}

function mascaraCep(valor){
  return String(valor||'').replace(/\D/g,'').slice(0,8).replace(/^(\d{2})(\d)/,'$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2-$3')
}

function formatarDocumentoCliente(event){novoCliente.value.cpf_cnpj=mascaraDocumento(event.target.value,novoCliente.value.tipo)}
function formatarCepCliente(event){novoCliente.value.cep=mascaraCep(event.target.value)}

function preencherCliente(dados){
  for(const campo of ['razao_social','nome_fantasia','cep','logradouro','numero','complemento','bairro','cidade','estado','telefone','email','ibge']){
    if(dados?.[campo]) novoCliente.value[campo]=dados[campo]
  }
  if(dados?.cnpj) novoCliente.value.cpf_cnpj=mascaraDocumento(dados.cnpj,'J')
  if(dados?.cep) novoCliente.value.cep=mascaraCep(dados.cep)
}

async function consultarCnpj(){
  const cnpj=String(novoCliente.value.cpf_cnpj||'').replace(/\D/g,'')
  if(cnpj.length!==14){toast.warning('Informe os 14 dígitos do CNPJ.');return}
  consultandoCnpj.value=true
  try{
    const {data}=await api.get(`/empresa/consultar-cnpj/${cnpj}`)
    preencherCliente(data.data)
    toast.success(`Dados carregados por ${data.data?.fonte||'consulta pública'}.`)
  }catch(e){toast.error(e.response?.data?.message||'Não foi possível consultar o CNPJ.')}
  finally{consultandoCnpj.value=false}
}

async function consultarCep(){
  const cep=String(novoCliente.value.cep||'').replace(/\D/g,'')
  if(cep.length!==8){toast.warning('Informe os 8 dígitos do CEP.');return}
  consultandoCep.value=true
  try{
    const {data}=await api.get(`/empresa/consultar-cep/${cep}`)
    preencherCliente(data.data)
    toast.success('Endereço preenchido automaticamente.')
  }catch(e){toast.error(e.response?.data?.message||'Não foi possível consultar o CEP.')}
  finally{consultandoCep.value=false}
}

function consultarCepAutomatico(){
  if(String(novoCliente.value.cep||'').replace(/\D/g,'').length===8 && !consultandoCep.value) consultarCep()
}

async function cadastrarClienteRapido(){
  salvandoCliente.value=true
  try{
    const payload={
      ...novoCliente.value,
      cpf_cnpj:String(novoCliente.value.cpf_cnpj||'').replace(/\D/g,''),
      cep:String(novoCliente.value.cep||'').replace(/\D/g,''),
      estado:String(novoCliente.value.estado||'').toUpperCase()
    }
    const {data}=await api.post('/clientes',payload)
    novoCliente.value=clienteVazio()
    mostrarCadastroCliente.value=false
    selecionarCliente(data.cliente)
    toast.success('Cliente cadastrado e selecionado para esta venda.')
  }catch(e){
    const erros=e.response?.data?.errors
    const primeiraMensagem=erros ? Object.values(erros).flat()[0] : null
    toast.error(primeiraMensagem||e.response?.data?.message||'Não foi possível cadastrar o cliente.')
  }finally{salvandoCliente.value=false}
}

function buscarClientes(){
  clearTimeout(clientTimer)
  clientTimer=setTimeout(async()=>{
    if(!buscaCliente.value.trim()){clientes.value=[];return}
    const {data}=await api.get('/pdv/clientes',{params:{q:buscaCliente.value}})
    clientes.value=data.data||[]
  },250)
}
function selecionarCliente(c){
  clienteSelecionado.value=c;modalCliente.value=false;buscaCliente.value='';clientes.value=[]
  aplicarPerfilFiscal(true)
}

function abrirMovimento(tipo){movimento.value={tipo,valor:0,descricao:''};modalMovimento.value=true}
async function registrarMovimento(){
  try{
    const {data}=await api.post('/pdv/caixas/movimentar',movimento.value)
    resumo.value=data.resumo;modalMovimento.value=false;toast.success(data.message)
  }catch(e){toast.error(e.response?.data?.message||'Erro ao registrar movimento.')}
}
async function fecharCaixa(){
  try{
    const {data}=await api.post('/pdv/caixas/fechar',fechamento.value)
    toast.success(data.message);modalFechamento.value=false;caixa.value=null;resumo.value=null
  }catch(e){toast.error(e.response?.data?.message||'Erro ao fechar caixa.')}
}

function onKey(e){
  if(e.key==='F2'){e.preventDefault();searchInput.value?.focus()}
  if(e.key==='F3'){e.preventDefault();modalCliente.value=true}
  if(e.key==='F4'){e.preventDefault();discountInput.value?.focus();discountInput.value?.select()}
  if(e.key==='F5'&&carrinho.value.length){e.preventDefault();abrirPagamento()}
  if(e.key==='F6'){e.preventDefault();selecionarFiscal('nfce')}
  if(e.key==='F7'){e.preventDefault();selecionarFiscal('nfe')}
  if(e.key==='F8'){e.preventDefault();if(!modalPagamento.value) abrirPagamento();nextTick(()=>notesInput.value?.focus())}
  if(e.key==='Escape'){modalCliente.value=false;modalPagamento.value=false;modalMovimento.value=false;modalResumo.value=false;modalFechamento.value=false}
}
let pdvProductsObserver
onMounted(()=>{
  pdvProductsObserver=new IntersectionObserver(entries=>{if(entries[0]?.isIntersecting)carregarMaisProdutos()},{rootMargin:'240px'})
  carregar();window.addEventListener('keydown',onKey)
})
watch(pdvProductSentinel,(element,previous)=>{if(previous)pdvProductsObserver?.unobserve(previous);if(element)pdvProductsObserver?.observe(element)})
onBeforeUnmount(()=>{clearTimeout(searchTimer);clearTimeout(clientTimer);window.removeEventListener('keydown',onKey);pdvProductsObserver?.disconnect()})
</script>

<style scoped>
.pdv-shell{min-height:calc(100vh - 120px);display:flex;flex-direction:column;gap:12px}
.pdv-header{display:flex;align-items:center;justify-content:space-between;gap:12px}
.pdv-header h1{font-size:1.2rem;font-weight:800;color:var(--text-primary)}
.pdv-header p{font-size:.7rem;color:var(--text-muted)}
.pdv-header-actions{display:flex;gap:8px;flex-wrap:wrap}
.open-box{min-height:65vh;display:flex;align-items:center;justify-content:center}
.pdv-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(380px,.65fr);gap:12px;min-height:calc(100vh - 185px)}
.catalog-panel,.cart-panel{border:1px solid var(--border);border-radius:12px;background:var(--bg-card);overflow:hidden}
.catalog-panel{display:flex;flex-direction:column}
.search-row{display:flex;align-items:center;gap:10px;padding:12px;border-bottom:1px solid var(--border);color:var(--text-muted)}
.pdv-search{flex:1;background:transparent;outline:none;font-size:.9rem;color:var(--text-primary)}
kbd{border:1px solid var(--border);border-radius:5px;padding:2px 5px;font-size:.6rem;color:var(--text-muted);background:var(--bg-input)}
.catalog-empty{flex:1;display:flex;align-items:center;justify-content:center;padding:30px;font-size:.78rem;color:var(--text-muted)}
.product-results{padding:10px;overflow:auto;display:flex;flex-direction:column;gap:8px}
.pdv-load-more{min-height:48px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:.68rem}.pdv-load-more button{width:100%;min-height:42px;border:1px solid var(--border);border-radius:8px;background:var(--bg-elevated);color:var(--text-secondary);font-weight:700}
.product-result{display:grid;grid-template-columns:56px 1fr auto;align-items:center;gap:12px;text-align:left;padding:10px;border:1px solid var(--border);border-radius:10px;background:var(--bg-input)}
.product-result:hover{border-color:var(--primary);background:var(--bg-elevated)}
.product-image{width:56px;height:56px;border-radius:8px;background:white;display:flex;align-items:center;justify-content:center;overflow:hidden;color:var(--text-muted)}
.product-image img{width:100%;height:100%;object-fit:contain}
.product-copy{min-width:0;display:flex;flex-direction:column}
.product-copy strong{font-size:.76rem;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.product-copy span,.product-copy small{font-size:.65rem;color:var(--text-muted);margin-top:2px}
.product-price{font-weight:800;color:var(--text-primary)}
.cart-panel{display:flex;flex-direction:column}
.cart-top,.client-box{display:flex;align-items:center;justify-content:space-between;padding:12px;border-bottom:1px solid var(--border)}
.cart-top h2{font-size:.86rem;font-weight:700}.cart-top p,.client-box span{font-size:.65rem;color:var(--text-muted)}
.client-box strong{display:block;font-size:.74rem;color:var(--text-primary);margin-top:2px}
.fiscal-box{padding:10px 12px;border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--primary) 4%,transparent)}
.fiscal-title{display:flex;align-items:center;justify-content:space-between}.fiscal-title span{display:block;font-size:.62rem;color:var(--text-muted)}.fiscal-title strong{display:block;margin-top:2px;font-size:.74rem;color:var(--text-primary)}
.fiscal-status{padding:3px 7px;border-radius:999px;background:var(--bg-input);font-weight:700}.fiscal-status.nfe{color:var(--success)}.fiscal-status.nfce{color:var(--primary)}
.fiscal-profile{display:grid;grid-template-columns:auto 1fr;align-items:center;gap:10px;margin-top:8px}.fiscal-profile>span{font-size:.64rem;color:var(--text-muted)}.fiscal-profile .select{min-height:32px;padding:5px 9px;font-size:.68rem}
.fiscal-options{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:8px}.fiscal-options button{padding:7px;border:1px solid var(--border);border-radius:7px;font-size:.68rem}.fiscal-options button.active{border-color:var(--primary);background:color-mix(in srgb,var(--primary) 14%,transparent);color:var(--text-primary)}
.fiscal-hint,.fiscal-warning,.fiscal-rule{margin-top:7px;font-size:.62rem}.fiscal-hint{color:var(--text-muted)}.fiscal-warning{color:var(--warning)}.fiscal-rule{color:var(--success)}
.cart-items{flex:1;overflow:auto;padding:8px}
.cart-empty{height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--text-muted);font-size:.75rem;gap:8px}
.cart-item{padding:10px;border-bottom:1px solid var(--border-subtle)}
.cart-item-main{display:flex;justify-content:space-between;gap:10px}.cart-item-main strong{font-size:.72rem;color:var(--text-primary)}.cart-item-main span{font-size:.62rem;color:var(--text-muted)}
.cart-item-controls,.cart-item-values{display:flex;align-items:center;gap:6px;margin-top:7px}
.cart-item-controls button{width:26px;height:26px;border:1px solid var(--border);border-radius:6px}
.cart-item-controls input{width:62px;text-align:center;background:var(--bg-input);border:1px solid var(--border);border-radius:6px;height:26px;font-size:.7rem}
.cart-item-values input{width:90px;background:var(--bg-input);border:1px solid var(--border);border-radius:6px;padding:5px;font-size:.7rem}
.cart-item-values strong{margin-left:auto;font-size:.72rem}.remove-item{color:var(--danger);font-size:1rem}
.cart-summary{padding:12px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:7px}
.cart-summary>div{display:flex;justify-content:space-between;align-items:center;font-size:.72rem}
.summary-input input{width:100px;text-align:right;background:var(--bg-input);border:1px solid var(--border);border-radius:6px;padding:5px}
.grand-total{padding-top:8px;border-top:1px solid var(--border);font-size:.9rem!important}.grand-total strong{font-size:1.2rem}
.finish-sale{margin:0 12px 12px;display:flex;align-items:center;justify-content:center;gap:8px;border-radius:10px;padding:13px;background:var(--primary);color:white;font-weight:700}
.finish-sale:disabled{opacity:.45}
.modal-backdrop{position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;padding:16px}
.modal-card{width:min(620px,100%);max-height:88vh;overflow:auto;padding:18px}
.modal-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}.modal-title h3{font-weight:800;color:var(--text-primary)}.modal-title button{font-size:1.4rem;color:var(--text-muted)}
.client-option{width:100%;text-align:left;padding:10px;border-bottom:1px solid var(--border)}.client-option strong{display:block;font-size:.75rem}.client-option span{font-size:.65rem;color:var(--text-muted)}
.new-client-button{width:100%;display:flex;align-items:center;justify-content:center;gap:7px;margin-bottom:12px;padding:10px;border:1px solid #22a95a;border-radius:8px;color:#22a95a;font-size:.74rem;font-weight:800}.new-client-button span{font-size:1.1rem;line-height:1}
.quick-client-form{display:flex;flex-direction:column;gap:12px}.quick-client-form label>span{display:block;margin-bottom:5px;font-size:.65rem;font-weight:700;color:var(--text-secondary)}
.type-switch{display:grid;grid-template-columns:1fr 1fr;padding:3px;border-radius:8px;background:var(--bg-input)}.type-switch button{padding:8px;border-radius:6px;font-size:.7rem;color:var(--text-muted)}.type-switch button.active{background:#22a95a;color:#fff;font-weight:800}
.quick-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.quick-grid .wide{grid-column:1/-1}.uppercase{text-transform:uppercase}
.lookup-field{display:flex;min-width:0}.lookup-field .input{min-width:0;border-radius:7px 0 0 7px}.lookup-field button{flex:0 0 auto;padding:0 9px;border:1px solid #22a95a;border-left:0;border-radius:0 7px 7px 0;color:#22a95a;font-size:.62rem;font-weight:800;white-space:nowrap}.lookup-field button:disabled{opacity:.55}
.payment-total{text-align:center;font-size:2rem;font-weight:900;color:var(--text-primary);padding:10px}
.payment-fiscal{padding:10px;margin-bottom:12px;border:1px solid var(--border);border-radius:9px;background:var(--bg-input)}.payment-fiscal>div{display:flex;justify-content:space-between;gap:12px;font-size:.7rem}.payment-fiscal label{display:flex;align-items:center;gap:7px;margin-top:9px;font-size:.68rem;color:var(--text-secondary)}
.payment-buttons{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.payment-buttons button{padding:12px;border:1px solid var(--border);border-radius:9px;display:flex;flex-direction:column;align-items:center;gap:5px;font-size:.7rem}
.payment-list{margin-top:12px;display:flex;flex-direction:column;gap:8px}.payment-list article{display:grid;grid-template-columns:1fr 130px 90px auto;gap:8px}
.payment-balance{margin:14px 0;padding:12px;border-radius:9px;background:var(--bg-input)}.payment-balance>div{display:flex;justify-content:space-between;font-size:.78rem}
.summary-lines{display:flex;flex-direction:column;gap:10px}.summary-lines>div{display:flex;justify-content:space-between}

/* Frente de caixa em fluxo operacional: Produto > Cliente > Pagamento */
.pdv-shell{gap:0;border:1px solid var(--border);border-radius:14px;overflow:hidden;background:var(--bg-card);box-shadow:0 18px 55px rgba(0,0,0,.2)}
.pdv-header{min-height:54px;padding:10px 16px;background:linear-gradient(90deg,#15803d,#22a95a);color:#fff}
.pdv-header h1,.pdv-header p{color:#fff}.pdv-header p{opacity:.82}
.pdv-header-actions .btn-secondary{border-color:rgba(255,255,255,.35);background:rgba(255,255,255,.13);color:#fff}.pdv-header-actions .btn-danger{background:#fff;color:#b91c1c}
.pdv-grid{grid-template-columns:minmax(390px,43%) minmax(0,57%);gap:0;min-height:calc(100vh - 190px);background:var(--bg-card)}
.catalog-panel,.cart-panel{border:0;border-radius:0}.catalog-panel{border-right:1px solid var(--border)}
.sale-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:4px;padding:9px;background:var(--bg-elevated);border-bottom:1px solid var(--border)}
.sale-steps button{position:relative;display:flex;align-items:center;justify-content:center;gap:6px;min-height:36px;padding:7px 9px;border-radius:7px;background:var(--bg-input);color:var(--text-secondary);font-size:.7rem;font-weight:700}.sale-steps button.active{background:#22a95a;color:#fff}.sale-steps button:disabled{opacity:.45}.sale-steps button span{display:grid;place-items:center;width:18px;height:18px;border-radius:50%;background:rgba(255,255,255,.16);font-size:.62rem}
.catalog-tools{display:grid;grid-template-columns:1fr auto;align-items:end;gap:18px;padding:14px 14px 10px}.catalog-tools label>span{display:block;margin-bottom:5px;font-size:.64rem;font-weight:700;color:var(--text-secondary)}.price-list{min-width:0}.barcode-toggle{display:grid!important;grid-template-columns:auto auto;align-items:center;column-gap:7px}.barcode-toggle span{grid-column:1/-1}.barcode-toggle input{width:34px;height:18px;accent-color:#22a95a}.barcode-toggle b{font-size:.63rem;color:#22a95a}
.search-row{margin:0 14px 10px;padding:10px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg-input)}
.catalog-empty{align-items:flex-start;justify-content:flex-start;margin:0 14px;padding:24px;border:1px dashed var(--border);border-radius:10px}
.product-results{flex:1}.catalog-footer{display:grid;grid-template-columns:.8fr 1.35fr;gap:10px;padding:12px 14px;border-top:1px solid var(--border);background:var(--bg-elevated)}.catalog-footer button{min-height:38px}.catalog-footer .btn-primary{background:#22a95a}
.cart-top{min-height:55px;background:var(--bg-elevated)}.client-box{background:color-mix(in srgb,#22a95a 5%,var(--bg-card))}
.cart-items{background:var(--bg-card)}.cart-empty{gap:10px}.cart-empty strong{font-size:.9rem;color:var(--text-secondary)}.empty-store{display:grid;place-items:center;width:120px;height:90px;border-radius:18px;background:color-mix(in srgb,#22a95a 9%,transparent);color:#22a95a}
.cart-summary{background:var(--bg-elevated)}.total-dock{display:grid;grid-template-columns:1fr auto auto;align-items:center;gap:12px;padding:12px 14px;border-top:1px solid var(--border);background:var(--bg-card)}.total-dock>span{font-size:.72rem;color:var(--text-muted)}.total-dock>strong{font-size:1.55rem;color:var(--text-primary)}
.finish-sale{margin:0;min-width:150px;padding:11px 16px;background:#22a95a}
.mobile-pdv-nav{display:none}
@media(max-width:1100px){.pdv-grid{grid-template-columns:1fr}.cart-panel{min-height:600px}}

.pos-sale-modal{width:min(760px,calc(100% - 24px));max-height:94vh;overflow:auto}
.sale-success{display:flex;align-items:center;gap:14px;border-bottom:1px solid var(--border);padding-bottom:16px}
.sale-success-icon{width:44px;color:#22c55e}
.sale-success h3{font-size:18px;font-weight:800;color:var(--text-primary)}
.sale-success p{font-size:12px;color:var(--text-muted);margin-top:3px}
.close-sale-modal{margin-left:auto;font-size:24px;color:var(--text-muted)}
.flow-line{display:flex;align-items:center;justify-content:center;gap:12px;margin:18px 0;padding:10px;border-radius:8px;background:var(--bg-muted);font-size:11px;color:var(--text-muted)}
.flow-line strong{color:var(--text-primary)}
.document-choice{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.document-card{border:1px solid var(--border);border-radius:10px;padding:16px;background:var(--bg-elevated)}
.document-card>svg{width:32px;color:#60a5fa}
.receipt-document>svg{color:#34d399}
.document-card>div>strong,.document-card>div>span{display:block}
.document-card>div>strong{font-size:14px;margin-top:10px;color:var(--text-primary)}
.document-card>div>span{font-size:10px;line-height:1.5;color:var(--text-muted);margin-top:4px}
.output-buttons{display:grid!important;grid-template-columns:1fr 1fr;gap:8px;margin-top:16px}
.output-buttons button{display:flex;align-items:center;justify-content:center;gap:6px}
.output-buttons svg{width:16px}
.post-sale-warning{margin-top:12px;padding:10px;border:1px solid rgba(245,158,11,.35);border-radius:8px;background:rgba(245,158,11,.08);color:#fbbf24;font-size:11px}
.post-sale-footer{display:flex;justify-content:space-between;align-items:center;margin-top:16px}
.post-sale-footer span{font-size:11px;color:var(--text-muted)}
@media(max-width:680px){.document-choice{grid-template-columns:1fr}.flow-line{flex-wrap:wrap}.output-buttons{grid-template-columns:1fr}}

/* Operação móvel: uma etapa por tela, controles táteis e recebimento sempre acessível. */
@media(max-width:767px){
  .pdv-shell{min-height:calc(100dvh - 76px);margin:-12px;border:0;border-radius:0;overflow:visible;padding-bottom:calc(78px + env(safe-area-inset-bottom));box-shadow:none}
  .pdv-header{position:relative;z-index:3;min-height:auto;align-items:flex-start;padding:12px 14px;gap:10px}
  .pdv-header h1{font-size:1.05rem}.pdv-header p{font-size:.68rem}
  .pdv-header-actions{max-width:58%;justify-content:flex-end;gap:5px}
  .pdv-header-actions button{min-height:36px;padding:6px 9px;font-size:.65rem}

  .pdv-grid{display:block;min-height:calc(100dvh - 154px);background:var(--bg-card)}
  .pdv-grid.mobile-panel-catalogo .cart-panel,.pdv-grid.mobile-panel-carrinho .catalog-panel{display:none}
  .catalog-panel,.cart-panel{min-height:calc(100dvh - 154px)!important;border:0!important}
  .catalog-panel{border-right:0}
  .cart-panel{height:auto}

  .sale-steps{display:none}
  .catalog-tools{grid-template-columns:1fr auto;padding:10px 12px 8px;gap:12px}
  .catalog-tools .select{min-height:42px;font-size:.8rem}
  .barcode-toggle>span,.barcode-toggle>b{display:none!important}
  .barcode-toggle{display:flex!important;align-items:center!important;padding-bottom:11px}
  .barcode-toggle input{width:42px;height:24px}
  .search-row{position:sticky;top:0;z-index:2;margin:0 10px 10px;padding:0 13px;min-height:50px;background:var(--bg-input);box-shadow:0 5px 14px rgba(0,0,0,.12)}
  .pdv-search{min-width:0;font-size:16px}
  .search-row kbd,kbd{display:none}
  .catalog-empty{min-height:42dvh;margin:0 10px;padding:24px 18px;align-items:center;justify-content:center;text-align:center;font-size:.82rem}
  .product-results{padding:0 10px 16px;gap:9px;overflow:visible;touch-action:pan-y;overscroll-behavior-y:auto}
  .product-result{grid-template-columns:62px minmax(0,1fr) auto;min-height:84px;padding:10px;gap:10px}
  .product-image{width:62px;height:62px}.product-copy strong{font-size:.82rem}.product-copy span,.product-copy small{font-size:.7rem}.product-price{font-size:.85rem}
  .catalog-footer{display:none}

  .cart-top,.client-box{min-height:58px;padding:11px 13px}
  .cart-top h2{font-size:.92rem}.client-box strong{max-width:58vw;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.78rem}
  .client-box button{min-height:40px}
  .fiscal-box{padding:11px 13px}.fiscal-title strong{font-size:.8rem}
  .fiscal-profile{grid-template-columns:1fr;gap:5px}.fiscal-profile .select{min-height:44px;font-size:.75rem}
  .fiscal-options{grid-template-columns:1fr 1fr;gap:7px}.fiscal-options button{min-height:42px;font-size:.73rem}
  .fiscal-rule,.fiscal-hint,.fiscal-warning{font-size:.68rem;line-height:1.45}
  .cart-items{min-height:180px;overflow:visible;padding:8px 12px}
  .cart-empty{min-height:35dvh;text-align:center}.empty-store{width:96px;height:76px}
  .cart-item{padding:12px 2px}.cart-item-main strong{font-size:.8rem}.cart-item-main span{font-size:.68rem}
  .cart-item-controls,.cart-item-values{gap:8px;margin-top:9px}
  .cart-item-controls button{width:42px;height:42px;font-size:1.2rem}.cart-item-controls input{width:72px;height:42px;font-size:16px}
  .cart-item-values input{width:104px;height:42px;font-size:16px}.cart-item-values strong{font-size:.82rem}.remove-item{width:40px;height:40px;font-size:1.3rem}
  .cart-summary{padding:13px;gap:10px}.cart-summary>div{font-size:.78rem}.summary-input input{width:112px;min-height:40px;font-size:16px}.grand-total strong{font-size:1.35rem}
  .total-dock{display:none}

  .mobile-pdv-nav{position:fixed;z-index:80;left:0;right:0;bottom:0;display:grid;grid-template-columns:72px 72px minmax(0,1fr);gap:7px;padding:8px 10px calc(8px + env(safe-area-inset-bottom));border-top:1px solid var(--border);background:color-mix(in srgb,var(--bg-card) 94%,transparent);box-shadow:0 -8px 28px rgba(0,0,0,.28);backdrop-filter:blur(16px)}
  .mobile-pdv-nav>button{min-height:54px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:2px;color:var(--text-muted);font-size:.65rem;font-weight:700}
  .mobile-pdv-nav>button.active{background:color-mix(in srgb,#22a95a 14%,transparent);color:#22a95a}
  .mobile-cart-icon{position:relative}.mobile-cart-icon b{position:absolute;right:-10px;top:-8px;display:grid;place-items:center;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#ef4444;color:#fff;font-size:.6rem}
  .mobile-pdv-nav .mobile-receive{display:grid;grid-template-columns:auto 1fr;grid-template-rows:auto auto;column-gap:8px;padding:7px 14px;background:#22a95a;color:#fff;text-align:left}
  .mobile-receive svg{grid-row:1/3}.mobile-receive span{align-self:end}.mobile-receive strong{align-self:start;font-size:.82rem}.mobile-receive:disabled{opacity:.45}

  .modal-backdrop{align-items:flex-end;padding:0}
  .modal-card,.pos-sale-modal{width:100%;max-width:none;max-height:92dvh;margin:0;padding:16px 14px calc(16px + env(safe-area-inset-bottom));border-radius:18px 18px 0 0}
  .modal-title{position:sticky;top:-16px;z-index:3;margin:-16px -14px 12px;padding:16px 14px 12px;border-bottom:1px solid var(--border);background:var(--bg-card)}
  .modal-title button,.close-sale-modal{min-width:40px;min-height:40px}
  .quick-grid{grid-template-columns:1fr}.quick-grid .wide{grid-column:auto}.quick-client-form .input,.quick-client-form .select{min-height:44px;font-size:16px}
  .payment-total{font-size:1.65rem}.payment-buttons{grid-template-columns:1fr 1fr}.payment-buttons button{min-height:62px}
  .payment-list article{grid-template-columns:1fr 1fr}.payment-list article>*:last-child{min-height:40px}
  .document-choice{grid-template-columns:1fr}.flow-line{flex-wrap:wrap}.output-buttons{grid-template-columns:1fr 1fr}
  .post-sale-footer{align-items:stretch;flex-direction:column;gap:10px}.post-sale-footer button{min-height:44px}
}

@media(max-width:390px){
  .pdv-header{flex-direction:column}.pdv-header-actions{max-width:none;width:100%;display:grid;grid-template-columns:repeat(4,1fr)}
  .pdv-header-actions button{padding:5px 4px;font-size:.58rem}
  .pdv-grid,.catalog-panel,.cart-panel{min-height:calc(100dvh - 194px)!important}
  .product-result{grid-template-columns:52px minmax(0,1fr)}.product-image{width:52px;height:52px}.product-price{grid-column:2}
}

</style>
