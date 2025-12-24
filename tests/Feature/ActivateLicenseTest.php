<?php

use App\Models\Activation;
use App\Models\Brand;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const PRODUCT_TOKEN = 'product-secret-token';

function createProductWithBrand(): Product
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
        'product_token_hash' => hash('sha256', PRODUCT_TOKEN),
    ]);
}

it('activates a valid license and returns entitlements', function () {
    $product = createProductWithBrand();

    $licenseKey = LicenseKey::create([
        'brand_id' => $product->brand_id,
        'key' => 'KEY-123',
        'customer_email' => 'user@example.com',
    ]);

    $expiresAt = Carbon::now()->addDays(10);

    $license = License::create([
        'license_key_id' => $licenseKey->id,
        'product_id' => $product->id,
        'status' => 'valid',
        'expires_at' => $expiresAt,
    ]);

    $payload = [
        'license_key' => 'KEY-123',
        'product_code' => $product->code,
        'instance_type' => 'url',
        'instance_identifier' => 'https://example.com',
    ];

    $response = $this->withHeaders(['X-Product-Token' => PRODUCT_TOKEN])
        ->postJson('/api/product/activate', $payload);

    $response->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('license_key', 'KEY-123')
        ->assertJsonPath('product_code', $product->code)
        ->assertJsonPath('entitlements.0.product_code', $product->code)
        ->assertJsonPath('entitlements.0.status', 'valid');

    expect(Carbon::parse($response->json('expires_at'))->isSameSecond($expiresAt))->toBeTrue();
    expect(Activation::count())->toBe(1);

    $this->assertDatabaseHas('activations', [
        'license_id' => $license->id,
        'instance_identifier' => 'https://example.com',
        'revoked_at' => null,
    ]);

    // Idempotent activation for the same instance
    $this->withHeaders(['X-Product-Token' => PRODUCT_TOKEN])
        ->postJson('/api/product/activate', $payload)
        ->assertOk();

    expect(Activation::count())->toBe(1);
});

it('returns license_key_not_found when the key does not exist', function () {
    createProductWithBrand();

    $response = $this->withHeaders(['X-Product-Token' => PRODUCT_TOKEN])
        ->postJson('/api/product/activate', [
            'license_key' => 'MISSING',
            'product_code' => 'rankmath-pro',
            'instance_type' => 'url',
            'instance_identifier' => 'https://example.com',
        ]);

    $response->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('reason', 'license_key_not_found');

    expect(Activation::count())->toBe(0);
});

it('returns no_entitlement_for_product when the license lacks the product', function () {
    $product = createProductWithBrand();
    $otherProduct = Product::create([
        'brand_id' => $product->brand_id,
        'code' => 'other-product',
        'name' => 'Other Product',
        'product_token_hash' => hash('sha256', 'other-token'),
    ]);

    $licenseKey = LicenseKey::create([
        'brand_id' => $product->brand_id,
        'key' => 'KEY-999',
        'customer_email' => 'user@example.com',
    ]);

    License::create([
        'license_key_id' => $licenseKey->id,
        'product_id' => $otherProduct->id,
        'status' => 'valid',
        'expires_at' => Carbon::now()->addDay(),
    ]);

    $response = $this->withHeaders(['X-Product-Token' => PRODUCT_TOKEN])
        ->postJson('/api/product/activate', [
            'license_key' => 'KEY-999',
            'product_code' => $product->code,
            'instance_type' => 'host',
            'instance_identifier' => 'host-1',
        ]);

    $response->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('reason', 'no_entitlement_for_product');
});

it('returns license_not_valid when the license is expired or suspended', function () {
    $product = createProductWithBrand();

    $licenseKey = LicenseKey::create([
        'brand_id' => $product->brand_id,
        'key' => 'KEY-777',
        'customer_email' => 'user@example.com',
    ]);

    License::create([
        'license_key_id' => $licenseKey->id,
        'product_id' => $product->id,
        'status' => 'suspended',
        'expires_at' => Carbon::now()->subDay(),
    ]);

    $response = $this->withHeaders(['X-Product-Token' => PRODUCT_TOKEN])
        ->postJson('/api/product/activate', [
            'license_key' => 'KEY-777',
            'product_code' => $product->code,
            'instance_type' => 'machine',
            'instance_identifier' => 'machine-1',
        ]);

    $response->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('reason', 'license_not_valid');
});

it('requires a valid product token', function () {
    createProductWithBrand();

    $response = $this->postJson('/api/product/activate', [
        'license_key' => 'KEY-123',
        'product_code' => 'rankmath-pro',
        'instance_type' => 'url',
        'instance_identifier' => 'https://example.com',
    ]);

    $response->assertStatus(401)
        ->assertJsonPath('message', 'Missing X-Product-Token');

    $response = $this->withHeaders(['X-Product-Token' => 'invalid-token'])
        ->postJson('/api/product/activate', [
            'license_key' => 'KEY-123',
            'product_code' => 'rankmath-pro',
            'instance_type' => 'url',
            'instance_identifier' => 'https://example.com',
        ]);

    $response->assertStatus(401)
        ->assertJsonPath('message', 'Invalid product token');
});
