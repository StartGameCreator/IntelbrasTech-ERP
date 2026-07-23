<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalEvento extends Model
{
    use HasEmpresa, HasUuid;

    protected $table = 'fiscal_eventos';

    protected $fillable = [
        'empresa_id', 'nota_fiscal_id', 'usuario_id', 'tipo', 'descricao',
        'ip', 'user_agent', 'dados',
    ];

    protected $casts = ['dados' => 'array'];

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class);
    }
}
