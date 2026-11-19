<?php
namespace App\Models;
use App\Traits\HasUuid;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaReceber extends Model
{
    use HasEmpresa, SoftDeletes, HasUuid;
    protected $table = 'contas_receber';
    protected $fillable = ['empresa_id','cliente_id','pedido_id','nota_fiscal_id','conta_pai_id','plano_conta_id','forma_pagamento_id','conta_bancaria_id','descricao','numero_documento','parcela','total_parcelas','valor','valor_desconto','valor_juros','valor_multa','valor_pago','data_emissao','data_vencimento','data_pagamento','status','observacoes','nosso_numero','linha_digitavel','codigo_pix'];

    protected $casts = [
        'data_emissao'    => 'date',
        'data_vencimento' => 'date',
        'data_pagamento'  => 'date',
        'valor'           => 'decimal:2',
        'valor_desconto'  => 'decimal:2',
        'valor_juros'     => 'decimal:2',
        'valor_multa'     => 'decimal:2',
        'valor_pago'      => 'decimal:2',
    ];

    protected $appends = ['saldo', 'esta_vencida'];

    const STATUS_ABERTO    = 'aberto';
    const STATUS_PARCIAL   = 'parcial';
    const STATUS_PAGO      = 'pago';
    const STATUS_CANCELADO = 'cancelado';
    const STATUS_ATRASADO  = 'atrasado';

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class);
    }

    public function contaPai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'conta_pai_id');
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class);
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class);
    }

    public function contaBancaria(): BelongsTo
    {
        return $this->belongsTo(ContaBancaria::class);
    }

    public function getSaldoAttribute(): float
    {
        return round((float) $this->valor + (float) $this->valor_juros + (float) $this->valor_multa
            - (float) $this->valor_desconto - (float) $this->valor_pago, 2);
    }

    public function getEstaVencidaAttribute(): bool
    {
        return $this->status !== self::STATUS_PAGO
            && $this->status !== self::STATUS_CANCELADO
            && $this->data_vencimento->isPast();
    }
}
