<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeactivateActivationRequest;
use App\Models\Activation;
use App\Models\LicenseKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeactivateActivationController extends Controller
{
    public function __invoke(DeactivateActivationRequest $request)
    {
        $requestId = $request->attributes->get('request_id');
        $product = $request->attributes->get('product');
        $productCode = $request->product_code;
        $licenseKeyValue = $request->license_key;
        $instance = $request->instance_identifier;

        // avoid log identifier
        $instanceHash = hash('sha256', (string) $instance);

        Log::info('license.deactivate.started', [
            'request_id' => $requestId,
            'product_id' => $product->id ?? null,
            'product_code' => $productCode,
            'instance_type' => $request->instance_type ?? null,
            'instance_hash' => $instanceHash,
        ]);

        try {

            if ($product && $product->code !== $request->product_code) {
                Log::warning('license.deactivate.forbidden.product_token_mismatch', [
                    'request_id' => $requestId,
                    'product_id' => $product->id,
                    'token_product_code' => $product->code,
                    'requested_product_code' => $productCode,
                ]);

                return response()->json([
                    'deactivated' => false,
                    'reason' => 'product_token_mismatch',
                    'request_id' => $requestId,
                ], 403);
            }

            $licenseKey = LicenseKey::where('key', $licenseKeyValue)
                ->with(['licenses.product'])
                ->first();

            if (!$licenseKey) {
                Log::info('license.deactivate.invalid.license_key_not_found', [
                    'request_id' => $requestId,
                    'product_code' => $productCode,
                ]);

                return response()->json([
                    'deactivated' => false,
                    'reason' => 'license_key_not_found',
                    'request_id' => $requestId,
                ], 200);
            }

            if ($product && $licenseKey->brand_id !== $product->brand_id) {
                Log::warning('license.deactivate.forbidden.license_key_not_for_brand', [
                    'request_id' => $requestId,
                    'license_key_id' => $licenseKey->id,
                    'license_key_brand_id' => $licenseKey->brand_id,
                    'product_brand_id' => $product->brand_id,
                ]);

                return response()->json([
                    'deactivated' => false,
                    'reason' => 'license_key_not_for_brand',
                    'request_id' => $requestId,
                ], 403);
            }

            $license = $licenseKey->licenses
                ->first(fn($l) => $l->product->code === $request->product_code);

            if (!$license) {
                Log::info('license.deactivate.invalid.no_entitlement_for_product', [
                    'request_id' => $requestId,
                    'license_key_id' => $licenseKey->id,
                    'product_code' => $productCode,
                ]);

                return response()->json([
                    'deactivated' => false,
                    'reason' => 'no_entitlement_for_product',
                    'request_id' => $requestId,
                ], 200);
            }

            $deactivated = DB::transaction(function () use ($license, $instance) {
                $activation = Activation::query()
                    ->where('license_id', $license->id)
                    ->where('instance_identifier', $instance)
                    ->whereNull('revoked_at')
//                    ->lockForUpdate()
                    ->first();

                if (!$activation) {
                    return false; // idempotent
                }

                $activation->revoked_at = now();
                $activation->save();

                return true;
            });

            if ($deactivated) {
                Log::info('license.deactivation', [
                    'license_id' => $license->id,
                    'license_key' => $licenseKey->key,
                    'product_code' => $request->product_code,
                    'instance_identifier' => $instance,
                ]);
            } else {
                Log::info('license.deactivate.idempotent.no_active_activation', [
                    'request_id' => $requestId,
                    'license_id' => $license->id,
                    'product_code' => $productCode,
                    'instance_hash' => $instanceHash,
                ]);
            }

            return response()->json([
                'deactivated' => $deactivated,
                'license_key' => $licenseKey->key,
                'product_code' => $request->product_code,
                'instance_identifier' => $instance,
                'request_id' => $requestId,
            ]);
        } catch (\Throwable $e) {
            Log::error('license.deactivate.failed', [
                'request_id' => $requestId,
                'product_id' => $product->id ?? null,
                'product_code' => $productCode,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'deactivated' => false,
                'reason' => 'internal_error',
                'request_id' => $requestId,
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }
}
