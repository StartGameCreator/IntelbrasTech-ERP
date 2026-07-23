<?php

namespace App\Traits;

use App\Models\LogSistema;

trait HasAuditoria
{
    protected static function bootHasAuditoria(): void
    {
        static::created(fn ($model) => static::registrarLog('criado', null, $model->toArray(), $model));
        static::updated(fn ($model) => static::registrarLog('atualizado', $model->getOriginal(), $model->getDirty(), $model));
        static::deleted(fn ($model) => static::registrarLog('excluido', $model->toArray(), null, $model));
    }

    protected static function registrarLog(string $acao, ?array $antes, ?array $depois, $model): void
    {
        if (! auth()->check()) return;
        if (app()->runningInConsole() && ! app()->runningUnitTests()) return;

        try {
            LogSistema::withoutGlobalScopes()->create([
                'empresa_id'  => auth()->user()->empresa_id ?? null,
                'usuario_id'  => auth()->id(),
                'acao'        => $acao,
                'modulo'      => class_basename($model),
                'tabela'      => $model->getTable(),
                'registro_id' => $model->getKey(),
                'dados_antes' => $antes ? json_encode($antes) : null,
                'dados_depois'=> $depois ? json_encode($depois) : null,
                'ip'          => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]);
        } catch (\Throwable) {
            // Nunca deixar auditoria quebrar a operação principal
        }
    }
}
