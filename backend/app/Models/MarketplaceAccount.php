<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;

class MarketplaceAccount extends Model
{
    use HasEmpresa;

    protected $fillable = [
        'empresa_id','connector_id','nome_conta','status',
        'access_token','refresh_token','token_expires_at',
        'configuracoes','ultima_sincronizacao_em',
    ];

    protected $hidden = ['access_token','refresh_token'];

    protected $casts = [
        'token_expires_at'=>'datetime',
        'ultima_sincronizacao_em'=>'datetime',
        'configuracoes'=>'array',
        'access_token'=>'encrypted',
        'refresh_token'=>'encrypted',
    ];

    public function connector()
    {
        return $this->belongsTo(MarketplaceConnector::class, 'connector_id');
    }
}
