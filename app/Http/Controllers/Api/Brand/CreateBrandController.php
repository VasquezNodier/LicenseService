<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBrandRequest;
use App\Models\Brand;
use Illuminate\Support\Str;

class CreateBrandController extends Controller
{
    public function __invoke(CreateBrandRequest $request)
    {
        /** @var Brand $requestingBrand */
        $requestingBrand = $request->attributes->get('brand');

        if ($requestingBrand->role !== 'ecosystem_admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rawToken = 'br_' . Str::slug($request->name, '_') . '_' . Str::random(32);
        $apiKeyHash = hash('sha256', $rawToken);

        $brand = Brand::create([
            'name' => $request->name,
            'api_key_hash' => $apiKeyHash,
            'role' => $request->role,
        ]);

        return response()->json([
            'brand_id' => $brand->id,
            'name' => $brand->name,
            'role' => $brand->role,
            'message' => 'Important: Keep this api_token safe as it will not be shown again. Save it on .env or your secrets manager as RANKMATH_BR_TOKEN, WP_ROCKET_BR_TOKEN, etc. based on the brand you created.',
            'api_token' => $rawToken,
        ], 201);
    }
}
