<?php

namespace App\Models;
use App\Traits\HasUuid;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\Builder;

class NotaFiscal extends Model
{
    use HasEmpresa, HasUuid;

    protected $table = 'notas_fiscais';

    protected $fillable = [
        'empresa_id','pedido_id','nota_fiscal_referenciada_id','tipo','modelo','serie','numero',
        'chave_acesso','chave_referenciada','natureza_operacao','ambiente','finalidade',
        'emitente_cnpj','emitente_ie','emitente_razao',
        'destinatario_cpf_cnpj','destinatario_ie','destinatario_nome',
        'destinatario_email','destinatario_cep','destinatario_logradouro',
        'destinatario_numero','destinatario_complemento','destinatario_bairro',
        'destinatario_cidade','destinatario_uf','destinatario_ibge',
        'base_calculo_icms','valor_icms','valor_produtos','valor_frete',
        'valor_seguro','outras_despesas','valor_desconto','valor_ipi',
        'base_pis','valor_pis','base_cofins','valor_cofins','valor_total',
        'status','data_emissao','data_autorizacao','data_cancelamento',
        'motivo_cancelamento','protocolo','xml','xml_cancelamento',
        'danfe_url','transportadora_id','tipo_frete','volumes',
        'especie','peso_bruto','peso_liquido',
        'informacoes_adicionais','informacoes_fisco','qr_code_url','url_chave',
    ];

    protected $casts = [
        'data_emissao'      => 'datetime',
        'data_autorizacao'  => 'datetime',
        'data_cancelamento' => 'datetime',
        'valor_total'       => 'decimal:2',
        'valor_produtos'    => 'decimal:2',
        'valor_icms'        => 'decimal:2',
        'valor_ipi'         => 'decimal:2',
        'valor_pis'         => 'decimal:2',
        'valor_cofins'      => 'decimal:2',
        'valor_frete'       => 'decimal:2',
        'valor_desconto'    => 'decimal:2',
        'ambiente'          => 'integer',
        'numero'            => 'integer',
    ];

    // Garante que os accessors abaixo sejam serializados no JSON retornado
    // pela API (necessário para o frontend, que consome pode_cancelar,
    // pode_carta_correcao, chave_formatada e ambiente_nome diretamente).
    protected $appends = [
        'ambiente_nome',
        'pode_cancelar',
        'pode_carta_correcao',
        'chave_formatada',
    ];

    const STATUS_RASCUNHO    = 'rascunho';
    const STATUS_PROCESSANDO = 'processando';
    const STATUS_AUTORIZADA  = 'autorizada';
    const STATUS_CANCELADA   = 'cancelada';
    const STATUS_DENEGADA    = 'denegada';
    const STATUS_INUTILIZADA = 'inutilizada';

    // ── Relações ──────────────────────────────────────────────

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NotaFiscalItem::class);
    }

    public function cartasCorrecao(): HasMany
    {
        return $this->hasMany(CartaCorrecao::class)->orderBy('sequencia', 'desc');
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeAutorizadas(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_AUTORIZADA);
    }

    public function scopeTipo(Builder $q, string $tipo): Builder
    {
        return $q->where('tipo', $tipo);
    }

    public function scopeAmbiente(Builder $q, int $ambiente): Builder
    {
        return $q->where('ambiente', $ambiente);
    }

    // ── Accessors ─────────────────────────────────────────────

    public function getAmbienteNomeAttribute(): string
    {
        return $this->ambiente === 1 ? 'Produção' : 'Homologação';
    }

    public function getPodeCancelarAttribute(): bool
    {
        if ($this->status !== self::STATUS_AUTORIZADA) return false;
        // Prazo de cancelamento: 24h após autorização (NF-e)
        return $this->data_autorizacao?->diffInHours(now()) <= 24;
    }

    public function getPodeCartaCorrecaoAttribute(): bool
    {
        return $this->status === self::STATUS_AUTORIZADA;
    }

    public function getChaveFormatadaAttribute(): string
    {
        if (! $this->chave_acesso) return '';
        return implode(' ', str_split($this->chave_acesso, 4));
    }
}
