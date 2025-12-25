<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeactivateActivationRequest;
use App\Models\Activation;
use App\Models\License;
use App\Models\LicenseKey;
use App\Support\DomainLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeactivateActivationController extends Controller
{
    public function __invoke(DeactivateActivationRequest $request)
    {
        $startedAt = microtime(true);
        $requestId = $request->attributes->get('request_id');
        $product = $request->attributes->get('product');
        $productCode = $request->product_code;
        $licenseKeyValue = $request->license_key;
        $instance = $request->instance_identifier;

        // avoid log identifier
        $licenseKeyHash = hash('sha256', config('app.key').'|'.$licenseKeyValue);
        $instanceHash   = hash('sha256', config('app.key').'|'.$instance);

        DomainLog::info('license.activation.deactivate.requested', [
            'license_key_hash' => $licenseKeyHash,
            'product_code' => $productCode,
            'instance_type' => (string) $request->instance_type,
            'instance_identifier_hash' => $instanceHash,
        ]);

        try {

            if ($product && $product->code !== $request->product_code) {
                DomainLog::warning('license.activation.deactivate.rejected', [
                    'reason' => 'product_token_mismatch',
                    'token_product_code' => $product->code,
                    'requested_product_code' => $productCode,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 403,
                ]);

                return response()->json([
                    'deactivated' => false,
                    'reason' => 'product_token_mismatch',
                    'request_id' => $requestId,
                ], 403);
            }

            $licenseKey = LicenseKey::query()
                ->where('key', $licenseKeyValue)
                ->with(['licenses.product'])
                ->first();

            if (!$licenseKey) {
                DomainLog::info('license.activation.deactivate.rejected', [
                    'reason' => 'license_key_not_found',
                    'license_key_hash' => $licenseKeyHash,
                    'product_code' => $productCode,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 200,
                ]);

                return response()->json([
                    'deactivated' => false,
                    'reason' => 'license_key_not_found',
                    'request_id' => $requestId,
                ], 200);
            }

            if ($product && $licenseKey->brand_id !== $product->brand_id) {
                DomainLog::warning('license.activation.deactivate.rejected', [
                    'reason' => 'license_key_not_for_brand',
                    'license_key_id' => $licenseKey->id,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 403,
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
                DomainLog::info('license.activation.deactivate.rejected', [
                    'reason' => 'no_entitlement_for_product',
                    'license_key_id' => $licenseKey->id,
                    'product_code' => $productCode,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 200,
                ]);

                return response()->json([
                    'deactivated' => false,
                    'reason' => 'no_entitlement_for_product',
                    'request_id' => $requestId,
                ], 200);
            }

            $result = DB::transaction(function () use ($license, $instance) {
                $locked = License::query()
                    ->whereKey($license->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $activation = Activation::query()
                    ->where('license_id', $license->id)
                    ->where('instance_identifier', $instance)
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->first();

                if (!$activation) {
                    return ['deactivated' => false, 'activation_id' => null];
                }

                $activation->revoked_at = now();
                $activation->save();

                return ['deactivated' => true, 'activation_id' => $activation->id];
            });

            if (!$result['deactivated']) {
                DomainLog::info('license.activation.deactivate.idempotent', [
                    'reason' => 'no_active_activation',
                    'license_id' => $license->id,
                    'license_key_id' => $licenseKey->id,
                    'product_code' => $productCode,
                    'instance_identifier_hash' => $instanceHash,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 200,
                ]);

                return response()->json([
                    'deactivated' => false, // idempotent: nothing to do
                    'reason' => 'no_active_activation',
                    'request_id' => $requestId,
                ], 200);
            }

            DomainLog::info('license.activation.deactivate.succeeded', [
                'activation_id' => $result['activation_id'],
                'license_id' => $license->id,
                'license_key_id' => $licenseKey->id,
                'product_code' => $productCode,
                'instance_identifier_hash' => $instanceHash,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 200,
            ]);

            return response()->json([
                'deactivated' => true,
                'request_id' => $requestId,
            ]);
        } catch (\Throwable $e) {
            DomainLog::error('license.activation.deactivate.failed', [
                'reason' => 'unhandled_exception',
                'product_code' => $productCode,
                'license_key_hash' => $licenseKeyHash,
                'instance_identifier_hash' => $instanceHash,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 500,
                'error_class' => get_class($e),
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
