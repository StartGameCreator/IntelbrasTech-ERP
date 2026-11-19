<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioItem extends Model
{
    protected $table = 'inventario_itens';
    protected $fillable = [
        'inventario_id', 'produto_id', 'variacao_id',
        'quantidade_sistema', 'quantidade_contada', 'diferenca',
    ];

    protected $casts = [
        'quantidade_sistema' => 'decimal:3',
        'quantidade_contada' => 'decimal:3',
        'diferenca'          => 'decimal:3',
    ];

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function variacao(): BelongsTo
    {
        return $this->belongsTo(ProdutoVariacao::class, 'variacao_id');
    }
}
