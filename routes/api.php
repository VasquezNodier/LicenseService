<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::get('/ready', function () {
    $startedAt = microtime(true);

    try {
        DB::select('select 1');

        return response()->json(['status' => 'ready'], 200);

    } catch (\Throwable $e) {

        \App\Support\DomainLog::error('system.ready.degraded', [
            'reason' => 'db_unreachable',
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'http_status' => 503,
            'error_class' => get_class($e),
            // Solo en local muestra el mensaje
            'error_message' => app()->isLocal() ? substr($e->getMessage(), 0, 500) : null,
        ]);

        return response()->json([
            'status' => 'degraded',
            'reason' => 'db_unreachable',
        ], 503);
    }
});

Route::prefix('brand')->middleware('brand.auth')->group(function () {
    Route::post('/brands', \App\Http\Controllers\Api\Brand\CreateBrandController::class);
    Route::post('/products', \App\Http\Controllers\Api\Brand\CreateProductController::class);
    Route::post('/license-keys', \App\Http\Controllers\Api\Brand\ProvisionLicenseKeyController::class);
    Route::get('/licenses', \App\Http\Controllers\Api\Brand\ListLicensesByEmailController::class);
    Route::patch('/licenses/{license_id}', \App\Http\Controllers\Api\Brand\UpdateLicenseLifecycleController::class);
});

Route::prefix('product')->middleware('product.auth')->group(function () {
    Route::post('/activate', \App\Http\Controllers\Api\Product\ActivateLicenseController::class);
    Route::get('/license-keys/{key}', \App\Http\Controllers\Api\Product\LicenseKeyStatusController::class);
    Route::delete('/deactivate', \App\Http\Controllers\Api\Product\DeactivateActivationController::class);
});
