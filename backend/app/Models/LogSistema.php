<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;

class LogSistema extends Model
{
    use HasEmpresa;
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $table = 'logs_sistema';
    protected $fillable = ['empresa_id','usuario_id','acao','modulo','tabela','registro_id','dados_antes','dados_depois','ip','user_agent'];
}
