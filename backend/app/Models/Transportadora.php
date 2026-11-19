<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transportadora extends Model
{
    use HasEmpresa;
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $table = 'transportadoras';
    protected $fillable = ['empresa_id','nome','cnpj','ie','email','telefone','api_tipo','api_usuario','api_senha','api_token','api_contrato','ativo'];
}
