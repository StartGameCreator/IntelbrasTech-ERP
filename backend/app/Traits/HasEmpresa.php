<?php

namespace App\Traits;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasEmpresa
{
    /**
     * Scope global: todas queries filtram por empresa_id automaticamente
     */
    protected static function bootHasEmpresa(): void
    {
        static::addGlobalScope('empresa', function (Builder $builder) {
            if (auth()->check() && ! app()->runningInConsole()) {
                $builder->where(
                    (new static)->getTable() . '.empresa_id',
                    auth()->user()->empresa_id
                );
            }
        });
    }

    /**
     * Relação com empresa
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Scope manual (para uso em commands/jobs)
     */
    public function scopeParaEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->withoutGlobalScope('empresa')
                     ->where($this->getTable() . '.empresa_id', $empresaId);
    }
}
