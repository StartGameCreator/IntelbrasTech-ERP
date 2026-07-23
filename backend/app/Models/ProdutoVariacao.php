<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProdutoVariacao extends Model
{
    use SoftDeletes;

    protected $table = 'produto_variacoes';

    protected $fillable = [
        'produto_id',
        'nome',
        'sku',
        'codigo_barras',
        'preco_venda',
        'preco_custo',
        'peso',
        'foto',
        'atributos',
        'estoque_minimo',
        'ordem',
        'ativo',
    ];

    protected $casts = [
        'preco_venda' => 'decimal:4',
        'preco_custo' => 'decimal:4',
        'peso' => 'decimal:3',
        'estoque_minimo' => 'decimal:3',
        'atributos' => 'array',
        'ordem' => 'integer',
        'ativo' => 'boolean',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id')->withTrashed();
    }

    public function estoques(): HasMany
    {
        return $this->hasMany(Estoque::class, 'variacao_id');
    }
}
