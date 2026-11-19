<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Caixa extends Model
{
    use HasUuid;

    protected $fillable = [
        'empresa_id','usuario_id','deposito_id','numero','status',
        'valor_abertura','valor_fechamento_informado','valor_fechamento_calculado',
        'diferenca','aberto_em','fechado_em','observacoes_abertura','observacoes_fechamento',
    ];

    protected $casts = [
        'valor_abertura'=>'decimal:2',
        'valor_fechamento_informado'=>'decimal:2',
        'valor_fechamento_calculado'=>'decimal:2',
        'diferenca'=>'decimal:2',
        'aberto_em'=>'datetime',
        'fechado_em'=>'datetime',
    ];

    public function usuario(): BelongsTo { return $this->belongsTo(Usuario::class); }
    public function deposito(): BelongsTo { return $this->belongsTo(Deposito::class); }
    public function movimentacoes(): HasMany { return $this->hasMany(CaixaMovimentacao::class); }
    public function vendas(): HasMany { return $this->hasMany(PdvVenda::class); }
}
