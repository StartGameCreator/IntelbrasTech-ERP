<?php
namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormaPagamento extends Model
{
    use HasEmpresa;
    public $timestamps = false;

    protected $table = 'formas_pagamento';
    protected $fillable = ['empresa_id', 'nome', 'tipo', 'taxa', 'prazo_recebimento_dias', 'conta_bancaria_id', 'ativo'];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class);
    }
}
