<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;

class SerieFiscal extends Model
{
    use HasEmpresa;

    protected $table = 'series_fiscais';

    /**
     * A tabela agora possui created_at e updated_at.
     * Mantemos os timestamps ativos para compatibilidade com firstOrCreate,
     * updateOrCreate e demais operações Eloquent do fluxo fiscal.
     */
    public $timestamps = true;

    protected $fillable = [
        'empresa_id',
        'tipo',
        'serie',
        'ultimo_numero',
        'ambiente',
        'ativo',
    ];

    protected $casts = [
        'ultimo_numero' => 'integer',
        'ambiente' => 'integer',
        'ativo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Próximo número disponível.
     *
     * O avanço definitivo da série deve ocorrer somente após a regra
     * definida pelo motor fiscal, evitando consumo indevido de numeração.
     */
    public function getProximoNumeroAttribute(): int
    {
        return (int) $this->ultimo_numero + 1;
    }
}
