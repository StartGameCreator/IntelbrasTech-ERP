<?php

namespace App\Models;
use App\Traits\HasUuid;

use App\Traits\HasEmpresa;
use App\Traits\HasAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Pedido extends Model
{
    use HasEmpresa, HasAuditoria, HasUuid;

    protected $fillable = [
        'empresa_id','numero','cliente_id','vendedor_id',
        'canal','marketplace','marketplace_pedido_id','tabela_preco_id',
        'entrega_cep','entrega_logradouro','entrega_numero','entrega_complemento',
        'entrega_bairro','entrega_cidade','entrega_estado','entrega_ibge',
        'subtotal','desconto_percentual','desconto_valor',
        'acrescimo_percentual','acrescimo_valor','frete','total',
        'status','status_pagamento','status_entrega',
        'data_pedido','data_confirmacao','data_faturamento','data_entrega','data_cancelamento',
        'motivo_cancelamento','transportadora_id','tipo_frete',
        'prazo_entrega_dias','codigo_rastreio','condicao_pagamento_id',
        'observacoes','observacoes_nfe','origem_id',
    ];

    protected $casts = [
        'subtotal'             => 'decimal:2',
        'desconto_percentual'  => 'decimal:2',
        'desconto_valor'       => 'decimal:2',
        'acrescimo_percentual' => 'decimal:2',
        'acrescimo_valor'      => 'decimal:2',
        'frete'                => 'decimal:2',
        'total'                => 'decimal:2',
        'data_pedido'          => 'datetime',
        'data_confirmacao'     => 'datetime',
        'data_faturamento'     => 'datetime',
        'data_entrega'         => 'datetime',
        'data_cancelamento'    => 'datetime',
    ];

    // ── Status constantes ─────────────────────────────────────

    const STATUS_ORCAMENTO  = 'orcamento';
    const STATUS_CONFIRMADO = 'confirmado';
    const STATUS_FATURADO   = 'faturado';
    const STATUS_ENTREGUE   = 'entregue';
    const STATUS_CANCELADO  = 'cancelado';

    const STATUS_PAG_PENDENTE  = 'pendente';
    const STATUS_PAG_PARCIAL   = 'parcial';
    const STATUS_PAG_PAGO      = 'pago';

    // ── Relações ──────────────────────────────────────────────

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'vendedor_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedidoItem::class)->with(['produto', 'variacao']);
    }

    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class);
    }

    public function contasReceber(): HasMany
    {
        return $this->hasMany(ContaReceber::class);
    }

    public function envio(): HasOne
    {
        return $this->hasOne(PedidoEnvio::class)->latest();
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }

    public function pedidoOrigem(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'origem_id');
    }

    // ── Scopes ────────────────────────────────────────────────

    public function scopeStatus(Builder $q, string|array $status): Builder
    {
        return is_array($status)
            ? $q->whereIn('status', $status)
            : $q->where('status', $status);
    }

    public function scopeCanal(Builder $q, string $canal): Builder
    {
        return $q->where('canal', $canal);
    }

    public function scopeMarketplace(Builder $q, string $marketplace): Builder
    {
        return $q->where('marketplace', $marketplace);
    }

    public function scopePeriodo(Builder $q, string $inicio, string $fim): Builder
    {
        return $q->whereBetween('data_pedido', [$inicio, $fim]);
    }

    public function scopeHoje(Builder $q): Builder
    {
        return $q->whereDate('data_pedido', today());
    }

    public function scopeMesAtual(Builder $q): Builder
    {
        return $q->whereMonth('data_pedido', now()->month)
                 ->whereYear('data_pedido', now()->year);
    }

    // ── Accessors ─────────────────────────────────────────────

    public function getStatusCorAttribute(): string
    {
        return match($this->status) {
            'orcamento'  => 'gray',
            'confirmado' => 'blue',
            'faturado'   => 'purple',
            'entregue'   => 'green',
            'cancelado'  => 'red',
            default      => 'gray',
        };
    }

    public function getPodeEmitirNfeAttribute(): bool
    {
        return in_array($this->status, ['confirmado', 'faturado'])
            && $this->notasFiscais()->where('status', 'autorizada')->doesntExist();
    }

    public function getPodeCancelarAttribute(): bool
    {
        return ! in_array($this->status, ['cancelado', 'entregue']);
    }

    // ── Helpers ───────────────────────────────────────────────

    public function recalcularTotais(): void
    {
        $subtotal = $this->itens->sum('total');
        $desconto = $this->desconto_valor;
        if ($this->desconto_percentual > 0) {
            $desconto = round($subtotal * ($this->desconto_percentual / 100), 2);
        }
        $acrescimo = $this->acrescimo_valor;
        $total = $subtotal - $desconto + $acrescimo + ($this->frete ?? 0);

        $this->update([
            'subtotal'       => $subtotal,
            'desconto_valor' => $desconto,
            'total'          => max(0, $total),
        ]);
    }

    public function gerarNumero(): string
    {
        $ultimo = static::withoutGlobalScope('empresa')
            ->where('empresa_id', $this->empresa_id)
            ->max('numero');
        return str_pad((intval($ultimo) + 1), 6, '0', STR_PAD_LEFT);
    }
}
