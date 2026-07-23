<?php

use App\Http\Controllers\Auth\TecnicoController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Shared\NotificacaoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Públicas ──────────────────────────────────────────────────────────
    Route::post('/auth/login',  [\App\Http\Controllers\Api\AuthApiController::class, 'login'])->name('auth.login');
    Route::get('/tecnicos/proximos', [TecnicoController::class, 'tecnicosProximos'])->name('tecnicos.proximos');
    Route::get('/tecnicos/{uuid}',   [\App\Http\Controllers\Api\TecnicoApiController::class, 'show'])->name('tecnicos.show');

    // ── Autenticadas ──────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/tecnico/chamados-proximos', [TecnicoController::class, 'chamadosProximos'])->name('tecnico.chamados.proximos');

        Route::get('/notificacoes',             [NotificacaoController::class, 'index'])->name('notificacoes.index');
        Route::post('/notificacoes/{id}/lida',  [NotificacaoController::class, 'marcarLida'])->name('notificacoes.lida');
        Route::post('/notificacoes/lidas',      [NotificacaoController::class, 'marcarTodasLidas'])->name('notificacoes.todas.lidas');
        Route::post('/notificacoes/push-token', [NotificacaoController::class, 'salvarToken'])->name('notificacoes.token');
    });

    // ── Admin ─────────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum','role:admin,colaborador'])->prefix('admin')->group(function () {
        Route::get('/stats',       [AdminController::class, 'apiStats'])->name('admin.stats');
        Route::get('/mapa-dados',  [AdminController::class, 'apiMapaDados'])->name('admin.mapa');
    });
});
