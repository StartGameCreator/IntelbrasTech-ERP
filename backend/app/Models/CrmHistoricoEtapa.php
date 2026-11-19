<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
class CrmHistoricoEtapa extends Model {
    use HasEmpresa; public $timestamps=false;
    protected $table='crm_historico_etapas';
    protected $fillable=['empresa_id','lead_id','etapa_origem_id','etapa_destino_id','usuario_id','movido_em','tempo_etapa_minutos'];
    protected $casts=['movido_em'=>'datetime'];
    public function origem(){return $this->belongsTo(PipelineEtapa::class,'etapa_origem_id');}
    public function destino(){return $this->belongsTo(PipelineEtapa::class,'etapa_destino_id');}
    public function usuario(){return $this->belongsTo(Usuario::class,'usuario_id');}
}
