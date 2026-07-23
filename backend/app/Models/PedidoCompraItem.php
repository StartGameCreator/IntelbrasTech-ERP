<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoCompraItem extends Model
{
    public $timestamps = false;

    protected $table = 'pedido_compra_itens';
    protected $fillable = [
        'pedido_compra_id', 'produto_id', 'variacao_id', 'descricao',
        'quantidade', 'quantidade_recebida', 'preco_unitario', 'desconto_percentual', 'total',
    ];

    protected $casts = [
        'quantidade'           => 'decimal:3',
        'quantidade_recebida'  => 'decimal:3',
        'preco_unitario'       => 'decimal:4',
        'desconto_percentual'  => 'decimal:2',
        'total'                => 'decimal:2',
    ];

    protected $appends = ['saldo_a_receber'];

    public function pedidoCompra(): BelongsTo
    {
        return $this->belongsTo(PedidoCompra::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function variacao(): BelongsTo
    {
        return $this->belongsTo(ProdutoVariacao::class, 'variacao_id');
    }

    public function getSaldoAReceberAttribute(): float
    {
        return round((float) $this->quantidade - (float) $this->quantidade_recebida, 3);
    }
}
