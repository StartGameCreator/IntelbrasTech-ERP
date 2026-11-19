<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;

class ConfiguracaoFiscal extends Model
{
    use HasEmpresa;

    protected $table = 'configuracoes_fiscais';

    protected $fillable = [
        'empresa_id', 'versao_layout', 'uf', 'ambiente_nfe', 'ambiente_nfce',
        'serie_nfe', 'serie_nfce', 'ativo',
    ];

    protected $casts = [
        'ambiente_nfe' => 'integer',
        'ambiente_nfce' => 'integer',
        'ativo' => 'boolean',
    ];
}
