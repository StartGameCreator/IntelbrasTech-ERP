<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceProduto extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $table = 'marketplace_produtos';
    protected $fillable = ['integracao_id','produto_id','variacao_id','marketplace_sku','marketplace_id','marketplace_url','titulo','preco_anunciado','estoque_sincronizado','status_anuncio','ultimo_sync'];

    protected $casts = [
        'preco_anunciado' => 'decimal:2',
        'ultimo_sync'     => 'datetime',
    ];

    public function integracao(): BelongsTo
    {
        return $this->belongsTo(Integracao::class);
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
