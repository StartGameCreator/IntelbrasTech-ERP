<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoListaPreco extends Model
{
    protected $table = 'produto_listas_preco';
    protected $fillable = ['produto_id','tabela_preco_id','preco','preco_minimo','ativo'];
    protected $casts = ['preco'=>'decimal:4','preco_minimo'=>'decimal:4','ativo'=>'boolean'];

    public function tabela(): BelongsTo
    {
        return $this->belongsTo(TabelaPreco::class, 'tabela_preco_id');
    }
}
