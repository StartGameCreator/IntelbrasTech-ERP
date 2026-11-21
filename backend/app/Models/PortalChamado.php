<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PortalChamado extends Model
{
    use HasEmpresa, HasUuid;

    protected $table = 'portal_chamados';

    protected $fillable = [
        'uuid', 'token_publico', 'empresa_id', 'cliente_id', 'lead_id', 'ordem_servico_id',
        'protocolo', 'status', 'tipo_demanda', 'prioridade', 'nome', 'email', 'telefone',
        'whatsapp', 'descricao', 'dados', 'orcamento_titulo', 'orcamento_descricao',
        'orcamento_valor', 'orcamento_validade', 'orcamento_enviado_em', 'autorizado_em',
        'autorizacao_ip_hash', 'autorizacao_user_agent',
    ];

    protected $casts = [
        'dados' => 'array',
        'orcamento_valor' => 'decimal:2',
        'orcamento_validade' => 'date',
        'orcamento_enviado_em' => 'datetime',
        'autorizado_em' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class);
    }

    public function contrato(): HasOne
    {
        return $this->hasOne(ContratoServico::class, 'portal_chamado_id');
    }
}
