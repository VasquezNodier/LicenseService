<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProductRequest;
use App\Models\Product;
use App\Support\DomainLog;
use Illuminate\Support\Str;
use Nette\Schema\ValidationException;

class CreateProductController extends Controller
{
    public function __invoke(CreateProductRequest $request)
    {
        $startedAt = microtime(true);

        $requestId = $request->attributes->get('request_id');
        $brand = $request->attributes->get('brand');
        $code = $request->code;
        $name = $request->name;

        DomainLog::info('product.create.requested', [
            'product_code' => $code,
            'product_name' => $name,
        ]);

        try {
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
        } catch (\Throwable $e) {

            DomainLog::error('product.create.failed', [
                'reason' => 'unhandled_exception',
                'product_code' => $code,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 500,
                'error_class' => get_class($e),
            ]);

            return response()->json([
                'message' => 'Internal error creating product',
                'request_id' => $requestId,
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }
}
