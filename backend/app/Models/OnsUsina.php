<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnsUsina extends Model
{
    protected $table = 'ons_usinas';

    protected $fillable = [
        'codigo_ceg','nome','uf','municipio','fase','fonte','potencia_mw',
        'latitude','longitude','entrada_operacao','proprietarios','sub_bacia',
        'fonte_dados','fonte_atualizada_em','lead_id','importada_crm_em',
    ];

    protected $casts = [
        'potencia_mw' => 'decimal:6',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'entrada_operacao' => 'date',
        'fonte_atualizada_em' => 'datetime',
        'importada_crm_em' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
