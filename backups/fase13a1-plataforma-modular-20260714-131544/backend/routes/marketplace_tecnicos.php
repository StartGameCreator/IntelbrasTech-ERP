<?php

use App\Http\Controllers\Api\V1\MarketplaceTecnicos\MarketplaceNucleoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/marketplace-tecnicos')->group(function () {
    Route::get('public-config', [MarketplaceNucleoController::class, 'publicConfig']);
    Route::post('cadastro/tecnico', [MarketplaceNucleoController::class, 'cadastrarTecnico']);
    Route::post('cadastro/cliente', [MarketplaceNucleoController::class, 'cadastrarCliente']);
    Route::post('cadastro-social/{provider}', [MarketplaceNucleoController::class, 'prepararCadastroSocial']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('dashboard', [MarketplaceNucleoController::class, 'dashboard']);
        Route::patch('tecnicos/{id}/status', [MarketplaceNucleoController::class, 'atualizarStatusTecnico']);
        Route::patch('clientes/{id}/status', [MarketplaceNucleoController::class, 'atualizarStatusCliente']);
    });
});
