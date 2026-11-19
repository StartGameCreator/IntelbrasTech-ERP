<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalSolicitacao extends Model
{
    protected $table = 'portal_solicitacoes';

    protected $fillable = [
        'uuid', 'empresa_id', 'lead_id', 'tipo', 'nome', 'email', 'telefone',
        'cidade', 'status', 'dados', 'ip_hash',
    ];

    protected $casts = ['dados' => 'array'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
