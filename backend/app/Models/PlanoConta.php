<?php
namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class PlanoConta extends Model
{
    use HasEmpresa;
    public $timestamps = false;

    protected $table = 'plano_contas';
    protected $fillable = ['empresa_id', 'conta_pai_id', 'codigo', 'nome', 'tipo', 'natureza', 'nivel', 'analitica', 'ativo'];

    protected $casts = [
        'analitica' => 'boolean',
        'ativo'     => 'boolean',
    ];

    public function contaPai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'conta_pai_id');
    }

    public function filhas(): HasMany
    {
        return $this->hasMany(self::class, 'conta_pai_id');
    }
}
