<?php
namespace App\Models;
use App\Traits\HasUuid;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comprovante de venda SEM validade fiscal.
 *
 * Existe para o cenário do MEI que, ao vender para pessoa física, não é
 * obrigado a emitir NF-e/NFC-e — mas ainda precisa de um documento para:
 *  - controle de garantia do produto junto ao cliente;
 *  - comprovar a entrega para parceiros de transporte (motorista de app,
 *    entregador terceirizado, etc.).
 *
 * Nunca é enviado à SEFAZ e não deve ser confundido com o Cupom Fiscal (NFC-e),
 * que é fiscal e emitido pelo módulo Fiscal normal.
 */
class Recibo extends Model
{
    use HasEmpresa, HasUuid;
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $table = 'recibos';
    protected $fillable = [
        'empresa_id', 'pedido_id', 'usuario_id', 'numero',
        'cliente_nome', 'cliente_documento', 'cliente_telefone',
        'itens_snapshot', 'valor_total', 'forma_pagamento', 'observacoes',
        'prazo_garantia_dias', 'data_garantia_vence',
        'entregador_tipo', 'entregador_app_nome', 'entregador_nome',
        'entregador_documento', 'entregador_telefone', 'veiculo_placa', 'veiculo_modelo',
        'data_emissao',
    ];

    protected $casts = [
        'itens_snapshot'      => 'array',
        'valor_total'         => 'decimal:2',
        'prazo_garantia_dias' => 'integer',
        'data_garantia_vence' => 'date',
        'data_emissao'        => 'datetime',
    ];

    protected $appends = ['garantia_vigente', 'tem_entregador'];

    const TIPO_ENTREGADOR_PROPRIO     = 'proprio';
    const TIPO_ENTREGADOR_UBER        = 'uber';
    const TIPO_ENTREGADOR_APP_PARCEIRO = 'app_parceiro';
    const TIPO_ENTREGADOR_OUTRO       = 'outro';

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function getGarantiaVigenteAttribute(): bool
    {
        return $this->data_garantia_vence !== null && $this->data_garantia_vence->isFuture();
    }

    public function getTemEntregadorAttribute(): bool
    {
        return ! empty($this->entregador_nome);
    }
}
