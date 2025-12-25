<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListLicensesByEmailRequest;
use App\Models\LicenseKey;
use App\Support\DomainLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ListLicensesByEmailController extends Controller
{
    public function __invoke(ListLicensesByEmailRequest $request)
    {
        $startedAt = microtime(true);

        $requestId = $request->attributes->get('request_id');
        $brand = $request->attributes->get('brand');
        $email = strtolower((string) $request->validated('email'));
        $emailHash = hash('sha256', config('app.key').'|'.$email);

        DomainLog::info('license.list_by_email.requested', [
            'customer_email_hash' => $emailHash,
        ]);

        if ($brand->role !== 'ecosystem_admin') {
            DomainLog::warning('license.list_by_email.rejected', [
                'result' => 'warning',
                'reason' => 'forbidden_role',
                'role' => $brand->role ?? null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 403,
            ]);
            return response()->json(['message' => 'Forbidden', 'request_id' => $requestId,], 403);
        }

        try {
            $keys = LicenseKey::query()
                ->with(['brand', 'licenses.product'])
                ->where('customer_email', $email)
                ->get();

            DomainLog::info('license.list_by_email.succeeded', [
                'result' => 'success',
                'customer_email_hash' => $emailHash,
                'license_keys_count' => $keys->count(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 200,
            ]);

            return response()->json([
                'customer_email' => $email,
                'license_keys' => $keys->map(fn ($k) => [
                    'brand' => $k->brand?->name, // null-safe
                    'license_key' => $k->key,
                    'licenses' => $k->licenses->map(fn ($lic) => [
                        'product_code' => $lic->product->code,
                        'status' => $lic->status,
                        'expires_at' => $lic->expires_at->toIso8601String(),
                        'max_seats' => $lic->max_seats,
                    ]),
                ]),
                'request_id' => $requestId,
            ]);

        } catch (\Throwable $e) {

            DomainLog::error('license.list_by_email.failed', [
                'result' => 'error',
                'reason' => 'unhandled_exception',
                'customer_email_hash' => $emailHash,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 500,
                'error_class' => get_class($e),
            ]);

            return response()->json([
                'message' => 'Internal error fetching licenses',
                'request_id' => $requestId,
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }

}
