<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class PipelineEtapa extends Model
{
    public $timestamps = false;

    protected $table = 'pipeline_etapas';
    protected $fillable = ['pipeline_id', 'nome', 'ordem', 'cor', 'probabilidade', 'etapa_ganha', 'etapa_perdida'];

    protected $casts = ['probabilidade'=>'integer','etapa_ganha'=>'boolean','etapa_perdida'=>'boolean'];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'etapa_id');
    }
}
