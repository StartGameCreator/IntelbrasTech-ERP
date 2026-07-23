<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Estoque extends Model
{
    use HasEmpresa;
    protected $table = 'estoque';
    public $timestamps = false;
    protected $fillable = ['empresa_id','produto_id','variacao_id','deposito_id','quantidade','quantidade_reservada','custo_medio'];

    protected $casts = [
        'quantidade'            => 'decimal:3',
        'quantidade_reservada'  => 'decimal:3',
        'quantidade_disponivel' => 'decimal:3',
        'custo_medio'           => 'decimal:4',
    ];

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function variacao(): BelongsTo
    {
        return $this->belongsTo(ProdutoVariacao::class, 'variacao_id');
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Deposito::class);
    }

    protected static function booted(): void
    {
        // quantidade_disponivel era coluna gerada pelo banco (Postgres); calculamos
        // aqui na aplicação para funcionar igual em Postgres e SQLite.
        static::saving(function (Estoque $estoque) {
            $estoque->quantidade_disponivel = $estoque->quantidade - $estoque->quantidade_reservada;
        });
    }
}
