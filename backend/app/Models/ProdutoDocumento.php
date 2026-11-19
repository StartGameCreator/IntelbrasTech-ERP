<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProdutoDocumento extends Model
{
    protected $table = 'produto_documentos';
    protected $fillable = [
        'produto_id','tipo','titulo','origem','url','arquivo',
        'mime_type','tamanho_bytes','publico','ordem'
    ];
    protected $casts = ['publico'=>'boolean','ordem'=>'integer','tamanho_bytes'=>'integer'];
}
