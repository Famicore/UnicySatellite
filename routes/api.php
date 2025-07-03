<?php

use Illuminate\Support\Facades\Route;
use UnicySatellite\Http\Controllers\SatelliteApiController;

/*
|--------------------------------------------------------------------------
| Satellite API Routes
|--------------------------------------------------------------------------
|
| Routes API pour la communication avec UnicyHub
| Ces routes permettent à UnicyHub de communiquer avec ce satellite
|
*/

Route::prefix(config('satellite.satellite.api_prefix', 'api/satellite'))
    ->middleware(['satellite.auth'])
    ->group(function () {
        
        // Health check endpoint
        Route::get('/health', [SatelliteApiController::class, 'health'])
            ->name('satellite.health');
        
        // Status endpoint
        Route::get('/status', [SatelliteApiController::class, 'status'])
            ->name('satellite.status');
        
        // Metrics endpoint
        Route::get('/metrics', [SatelliteApiController::class, 'metrics'])
            ->name('satellite.metrics');
        
        // Commands endpoint - permet à UnicyHub d'exécuter des commandes
        Route::post('/commands', [SatelliteApiController::class, 'executeCommand'])
            ->name('satellite.commands');
        
        // Update endpoint - reçoit les mises à jour depuis UnicyHub
        Route::post('/updates', [SatelliteApiController::class, 'receiveUpdates'])
            ->name('satellite.updates');
        
        // Cache management
        Route::delete('/cache', [SatelliteApiController::class, 'clearCache'])
            ->name('satellite.cache.clear');
        
        // Information endpoint
        Route::get('/info', [SatelliteApiController::class, 'info'])
            ->name('satellite.info');
    }); 