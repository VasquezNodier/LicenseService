<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateLicenseRequest;
use App\Models\Activation;
use App\Models\License;
use App\Models\LicenseKey;
use App\Support\DomainLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivateLicenseController extends Controller
{
    public function __invoke(ActivateLicenseRequest $request)
    {
        $startedAt = microtime(true);

        $requestId = $request->attributes->get('request_id');
        $product = $request->attributes->get('product');

        $licenseKeyValue = (string) $request->license_key;
        $licenseKeyHash  = hash('sha256', config('app.key').'|'.$licenseKeyValue);

        $productCode = (string) $request->product_code;

        DomainLog::info('license.activation.requested', [
            'license_key_hash' => $licenseKeyHash,
            'product_code' => $productCode,
            'instance_type' => (string) $request->instance_type,
        ]);

        try {
            if ($product && $product->code !== $request->product_code) {

                DomainLog::warning('license.activation.rejected', [
                    'reason' => 'product_token_mismatch',
                    'token_product_code' => $product->code,
                    'requested_product_code' => $productCode,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 403,
                ]);

                return response()->json([
                    'valid' => false,
                    'reason' => 'product_token_mismatch',
                    'request_id' => $requestId,
                ], 403);
            }

            $licenseKey = LicenseKey::query()
                ->where('key', $licenseKeyValue)
                ->with(['licenses.product'])
                ->first();

            if (!$licenseKey) {
                DomainLog::info('license.activation.rejected', [
                    'reason' => 'license_key_not_found',
                    'license_key_hash' => $licenseKeyHash,
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
                DomainLog::warning('license.activation.rejected', [
                    'reason' => 'license_key_not_for_brand',
                    'license_key_id' => $licenseKey->id,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 403,
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
                DomainLog::info('license.activation.rejected', [
                    'reason' => 'no_entitlement_for_product',
                    'license_key_id' => $licenseKey->id,
                    'product_code' => $productCode,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 200,
                ]);

                return response()->json([
                    'valid' => false,
                    'reason' => 'no_entitlement_for_product',
                    'request_id' => $requestId,
                ], 200);
            }

            if (!$license->isValid()) {
                DomainLog::info('license.activation.rejected', [
                    'reason' => 'license_not_valid',
                    'license_id' => $license->id,
                    'status' => $license->status,
                    'expires_at' => optional($license->expires_at)?->toIso8601String(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 200,
                ]);

                return response()->json([
                    'valid' => false,
                    'reason' => 'license_not_valid',
                    'request_id' => $requestId,
                ], 200);
            }

            $result  = DB::transaction(function () use ($license, $request) {
                $locked = License::query()
                    ->whereKey($license->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->max_seats !== null) {

//                    $activeSeats = $locked->activeActivations()->lockForUpdate()->count();
                    $activeSeats = $locked->activeActivations()->count();

                    if ($activeSeats >= $locked->max_seats) {
                        return ['activation' => null, 'reason' => 'max_seats_reached', 'active_seats' => $activeSeats];
                    }
                }

                $activation = Activation::firstOrCreate(
                    [
                        'license_id' => $locked->id,
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

            if ($result['activation'] === null) {
                DomainLog::info('license.activation.rejected', [
                    'reason' => 'max_seats_reached',
                    'license_id' => $license->id,
                    'max_seats' => $license->max_seats,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 200,
                ]);

                return response()->json([
                    'valid' => false,
                    'reason' => 'max_seats_reached',
                    'max_seats' => $license->max_seats,
                    'request_id' => $requestId,
                ], 200);
            }

            $activation = $result['activation'];

            DomainLog::info('license.activation.succeeded', [
                'activation_id' => $activation->id,
                'license_key_id' => $licenseKey->id,
                'license_id' => $license->id,
                'product_code' => $productCode,
                'instance_identifier_hash' => hash('sha256', config('app.key').'|'.(string) $request->instance_identifier),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 200,
            ]);

            $licenses = $licenseKey->licenses;

            return response()->json([
                'valid' => true,
                'license_key' => $licenseKey->key,
                'product_code' => $productCode,
                'expires_at' => $license->expires_at->toIso8601String(),
                'entitlements' => $licenses->map(fn ($lic) => [
                    'product_code' => $lic->product->code,
                    'status' => $lic->status,
                    'expires_at' => $lic->expires_at->toIso8601String(),
                    'max_seats' => $lic->max_seats,
                    'active_seats' => $lic->activeActivations()->count(),
                    'remaining_seats' => $lic->remainingSeats(),
                ]),
            ], 200);
        } catch (\Throwable $e) {
            DomainLog::error('license.activation.failed', [
                'reason' => 'unhandled_exception',
                'product_code' => $productCode,
                'license_key_hash' => $licenseKeyHash,
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
