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
        $product = $request->attributes->get('product');

        if ($product && $product->code !== $request->product_code) {
            return response()->json(['valid' => false, 'reason' => 'product_token_mismatch'], 403);
        }

        $licenseKey = LicenseKey::where('key', $request->license_key)
            ->with(['licenses.product'])
            ->first();

        if (!$licenseKey) {
            return response()->json(['valid' => false, 'reason' => 'license_key_not_found'], 200);
        }

        if ($product && $licenseKey->brand_id !== $product->brand_id) {
            return response()->json(['valid' => false, 'reason' => 'license_key_not_for_brand'], 403);
        }

        $license = $licenseKey->licenses
            ->first(fn($l) => $l->product->code === $request->product_code);

        if (!$license) {
            return response()->json(['valid' => false, 'reason' => 'no_entitlement_for_product'], 200);
        }

        if (!$license->isValid()) {
            return response()->json(['valid' => false, 'reason' => 'license_not_valid'], 200);
        }

        $activation = DB::transaction(function () use ($license, $request) {
            if ($license->max_seats !== null) {
                $activeSeats = $license->activeActivations()->count();
                if ($activeSeats >= $license->max_seats) {
                    return null;
                }
            }

            return Activation::firstOrCreate(
                [
                    'license_id' => $license->id,
                    'instance_identifier' => $request->instance_identifier,
                    'revoked_at' => null,
                ],
                [
                    'instance_type' => $request->instance_type,
                    'activated_at' => now(),
                ]
            );
        });

        if ($activation === null) {
            return response()->json([
                'valid' => false,
                'reason' => 'max_seats_reached',
                'max_seats' => $license->max_seats,
            ], 200);
        }

        Log::info('license.activation', [
            'license_id' => $license->id,
            'license_key' => $licenseKey->key,
            'product_code' => $request->product_code,
            'instance_identifier' => $request->instance_identifier,
        ]);


        return response()->json([
            'valid' => true,
            'license_key' => $licenseKey->key,
            'product_code' => $request->product_code,
            'expires_at' => $license->expires_at->toIso8601String(),
            'entitlements' => $licenseKey->licenses->map(fn($lic) => [
                'product_code' => $lic->product->code,
                'status' => $lic->status,
                'expires_at' => $lic->expires_at->toIso8601String(),
                'max_seats' => $lic->max_seats,
                'active_seats' => $lic->activeActivations()->count(),
                'remaining_seats' => $lic->remainingSeats(),
            ]),
        ]);
    }

}
