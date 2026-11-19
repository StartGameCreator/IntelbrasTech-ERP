<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificadoDigital extends Model
{
    use HasEmpresa, SoftDeletes;

    protected $table = 'certificados_digitais';

    protected $fillable = [
        'empresa_id', 'tipo', 'arquivo', 'senha', 'validade', 'validade_inicio',
        'serie', 'ativo', 'nome', 'titular', 'documento', 'emissor', 'thumbprint',
        'armazenamento', 'observacoes',
    ];

    protected $hidden = ['senha', 'arquivo'];

    protected function casts(): array
    {
        return [
            'senha' => 'encrypted',
            'validade' => 'date:Y-m-d',
            'validade_inicio' => 'date:Y-m-d',
            'ativo' => 'boolean',
        ];
    }
}
