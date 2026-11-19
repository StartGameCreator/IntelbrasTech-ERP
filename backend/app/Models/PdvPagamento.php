<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdvPagamento extends Model
{
    protected $table = 'pdv_pagamentos';

    protected $fillable = [
        'pdv_venda_id',
        'forma_pagamento_id',
        'tipo',
        'valor',
        'parcelas',
        'nsu',
        'autorizacao',
        'metadados',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'parcelas' => 'integer',
        'metadados' => 'array',
    ];

    public function venda(): BelongsTo
    {
        return $this->belongsTo(PdvVenda::class, 'pdv_venda_id');
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class, 'forma_pagamento_id');
    }
}
