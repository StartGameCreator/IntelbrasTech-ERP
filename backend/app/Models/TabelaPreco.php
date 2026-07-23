<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TabelaPreco extends Model
{
    use HasEmpresa;
    protected $table = 'tabelas_preco';
    protected $fillable = ['empresa_id','nome','descricao','desconto_geral','acrescimo_geral','ativa'];
}
