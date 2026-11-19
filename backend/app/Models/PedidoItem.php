<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItem extends Model
{
    public $timestamps = false;

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $table = 'pedido_itens';

    protected $fillable = [
        'pedido_id',
        'produto_id',
        'variacao_id',
        'deposito_id',
        'codigo',
        'descricao',
        'ncm',
        'cfop',
        'unidade',
        'quantidade',
        'preco_unitario',
        'desconto_percentual',
        'desconto_valor',
        'total',
        'icms_cst',
        'icms_aliquota',
        'pis_cst',
        'pis_aliquota',
        'cofins_cst',
        'cofins_aliquota',
    ];

    protected $casts = [
        'quantidade' => 'decimal:4',
        'preco_unitario' => 'decimal:4',
        'desconto_percentual' => 'decimal:2',
        'desconto_valor' => 'decimal:2',
        'total' => 'decimal:2',
        'icms_aliquota' => 'decimal:2',
        'pis_aliquota' => 'decimal:2',
        'cofins_aliquota' => 'decimal:2',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id')->withTrashed();
    }

    public function variacao(): BelongsTo
    {
        return $this->belongsTo(ProdutoVariacao::class, 'variacao_id')->withTrashed();
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Deposito::class, 'deposito_id');
    }
}
