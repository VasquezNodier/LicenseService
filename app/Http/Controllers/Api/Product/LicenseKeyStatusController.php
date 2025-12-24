<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Models\LicenseKey;
use Illuminate\Http\Request;

class LicenseKeyStatusController extends Controller
{
    public function __invoke(string $key)
    {
        $product = request()->attributes->get('product');
        
        $licenseKey = LicenseKey::where('key', $key)
            ->with(['licenses.product'])
            ->first();

        if (!$licenseKey) {
            return response()->json(['valid' => false, 'reason' => 'license_key_not_found'], 200);
        }

        if ($product && $licenseKey->brand_id !== $product->brand_id) {
            return response()->json(['valid' => false, 'reason' => 'license_key_not_for_brand'], 403);
        }

        $entitlements = $licenseKey->licenses->map(fn($lic) => [
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
    }

}
