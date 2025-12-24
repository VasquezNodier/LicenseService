<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Models\LicenseKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LicenseKeyStatusController extends Controller
{
    public function __invoke(Request $request,string $key)
    {
        $requestId = $request->attributes->get('request_id');
        $product = request()->attributes->get('product');
        $keyHash = hash('sha256', $key);

        Log::info('license.status.started', [
            'request_id' => $requestId,
            'product_id' => $product->id ?? null,
            'product_code' => $product->code ?? null,
            'license_key_hash' => $keyHash,
        ]);

        try {

            $licenseKey = LicenseKey::where('key', $key)
                ->with(['licenses.product'])
                ->first();

            if (!$licenseKey) {
                Log::info('license.status.invalid.license_key_not_found', [
                    'request_id' => $requestId,
                    'license_key_hash' => $keyHash,
                ]);

                return response()->json([
                    'valid' => false,
                    'reason' => 'license_key_not_found',
                    'request_id' => $requestId,
                ], 200);
            }

            if ($product && $licenseKey->brand_id !== $product->brand_id) {
                Log::warning('license.status.forbidden.license_key_not_for_brand', [
                    'request_id' => $requestId,
                    'license_key_id' => $licenseKey->id,
                    'license_key_brand_id' => $licenseKey->brand_id,
                    'product_brand_id' => $product->brand_id,
                ]);

                return response()->json([
                    'valid' => false,
                    'reason' => 'license_key_not_for_brand',
                    'request_id' => $requestId,
                ], 403);
            }

            $licenses = $licenseKey->licenses;

            $entitlements = $licenses->map(fn($lic) => [
                'product_code' => $lic->product->code,
                'status' => $lic->status,
                'expires_at' => $lic->expires_at->toIso8601String(),
                'is_valid' => $lic->isValid(),
                'max_seats' => $lic->max_seats,
                'active_seats' => $lic->activeActivations()->count(),
                'remaining_seats' => $lic->remainingSeats(),
            ]);

            return response()->json([
                'valid' => $entitlements->contains(fn($e) => $e['is_valid'] === true),
                'license_key' => $licenseKey->key,
                'customer_email' => $licenseKey->customer_email,
                'entitlements' => $entitlements,
            ]);
        } catch (\Throwable $e) {
            Log::error('license.status.failed', [
                'request_id' => $requestId,
                'product_id' => $product->id ?? null,
                'license_key_hash' => $keyHash,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'valid' => false,
                'reason' => 'internal_error',
                'request_id' => $requestId,
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

}
