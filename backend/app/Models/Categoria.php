<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasEmpresa;
    protected $table = 'categorias';
    protected $fillable = ['empresa_id','categoria_pai_id','nome','slug','descricao','imagem','ordem','ativo'];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }
}
