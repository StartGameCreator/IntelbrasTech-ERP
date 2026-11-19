<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoComposicao extends Model
{
    protected $table = 'produto_composicao';
    protected $fillable = ['produto_pai_id','produto_filho_id','quantidade'];

    public function produtoPai(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_pai_id');
    }

    public function produtoFilho(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_filho_id');
    }
}
