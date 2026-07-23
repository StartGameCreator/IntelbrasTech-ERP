<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevolucaoFiscalItem extends Model
{
    protected $table = 'devolucao_fiscal_itens';

    protected $fillable = [
        'devolucao_fiscal_id','pedido_item_id','produto_id','variacao_id','deposito_id',
        'codigo','descricao','ncm','unidade','quantidade_vendida','quantidade_devolvida',
        'valor_unitario','valor_total','condicao','retornou_estoque',
    ];

    protected $casts = [
        'quantidade_vendida' => 'decimal:4',
        'quantidade_devolvida' => 'decimal:4',
        'valor_unitario' => 'decimal:4',
        'valor_total' => 'decimal:2',
        'retornou_estoque' => 'boolean',
    ];

    public function devolucao(): BelongsTo { return $this->belongsTo(DevolucaoFiscal::class, 'devolucao_fiscal_id'); }
    public function produto(): BelongsTo { return $this->belongsTo(Produto::class)->withTrashed(); }
    public function deposito(): BelongsTo { return $this->belongsTo(Deposito::class); }
}
