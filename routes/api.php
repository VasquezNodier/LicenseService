<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::get('/ready', function () {
    try {
        DB::select('select 1');
        return response()->json(['status' => 'ready']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'degraded', 'reason' => $e->getMessage()], 503);
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
