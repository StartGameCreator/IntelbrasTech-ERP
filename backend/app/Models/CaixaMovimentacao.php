<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaixaMovimentacao extends Model
{
    public $timestamps = false;
    protected $table = 'caixa_movimentacoes';
    protected $fillable = [
        'caixa_id','usuario_id','tipo','valor','forma_pagamento',
        'descricao','pedido_id','metadados','created_at',
    ];
    protected $casts = ['valor'=>'decimal:2','metadados'=>'array','created_at'=>'datetime'];
}
