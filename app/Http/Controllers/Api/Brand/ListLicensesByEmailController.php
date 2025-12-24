<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Models\LicenseKey;
use Illuminate\Http\Request;

class ListLicensesByEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        /** @var Brand $brand */
        $brand = $request->attributes->get('brand');

        if ($brand->role !== 'ecosystem_admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $email = strtolower($request->query('email',''));
        if (!$email) return response()->json(['message' => 'email is required'], 422);

        $keys = LicenseKey::with(['brand','licenses.product'])
            ->where('customer_email', $email)
            ->get();

        return response()->json([
            'customer_email' => $email,
            'license_keys' => $keys->map(fn($k) => [
                'brand' => $k->brand->name,
                'license_key' => $k->key,
                'licenses' => $k->licenses->map(fn($lic) => [
                    'product_code' => $lic->product->code,
                    'status' => $lic->status,
                    'expires_at' => $lic->expires_at->toIso8601String(),
                ]),
            ]),
        ]);
    }

}
