<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;

class ImportacaoProduto extends Model
{
    use HasEmpresa;

    protected $table = 'importacoes_produtos';

    protected $fillable = [
        'empresa_id', 'usuario_id', 'uuid', 'arquivo', 'status',
        'uf_preferencial', 'produtos_recebidos', 'produtos_criados',
        'produtos_atualizados', 'precos_recebidos', 'precos_gravados',
        'erros', 'detalhes', 'concluida_em',
    ];

    protected $casts = [
        'detalhes' => 'array',
        'concluida_em' => 'datetime',
    ];
}
