<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integracao extends Model
{
    use HasEmpresa;
    protected $table = 'integracoes';
    protected $fillable = ['empresa_id','tipo','nome','credenciais','config','status','ultimo_sync','ativo'];

    protected $casts = [
        'credenciais' => 'array',
        'config'      => 'array',
        'ultimo_sync' => 'datetime',
        'ativo'       => 'boolean',
    ];

    protected $hidden = ['credenciais'];

    public function marketplaceProdutos(): HasMany
    {
        return $this->hasMany(MarketplaceProduto::class);
    }

    public function marketplacePedidos(): HasMany
    {
        return $this->hasMany(MarketplacePedido::class);
    }
}
