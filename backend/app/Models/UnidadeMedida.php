<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnidadeMedida extends Model
{
    use HasEmpresa;
    protected $table = 'unidades_medida';
    protected $fillable = ['empresa_id','sigla','descricao'];
}
