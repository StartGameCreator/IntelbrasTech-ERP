<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deposito extends Model
{
    use HasEmpresa;
    protected $table = 'depositos';
    protected $fillable = ['empresa_id','nome','descricao','responsavel','cep','logradouro','numero','bairro','cidade','estado','principal','ativo'];

    public function estoque(): HasMany
    {
        return $this->hasMany(Estoque::class);
    }
}
