<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Marca extends Model
{
    use HasEmpresa;
    protected $table = 'marcas';
    protected $fillable = ['empresa_id','nome','slug','logo','ativo'];
}
