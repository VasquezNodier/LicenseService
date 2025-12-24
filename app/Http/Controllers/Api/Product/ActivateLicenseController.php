<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateLicenseRequest;
use App\Models\Activation;
use App\Models\LicenseKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivateLicenseController extends Controller
{
    public function __invoke(ActivateLicenseRequest $request)
    {
        $requestId = $request->attributes->get('request_id');
        $product = $request->attributes->get('product');
        $licenseKeyValue = $request->license_key;
        $productCode = $request->product_code;

        Log::info('License activation started', [
            'request_id' => $requestId,
            'product_id' => $product->id ?? null,
            'product_code' => $productCode,
            'instance_type' => $request->instance_type,
        ]);

        try {
            if ($product && $product->code !== $request->product_code) {
                Log::warning('License activation forbidden due to product_token_mismatch', [
                    'request_id' => $requestId,
                    'product_id' => $product->id,
                    'token_product_code' => $product->code,
                    'requested_product_code' => $productCode,
                ]);

                return response()->json([
                    'valid' => false,
                    'reason' => 'product_token_mismatch',
                    'request_id' => $requestId,
                ], 403);
            }

            $licenseKey = LicenseKey::where('key', $licenseKeyValue)
                ->with(['licenses.product'])
                ->first();

            if (!$licenseKey) {
                return response()->json([
                    'valid' => false,
                    'reason' => 'license_key_not_found',
                    'request_id' => $requestId,
                ], 200);
            }

            if ($product && $licenseKey->brand_id !== $product->brand_id) {
                Log::warning('License activation forbidden due to license key not for brand', [
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

            $license = $licenseKey->licenses
                ->first(fn($l) => $l->product->code === $request->product_code);

            if (!$license) {
                Log::info('License activation invalid due to no entitlement for product', [
                    'request_id' => $requestId,
                    'license_key_id' => $licenseKey->id,
                    'product_code' => $productCode,
                ]);

                return response()->json([
                    'valid' => false,
                    'reason' => 'no entitlement for product',
                    'request_id' => $requestId,
                ], 200);
            }

            if (!$license->isValid()) {
                Log::info('License activation invalid due to license not valid', [
                    'request_id' => $requestId,
                    'license_id' => $license->id,
                    'status' => $license->status,
                    'expires_at' => optional($license->expires_at)?->toIso8601String(),
                ]);

                return response()->json([
                    'valid' => false,
                    'reason' => 'license not valid',
                    'request_id' => $requestId,
                ], 200);
            }

            $activation = DB::transaction(function () use ($license, $request) {
                $lockedLicense = \App\Models\License::whereKey($license->id)->lockForUpdate()->first();

                if ($lockedLicense->max_seats !== null) {
                    $activeSeats = $lockedLicense->activeActivations()->lockForUpdate()->count();
                    if ($activeSeats >= $license->max_seats) {
                        return ['activation' => null, 'reason' => 'max_seats_reached'];
                    }
                }

                $activation = Activation::firstOrCreate(
                    [
                        'license_id' => $lockedLicense->id,
                        'instance_identifier' => $request->instance_identifier,
                        'revoked_at' => null,
                    ],
                    [
                        'instance_type' => $request->instance_type,
                        'activated_at' => now(),
                    ]
                );

                return ['activation' => $activation, 'reason' => null];
            });

            if ($activation === null) {
                $reason = $activation['reason'] ?? 'max_seats_reached';

                Log::info('License activation invalid.' . $reason, [
                    'request_id' => $requestId,
                    'license_id' => $license->id,
                    'max_seats' => $license->max_seats,
                ]);

                return response()->json([
                    'valid' => false,
                    'reason' => 'max_seats_reached',
                    'max_seats' => $license->max_seats,
                    'request_id' => $requestId,
                ], 200);
            }

            Log::info('license activation', [
                'license_id' => $license->id,
                'license_key' => $licenseKey->key,
                'product_code' => $request->product_code,
                'instance_identifier' => $request->instance_identifier,
                'request_id' => $requestId,
            ]);

            $licenses = $licenseKey->licenses;

            return response()->json([
                'valid' => true,
                'license_key' => $licenseKey->key,
                'product_code' => $request->product_code,
                'expires_at' => $license->expires_at->toIso8601String(),
                'entitlements' => $licenses->map(fn($lic) => [
                    'product_code' => $lic->product->code,
                    'status' => $lic->status,
                    'expires_at' => $lic->expires_at->toIso8601String(),
                    'max_seats' => $lic->max_seats,
                    'active_seats' => $lic->activeActivations()->count(),
                    'remaining_seats' => $lic->remainingSeats(),
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('License activation failed', [
                'request_id' => $requestId,
                'product_id' => $product->id ?? null,
                'product_code' => $productCode,
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
