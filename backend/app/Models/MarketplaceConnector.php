<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceConnector extends Model
{
    protected $fillable = [
        'slug','nome','categoria','descricao','icone',
        'status','ativo','ordem','recursos',
    ];

    protected $casts = [
        'ativo'=>'boolean',
        'ordem'=>'integer',
        'recursos'=>'array',
    ];
}
