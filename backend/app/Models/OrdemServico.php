<?php

namespace App\Models;
use App\Traits\HasUuid;

use App\Traits\HasEmpresa;
use App\Traits\HasAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class OrdemServico extends Model
{
    use HasEmpresa, HasAuditoria, HasUuid;

    protected $table = 'ordens_servico';

    protected $fillable = [
        'empresa_id','numero','cliente_id','tecnico_id','status_id',
        'equipamento_tipo','equipamento_marca','equipamento_modelo',
        'equipamento_serie','equipamento_imei',
        'defeito_relatado','defeito_constatado','laudo_tecnico','solucao',
        'garantia','numero_nf_compra','data_compra','em_garantia',
        'valor_servico','desconto','total',
        'orcamento_aprovado','data_aprovacao_orcamento',
        'data_entrada','data_previsao','data_conclusao','data_entrega',
        'prazo_garantia_dias','data_garantia_vence',
        'nota_cliente','comentario_cliente',
        'observacoes','observacoes_internas',
    ];

    protected $casts = [
        'garantia'                  => 'boolean',
        'em_garantia'               => 'boolean',
        'orcamento_aprovado'        => 'boolean',
        'valor_servico'             => 'decimal:2',
        'desconto'                  => 'decimal:2',
        'total'                     => 'decimal:2',
        'data_compra'               => 'date',
        'data_entrada'              => 'datetime',
        'data_previsao'             => 'date',
        'data_conclusao'            => 'datetime',
        'data_entrega'              => 'datetime',
        'data_aprovacao_orcamento'  => 'datetime',
        'data_garantia_vence'       => 'date',
        'nota_cliente'              => 'integer',
    ];

    // ── Relações ──────────────────────────────────────────────

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tecnico_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OsStatus::class, 'status_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(OsFoto::class, 'os_id')->orderBy('created_at');
    }

    public function pecas(): HasMany
    {
        return $this->hasMany(OsPeca::class, 'os_id');
    }

    public function historico(): HasMany
    {
        return $this->hasMany(OsHistorico::class, 'os_id')->with('usuario:id,nome')->orderByDesc('created_at');
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeAtrasadas(Builder $q): Builder
    {
        return $q->whereHas('status', fn($s) => $s->where('tipo', 'andamento'))
                 ->where('data_previsao', '<', today());
    }

    public function scopeTecnico(Builder $q, int $tecnicoId): Builder
    {
        return $q->where('tecnico_id', $tecnicoId);
    }

    public function scopePeriodo(Builder $q, string $inicio, string $fim): Builder
    {
        return $q->whereBetween('data_entrada', [$inicio, $fim]);
    }

    // ── Accessors ─────────────────────────────────────────────

    public function getEstaNoPrazoAttribute(): bool
    {
        if (! $this->data_previsao) return true;
        return ! $this->data_previsao->isPast() || $this->data_conclusao !== null;
    }

    public function getDiasAbertoAttribute(): int
    {
        $fim = $this->data_conclusao ?? now();
        return (int) $this->data_entrada->diffInDays($fim);
    }

    public function getTotalPecasAttribute(): float
    {
        return (float) $this->pecas->sum('total');
    }

    public function getTotalGeralAttribute(): float
    {
        return $this->valor_servico + $this->total_pecas - ($this->desconto ?? 0);
    }

    // ── Helpers ───────────────────────────────────────────────

    public function gerarNumero(): string
    {
        $ultimo = static::withoutGlobalScope('empresa')
            ->where('empresa_id', $this->empresa_id)
            ->max('numero');
        return 'OS' . str_pad((intval($ultimo ?? 0) + 1), 5, '0', STR_PAD_LEFT);
    }

    public function registrarHistorico(string $statusNovo, ?string $observacao = null): OsHistorico
    {
        return $this->historico()->create([
            'usuario_id'     => auth()->id(),
            'status_anterior'=> $this->status?->nome,
            'status_novo'    => $statusNovo,
            'observacao'     => $observacao,
        ]);
    }
}
