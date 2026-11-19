<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CartaCorrecao extends Model
{
    protected $table = 'cartas_correcao';
    protected $fillable = ['nota_fiscal_id','sequencia','descricao','protocolo'];
}
