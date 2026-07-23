<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasEmpresa;
use App\Traits\HasAuditoria;

abstract class BaseModel extends Model
{
    use HasEmpresa, HasAuditoria;

    /**
     * Boot padrão — aplica scopo global de empresa
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->empresa_id) && auth()->check()) {
                $model->empresa_id = auth()->user()->empresa_id;
            }
        });
    }
}
