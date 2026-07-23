<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoPrecoUf extends Model
{
    use HasEmpresa;

    protected $table = 'produto_precos_uf';

    protected $fillable = [
        'empresa_id', 'produto_id', 'tabela', 'uf_origem', 'uf_destino',
        'ipi', 'icms', 'pv', 'custo', 'pp', 'psd', 'pscf', 'multiplo',
        'preco_referencia', 'ativo', 'importado_em',
    ];

    protected $casts = [
        'ipi' => 'decimal:4',
        'icms' => 'decimal:4',
        'pv' => 'decimal:4',
        'custo' => 'decimal:4',
        'pp' => 'decimal:4',
        'psd' => 'decimal:4',
        'pscf' => 'decimal:4',
        'multiplo' => 'decimal:3',
        'ativo' => 'boolean',
        'importado_em' => 'datetime',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
