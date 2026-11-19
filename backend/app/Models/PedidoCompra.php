<?php
namespace App\Models;
use App\Traits\HasUuid;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class PedidoCompra extends Model
{
    use HasEmpresa, HasUuid;
    protected $table = 'pedidos_compra';
    protected $fillable = ['empresa_id','numero','fornecedor_id','deposito_id','status','subtotal','desconto_valor','frete','outras_despesas','total','data_emissao','data_previsao','data_recebimento','condicao_pagamento_id','observacoes'];

    protected $casts = [
        'subtotal'         => 'decimal:2',
        'desconto_valor'   => 'decimal:2',
        'frete'            => 'decimal:2',
        'outras_despesas'  => 'decimal:2',
        'total'            => 'decimal:2',
        'data_emissao'     => 'date',
        'data_previsao'    => 'date',
        'data_recebimento' => 'date',
    ];

    protected $appends = ['pode_receber', 'pode_editar'];

    const STATUS_RASCUNHO = 'rascunho';
    const STATUS_ENVIADO  = 'enviado';
    const STATUS_PARCIAL  = 'parcial';
    const STATUS_RECEBIDO = 'recebido';
    const STATUS_CANCELADO = 'cancelado';

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Deposito::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedidoCompraItem::class);
    }

    public function gerarNumero(): string
    {
        return 'PC-' . now()->format('Ymd') . '-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getPodeReceberAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_ENVIADO, self::STATUS_PARCIAL]);
    }

    public function getPodeEditarAttribute(): bool
    {
        return $this->status === self::STATUS_RASCUNHO;
    }
}
