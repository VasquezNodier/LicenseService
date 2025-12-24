<?php

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const BRAND_PRODUCT_TOKEN = 'brand-product-token';

function makeBrandForProducts(): Brand
{
    return Brand::create([
        'name' => 'RankMath',
        'api_key_hash' => hash('sha256', BRAND_PRODUCT_TOKEN),
        'role' => 'standard',
    ]);
}

it('creates a product for the authenticated brand and returns plaintext token', function () {
    $brand = makeBrandForProducts();

    $response = $this->withHeaders(['X-Brand-Key' => BRAND_PRODUCT_TOKEN])
        ->postJson('/api/brand/products', [
            'code' => 'rankmath-pro',
            'name' => 'RankMath Pro',
        ]);

    $response->assertCreated()
        ->assertJsonPath('brand_id', $brand->id)
        ->assertJsonPath('code', 'rankmath-pro')
        ->assertJsonPath('name', 'RankMath Pro')
        ->assertJsonStructure(['product_token']);

    $token = $response->json('product_token');
    expect($token)->toStartWith('prd_rankmath_pro_');

    $product = Product::where('code', 'rankmath-pro')->firstOrFail();
    expect($product->brand_id)->toBe($brand->id);
    expect($product->product_token_hash)->toBe(hash('sha256', $token));
});

it('validates required product fields', function () {
    makeBrandForProducts();

    $response = $this->withHeaders(['X-Brand-Key' => BRAND_PRODUCT_TOKEN])
        ->postJson('/api/brand/products', []);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['code', 'name']]);
});
