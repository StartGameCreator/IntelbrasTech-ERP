<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FiscalEvento extends Model
{
    use HasEmpresa;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'fiscal_eventos';

    protected $fillable = [
        'empresa_id', 'nota_fiscal_id', 'usuario_id', 'tipo', 'descricao',
        'ip', 'user_agent', 'dados',
    ];

    protected $casts = ['dados' => 'array'];

    protected static function booted(): void
    {
        static::creating(function (self $evento) {
            if (! $evento->getKey()) {
                $evento->setAttribute($evento->getKeyName(), (string) Str::uuid());
            }
        });
    }

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class);
    }
}
