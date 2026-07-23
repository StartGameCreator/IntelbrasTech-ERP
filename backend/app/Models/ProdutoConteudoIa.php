<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoConteudoIa extends Model
{
    protected $table = 'produto_conteudos_ia';

    protected $fillable = [
        'produto_id','usuario_id','tipo','canal','fonte',
        'titulo','conteudo','metadados','aplicado',
    ];

    protected $casts = [
        'metadados' => 'array',
        'aplicado' => 'boolean',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
