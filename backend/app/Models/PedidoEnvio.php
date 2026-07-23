<?php
namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoEnvio extends Model
{
    use HasEmpresa;

    protected $table = 'pedido_envios';
    protected $fillable = [
        'empresa_id', 'pedido_id', 'nota_fiscal_id', 'transportadora_id',
        'servico', 'codigo_rastreio', 'etiqueta_url', 'etiqueta_base64',
        'status', 'peso', 'altura', 'largura', 'comprimento', 'valor_frete',
        'prazo_dias', 'data_postagem', 'data_previsao', 'data_entrega',
    ];

    protected $casts = [
        'peso'          => 'decimal:3',
        'altura'        => 'decimal:2',
        'largura'       => 'decimal:2',
        'comprimento'   => 'decimal:2',
        'valor_frete'   => 'decimal:2',
        'data_postagem' => 'date',
        'data_previsao' => 'date',
        'data_entrega'  => 'date',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class);
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Transportadora::class);
    }
}
