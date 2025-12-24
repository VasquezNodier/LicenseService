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
        /** @var \App\Models\Product $product */
        $product = $request->attributes->get('product');

        if ($product && $product->code !== $request->product_code) {
            return response()->json(['deactivated' => false, 'reason' => 'product_token_mismatch'], 403);
        }

        $licenseKey = LicenseKey::where('key', $request->license_key)
            ->with(['licenses.product'])
            ->first();

        if (!$licenseKey) {
            return response()->json(['deactivated' => false, 'reason' => 'license_key_not_found'], 200);
        }

        if ($product && $licenseKey->brand_id !== $product->brand_id) {
            return response()->json(['deactivated' => false, 'reason' => 'license_key_not_for_brand'], 403);
        }

        $license = $licenseKey->licenses
            ->first(fn($l) => $l->product->code === $request->product_code);

        if (!$license) {
            return response()->json(['deactivated' => false, 'reason' => 'no_entitlement_for_product'], 200);
        }

        $instance = $request->instance_identifier;

        $deactivated = DB::transaction(function () use ($license, $instance) {
            $activation = Activation::query()
                ->where('license_id', $license->id)
                ->where('instance_identifier', $instance)
                ->whereNull('revoked_at')
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
        }

        return response()->json([
            'deactivated' => $deactivated,
            'license_key' => $licenseKey->key,
            'product_code' => $request->product_code,
            'instance_identifier' => $instance,
        ]);
    }
}
