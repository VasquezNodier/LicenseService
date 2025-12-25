<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProvisionLicenseKeyRequest;
use App\Models\License;
use App\Models\LicenseKey;
use App\Models\Product;
use App\Support\DomainLog;
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

        $startedAt = microtime(true);

        $requestId = $request->attributes->get('request_id');
        $brand = $request->attributes->get('brand');

        $customerEmail = strtolower((string) $request->customer_email);
        $emailHash = hash('sha256', config('app.key').'|'.$customerEmail);
        $productCodes = collect($request->licenses)->pluck('product_code')->values()->all();

        DomainLog::info('license.provision.requested', [
            'customer_email_hash' => $emailHash,
            'licenses_count' => count($request->licenses),
            'product_codes' => $productCodes,
        ]);

        try {
            $response = DB::transaction(function () use ($request, $brand, $requestId, $emailHash, $productCodes) {

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

            DomainLog::info('license.provision.succeeded', [
                'result' => 'success',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => $response->status(),
            ]);

            return $response;

        } catch (ModelNotFoundException $e) {
            DomainLog::warning('license.provision.rejected', [
                'result' => 'error',
                'reason' => 'product_not_found_for_brand',
                'customer_email_hash' => $emailHash,
                'product_codes' => $productCodes,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'model' => $e->getModel(),
                'error_class' => get_class($e),
                'http_status' => 422,
            ]);

            return response()->json([
                'message' => 'Validation error',
                'errors' => [
                    'licenses' => ['One or more product_code are invalid for this brand.'],
                ],
                'request_id' => $requestId,
            ], 422);
        } catch (\Throwable $e) {

            DomainLog::error('license.provision.failed', [
                'result' => 'error',
                'reason' => 'unhandled_exception',
                'customer_email_hash' => $emailHash,
                'product_codes' => $productCodes,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 500,
                'error_class' => get_class($e),
            ]);

            return response()->json([
                'message' => 'Internal error provisioning license',
                'error' => app()->isLocal() ? $e->getMessage() : null,
                'request_id' => $requestId,
            ], 500);
        }
    }
}
