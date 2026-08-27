<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\CategoriaController;
use App\Http\Controllers\Api\V1\ProdutoController;
use App\Http\Middleware\AddApiVersionHeader;
use Illuminate\Support\Facades\Route;

Route::get('health-db', HealthController::class)->name('health');

Route::prefix('v1')
    ->name('v1.')
    ->middleware(AddApiVersionHeader::class)
    ->group(function (): void {
        Route::middleware('throttle:60,1')->group(function (): void {
            Route::apiResource('categorias', CategoriaController::class);
            Route::apiResource('produtos', ProdutoController::class);
        });
    });
