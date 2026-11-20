<?php

namespace App\Models;

use App\Traits\{HasUuid, HasEmpresa, HasAuditoria};
use Illuminate\Database\Eloquent\{Model, SoftDeletes, Builder};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Produto extends Model
{
    use HasEmpresa, HasAuditoria, SoftDeletes, HasUuid;

    protected $fillable = [
        'empresa_id','categoria_id','marca_id','fornecedor_id','unidade_id',
        'tipo','formato','condicao','producao','codigo','codigo_barras','sku','ean',
        'ean_tributario','ncm','cest','cfop','origem','nome','slug','descricao',
        'descricao_curta','descricao_complementar','link_externo','video_url','observacoes',
        'tags','peso','peso_bruto','largura','altura','comprimento','volumes','itens_caixa',
        'unidade_dimensao','data_validade','frete_gratis',
        'preco_custo','preco_venda','preco_venda_minimo','margem_lucro','desconto_maximo','modo_preco_venda','markup_venda',
        'icms_cst','icms_aliquota','pis_cst','pis_aliquota','cofins_cst','cofins_aliquota',
        'ipi_cst','ipi_aliquota','tipo_item','grupo_produtos','percentual_tributos',
        'icms_st_base','icms_st_valor','icms_st_substituto','ipi_excecao',
        'pis_valor_fixo','cofins_valor_fixo','informacoes_adicionais',
        'controla_estoque','estoque_minimo','estoque_maximo','ponto_reposicao',
        'ativo','destaque','bu','segmento','familia','multiplo_venda',
        'preco_referencia_tipo','disponivel_bndes','segmento_portfolio','showroom',
        'item_consumo','casa_inteligente','observacoes_importacao','fonte_importacao',
        'importado_em','conteudo_ia_status','conteudo_ia_em','seo_titulo','seo_descricao','seo_palavras_chave','ia_ultima_acao','ia_ultima_fonte',
    ];

    protected $casts = [
        'preco_custo'=>'decimal:4','preco_venda'=>'decimal:4',
        'preco_venda_minimo'=>'decimal:4','margem_lucro'=>'decimal:2','markup_venda'=>'decimal:2',
        'desconto_maximo'=>'decimal:2','icms_aliquota'=>'decimal:2',
        'pis_aliquota'=>'decimal:2','cofins_aliquota'=>'decimal:2',
        'ipi_aliquota'=>'decimal:2','peso'=>'decimal:3','peso_bruto'=>'decimal:3',
        'largura'=>'decimal:2','altura'=>'decimal:2','comprimento'=>'decimal:2',
        'itens_caixa'=>'decimal:3','estoque_minimo'=>'decimal:3',
        'estoque_maximo'=>'decimal:3','controla_estoque'=>'boolean','ativo'=>'boolean',
        'destaque'=>'boolean','frete_gratis'=>'boolean','origem'=>'integer',
        'data_validade'=>'date','tags'=>'array','seo_palavras_chave'=>'array','disponivel_bndes'=>'boolean',
        'showroom'=>'boolean','item_consumo'=>'boolean','casa_inteligente'=>'boolean',
        'importado_em'=>'datetime','conteudo_ia_em'=>'datetime',
    ];

    public function categoria(): BelongsTo { return $this->belongsTo(Categoria::class); }
    public function marca(): BelongsTo { return $this->belongsTo(Marca::class); }
    public function fornecedor(): BelongsTo { return $this->belongsTo(Fornecedor::class); }
    public function unidade(): BelongsTo { return $this->belongsTo(UnidadeMedida::class, 'unidade_id'); }
    public function imagens(): HasMany { return $this->hasMany(ProdutoImagem::class)->orderBy('ordem'); }
    public function imagemPrincipal(): HasMany { return $this->hasMany(ProdutoImagem::class)->where('principal', true)->limit(1); }
    public function variacoes(): HasMany { return $this->hasMany(ProdutoVariacao::class)->where('ativo', true); }
    public function composicao(): HasMany { return $this->hasMany(ProdutoComposicao::class, 'produto_pai_id')->with('produtoFilho'); }
    public function precosUf(): HasMany { return $this->hasMany(ProdutoPrecoUf::class); }
    public function estoque(): HasMany { return $this->hasMany(Estoque::class); }
    public function movimentacoes(): HasMany { return $this->hasMany(MovimentacaoEstoque::class); }
    public function fornecedoresProduto(): HasMany { return $this->hasMany(ProdutoFornecedor::class); }
    public function canais(): HasMany { return $this->hasMany(ProdutoCanal::class); }
    public function codigos(): HasMany { return $this->hasMany(ProdutoCodigo::class); }
    public function listasPreco(): HasMany { return $this->hasMany(ProdutoListaPreco::class); }
    public function documentos(): HasMany { return $this->hasMany(ProdutoDocumento::class)->orderBy('ordem'); }
    public function conteudosIa(): HasMany { return $this->hasMany(ProdutoConteudoIa::class); }

    public function scopeAtivos(Builder $q): Builder { return $q->where('ativo', true); }
    public function scopeBusca(Builder $q, string $termo): Builder
    {
        return $q->where(function ($q) use ($termo) {
            $q->whereLike('nome', "%{$termo}%")
              ->orWhere('codigo', 'like', "%{$termo}%")
              ->orWhere('sku', 'like', "%{$termo}%")
              ->orWhere('codigo_barras', 'like', "%{$termo}%")
              ->orWhere('ean', 'like', "%{$termo}%");
        });
    }
    public function scopeEstoqueBaixo(Builder $q): Builder
    {
        return $q->where('controla_estoque', true)
            ->whereHas('estoque', fn ($q) => $q->whereRaw('quantidade_disponivel <= produtos.estoque_minimo'));
    }
    public function scopePorCategoria(Builder $q, int $categoriaId): Builder { return $q->where('categoria_id', $categoriaId); }
    public function scopeTipo(Builder $q, string $tipo): Builder { return $q->where('tipo', $tipo); }

    public function getEstoqueTotalAttribute(): float { return (float) $this->estoque->sum('quantidade_disponivel'); }
    public function getEstoqueDisponivelAttribute(): float { return (float) $this->estoque->sum('quantidade_disponivel'); }
    public function getMargemCalculadaAttribute(): float
    {
        if ($this->preco_custo <= 0) return 0;
        return round((($this->preco_venda - $this->preco_custo) / $this->preco_custo) * 100, 2);
    }
    public function getAbaixoMinimoAttribute(): bool
    {
        return $this->controla_estoque && $this->estoque_total <= $this->estoque_minimo;
    }
    public function calcularMargem(float $precoCusto, float $precoVenda): float
    {
        if ($precoCusto <= 0) return 0;
        return round((($precoVenda - $precoCusto) / $precoCusto) * 100, 2);
    }
    public function temEstoqueSuficiente(float $quantidade, ?int $depositoId = null): bool
    {
        if (!$this->controla_estoque) return true;
        $q = $this->estoque();
        if ($depositoId) $q->where('deposito_id', $depositoId);
        return (float) $q->sum('quantidade_disponivel') >= $quantidade;
    }
}
