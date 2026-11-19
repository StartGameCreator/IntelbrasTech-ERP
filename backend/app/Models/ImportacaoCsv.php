<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;

class ImportacaoCsv extends Model
{
    use HasEmpresa;

    protected $table = 'importacoes_csv';

    protected $fillable = [
        'empresa_id', 'tipo', 'nome_original', 'arquivo', 'status',
        'total_linhas', 'linhas_processadas', 'erros', 'mapeamento',
    ];

    protected $casts = [
        'erros' => 'array',
        'mapeamento' => 'array',
    ];
}
