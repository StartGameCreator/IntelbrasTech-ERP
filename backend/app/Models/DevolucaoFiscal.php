<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class DevolucaoFiscal extends Model
{
    protected $table = 'devolucoes_fiscais';

    protected $fillable = [
        'empresa_id','pdv_venda_id','nota_fiscal_origem_id','nota_fiscal_devolucao_id',
        'cliente_id','usuario_id','numero','tipo','motivo','descricao_motivo','status',
        'situacao_mercadoria','forma_reembolso','status_reembolso','valor_produtos',
        'valor_reembolso','solicitada_em','recebida_em','concluida_em','observacoes',
    ];

    protected $casts = [
        'valor_produtos' => 'decimal:2',
        'valor_reembolso' => 'decimal:2',
        'solicitada_em' => 'datetime',
        'recebida_em' => 'datetime',
        'concluida_em' => 'datetime',
    ];

    public function itens(): HasMany { return $this->hasMany(DevolucaoFiscalItem::class); }
    public function venda(): BelongsTo { return $this->belongsTo(PdvVenda::class, 'pdv_venda_id'); }
    public function notaOrigem(): BelongsTo { return $this->belongsTo(NotaFiscal::class, 'nota_fiscal_origem_id'); }
    public function notaDevolucao(): BelongsTo { return $this->belongsTo(NotaFiscal::class, 'nota_fiscal_devolucao_id'); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
}
