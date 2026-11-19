<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdvNfceDocumento extends Model
{
    protected $table = 'pdv_nfce_documentos';

    protected $fillable = [
        'empresa_id','pdv_venda_id','ambiente','serie','numero',
        'status','xml_rascunho','validacoes','gerado_em',
    ];

    protected $casts = [
        'serie'=>'integer','numero'=>'integer',
        'validacoes'=>'array','gerado_em'=>'datetime',
    ];
}
