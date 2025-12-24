<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateLicenseRequest;
use App\Models\Activation;
use App\Models\LicenseKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivateLicenseController extends Controller
{
    public function __invoke(ActivateLicenseRequest $request)
    {
        $licenseKey = LicenseKey::where('key', $request->license_key)
            ->with(['licenses.product'])
            ->first();

        if (!$licenseKey) {
            return response()->json(['valid' => false, 'reason' => 'license_key_not_found'], 200);
        }

        $license = $licenseKey->licenses
            ->first(fn($l) => $l->product->code === $request->product_code);

        if (!$license) {
            return response()->json(['valid' => false, 'reason' => 'no_entitlement_for_product'], 200);
        }

        if (!$license->isValid()) {
            return response()->json(['valid' => false, 'reason' => 'license_not_valid'], 200);
        }

        DB::transaction(function () use ($license, $request) {
            Activation::firstOrCreate(
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

        return response()->json([
            'valid' => true,
            'license_key' => $licenseKey->key,
            'product_code' => $request->product_code,
            'expires_at' => $license->expires_at->toIso8601String(),
            'entitlements' => $licenseKey->licenses->map(fn($lic) => [
                'product_code' => $lic->product->code,
                'status' => $lic->status,
                'expires_at' => $lic->expires_at->toIso8601String(),
            ]),
        ]);
    }

}
