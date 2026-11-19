<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoImagem extends Model
{
    protected $table = 'produto_imagens';

    protected $fillable = [
        'produto_id','url','arquivo','origem','titulo','alt_text','principal','ordem',
        'mime_type','extensao','largura','altura','tamanho_bytes','status',
    ];

    protected $casts = [
        'principal' => 'boolean',
        'ordem' => 'integer',
        'largura' => 'integer',
        'altura' => 'integer',
        'tamanho_bytes' => 'integer',
    ];
}
