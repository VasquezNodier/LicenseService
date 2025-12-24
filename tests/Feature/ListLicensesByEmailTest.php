<?php

use App\Models\Brand;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('returns license keys for an ecosystem admin brand', function () {
    $brand = Brand::create([
        'name' => 'RankMath',
        'api_key_hash' => hash('sha256', 'brand-secret-token'),
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

    $response = $this->withHeaders(['X-Brand-Key' => 'brand-secret-token'])
        ->getJson('/api/brand/licenses?email=user@example.com');

    $response->assertOk()
        ->assertJsonPath('customer_email', 'user@example.com')
        ->assertJsonPath('license_keys.0.brand', 'RankMath')
        ->assertJsonPath('license_keys.0.license_key', 'KEY-123')
        ->assertJsonPath('license_keys.0.licenses.0.product_code', 'rankmath-pro')
        ->assertJsonPath('license_keys.0.licenses.0.status', 'valid');

    expect(Carbon::parse($response->json('license_keys.0.licenses.0.expires_at'))->equalTo($expiresAt))->toBeTrue();
});

it('returns forbidden for non admin brand', function () {
    Brand::create([
        'name' => 'StandardBrand',
        'api_key_hash' => hash('sha256', 'brand-secret-token'),
        'role' => 'standard',
    ]);

    $response = $this->withHeaders(['X-Brand-Key' => 'brand-secret-token'])
        ->getJson('/api/brand/licenses?email=user@example.com');

    $response->assertForbidden();
});

it('validates required email parameter', function () {
    Brand::create([
        'name' => 'RankMath',
        'api_key_hash' => hash('sha256', 'brand-secret-token'),
        'role' => 'ecosystem_admin',
    ]);

    $response = $this->withHeaders(['X-Brand-Key' => 'brand-secret-token'])
        ->getJson('/api/brand/licenses');

    $response->assertStatus(422)
        ->assertJsonPath('message', 'email is required');
});
