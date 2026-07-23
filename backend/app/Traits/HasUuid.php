<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Gera automaticamente um UUID v4 para a coluna `uuid` de um model ao criar
 * o registro, caso ainda não tenha sido informado. Necessário porque a
 * coluna é NOT NULL e nem Postgres nem SQLite geram esse valor sozinhos
 * (ao contrário da chave primária incremental) — quem tem que gerar é a
 * aplicação.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
