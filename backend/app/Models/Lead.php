<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasEmpresa;
    protected $table = 'leads';
    protected $fillable = [
        'razao_social',
        'nome_fantasia',
        'situacao_cadastral',
        'endereco_empresarial',
        'logradouro',
        'numero_endereco',
        'complemento_endereco',
        'bairro',
        'cep',
        'cnae',
        'atividade_principal',
        'natureza_juridica',
        'porte_empresa',
        'capital_social',
        'socios',
        'rfb_provider',
        'rfb_consultado_em',
        'rfb_dados',
        'cnpj',
        'cidade',
        'uf',
        'latitude',
        'longitude',
        'fonte_referencia',
        'dados_extras',
        'uuid','empresa_id','pipeline_id','etapa_id','responsavel_id','cliente_id','nome','email','telefone','empresa_nome','origem','titulo','valor_estimado','probabilidade','data_previsao_fechamento','status','motivo_perda','tags'];

    protected $casts = [
        'valor_estimado'           => 'decimal:2',
        'probabilidade'            => 'integer',
        'data_previsao_fechamento' => 'date',
    ];

    const STATUS_ABERTO  = 'aberto';
    const STATUS_GANHO   = 'ganho';
    const STATUS_PERDIDO = 'perdido';

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(PipelineEtapa::class, 'etapa_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'responsavel_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function scopeAberto($query)
    {
        return $query->where('status', self::STATUS_ABERTO);
    }
}
