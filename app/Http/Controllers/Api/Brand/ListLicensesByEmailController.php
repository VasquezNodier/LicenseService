<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListLicensesByEmailRequest;
use App\Models\LicenseKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ListLicensesByEmailController extends Controller
{
    public function __invoke(ListLicensesByEmailRequest $request)
    {
        $requestId = $request->attributes->get('request_id');
        $brand = $request->attributes->get('brand');
        $email = $request->validated('email');

        Log::info('List licenses by email started', [
            'request_id' => $requestId,
            'brand_id' => $brand->id,
            'email' => $email,
        ]);

        if ($brand->role !== 'ecosystem_admin') {
            Log::warning('List licenses by email forbidden', [
                'request_id' => $requestId,
                'brand_id' => $brand->id ?? null,
                'role' => $brand->role ?? null,
            ]);
            return response()->json(['message' => 'Forbidden'], 403);
        }

        try {
            $keys = LicenseKey::query()
                ->with(['brand', 'licenses.product'])
                ->where('customer_email', $email)
                ->get();

            return response()->json([
                'customer_email' => $email,
                'license_keys' => $keys->map(fn ($k) => [
                    'brand' => $k->brand?->name, // null-safe
                    'license_key' => $k->key,
                    'licenses' => $k->licenses->map(fn ($lic) => [
                        'product_code' => $lic->product->code,
                        'status' => $lic->status,
                        'expires_at' => $lic->expires_at->toIso8601String(),
                        'max_seats' => $lic->max_seats,
                    ]),
                ]),
                'request_id' => $requestId,
            ]);

        } catch (\Throwable $e) {

            Log::error('List licenses by email failed', [
                'request_id' => $requestId,
                'brand_id' => $brand->id ?? null,
                'email' => $email,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Internal error fetching licenses',
                'request_id' => $requestId,
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

}
