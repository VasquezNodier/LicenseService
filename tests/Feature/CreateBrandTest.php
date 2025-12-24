<?php

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeAdminBrand(): Brand
{
    return Brand::create([
        'name' => 'Ecosystem Admin',
        'api_key_hash' => hash('sha256', 'admin-token'),
        'role' => 'ecosystem_admin',
    ]);
}

it('creates a new brand when requester is ecosystem admin', function () {
    $admin = makeAdminBrand();

    $response = $this->withHeaders(['X-Brand-Key' => 'admin-token'])
        ->postJson('/api/brand/brands', [
            'name' => 'RankMath',
            'role' => 'standard',
        ]);

    $response->assertCreated()
        ->assertJsonPath('name', 'RankMath')
        ->assertJsonPath('role', 'standard')
        ->assertJsonStructure(['api_token', 'brand_id']);

    $apiToken = $response->json('api_token');

    expect($apiToken)->toStartWith('br_rankmath_');
    expect(strlen($apiToken))->toBeGreaterThan(10);

    $this->assertDatabaseHas('brands', [
        'name' => 'RankMath',
        'api_key_hash' => hash('sha256', $apiToken),
    ]);
});

it('forbids standard brands from creating new brands', function () {
    Brand::create([
        'name' => 'Standard',
        'api_key_hash' => hash('sha256', 'standard-token'),
        'role' => 'standard',
    ]);

    $response = $this->withHeaders(['X-Brand-Key' => 'standard-token'])
        ->postJson('/api/brand/brands', [
            'name' => 'NewBrand',
            'role' => 'standard',
        ]);

    $response->assertStatus(403);
});

it('validates required brand fields', function () {
    makeAdminBrand();

    $response = $this->withHeaders(['X-Brand-Key' => 'admin-token'])
        ->postJson('/api/brand/brands', []);

    $response->assertStatus(422)
        ->assertJsonStructure(['errors' => ['name', 'role']]);
});
