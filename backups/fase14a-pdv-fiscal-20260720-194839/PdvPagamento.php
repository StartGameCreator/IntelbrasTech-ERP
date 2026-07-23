<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdvPagamento extends Model
{
    protected $table = 'pdv_pagamentos';
    protected $fillable = [
        'pdv_venda_id','forma_pagamento_id','tipo','valor',
        'parcelas','nsu','autorizacao','metadados',
    ];
    protected $casts = ['valor'=>'decimal:2','parcelas'=>'integer','metadados'=>'array'];
}
