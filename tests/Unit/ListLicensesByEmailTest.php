<?php

use App\Http\Controllers\Api\Brand\ListLicensesByEmailController;
use App\Models\Brand;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns 403 when brand is not ecosystem admin', function () {
    $request = Request::create('/api/brand/licenses', 'GET');
    $request->attributes->set('brand', new Brand(['role' => 'standard']));

    $response = app(ListLicensesByEmailController::class)($request);

    expect($response->status())->toBe(403);
});

it('returns 422 when email is missing', function () {
    $brand = new Brand(['role' => 'ecosystem_admin']);

    $request = Request::create('/api/brand/licenses', 'GET');
    $request->attributes->set('brand', $brand);

    $response = app(ListLicensesByEmailController::class)($request);

    expect($response->status())->toBe(422);
});

it('lists licenses for the given customer email', function () {
    $brand = Brand::create([
        'name' => 'RankMath',
        'api_key_hash' => hash('sha256', 'brand-key'),
        'role' => 'ecosystem_admin',
    ]);

    $product = Product::create([
        'brand_id' => $brand->id,
        'code' => 'rankmath-pro',
        'name' => 'RankMath Pro',
        'product_token_hash' => hash('sha256', 'product-token'),
    ]);

    $licenseKey = LicenseKey::create([
        'brand_id' => $brand->id,
        'key' => 'KEY-123',
        'customer_email' => 'user@example.com',
    ]);

    $expiresAt = Carbon::create(2025, 1, 1, 0, 0, 0, config('app.timezone'));

    License::create([
        'license_key_id' => $licenseKey->id,
        'product_id' => $product->id,
        'status' => 'valid',
        'expires_at' => $expiresAt,
    ]);

    $request = Request::create('/api/brand/licenses', 'GET', ['email' => 'user@example.com']);
    $request->attributes->set('brand', $brand);

    $response = app(ListLicensesByEmailController::class)($request);
    $payload = $response->getData(true);

    expect($response->status())->toBe(200);
    expect($payload['customer_email'])->toBe('user@example.com');
    expect($payload['license_keys'][0]['brand'])->toBe('RankMath');
    expect($payload['license_keys'][0]['license_key'])->toBe('KEY-123');
    expect($payload['license_keys'][0]['licenses'][0]['product_code'])->toBe('rankmath-pro');
    expect($payload['license_keys'][0]['licenses'][0]['status'])->toBe('valid');
    expect(Carbon::parse($payload['license_keys'][0]['licenses'][0]['expires_at'])->equalTo($expiresAt))->toBeTrue();
});
