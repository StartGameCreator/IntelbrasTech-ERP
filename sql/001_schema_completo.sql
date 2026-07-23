-- ============================================================
-- IntelbrasTech ERP - Schema PostgreSQL Completo
-- Versão: 1.0.0 | Data: 2025
-- Tecnologia: PostgreSQL 16+
-- ============================================================

-- Extensões necessárias
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "postgis";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
CREATE EXTENSION IF NOT EXISTS "unaccent";

-- ============================================================
-- BLOCO 1: SISTEMA / MULTIEMPRESA
-- ============================================================

CREATE TABLE empresas (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    razao_social VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(200),
    cnpj VARCHAR(18) UNIQUE NOT NULL,
    ie VARCHAR(30),
    im VARCHAR(30),
    email VARCHAR(150),
    telefone VARCHAR(20),
    celular VARCHAR(20),
    cep VARCHAR(9),
    logradouro VARCHAR(200),
    numero VARCHAR(10),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado CHAR(2),
    ibge VARCHAR(7),
    pais VARCHAR(50) DEFAULT 'Brasil',
    regime_tributario SMALLINT DEFAULT 1, -- 1=Simples, 2=Presumido, 3=Real
    cnae VARCHAR(10),
    site VARCHAR(200),
    logo VARCHAR(500),
    ativo BOOLEAN DEFAULT TRUE,
    plano VARCHAR(30) DEFAULT 'mvp',
    trial_ate DATE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE perfis (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    admin BOOLEAN DEFAULT FALSE,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE usuarios (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    perfil_id BIGINT REFERENCES perfis(id),
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    email_verificado_em TIMESTAMP,
    senha VARCHAR(255) NOT NULL,
    avatar VARCHAR(500),
    telefone VARCHAR(20),
    cargo VARCHAR(100),
    ativo BOOLEAN DEFAULT TRUE,
    ultimo_acesso TIMESTAMP,
    token_recuperacao VARCHAR(255),
    token_recuperacao_expira TIMESTAMP,
    preferencias JSONB DEFAULT '{}',
    tema VARCHAR(10) DEFAULT 'dark', -- dark | light | system
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(empresa_id, email)
);

CREATE TABLE permissoes (
    id BIGSERIAL PRIMARY KEY,
    modulo VARCHAR(50) NOT NULL,
    acao VARCHAR(50) NOT NULL, -- view, create, edit, delete, export
    descricao VARCHAR(200),
    UNIQUE(modulo, acao)
);

CREATE TABLE perfil_permissoes (
    perfil_id BIGINT NOT NULL REFERENCES perfis(id) ON DELETE CASCADE,
    permissao_id BIGINT NOT NULL REFERENCES permissoes(id) ON DELETE CASCADE,
    PRIMARY KEY (perfil_id, permissao_id)
);

CREATE TABLE logs_sistema (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT REFERENCES empresas(id),
    usuario_id BIGINT REFERENCES usuarios(id),
    acao VARCHAR(100) NOT NULL,
    modulo VARCHAR(50),
    tabela VARCHAR(100),
    registro_id BIGINT,
    dados_antes JSONB,
    dados_depois JSONB,
    ip VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 2: ENDEREÇOS / GEO
-- ============================================================

CREATE TABLE enderecos (
    id BIGSERIAL PRIMARY KEY,
    cep VARCHAR(9),
    logradouro VARCHAR(200),
    numero VARCHAR(10),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado CHAR(2),
    ibge VARCHAR(7),
    pais VARCHAR(50) DEFAULT 'Brasil',
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    principal BOOLEAN DEFAULT FALSE,
    tipo VARCHAR(30), -- comercial, residencial, entrega, cobranca
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 3: CLIENTES
-- ============================================================

CREATE TABLE clientes (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    tipo CHAR(1) DEFAULT 'F', -- F=Física, J=Jurídica
    razao_social VARCHAR(200),
    nome_fantasia VARCHAR(200),
    cpf_cnpj VARCHAR(18),
    rg_ie VARCHAR(30),
    email VARCHAR(150),
    email_nfe VARCHAR(150),
    telefone VARCHAR(20),
    celular VARCHAR(20),
    whatsapp VARCHAR(20),
    site VARCHAR(200),
    inscricao_estadual VARCHAR(30),
    inscricao_municipal VARCHAR(30),
    contribuinte_icms BOOLEAN DEFAULT FALSE,
    data_nascimento DATE,
    sexo CHAR(1),
    profissao VARCHAR(100),
    estado_civil VARCHAR(20),
    limite_credito DECIMAL(15,2) DEFAULT 0,
    desconto_padrao DECIMAL(5,2) DEFAULT 0,
    tabela_preco_id BIGINT,
    vendedor_id BIGINT REFERENCES usuarios(id),
    tags TEXT[],
    observacoes TEXT,
    foto VARCHAR(500),
    ativo BOOLEAN DEFAULT TRUE,
    bloqueado BOOLEAN DEFAULT FALSE,
    motivo_bloqueio TEXT,
    -- Endereço principal
    cep VARCHAR(9),
    logradouro VARCHAR(200),
    numero VARCHAR(10),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado CHAR(2),
    ibge VARCHAR(7),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    -- Origem
    origem VARCHAR(50), -- indicacao, site, marketplace, redes_sociais
    canal_aquisicao VARCHAR(50),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE cliente_enderecos (
    id BIGSERIAL PRIMARY KEY,
    cliente_id BIGINT NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
    tipo VARCHAR(30) DEFAULT 'entrega',
    destinatario VARCHAR(200),
    cep VARCHAR(9),
    logradouro VARCHAR(200),
    numero VARCHAR(10),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado CHAR(2),
    ibge VARCHAR(7),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    principal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE cliente_contatos (
    id BIGSERIAL PRIMARY KEY,
    cliente_id BIGINT NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
    nome VARCHAR(150) NOT NULL,
    cargo VARCHAR(100),
    email VARCHAR(150),
    telefone VARCHAR(20),
    celular VARCHAR(20),
    whatsapp VARCHAR(20),
    principal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 4: FORNECEDORES
-- ============================================================

CREATE TABLE fornecedores (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    tipo CHAR(1) DEFAULT 'J',
    razao_social VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(200),
    cpf_cnpj VARCHAR(18),
    ie VARCHAR(30),
    email VARCHAR(150),
    telefone VARCHAR(20),
    celular VARCHAR(20),
    whatsapp VARCHAR(20),
    site VARCHAR(200),
    cep VARCHAR(9),
    logradouro VARCHAR(200),
    numero VARCHAR(10),
    complemento VARCHAR(100),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado CHAR(2),
    ibge VARCHAR(7),
    prazo_entrega_dias INT DEFAULT 0,
    prazo_pagamento_dias INT DEFAULT 0,
    desconto_padrao DECIMAL(5,2) DEFAULT 0,
    avaliacao SMALLINT DEFAULT 0, -- 0-5
    banco VARCHAR(100),
    agencia VARCHAR(10),
    conta VARCHAR(20),
    pix VARCHAR(100),
    contato_principal VARCHAR(150),
    observacoes TEXT,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 5: PRODUTOS / CATÁLOGO
-- ============================================================

CREATE TABLE categorias (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    categoria_pai_id BIGINT REFERENCES categorias(id),
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(150),
    descricao TEXT,
    imagem VARCHAR(500),
    ordem INT DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE marcas (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(150),
    logo VARCHAR(500),
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE unidades_medida (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    sigla VARCHAR(10) NOT NULL,
    descricao VARCHAR(50),
    UNIQUE(empresa_id, sigla)
);

CREATE TABLE produtos (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    categoria_id BIGINT REFERENCES categorias(id),
    marca_id BIGINT REFERENCES marcas(id),
    fornecedor_id BIGINT REFERENCES fornecedores(id),
    unidade_id BIGINT REFERENCES unidades_medida(id),
    tipo VARCHAR(20) DEFAULT 'simples', -- simples, composto, kit, servico, digital
    codigo VARCHAR(50),
    codigo_barras VARCHAR(50),
    sku VARCHAR(100),
    ean VARCHAR(20),
    ncm VARCHAR(10),
    cest VARCHAR(10),
    cfop VARCHAR(10),
    origem SMALLINT DEFAULT 0,
    nome VARCHAR(200) NOT NULL,
    slug VARCHAR(250),
    descricao TEXT,
    descricao_curta VARCHAR(500),
    peso DECIMAL(10,3) DEFAULT 0,
    largura DECIMAL(10,2) DEFAULT 0,
    altura DECIMAL(10,2) DEFAULT 0,
    comprimento DECIMAL(10,2) DEFAULT 0,
    preco_custo DECIMAL(15,4) DEFAULT 0,
    preco_venda DECIMAL(15,4) DEFAULT 0,
    preco_venda_minimo DECIMAL(15,4) DEFAULT 0,
    margem_lucro DECIMAL(5,2) DEFAULT 0,
    desconto_maximo DECIMAL(5,2) DEFAULT 0,
    -- Tributos
    icms_cst VARCHAR(5),
    icms_aliquota DECIMAL(5,2) DEFAULT 0,
    pis_cst VARCHAR(3),
    pis_aliquota DECIMAL(5,2) DEFAULT 0,
    cofins_cst VARCHAR(3),
    cofins_aliquota DECIMAL(5,2) DEFAULT 0,
    ipi_cst VARCHAR(3),
    ipi_aliquota DECIMAL(5,2) DEFAULT 0,
    -- Controle
    controla_estoque BOOLEAN DEFAULT TRUE,
    estoque_minimo DECIMAL(10,3) DEFAULT 0,
    estoque_maximo DECIMAL(10,3) DEFAULT 0,
    ponto_reposicao DECIMAL(10,3) DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE,
    destaque BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE produto_imagens (
    id BIGSERIAL PRIMARY KEY,
    produto_id BIGINT NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    url VARCHAR(500) NOT NULL,
    titulo VARCHAR(200),
    principal BOOLEAN DEFAULT FALSE,
    ordem INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE produto_atributos (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    nome VARCHAR(100) NOT NULL, -- Cor, Tamanho, Voltagem
    tipo VARCHAR(20) DEFAULT 'texto',
    UNIQUE(empresa_id, nome)
);

CREATE TABLE produto_atributo_valores (
    id BIGSERIAL PRIMARY KEY,
    atributo_id BIGINT NOT NULL REFERENCES produto_atributos(id) ON DELETE CASCADE,
    valor VARCHAR(100) NOT NULL
);

CREATE TABLE produto_variacoes (
    id BIGSERIAL PRIMARY KEY,
    produto_id BIGINT NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    sku VARCHAR(100),
    codigo_barras VARCHAR(50),
    preco_venda DECIMAL(15,4),
    preco_custo DECIMAL(15,4),
    peso DECIMAL(10,3),
    foto VARCHAR(500),
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE produto_variacao_atributos (
    variacao_id BIGINT NOT NULL REFERENCES produto_variacoes(id) ON DELETE CASCADE,
    atributo_id BIGINT NOT NULL REFERENCES produto_atributos(id),
    valor_id BIGINT NOT NULL REFERENCES produto_atributo_valores(id),
    PRIMARY KEY (variacao_id, atributo_id)
);

CREATE TABLE produto_composicao (
    id BIGSERIAL PRIMARY KEY,
    produto_pai_id BIGINT NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    produto_filho_id BIGINT NOT NULL REFERENCES produtos(id),
    quantidade DECIMAL(10,3) DEFAULT 1,
    UNIQUE(produto_pai_id, produto_filho_id)
);

CREATE TABLE tabelas_preco (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    desconto_geral DECIMAL(5,2) DEFAULT 0,
    acrescimo_geral DECIMAL(5,2) DEFAULT 0,
    ativa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE tabela_preco_itens (
    id BIGSERIAL PRIMARY KEY,
    tabela_id BIGINT NOT NULL REFERENCES tabelas_preco(id) ON DELETE CASCADE,
    produto_id BIGINT REFERENCES produtos(id),
    categoria_id BIGINT REFERENCES categorias(id),
    preco DECIMAL(15,4),
    desconto DECIMAL(5,2),
    acrescimo DECIMAL(5,2)
);

-- ============================================================
-- BLOCO 6: DEPÓSITOS / ESTOQUE
-- ============================================================

CREATE TABLE depositos (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    responsavel VARCHAR(150),
    cep VARCHAR(9),
    logradouro VARCHAR(200),
    numero VARCHAR(10),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    estado CHAR(2),
    principal BOOLEAN DEFAULT FALSE,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE estoque (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    produto_id BIGINT NOT NULL REFERENCES produtos(id) ON DELETE CASCADE,
    variacao_id BIGINT REFERENCES produto_variacoes(id),
    deposito_id BIGINT NOT NULL REFERENCES depositos(id),
    quantidade DECIMAL(10,3) DEFAULT 0,
    quantidade_reservada DECIMAL(10,3) DEFAULT 0,
    quantidade_disponivel DECIMAL(10,3) GENERATED ALWAYS AS (quantidade - quantidade_reservada) STORED,
    custo_medio DECIMAL(15,4) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(produto_id, variacao_id, deposito_id)
);

CREATE TABLE movimentacoes_estoque (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    produto_id BIGINT NOT NULL REFERENCES produtos(id),
    variacao_id BIGINT REFERENCES produto_variacoes(id),
    deposito_id BIGINT NOT NULL REFERENCES depositos(id),
    deposito_destino_id BIGINT REFERENCES depositos(id),
    tipo VARCHAR(30) NOT NULL, -- entrada, saida, transferencia, ajuste, inventario
    motivo VARCHAR(50), -- compra, venda, devolucao, perda, ajuste
    documento_tipo VARCHAR(30), -- pedido, nota_fiscal, os, compra
    documento_id BIGINT,
    documento_numero VARCHAR(50),
    quantidade DECIMAL(10,3) NOT NULL,
    custo_unitario DECIMAL(15,4) DEFAULT 0,
    custo_total DECIMAL(15,4) DEFAULT 0,
    saldo_anterior DECIMAL(10,3),
    saldo_posterior DECIMAL(10,3),
    usuario_id BIGINT REFERENCES usuarios(id),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE inventarios (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    deposito_id BIGINT REFERENCES depositos(id),
    numero VARCHAR(20),
    data_inicio TIMESTAMP,
    data_fim TIMESTAMP,
    status VARCHAR(20) DEFAULT 'aberto', -- aberto, em_andamento, finalizado, cancelado
    responsavel_id BIGINT REFERENCES usuarios(id),
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE inventario_itens (
    id BIGSERIAL PRIMARY KEY,
    inventario_id BIGINT NOT NULL REFERENCES inventarios(id) ON DELETE CASCADE,
    produto_id BIGINT NOT NULL REFERENCES produtos(id),
    variacao_id BIGINT REFERENCES produto_variacoes(id),
    quantidade_sistema DECIMAL(10,3),
    quantidade_contada DECIMAL(10,3),
    divergencia DECIMAL(10,3) GENERATED ALWAYS AS (quantidade_contada - quantidade_sistema) STORED,
    custo_unitario DECIMAL(15,4),
    status VARCHAR(20) DEFAULT 'pendente'
);

-- ============================================================
-- BLOCO 7: PEDIDOS DE VENDA
-- ============================================================

CREATE TABLE pedidos (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    numero VARCHAR(20) NOT NULL,
    cliente_id BIGINT REFERENCES clientes(id),
    vendedor_id BIGINT REFERENCES usuarios(id),
    canal VARCHAR(30) DEFAULT 'interno', -- interno, marketplace, ecommerce, whatsapp
    marketplace VARCHAR(30), -- mercadolivre, shopee, amazon, etc
    marketplace_pedido_id VARCHAR(100),
    tabela_preco_id BIGINT REFERENCES tabelas_preco(id),
    -- Endereço de entrega
    entrega_cep VARCHAR(9),
    entrega_logradouro VARCHAR(200),
    entrega_numero VARCHAR(10),
    entrega_complemento VARCHAR(100),
    entrega_bairro VARCHAR(100),
    entrega_cidade VARCHAR(100),
    entrega_estado CHAR(2),
    entrega_ibge VARCHAR(7),
    -- Totais
    subtotal DECIMAL(15,2) DEFAULT 0,
    desconto_percentual DECIMAL(5,2) DEFAULT 0,
    desconto_valor DECIMAL(15,2) DEFAULT 0,
    acrescimo_percentual DECIMAL(5,2) DEFAULT 0,
    acrescimo_valor DECIMAL(15,2) DEFAULT 0,
    frete DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    -- Status
    status VARCHAR(30) DEFAULT 'orcamento', -- orcamento, confirmado, faturado, entregue, cancelado
    status_pagamento VARCHAR(30) DEFAULT 'pendente',
    status_entrega VARCHAR(30) DEFAULT 'pendente',
    -- Datas
    data_pedido TIMESTAMP DEFAULT NOW(),
    data_confirmacao TIMESTAMP,
    data_faturamento TIMESTAMP,
    data_entrega TIMESTAMP,
    data_cancelamento TIMESTAMP,
    motivo_cancelamento TEXT,
    -- Transporte
    transportadora_id BIGINT,
    tipo_frete CHAR(1) DEFAULT 'C', -- C=CIF, F=FOB, T=Terceiros
    prazo_entrega_dias INT,
    codigo_rastreio VARCHAR(50),
    -- Financeiro
    condicao_pagamento_id BIGINT,
    -- Outros
    observacoes TEXT,
    observacoes_nfe TEXT,
    origem_id BIGINT, -- pedido de origem (devolução)
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE pedido_itens (
    id BIGSERIAL PRIMARY KEY,
    pedido_id BIGINT NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
    produto_id BIGINT NOT NULL REFERENCES produtos(id),
    variacao_id BIGINT REFERENCES produto_variacoes(id),
    deposito_id BIGINT REFERENCES depositos(id),
    codigo VARCHAR(50),
    descricao VARCHAR(300),
    ncm VARCHAR(10),
    cfop VARCHAR(10),
    unidade VARCHAR(10),
    quantidade DECIMAL(10,3) NOT NULL,
    preco_unitario DECIMAL(15,4) NOT NULL,
    desconto_percentual DECIMAL(5,2) DEFAULT 0,
    desconto_valor DECIMAL(15,2) DEFAULT 0,
    acrescimo_valor DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) NOT NULL,
    -- Tributos
    icms_cst VARCHAR(5),
    icms_base DECIMAL(15,2),
    icms_aliquota DECIMAL(5,2),
    icms_valor DECIMAL(15,2),
    pis_cst VARCHAR(3),
    pis_base DECIMAL(15,2),
    pis_aliquota DECIMAL(5,2),
    pis_valor DECIMAL(15,2),
    cofins_cst VARCHAR(3),
    cofins_base DECIMAL(15,2),
    cofins_aliquota DECIMAL(5,2),
    cofins_valor DECIMAL(15,2),
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 8: COMPRAS
-- ============================================================

CREATE TABLE pedidos_compra (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    numero VARCHAR(20) NOT NULL,
    fornecedor_id BIGINT REFERENCES fornecedores(id),
    deposito_id BIGINT REFERENCES depositos(id),
    status VARCHAR(30) DEFAULT 'rascunho', -- rascunho, enviado, parcial, recebido, cancelado
    subtotal DECIMAL(15,2) DEFAULT 0,
    desconto_valor DECIMAL(15,2) DEFAULT 0,
    frete DECIMAL(15,2) DEFAULT 0,
    outras_despesas DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    data_emissao DATE DEFAULT CURRENT_DATE,
    data_previsao DATE,
    data_recebimento DATE,
    condicao_pagamento_id BIGINT,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE pedido_compra_itens (
    id BIGSERIAL PRIMARY KEY,
    pedido_compra_id BIGINT NOT NULL REFERENCES pedidos_compra(id) ON DELETE CASCADE,
    produto_id BIGINT NOT NULL REFERENCES produtos(id),
    variacao_id BIGINT REFERENCES produto_variacoes(id),
    descricao VARCHAR(300),
    quantidade DECIMAL(10,3) NOT NULL,
    quantidade_recebida DECIMAL(10,3) DEFAULT 0,
    preco_unitario DECIMAL(15,4) NOT NULL,
    desconto_percentual DECIMAL(5,2) DEFAULT 0,
    total DECIMAL(15,2) NOT NULL
);

-- ============================================================
-- BLOCO 9: FINANCEIRO
-- ============================================================

CREATE TABLE plano_contas (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    conta_pai_id BIGINT REFERENCES plano_contas(id),
    codigo VARCHAR(20),
    nome VARCHAR(150) NOT NULL,
    tipo VARCHAR(20) NOT NULL, -- receita, despesa, ativo, passivo
    natureza CHAR(1), -- D=Débito, C=Crédito
    nivel INT DEFAULT 1,
    analitica BOOLEAN DEFAULT TRUE, -- false = sintética
    ativo BOOLEAN DEFAULT TRUE
);

CREATE TABLE contas_bancarias (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    nome VARCHAR(100) NOT NULL,
    banco VARCHAR(100),
    agencia VARCHAR(10),
    conta VARCHAR(20),
    tipo VARCHAR(20) DEFAULT 'corrente', -- corrente, poupanca, caixa, investimento
    saldo_inicial DECIMAL(15,2) DEFAULT 0,
    saldo_atual DECIMAL(15,2) DEFAULT 0,
    cor VARCHAR(7),
    icone VARCHAR(50),
    principal BOOLEAN DEFAULT FALSE,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE condicoes_pagamento (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    tipo VARCHAR(20), -- a_vista, parcelado, prazo
    parcelas INT DEFAULT 1,
    intervalo_dias INT DEFAULT 30,
    acrescimo DECIMAL(5,2) DEFAULT 0,
    desconto DECIMAL(5,2) DEFAULT 0,
    ativo BOOLEAN DEFAULT TRUE
);

CREATE TABLE formas_pagamento (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(30) NOT NULL, -- dinheiro, pix, boleto, cartao_credito, cartao_debito, transferencia, cheque
    taxa DECIMAL(5,2) DEFAULT 0,
    prazo_recebimento_dias INT DEFAULT 0,
    conta_bancaria_id BIGINT REFERENCES contas_bancarias(id),
    ativo BOOLEAN DEFAULT TRUE
);

CREATE TABLE contas_receber (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    cliente_id BIGINT REFERENCES clientes(id),
    pedido_id BIGINT REFERENCES pedidos(id),
    nota_fiscal_id BIGINT,
    conta_pai_id BIGINT REFERENCES contas_receber(id),
    plano_conta_id BIGINT REFERENCES plano_contas(id),
    forma_pagamento_id BIGINT REFERENCES formas_pagamento(id),
    conta_bancaria_id BIGINT REFERENCES contas_bancarias(id),
    descricao VARCHAR(300) NOT NULL,
    numero_documento VARCHAR(50),
    parcela INT DEFAULT 1,
    total_parcelas INT DEFAULT 1,
    valor DECIMAL(15,2) NOT NULL,
    valor_desconto DECIMAL(15,2) DEFAULT 0,
    valor_juros DECIMAL(15,2) DEFAULT 0,
    valor_multa DECIMAL(15,2) DEFAULT 0,
    valor_pago DECIMAL(15,2) DEFAULT 0,
    data_emissao DATE DEFAULT CURRENT_DATE,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE,
    status VARCHAR(20) DEFAULT 'aberto', -- aberto, parcial, pago, cancelado, atrasado
    observacoes TEXT,
    nosso_numero VARCHAR(50), -- boleto
    linha_digitavel TEXT,
    codigo_pix TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE contas_pagar (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    fornecedor_id BIGINT REFERENCES fornecedores(id),
    pedido_compra_id BIGINT REFERENCES pedidos_compra(id),
    conta_pai_id BIGINT REFERENCES contas_pagar(id),
    plano_conta_id BIGINT REFERENCES plano_contas(id),
    forma_pagamento_id BIGINT REFERENCES formas_pagamento(id),
    conta_bancaria_id BIGINT REFERENCES contas_bancarias(id),
    descricao VARCHAR(300) NOT NULL,
    numero_documento VARCHAR(50),
    parcela INT DEFAULT 1,
    total_parcelas INT DEFAULT 1,
    valor DECIMAL(15,2) NOT NULL,
    valor_desconto DECIMAL(15,2) DEFAULT 0,
    valor_juros DECIMAL(15,2) DEFAULT 0,
    valor_multa DECIMAL(15,2) DEFAULT 0,
    valor_pago DECIMAL(15,2) DEFAULT 0,
    data_emissao DATE DEFAULT CURRENT_DATE,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE,
    status VARCHAR(20) DEFAULT 'aberto',
    recorrente BOOLEAN DEFAULT FALSE,
    recorrencia_tipo VARCHAR(20), -- mensal, semanal, anual
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE lancamentos (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    conta_bancaria_id BIGINT NOT NULL REFERENCES contas_bancarias(id),
    conta_receber_id BIGINT REFERENCES contas_receber(id),
    conta_pagar_id BIGINT REFERENCES contas_pagar(id),
    plano_conta_id BIGINT REFERENCES plano_contas(id),
    tipo CHAR(1) NOT NULL, -- E=Entrada, S=Saída
    descricao VARCHAR(300) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data_lancamento DATE NOT NULL,
    conciliado BOOLEAN DEFAULT FALSE,
    data_conciliacao DATE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 10: FISCAL / NF-e
-- ============================================================

CREATE TABLE certificados_digitais (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    tipo CHAR(2) DEFAULT 'A1', -- A1, A3
    arquivo VARCHAR(500),
    senha VARCHAR(255),
    validade DATE,
    serie VARCHAR(50),
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE series_fiscais (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    tipo VARCHAR(10) NOT NULL, -- NFe, NFCe, NFSe, CTe, MDFe
    serie VARCHAR(5) NOT NULL,
    ultimo_numero BIGINT DEFAULT 0,
    ambiente SMALLINT DEFAULT 2, -- 1=Produção, 2=Homologação
    ativo BOOLEAN DEFAULT TRUE
);

CREATE TABLE notas_fiscais (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    pedido_id BIGINT REFERENCES pedidos(id),
    tipo VARCHAR(10) NOT NULL, -- NFe, NFCe, NFSe
    modelo VARCHAR(5),
    serie VARCHAR(5),
    numero BIGINT,
    chave_acesso VARCHAR(44),
    natureza_operacao VARCHAR(100),
    ambiente SMALLINT DEFAULT 2,
    finalidade SMALLINT DEFAULT 1,
    -- Emitente
    emitente_cnpj VARCHAR(18),
    emitente_ie VARCHAR(30),
    emitente_razao VARCHAR(200),
    -- Destinatário
    destinatario_cpf_cnpj VARCHAR(18),
    destinatario_ie VARCHAR(30),
    destinatario_nome VARCHAR(200),
    destinatario_email VARCHAR(150),
    destinatario_cep VARCHAR(9),
    destinatario_logradouro VARCHAR(200),
    destinatario_numero VARCHAR(10),
    destinatario_complemento VARCHAR(100),
    destinatario_bairro VARCHAR(100),
    destinatario_cidade VARCHAR(100),
    destinatario_uf CHAR(2),
    destinatario_ibge VARCHAR(7),
    -- Totais
    base_calculo_icms DECIMAL(15,2) DEFAULT 0,
    valor_icms DECIMAL(15,2) DEFAULT 0,
    valor_produtos DECIMAL(15,2) DEFAULT 0,
    valor_frete DECIMAL(15,2) DEFAULT 0,
    valor_seguro DECIMAL(15,2) DEFAULT 0,
    outras_despesas DECIMAL(15,2) DEFAULT 0,
    valor_desconto DECIMAL(15,2) DEFAULT 0,
    valor_ipi DECIMAL(15,2) DEFAULT 0,
    base_pis DECIMAL(15,2) DEFAULT 0,
    valor_pis DECIMAL(15,2) DEFAULT 0,
    base_cofins DECIMAL(15,2) DEFAULT 0,
    valor_cofins DECIMAL(15,2) DEFAULT 0,
    valor_total DECIMAL(15,2) DEFAULT 0,
    -- Status
    status VARCHAR(30) DEFAULT 'rascunho', -- rascunho, processando, autorizada, cancelada, denegada, inutilizada
    data_emissao TIMESTAMP,
    data_autorizacao TIMESTAMP,
    data_cancelamento TIMESTAMP,
    motivo_cancelamento TEXT,
    protocolo VARCHAR(20),
    xml TEXT,
    xml_cancelamento TEXT,
    danfe_url VARCHAR(500),
    -- Transporte
    transportadora_id BIGINT,
    tipo_frete CHAR(1),
    volumes INT,
    especie VARCHAR(30),
    peso_bruto DECIMAL(10,3),
    peso_liquido DECIMAL(10,3),
    -- Outros
    informacoes_adicionais TEXT,
    informacoes_fisco TEXT,
    qr_code_url TEXT,
    url_chave TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE nota_fiscal_itens (
    id BIGSERIAL PRIMARY KEY,
    nota_fiscal_id BIGINT NOT NULL REFERENCES notas_fiscais(id) ON DELETE CASCADE,
    produto_id BIGINT REFERENCES produtos(id),
    numero_item INT NOT NULL,
    codigo VARCHAR(50),
    descricao VARCHAR(300) NOT NULL,
    ncm VARCHAR(10),
    cfop VARCHAR(10),
    unidade VARCHAR(10),
    quantidade DECIMAL(10,4) NOT NULL,
    valor_unitario DECIMAL(15,10) NOT NULL,
    valor_total DECIMAL(15,2) NOT NULL,
    valor_desconto DECIMAL(15,2) DEFAULT 0,
    -- ICMS
    icms_origem SMALLINT DEFAULT 0,
    icms_cst VARCHAR(5),
    icms_base DECIMAL(15,2) DEFAULT 0,
    icms_aliquota DECIMAL(5,2) DEFAULT 0,
    icms_valor DECIMAL(15,2) DEFAULT 0,
    -- IPI
    ipi_cst VARCHAR(3),
    ipi_base DECIMAL(15,2) DEFAULT 0,
    ipi_aliquota DECIMAL(5,2) DEFAULT 0,
    ipi_valor DECIMAL(15,2) DEFAULT 0,
    -- PIS
    pis_cst VARCHAR(3),
    pis_base DECIMAL(15,2) DEFAULT 0,
    pis_aliquota DECIMAL(5,2) DEFAULT 0,
    pis_valor DECIMAL(15,2) DEFAULT 0,
    -- COFINS
    cofins_cst VARCHAR(3),
    cofins_base DECIMAL(15,2) DEFAULT 0,
    cofins_aliquota DECIMAL(5,2) DEFAULT 0,
    cofins_valor DECIMAL(15,2) DEFAULT 0
);

CREATE TABLE inutilizacoes (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    tipo VARCHAR(10) NOT NULL,
    serie VARCHAR(5),
    numero_inicial BIGINT,
    numero_final BIGINT,
    justificativa TEXT,
    protocolo VARCHAR(20),
    ambiente SMALLINT DEFAULT 2,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE cartas_correcao (
    id BIGSERIAL PRIMARY KEY,
    nota_fiscal_id BIGINT NOT NULL REFERENCES notas_fiscais(id),
    sequencia INT DEFAULT 1,
    descricao TEXT NOT NULL,
    protocolo VARCHAR(20),
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 11: LOGÍSTICA / TRANSPORTADORAS
-- ============================================================

CREATE TABLE transportadoras (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    nome VARCHAR(150) NOT NULL,
    cnpj VARCHAR(18),
    ie VARCHAR(30),
    email VARCHAR(150),
    telefone VARCHAR(20),
    api_tipo VARCHAR(30), -- correios, jadlog, loggi, azul, total_express, melhor_envio
    api_usuario VARCHAR(200),
    api_senha VARCHAR(200),
    api_token TEXT,
    api_contrato VARCHAR(50),
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE pedido_envios (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    pedido_id BIGINT NOT NULL REFERENCES pedidos(id),
    nota_fiscal_id BIGINT REFERENCES notas_fiscais(id),
    transportadora_id BIGINT REFERENCES transportadoras(id),
    servico VARCHAR(100),
    codigo_rastreio VARCHAR(100),
    etiqueta_url VARCHAR(500),
    etiqueta_base64 TEXT,
    status VARCHAR(30) DEFAULT 'pendente',
    peso DECIMAL(10,3),
    altura DECIMAL(10,2),
    largura DECIMAL(10,2),
    comprimento DECIMAL(10,2),
    valor_frete DECIMAL(15,2),
    prazo_dias INT,
    data_postagem DATE,
    data_previsao DATE,
    data_entrega DATE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE rastreamentos (
    id BIGSERIAL PRIMARY KEY,
    envio_id BIGINT NOT NULL REFERENCES pedido_envios(id),
    codigo_rastreio VARCHAR(100),
    data_evento TIMESTAMP,
    descricao TEXT,
    local VARCHAR(200),
    status VARCHAR(50),
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 12: MARKETPLACES / INTEGRAÇÕES
-- ============================================================

CREATE TABLE integracoes (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id) ON DELETE CASCADE,
    tipo VARCHAR(50) NOT NULL, -- mercadolivre, shopee, amazon, nuvemshop, woocommerce, etc
    nome VARCHAR(100),
    credenciais JSONB DEFAULT '{}',
    config JSONB DEFAULT '{}',
    status VARCHAR(20) DEFAULT 'inativo',
    ultimo_sync TIMESTAMP,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE marketplace_produtos (
    id BIGSERIAL PRIMARY KEY,
    integracao_id BIGINT NOT NULL REFERENCES integracoes(id) ON DELETE CASCADE,
    produto_id BIGINT REFERENCES produtos(id),
    variacao_id BIGINT REFERENCES produto_variacoes(id),
    marketplace_sku VARCHAR(200),
    marketplace_id VARCHAR(200),
    marketplace_url VARCHAR(500),
    titulo VARCHAR(300),
    preco_anunciado DECIMAL(15,2),
    estoque_sincronizado INT DEFAULT 0,
    status_anuncio VARCHAR(30),
    ultimo_sync TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE marketplace_pedidos (
    id BIGSERIAL PRIMARY KEY,
    integracao_id BIGINT NOT NULL REFERENCES integracoes(id),
    pedido_id BIGINT REFERENCES pedidos(id),
    marketplace_pedido_id VARCHAR(200) NOT NULL,
    marketplace VARCHAR(50),
    status_marketplace VARCHAR(50),
    dados_raw JSONB,
    sincronizado BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE sync_logs (
    id BIGSERIAL PRIMARY KEY,
    integracao_id BIGINT NOT NULL REFERENCES integracoes(id),
    tipo VARCHAR(50), -- produtos, pedidos, estoque, precos
    status VARCHAR(20), -- sucesso, erro, parcial
    registros_processados INT DEFAULT 0,
    registros_erro INT DEFAULT 0,
    mensagem TEXT,
    detalhes JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 13: CRM
-- ============================================================

CREATE TABLE pipelines (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    padrao BOOLEAN DEFAULT FALSE,
    ativo BOOLEAN DEFAULT TRUE
);

CREATE TABLE pipeline_etapas (
    id BIGSERIAL PRIMARY KEY,
    pipeline_id BIGINT NOT NULL REFERENCES pipelines(id) ON DELETE CASCADE,
    nome VARCHAR(100) NOT NULL,
    cor VARCHAR(7),
    ordem INT DEFAULT 0,
    tipo VARCHAR(20) DEFAULT 'em_andamento' -- em_andamento, ganho, perdido
);

CREATE TABLE leads (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    pipeline_id BIGINT REFERENCES pipelines(id),
    etapa_id BIGINT REFERENCES pipeline_etapas(id),
    responsavel_id BIGINT REFERENCES usuarios(id),
    cliente_id BIGINT REFERENCES clientes(id),
    nome VARCHAR(200) NOT NULL,
    email VARCHAR(150),
    telefone VARCHAR(20),
    empresa_nome VARCHAR(200),
    origem VARCHAR(50),
    titulo VARCHAR(200),
    valor_estimado DECIMAL(15,2),
    probabilidade SMALLINT DEFAULT 0,
    data_previsao_fechamento DATE,
    status VARCHAR(20) DEFAULT 'aberto', -- aberto, ganho, perdido
    motivo_perda TEXT,
    tags TEXT[],
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE atividades (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    lead_id BIGINT REFERENCES leads(id),
    cliente_id BIGINT REFERENCES clientes(id),
    usuario_id BIGINT REFERENCES usuarios(id),
    tipo VARCHAR(30) NOT NULL, -- ligacao, email, whatsapp, reuniao, visita, tarefa
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT,
    data_agendada TIMESTAMP,
    data_realizada TIMESTAMP,
    duracao_minutos INT,
    status VARCHAR(20) DEFAULT 'pendente',
    prioridade VARCHAR(10) DEFAULT 'normal',
    resultado TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 14: ASSISTÊNCIA TÉCNICA (Exclusivo Intelbras)
-- ============================================================

CREATE TABLE os_status (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    nome VARCHAR(100) NOT NULL,
    cor VARCHAR(7),
    ordem INT DEFAULT 0,
    tipo VARCHAR(20) DEFAULT 'andamento'
);

CREATE TABLE ordens_servico (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    numero VARCHAR(20) NOT NULL,
    cliente_id BIGINT REFERENCES clientes(id),
    tecnico_id BIGINT REFERENCES usuarios(id),
    status_id BIGINT REFERENCES os_status(id),
    -- Equipamento
    equipamento_tipo VARCHAR(100),
    equipamento_marca VARCHAR(100) DEFAULT 'Intelbras',
    equipamento_modelo VARCHAR(200),
    equipamento_serie VARCHAR(100),
    equipamento_imei VARCHAR(50),
    -- Defeito
    defeito_relatado TEXT,
    defeito_constatado TEXT,
    laudo_tecnico TEXT,
    solucao TEXT,
    -- Garantia
    garantia BOOLEAN DEFAULT FALSE,
    numero_nf_compra VARCHAR(50),
    data_compra DATE,
    em_garantia BOOLEAN DEFAULT FALSE,
    -- Financeiro
    valor_servico DECIMAL(15,2) DEFAULT 0,
    desconto DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    orcamento_aprovado BOOLEAN,
    data_aprovacao_orcamento TIMESTAMP,
    -- Datas
    data_entrada TIMESTAMP DEFAULT NOW(),
    data_previsao DATE,
    data_conclusao TIMESTAMP,
    data_entrega TIMESTAMP,
    prazo_garantia_dias INT DEFAULT 90,
    data_garantia_vence DATE,
    -- Avaliação
    nota_cliente SMALLINT,
    comentario_cliente TEXT,
    -- Outros
    observacoes TEXT,
    observacoes_internas TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE os_fotos (
    id BIGSERIAL PRIMARY KEY,
    os_id BIGINT NOT NULL REFERENCES ordens_servico(id) ON DELETE CASCADE,
    url VARCHAR(500) NOT NULL,
    descricao VARCHAR(200),
    tipo VARCHAR(20) DEFAULT 'entrada', -- entrada, diagnostico, conclusao
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE os_pecas (
    id BIGSERIAL PRIMARY KEY,
    os_id BIGINT NOT NULL REFERENCES ordens_servico(id) ON DELETE CASCADE,
    produto_id BIGINT REFERENCES produtos(id),
    descricao VARCHAR(300),
    quantidade DECIMAL(10,3) DEFAULT 1,
    preco_unitario DECIMAL(15,4),
    total DECIMAL(15,2)
);

CREATE TABLE os_historico (
    id BIGSERIAL PRIMARY KEY,
    os_id BIGINT NOT NULL REFERENCES ordens_servico(id),
    usuario_id BIGINT REFERENCES usuarios(id),
    status_anterior VARCHAR(100),
    status_novo VARCHAR(100),
    observacao TEXT,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 15: RH
-- ============================================================

CREATE TABLE funcionarios (
    id BIGSERIAL PRIMARY KEY,
    usuario_id BIGINT REFERENCES usuarios(id),
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    nome VARCHAR(150) NOT NULL,
    cpf VARCHAR(14) UNIQUE,
    rg VARCHAR(20),
    data_nascimento DATE,
    sexo CHAR(1),
    cargo VARCHAR(100),
    departamento VARCHAR(100),
    salario DECIMAL(15,2),
    data_admissao DATE,
    data_demissao DATE,
    tipo_contrato VARCHAR(30), -- clt, pj, estagio
    banco VARCHAR(100),
    agencia VARCHAR(10),
    conta VARCHAR(20),
    pix VARCHAR(100),
    email VARCHAR(150),
    telefone VARCHAR(20),
    cep VARCHAR(9),
    logradouro VARCHAR(200),
    numero VARCHAR(10),
    cidade VARCHAR(100),
    estado CHAR(2),
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE comissoes_regras (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    nome VARCHAR(100),
    vendedor_id BIGINT REFERENCES usuarios(id),
    tipo VARCHAR(20), -- percentual, fixo
    percentual DECIMAL(5,2),
    valor_fixo DECIMAL(15,2),
    base_calculo VARCHAR(20) DEFAULT 'total', -- total, lucro
    produto_id BIGINT REFERENCES produtos(id),
    categoria_id BIGINT REFERENCES categorias(id),
    ativa BOOLEAN DEFAULT TRUE
);

CREATE TABLE comissoes (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    vendedor_id BIGINT NOT NULL REFERENCES usuarios(id),
    pedido_id BIGINT REFERENCES pedidos(id),
    regra_id BIGINT REFERENCES comissoes_regras(id),
    valor_venda DECIMAL(15,2),
    percentual DECIMAL(5,2),
    valor_comissao DECIMAL(15,2),
    status VARCHAR(20) DEFAULT 'pendente', -- pendente, aprovada, paga
    data_venda DATE,
    data_pagamento DATE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 16: DASHBOARD / RELATÓRIOS
-- ============================================================

CREATE TABLE metas (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    usuario_id BIGINT REFERENCES usuarios(id),
    tipo VARCHAR(30), -- vendas, clientes, os, produto
    periodo VARCHAR(10), -- mensal, trimestral, anual
    ano INT,
    mes INT,
    valor_meta DECIMAL(15,2),
    valor_realizado DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE relatorios_salvos (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    usuario_id BIGINT REFERENCES usuarios(id),
    nome VARCHAR(150) NOT NULL,
    tipo VARCHAR(50),
    filtros JSONB DEFAULT '{}',
    colunas JSONB DEFAULT '[]',
    publico BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 17: NOTIFICAÇÕES / ALERTAS
-- ============================================================

CREATE TABLE notificacoes (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    usuario_id BIGINT REFERENCES usuarios(id),
    tipo VARCHAR(50),
    titulo VARCHAR(200) NOT NULL,
    mensagem TEXT,
    icone VARCHAR(50),
    cor VARCHAR(7),
    lida BOOLEAN DEFAULT FALSE,
    url VARCHAR(500),
    dados JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE webhooks (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    nome VARCHAR(100),
    url TEXT NOT NULL,
    eventos TEXT[] NOT NULL,
    secret VARCHAR(255),
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE webhook_logs (
    id BIGSERIAL PRIMARY KEY,
    webhook_id BIGINT NOT NULL REFERENCES webhooks(id),
    evento VARCHAR(100),
    payload JSONB,
    resposta TEXT,
    status_code INT,
    sucesso BOOLEAN,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 18: API / INTEGRAÇÕES EXTERNAS
-- ============================================================

CREATE TABLE api_tokens (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    usuario_id BIGINT REFERENCES usuarios(id),
    nome VARCHAR(100) NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    permissoes TEXT[],
    ultimo_uso TIMESTAMP,
    expira_em TIMESTAMP,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- BLOCO 19: CONFIGURAÇÕES
-- ============================================================

CREATE TABLE configuracoes (
    id BIGSERIAL PRIMARY KEY,
    empresa_id BIGINT NOT NULL REFERENCES empresas(id),
    chave VARCHAR(100) NOT NULL,
    valor TEXT,
    tipo VARCHAR(20) DEFAULT 'string',
    grupo VARCHAR(50),
    UNIQUE(empresa_id, chave)
);

-- ============================================================
-- ÍNDICES DE PERFORMANCE
-- ============================================================

-- Clientes
CREATE INDEX idx_clientes_empresa ON clientes(empresa_id);
CREATE INDEX idx_clientes_cpf_cnpj ON clientes(cpf_cnpj);
CREATE INDEX idx_clientes_nome ON clientes USING gin(nome gin_trgm_ops);
CREATE INDEX idx_clientes_geo ON clientes(latitude, longitude) WHERE latitude IS NOT NULL;

-- Produtos
CREATE INDEX idx_produtos_empresa ON produtos(empresa_id);
CREATE INDEX idx_produtos_categoria ON produtos(categoria_id);
CREATE INDEX idx_produtos_nome ON produtos USING gin(nome gin_trgm_ops);
CREATE INDEX idx_produtos_codigo ON produtos(codigo);
CREATE INDEX idx_produtos_sku ON produtos(sku);

-- Estoque
CREATE INDEX idx_estoque_produto ON estoque(produto_id);
CREATE INDEX idx_estoque_deposito ON estoque(deposito_id);
CREATE INDEX idx_movimentacoes_empresa ON movimentacoes_estoque(empresa_id);
CREATE INDEX idx_movimentacoes_data ON movimentacoes_estoque(created_at);

-- Pedidos
CREATE INDEX idx_pedidos_empresa ON pedidos(empresa_id);
CREATE INDEX idx_pedidos_cliente ON pedidos(cliente_id);
CREATE INDEX idx_pedidos_status ON pedidos(status);
CREATE INDEX idx_pedidos_data ON pedidos(data_pedido);
CREATE INDEX idx_pedidos_marketplace ON pedidos(marketplace, marketplace_pedido_id);

-- Financeiro
CREATE INDEX idx_cr_empresa ON contas_receber(empresa_id);
CREATE INDEX idx_cr_cliente ON contas_receber(cliente_id);
CREATE INDEX idx_cr_vencimento ON contas_receber(data_vencimento);
CREATE INDEX idx_cr_status ON contas_receber(status);
CREATE INDEX idx_cp_empresa ON contas_pagar(empresa_id);
CREATE INDEX idx_cp_vencimento ON contas_pagar(data_vencimento);
CREATE INDEX idx_cp_status ON contas_pagar(status);

-- NF-e
CREATE INDEX idx_nfe_empresa ON notas_fiscais(empresa_id);
CREATE INDEX idx_nfe_chave ON notas_fiscais(chave_acesso);
CREATE INDEX idx_nfe_status ON notas_fiscais(status);
CREATE INDEX idx_nfe_data ON notas_fiscais(data_emissao);

-- Logs
CREATE INDEX idx_logs_empresa ON logs_sistema(empresa_id);
CREATE INDEX idx_logs_data ON logs_sistema(created_at);

-- ============================================================
-- DADOS INICIAIS
-- ============================================================

-- Permissões padrão
INSERT INTO permissoes (modulo, acao, descricao) VALUES
('clientes', 'view', 'Visualizar clientes'),
('clientes', 'create', 'Criar clientes'),
('clientes', 'edit', 'Editar clientes'),
('clientes', 'delete', 'Excluir clientes'),
('clientes', 'export', 'Exportar clientes'),
('produtos', 'view', 'Visualizar produtos'),
('produtos', 'create', 'Criar produtos'),
('produtos', 'edit', 'Editar produtos'),
('produtos', 'delete', 'Excluir produtos'),
('estoque', 'view', 'Visualizar estoque'),
('estoque', 'edit', 'Ajustar estoque'),
('pedidos', 'view', 'Visualizar pedidos'),
('pedidos', 'create', 'Criar pedidos'),
('pedidos', 'edit', 'Editar pedidos'),
('pedidos', 'delete', 'Cancelar pedidos'),
('financeiro', 'view', 'Visualizar financeiro'),
('financeiro', 'create', 'Lançar financeiro'),
('financeiro', 'edit', 'Editar financeiro'),
('fiscal', 'view', 'Visualizar fiscal'),
('fiscal', 'emitir', 'Emitir NF-e'),
('fiscal', 'cancelar', 'Cancelar NF-e'),
('compras', 'view', 'Visualizar compras'),
('compras', 'create', 'Criar compras'),
('crm', 'view', 'Visualizar CRM'),
('crm', 'create', 'Criar leads'),
('os', 'view', 'Visualizar ordens de serviço'),
('os', 'create', 'Criar ordens de serviço'),
('os', 'edit', 'Editar ordens de serviço'),
('rh', 'view', 'Visualizar RH'),
('relatorios', 'view', 'Visualizar relatórios'),
('relatorios', 'export', 'Exportar relatórios'),
('configuracoes', 'view', 'Visualizar configurações'),
('configuracoes', 'edit', 'Editar configurações'),
('usuarios', 'view', 'Visualizar usuários'),
('usuarios', 'create', 'Criar usuários'),
('usuarios', 'edit', 'Editar usuários'),
('usuarios', 'delete', 'Excluir usuários');

-- Unidades de medida padrão (serão inseridas por empresa na instalação)
-- Status padrão de OS (serão inseridos por empresa na instalação)

COMMENT ON DATABASE current_database() IS 'IntelbrasTech ERP - v1.0.0';
