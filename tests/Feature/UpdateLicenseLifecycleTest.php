<?php

use App\Models\Brand;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const BRAND_TOKEN = 'brand-secret-token';

function createBrandWithProductAndLicense(string $status = 'valid', ?Carbon $expiresAt = null): array
{
    $brand = Brand::create([
        'name' => 'RankMath',
        'api_key_hash' => hash('sha256', BRAND_TOKEN),
        'role' => 'standard',
    ]);

    $product = Product::create([
        'brand_id' => $brand->id,
        'code' => 'rankmath-pro',
        'name' => 'RankMath Pro',
        'product_token_hash' => hash('sha256', 'token'),
    ]);

    $licenseKey = LicenseKey::create([
        'brand_id' => $brand->id,
        'key' => 'KEY-123',
        'customer_email' => 'user@example.com',
    ]);

    $license = License::create([
        'license_key_id' => $licenseKey->id,
        'product_id' => $product->id,
        'status' => $status,
        'expires_at' => $expiresAt ?? Carbon::now()->addDays(10),
    ]);

    return [$brand, $product, $license];
}

it('renews a license updating expiration date', function () {
    [, $product, $license] = createBrandWithProductAndLicense();

    $newExpiry = Carbon::now()->addMonths(3);

    $response = $this->withHeaders(['X-Brand-Key' => BRAND_TOKEN])
        ->patchJson("/api/brand/licenses/{$license->id}", [
            'action' => 'renew',
            'expires_at' => $newExpiry->toIso8601String(),
        ]);

    $response->assertOk()
        ->assertJsonPath('license_id', $license->id)
        ->assertJsonPath('product_code', $product->code)
        ->assertJsonPath('status', 'valid');

    expect(Carbon::parse($response->json('expires_at'))->isSameSecond($newExpiry))->toBeTrue();

    $license->refresh();
    expect($license->expires_at->isSameSecond($newExpiry))->toBeTrue();
});

it('suspends and resumes a license', function () {
    [, $product, $license] = createBrandWithProductAndLicense();

    $suspendResponse = $this->withHeaders(['X-Brand-Key' => BRAND_TOKEN])
        ->patchJson("/api/brand/licenses/{$license->id}", ['action' => 'suspend']);

    $suspendResponse->assertOk()
        ->assertJsonPath('status', 'suspended');

    $resumeResponse = $this->withHeaders(['X-Brand-Key' => BRAND_TOKEN])
        ->patchJson("/api/brand/licenses/{$license->id}", ['action' => 'resume']);

    $resumeResponse->assertOk()
        ->assertJsonPath('status', 'valid');
});

it('cancels a license', function () {
    [, $product, $license] = createBrandWithProductAndLicense();

    $response = $this->withHeaders(['X-Brand-Key' => BRAND_TOKEN])
        ->patchJson("/api/brand/licenses/{$license->id}", ['action' => 'cancel']);

    $response->assertOk()
        ->assertJsonPath('status', 'cancelled')
        ->assertJsonPath('product_code', $product->code);
});

it('returns forbidden when license does not belong to brand', function () {
    [, , $license] = createBrandWithProductAndLicense();

    $otherBrand = Brand::create([
        'name' => 'OtherBrand',
        'api_key_hash' => hash('sha256', 'other-token'),
        'role' => 'standard',
    ]);

    $response = $this->withHeaders(['X-Brand-Key' => 'other-token'])
        ->patchJson("/api/brand/licenses/{$license->id}", ['action' => 'suspend']);

    $response->assertStatus(403)
        ->assertJsonPath('message', 'Forbidden');
});

it('cannot renew a cancelled license', function () {
    [, , $license] = createBrandWithProductAndLicense(status: 'cancelled');

    $newExpiry = Carbon::now()->addMonth();

    $response = $this->withHeaders(['X-Brand-Key' => BRAND_TOKEN])
        ->patchJson("/api/brand/licenses/{$license->id}", [
            'action' => 'renew',
            'expires_at' => $newExpiry->toIso8601String(),
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Cannot renew a cancelled license');
});
