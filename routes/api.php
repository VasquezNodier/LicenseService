<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('brand')->middleware('brand.auth')->group(function () {
    Route::post('/license-keys', \App\Http\Controllers\Api\Brand\ProvisionLicenseKeyController::class);
    Route::get('/licenses', \App\Http\Controllers\Api\Brand\ListLicensesByEmailController::class);
 });

Route::prefix('product')->middleware('product.auth')->group(function () {
    Route::post('/activate', \App\Http\Controllers\Api\Product\ActivateLicenseController::class);
    Route::get('/license-keys/{key}', \App\Http\Controllers\Api\Product\LicenseKeyStatusController::class);
});
