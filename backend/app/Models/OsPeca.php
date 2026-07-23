<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OsPeca extends Model
{
    public $timestamps = false;

    protected $table = 'os_pecas';
    protected $fillable = ['os_id','produto_id','descricao','quantidade','preco_unitario','total'];

    public function os(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'os_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
