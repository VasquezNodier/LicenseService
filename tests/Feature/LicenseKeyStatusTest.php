<?php

use App\Models\Brand;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function statusProductToken(): string
{
    return 'status-product-token';
}

function createStatusProduct(): Product
{
    $brand = Brand::create([
        'name' => 'RankMath',
        'api_key_hash' => hash('sha256', 'brand-key'),
        'role' => 'standard',
    ]);

    return Product::create([
        'brand_id' => $brand->id,
        'code' => 'rankmath-pro',
        'name' => 'RankMath Pro',
        'product_token_hash' => hash('sha256', statusProductToken()),
    ]);
}

it('returns license status with entitlements for the product brand', function () {
    $product = createStatusProduct();

    $licenseKey = LicenseKey::create([
        'brand_id' => $product->brand_id,
        'key' => 'KEY-123',
        'customer_email' => 'user@example.com',
    ]);

    $expiresAt = Carbon::now()->addDays(5);

    License::create([
        'license_key_id' => $licenseKey->id,
        'product_id' => $product->id,
        'status' => 'valid',
        'expires_at' => $expiresAt,
    ]);

    $response = $this->withHeaders(['X-Product-Token' => statusProductToken()])
        ->getJson("/api/product/license-keys/{$licenseKey->key}");

    $response->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('license_key', 'KEY-123')
        ->assertJsonPath('customer_email', 'user@example.com')
        ->assertJsonPath('entitlements.0.product_code', $product->code)
        ->assertJsonPath('entitlements.0.status', 'valid')
        ->assertJsonPath('entitlements.0.is_valid', true);

    expect(Carbon::parse($response->json('entitlements.0.expires_at'))->isSameSecond($expiresAt))->toBeTrue();
});

it('returns license_key_not_found for unknown key', function () {
    createStatusProduct();

    $response = $this->withHeaders(['X-Product-Token' => statusProductToken()])
        ->getJson('/api/product/license-keys/MISSING-KEY');

    $response->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('reason', 'license_key_not_found');
});

it('returns license_key_not_for_brand when the key belongs to another brand', function () {
    $product = createStatusProduct();

    $otherBrand = Brand::create([
        'name' => 'OtherBrand',
        'api_key_hash' => hash('sha256', 'other-brand-key'),
        'role' => 'standard',
    ]);

    $licenseKey = LicenseKey::create([
        'brand_id' => $otherBrand->id,
        'key' => 'KEY-OTHER',
        'customer_email' => 'user@example.com',
    ]);

    $response = $this->withHeaders(['X-Product-Token' => statusProductToken()])
        ->getJson("/api/product/license-keys/{$licenseKey->key}");

    $response->assertStatus(403)
        ->assertJsonPath('valid', false)
        ->assertJsonPath('reason', 'license_key_not_for_brand');
});

it('marks license as invalid when entitlements are expired', function () {
    $product = createStatusProduct();

    $licenseKey = LicenseKey::create([
        'brand_id' => $product->brand_id,
        'key' => 'KEY-EXPIRED',
        'customer_email' => 'user@example.com',
    ]);

    License::create([
        'license_key_id' => $licenseKey->id,
        'product_id' => $product->id,
        'status' => 'valid',
        'expires_at' => Carbon::now()->subDay(),
    ]);

    $response = $this->withHeaders(['X-Product-Token' => statusProductToken()])
        ->getJson("/api/product/license-keys/{$licenseKey->key}");

    $response->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('entitlements.0.is_valid', false);
});

it('requires a product token', function () {
    createStatusProduct();

    $response = $this->getJson('/api/product/license-keys/ANY');
    $response->assertStatus(401)
        ->assertJsonPath('message', 'Missing X-Product-Token');

    $response = $this->withHeaders(['X-Product-Token' => 'invalid-token'])
        ->getJson('/api/product/license-keys/ANY');

    $response->assertStatus(401)
        ->assertJsonPath('message', 'Invalid product token');
});
