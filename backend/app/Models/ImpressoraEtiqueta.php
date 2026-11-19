<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;

class ImpressoraEtiqueta extends Model
{
    use HasEmpresa;

    protected $table = 'impressoras_etiquetas';

    protected $fillable = [
        'empresa_id','nome','nome_sistema','tipo','formato_padrao',
        'papel_padrao','dpi','padrao','ativa','configuracoes',
    ];

    protected $casts = [
        'padrao' => 'boolean',
        'ativa' => 'boolean',
        'configuracoes' => 'array',
    ];
}
