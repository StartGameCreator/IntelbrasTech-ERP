<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use DomainException;
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

    protected static function booted(): void
    {
        static::updating(function (SerieFiscal $serie): void {
            if (
                $serie->isDirty('ultimo_numero')
                && (int) $serie->ultimo_numero < (int) $serie->getOriginal('ultimo_numero')
            ) {
                throw new DomainException(
                    'A numeração fiscal não pode retroceder. Informe um próximo número maior que o atual.'
                );
            }
        });
    }

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
