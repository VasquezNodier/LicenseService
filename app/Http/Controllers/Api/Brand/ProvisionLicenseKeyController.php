<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProvisionLicenseKeyRequest;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionLicenseKeyController extends Controller
{
    public function __invoke(ProvisionLicenseKeyRequest $request)
    {
        $brand = $request->attributes->get('brand');

        return DB::transaction(function () use ($request, $brand) {

            $licenseKey = LicenseKey::firstOrCreate(
                ['brand_id' => $brand->id, 'customer_email' => strtolower($request->customer_email)],
                ['key' => strtoupper(Str::uuid()->toString())]
            );

            foreach ($request->licenses as $l) {
                $product = Product::where('brand_id', $brand->id)
                    ->where('code', $l['product_code'])
                    ->firstOrFail();

                License::updateOrCreate(
                    ['license_key_id' => $licenseKey->id, 'product_id' => $product->id],
                    [
                        'status' => 'valid', 
                        'expires_at' => Carbon::parse($l['expires_at']),
                        'max_seats' => $l['max_seats'] ?? null,
                    ]
                    
                );
            }

            $licenseKey->load('licenses.product');

            return response()->json([
                'license_key' => $licenseKey->key,
                'customer_email' => $licenseKey->customer_email,
                'licenses' => $licenseKey->licenses->map(fn($lic) => [
                    'product_code' => $lic->product->code,
                    'status' => $lic->status,
                    'expires_at' => $lic->expires_at->toIso8601String(),
                    'max_seats' => $lic->max_seats,
                ]),
            ], 201);
        });
    }
}
