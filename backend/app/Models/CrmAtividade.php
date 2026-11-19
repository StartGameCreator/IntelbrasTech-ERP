<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
class CrmAtividade extends Model {
    use HasEmpresa;
    protected $table='crm_atividades';
    protected $fillable=['empresa_id','lead_id','usuario_id','tipo','titulo','descricao','status','agendada_para','concluida_em','resultado'];
    protected $casts=['agendada_para'=>'datetime','concluida_em'=>'datetime'];
    public function usuario(){return $this->belongsTo(Usuario::class,'usuario_id');}
}
