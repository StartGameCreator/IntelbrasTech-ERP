<?php
namespace App\Models;

use App\Traits\HasEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use HasEmpresa;
    public $timestamps = false;

    protected $table = 'pipelines';
    protected $fillable = ['empresa_id', 'nome', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public function etapas(): HasMany
    {
        return $this->hasMany(PipelineEtapa::class)->orderBy('ordem');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
