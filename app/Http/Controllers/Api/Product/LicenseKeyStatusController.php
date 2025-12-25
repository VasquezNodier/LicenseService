<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Models\LicenseKey;
use App\Support\DomainLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LicenseKeyStatusController extends Controller
{
    public function __invoke(Request $request,string $key)
    {
        $startedAt = microtime(true);
        $requestId = $request->attributes->get('request_id');
        $product = request()->attributes->get('product');
        $keyHash = hash('sha256', $key);

        DomainLog::info('license.status.requested', [
            'license_key_hash' => $keyHash,
        ]);

        try {

            $licenseKey = LicenseKey::where('key', $key)
                ->with(['licenses.product'])
                ->first();

            if (!$licenseKey) {
                DomainLog::info('license.status.rejected', [
                    'reason' => 'license_key_not_found',
                    'license_key_hash' => $keyHash,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 200,
                ]);

                return response()->json([
                    'valid' => false,
                    'reason' => 'license_key_not_found',
                    'request_id' => $requestId,
                ], 200);
            }

            if ($product && $licenseKey->brand_id !== $product->brand_id) {
                DomainLog::warning('license.status.rejected', [
                    'reason' => 'license_key_not_for_brand',
                    'license_key_id' => $licenseKey->id,
                    'license_key_brand_id' => $licenseKey->brand_id,
                    'product_brand_id' => $product->brand_id,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 403,
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

            $isValid = $entitlements->contains(fn ($e) => $e['is_valid'] === true);

            DomainLog::info('license.status.succeeded', [
                'license_key_id' => $licenseKey->id,
                'license_key_hash' => $keyHash,
                'entitlements_count' => $entitlements->count(),
                'valid' => $isValid,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 200,
            ]);

            return response()->json([
                'valid' => $entitlements->contains(fn($e) => $e['is_valid'] === true),
                'license_key' => $licenseKey->key,
                'customer_email' => $licenseKey->customer_email,
                'entitlements' => $entitlements,
                'request_id' => $requestId,
            ]);
        } catch (\Throwable $e) {
            DomainLog::error('license.status.failed', [
                'reason' => 'unhandled_exception',
                'license_key_hash' => $keyHash,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 500,
                'error_class' => get_class($e),
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
