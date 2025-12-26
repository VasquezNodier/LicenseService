<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBrandRequest;
use App\Models\Brand;
use App\Support\DomainLog;
use Illuminate\Support\Str;

class CreateBrandController extends Controller
{
    public function __invoke(CreateBrandRequest $request)
    {

        $startedAt = microtime(true);
        $requestId = $request->attributes->get('request_id');
        $requestingBrand = $request->attributes->get('brand');

        $name = $request->name;
        $role = $request->role;

        DomainLog::info('brand.create.requested', [
            'brand_name' => $name,
            'role' => $role,
        ]);

        try {

            if ($requestingBrand->role !== 'ecosystem_admin') {

                DomainLog::warning('brand.create.rejected', [
                    'reason' => 'forbidden_role',
                    'requester_role' => $requestingBrand->role ?? null,
                    'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 403,
                ]);

                return response()->json(['message' => 'Forbidden'], 403);
            }

            $rawToken = 'br_' . Str::slug($request->name, '_') . '_' . Str::random(32);
            $apiKeyHash = hash('sha256', $rawToken);

            $brand = Brand::create([
                'name' => $request->name,
                'api_key_hash' => $apiKeyHash,
                'role' => $request->role,
            ]);

            DomainLog::info('brand.create.succeeded', [
                'created_brand_id' => $brand->id,
                'created_brand_role' => $brand->role,
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
                'http_status' => 201,
            ]);

            return response()->json([
                'brand_id' => $brand->id,
                'name' => $brand->name,
                'role' => $brand->role,
                'message' => 'Important: Keep this api_token safe as it will not be shown again. Save it on .env or your secrets manager as RANKMATH_BR_TOKEN, WP_ROCKET_BR_TOKEN, etc. based on the brand you created.',
                'api_token' => $rawToken,
                'request_id' => $requestId,
            ], 201);
        } catch (\Throwable $e) {

            DomainLog::error('brand.create.failed', [
                'reason' => 'unhandled_exception',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 500,
                'error_class' => get_class($e),
            ]);

            return response()->json([
                'message' => 'Internal error creating brand',
                'request_id' => $requestId,
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }

    }
}
