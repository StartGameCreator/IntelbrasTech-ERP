<?php
namespace App\Models;
use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};

class Perfil extends Model
{
    use HasEmpresa;
    protected $table = 'perfis';
    protected $fillable = ['empresa_id','nome','descricao','admin','ativo'];

    protected $casts = [
        'admin' => 'boolean',
        'ativo' => 'boolean',
    ];

    public function permissoes(): BelongsToMany
    {
        return $this->belongsToMany(Permissao::class, 'perfil_permissoes');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }
}
