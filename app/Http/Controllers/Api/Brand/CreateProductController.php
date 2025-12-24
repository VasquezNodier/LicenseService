<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProductRequest;
use App\Models\Product;
use Illuminate\Support\Str;

class CreateProductController extends Controller
{
    public function __invoke(CreateProductRequest $request)
    {
        $brand = $request->attributes->get('brand');

        $rawToken = 'prd_' . Str::slug($request->code, '_') . '_' . Str::random(32);
        $tokenHash = hash('sha256', $rawToken);

        $product = Product::create([
            'brand_id' => $brand->id,
            'code' => $request->code,
            'name' => $request->name,
            'product_token_hash' => $tokenHash,
        ]);

        return response()->json([
            'product_id' => $product->id,
            'brand_id' => $brand->id,
            'code' => $product->code,
            'name' => $product->name,
            'product_token' => $rawToken, // return plaintext once
        ], 201);
    }
}
