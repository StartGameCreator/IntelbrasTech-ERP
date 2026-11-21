<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratoServico extends Model
{
    use HasEmpresa, HasUuid;

    protected $table = 'contratos_servico';

    protected $fillable = [
        'uuid', 'empresa_id', 'cliente_id', 'portal_chamado_id', 'numero', 'status', 'titulo',
        'objeto', 'valor', 'validade_orcamento', 'conteudo_html', 'arquivo_disk', 'arquivo_path',
        'aceite_nome', 'aceito_em', 'aceite_ip_hash', 'aceite_user_agent',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'validade_orcamento' => 'date',
        'aceito_em' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function chamado(): BelongsTo
    {
        return $this->belongsTo(PortalChamado::class, 'portal_chamado_id');
    }
}
