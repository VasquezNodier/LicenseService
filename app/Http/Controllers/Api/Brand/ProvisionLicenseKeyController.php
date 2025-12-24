<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProvisionLicenseKeyRequest;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProvisionLicenseKeyController extends Controller
{
    public function __invoke(ProvisionLicenseKeyRequest $request)
    {

        $requestId = $request->attributes->get('request_id');
        $brand = $request->attributes->get('brand');

        Log::info('Provision license request started', [
            'request_id' => $requestId,
            'brand_id' => $brand->id,
            'customer_email' => $request->customer_email,
            'licenses_count' => count($request->licenses),
        ]);

        try {
            return DB::transaction(function () use ($request, $brand, $requestId) {

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
        } catch (HttpException $e) {

            throw $e;

        } catch (ModelNotFoundException $e) {
            Log::warning('Product not found', [
                'request_id' => $requestId,
                'brand_id' => $brand->id ?? null,
                'model' => $e->getModel(),
            ]);

            return response()->json([
                'message' => 'Validation error',
                'errors' => [
                    'licenses' => ['One or more product_code are invalid for this brand.'],
                ],
                'request_id' => $requestId,
            ], 422);
        } catch (\Throwable $e) {

            Log::error('Provision license failed', [
                'request_id' => $requestId,
                'brand_id' => $brand->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Internal error provisioning license',
                'error' => app()->isLocal() ? $e->getMessage() : null,
                'request_id' => $requestId,
            ], 500);
        }
    }
}
