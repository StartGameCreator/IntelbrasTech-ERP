<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplacePedido extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $table = 'marketplace_pedidos';
    protected $fillable = ['integracao_id','pedido_id','marketplace_pedido_id','marketplace','status_marketplace','dados_raw','sincronizado'];

    protected $casts = [
        'dados_raw'     => 'array',
        'sincronizado'  => 'boolean',
    ];

    public function integracao(): BelongsTo
    {
        return $this->belongsTo(Integracao::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
