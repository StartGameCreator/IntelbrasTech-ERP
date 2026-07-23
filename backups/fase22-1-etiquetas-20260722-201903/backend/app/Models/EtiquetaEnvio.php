<?php

namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtiquetaEnvio extends Model
{
    use HasEmpresa;

    protected $table = 'etiquetas_envio';

    protected $fillable = [
        'empresa_id','expedicao_id','pedido_id','volume_id','marketplace',
        'transportadora','servico','external_shipment_id','codigo_rastreio',
        'origem','formato','tamanho_papel','nome_original','arquivo_path',
        'arquivo_hash','arquivo_tamanho','mime_type','status',
        'quantidade_impressoes','gerada_em','ultima_impressao_em',
        'criado_por','ultimo_impresso_por','ultimo_erro','metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'gerada_em' => 'datetime',
        'ultima_impressao_em' => 'datetime',
    ];

    public function pedido(): BelongsTo { return $this->belongsTo(Pedido::class); }
}
