<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProdutoCodigo extends Model
{
    protected $table = 'produto_codigos';
    protected $fillable = ['produto_id','tipo','codigo','quantidade','principal'];
    protected $casts = ['quantidade'=>'decimal:3','principal'=>'boolean'];
}
