<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class PdvVenda extends Model
{
    use HasUuid;

    protected $table = 'pdv_vendas';
    protected $fillable = [
        'empresa_id','caixa_id','pedido_id','cliente_id','usuario_id','numero',
        'subtotal','desconto','acrescimo','total','troco','status','observacoes','concluida_em','comprovante_codigo','nfce_status','nota_fiscal_id',
    ];
    protected $casts = [
        'subtotal'=>'decimal:2','desconto'=>'decimal:2','acrescimo'=>'decimal:2',
        'total'=>'decimal:2','troco'=>'decimal:2','concluida_em'=>'datetime',
    ];

    public function pagamentos(): HasMany { return $this->hasMany(PdvPagamento::class); }
    public function pedido(): BelongsTo { return $this->belongsTo(Pedido::class); }
    public function caixa(): BelongsTo { return $this->belongsTo(Caixa::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
}
