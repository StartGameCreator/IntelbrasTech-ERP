<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SerieFiscal extends Model
{
    use HasEmpresa;
    protected $table = 'series_fiscais';
    protected $fillable = ['empresa_id','tipo','serie','ultimo_numero','ambiente','ativo'];

    protected $casts = [
        'ultimo_numero' => 'integer',
        'ambiente'      => 'integer',
        'ativo'         => 'boolean',
    ];

    /**
     * Próximo número a ser utilizado na emissão (ultimo_numero + 1).
     * Não incrementa nada sozinho — a persistência do avanço acontece
     * em NFeService::incrementarSerie() somente após autorização confirmada.
     */
    public function getProximoNumeroAttribute(): int
    {
        return (int) $this->ultimo_numero + 1;
    }
}
