<?php

use App\Models\Brand;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('provisions a license key and licenses for a brand', function () {
    $apiKey = 'brand-secret-token';
    $brand = Brand::create([
        'name' => 'RankMath',
        'api_key_hash' => hash('sha256', $apiKey),
        'role' => 'standard',
    ]);

    $product = Product::create([
        'brand_id' => $brand->id,
        'code' => 'rankmath-pro',
        'name' => 'RankMath Pro',
        'product_token_hash' => hash('sha256', 'product-token'),
    ]);

    $expiresAt = Carbon::now()->addMonth();

    $response = $this->postJson('/api/brand/license-keys', [
        'customer_email' => 'User@example.com',
        'licenses' => [
            [
                'product_code' => $product->code,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ],
    ], [
        'X-Brand-Key' => $apiKey,
    ]);

    $response->assertCreated()
        ->assertJsonPath('customer_email', 'user@example.com')
        ->assertJsonPath('licenses.0.product_code', $product->code)
        ->assertJsonPath('licenses.0.status', 'valid')
        ->assertJsonPath('licenses.0.expires_at', $expiresAt->toIso8601String());

    $this->assertDatabaseHas('license_keys', [
        'brand_id' => $brand->id,
        'customer_email' => 'user@example.com',
    ]);

    $this->assertDatabaseHas('licenses', [
        'product_id' => $product->id,
        'status' => 'valid',
    ]);

    expect(LicenseKey::count())->toBe(1);
});

it('reuses an existing license key and updates license data', function () {
    $apiKey = 'brand-secret-token';
    $brand = Brand::create([
        'name' => 'WP Rocket',
        'api_key_hash' => hash('sha256', $apiKey),
        'role' => 'standard',
    ]);

    $product = Product::create([
        'brand_id' => $brand->id,
        'code' => 'wp-rocket',
        'name' => 'WP Rocket',
        'product_token_hash' => hash('sha256', 'product-token'),
    ]);

    $licenseKey = LicenseKey::create([
        'brand_id' => $brand->id,
        'key' => 'EXISTING-KEY-123',
        'customer_email' => 'user@example.com',
    ]);

    $license = License::create([
        'license_key_id' => $licenseKey->id,
        'product_id' => $product->id,
        'status' => 'valid',
        'expires_at' => Carbon::now()->addDays(2),
    ]);

    $newExpiration = Carbon::now()->addMonths(2);

    $response = $this->postJson('/api/brand/license-keys', [
        'customer_email' => 'user@example.com',
        'licenses' => [
            [
                'product_code' => $product->code,
                'expires_at' => $newExpiration->toIso8601String(),
            ],
        ],
    ], [
        'X-Brand-Key' => $apiKey,
    ]);

    $response->assertCreated()
        ->assertJsonPath('license_key', 'EXISTING-KEY-123')
        ->assertJsonPath('licenses.0.expires_at', $newExpiration->toIso8601String());

    $this->assertDatabaseCount('license_keys', 1);
    $this->assertDatabaseCount('licenses', 1);

    $license->refresh();

    expect($license->expires_at->toIso8601String())->toBe($newExpiration->toIso8601String());
});

it('fails when the product code is not found for the brand', function () {
    $apiKey = 'brand-secret-token';
    $brand = Brand::create([
        'name' => 'Content AI',
        'api_key_hash' => hash('sha256', $apiKey),
        'role' => 'standard',
    ]);

    $response = $this->postJson('/api/brand/license-keys', [
        'customer_email' => 'user@example.com',
        'licenses' => [
            [
                'product_code' => 'missing-code',
                'expires_at' => Carbon::now()->addWeek()->toIso8601String(),
            ],
        ],
    ], [
        'X-Brand-Key' => $apiKey,
    ]);

    $response->assertNotFound();

    $this->assertDatabaseCount('license_keys', 0);
    $this->assertDatabaseCount('licenses', 0);
});
