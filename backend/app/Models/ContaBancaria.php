<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContaBancaria extends Model
{
    use HasEmpresa, SoftDeletes;
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $table = 'contas_bancarias';
    protected $fillable = ['empresa_id','nome','banco','agencia','conta','tipo','saldo_inicial','saldo_atual','cor','icone','principal','ativo'];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
        'saldo_atual'   => 'decimal:2',
        'principal'     => 'boolean',
        'ativo'         => 'boolean',
    ];

    public function contasPagar(): HasMany
    {
        return $this->hasMany(ContaPagar::class);
    }

    public function contasReceber(): HasMany
    {
        return $this->hasMany(ContaReceber::class);
    }
}
