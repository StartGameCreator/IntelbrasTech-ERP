<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoFornecedor extends Model
{
    protected $table = 'produto_fornecedores';
    protected $guarded = [];

    protected $casts = [
        'custo' => 'decimal:4',
        'estoque_fornecedor' => 'decimal:3',
        'dropship_ativo' => 'boolean',
        'principal' => 'boolean',
        'ultima_sincronizacao_em' => 'datetime',
    ];

    public function produto() { return $this->belongsTo(Produto::class); }
    public function fornecedor() { return $this->belongsTo(Fornecedor::class); }
}
