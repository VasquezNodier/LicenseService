<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLicenseLifecycleRequest;
use App\Models\License;
use App\Support\DomainLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UpdateLicenseLifecycleController extends Controller
{
    public function __invoke(UpdateLicenseLifecycleRequest $request, int $license_id)
    {
        $startedAt = microtime(true);
        $requestId = $request->attributes->get('request_id');
        $brand = $request->attributes->get('brand');
        $action = $request->string('action')->toString();
        $expiresAt = $request->date('expires_at');

        DomainLog::info('license.lifecycle.update.requested', [
            'license_id' => $license_id,
            'action' => $action,
            'expires_at' => $expiresAt?->toIso8601String(),
        ]);

        try {

            $license = License::query()
                ->with(['licenseKey.brand', 'product'])
                ->whereKey($license_id)
                ->firstOrFail();

            // tenant boundary: the brand of license_key must be the same brand auth
            if ($license->licenseKey->brand_id !== $brand->id) {
                DomainLog::warning('license.lifecycle.update.rejected', [
                    'reason' => 'tenant_boundary_violation',
                    'license_id' => $license_id,
                    'action' => $action,
                    'license_brand_id' => $license->licenseKey?->brand_id,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'http_status' => 403,
                ]);

                return response()->json([
                    'message' => 'Forbidden',
                    'request_id' => $requestId,
                ], 403);
            }

            DB::transaction(function () use ($license, $action, $request) {
                match ($action) {
                    'renew' => $license->renew($request->date('expires_at')),
                    'suspend' => $license->suspend(),
                    'resume' => $license->resume(),
                    'cancel' => $license->cancel(),
                    default => null
                };
            });

            $license->refresh();

            DomainLog::info('license.lifecycle.update.succeeded', [
                'license_id' => $license->id,
                'action' => $action,
                'status' => $license->status,
                'expires_at' => $license->expires_at?->toIso8601String(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 200,
            ]);

            return response()->json([
                'license_id' => $license->id,
                'product_code' => $license->product->code,
                'status' => $license->status,
                'expires_at' => $license->expires_at->toIso8601String(),
                'max_seats' => $license->max_seats,
                'request_id' => $requestId,
            ], 200);

        } catch (ModelNotFoundException $e) {
            Log::warning('License not found', [
                'request_id' => $requestId,
                'brand_id' => $brand->id ?? null,
                'license_id' => $license_id,
            ]);

            return response()->json([
                'message' => 'License not found',
                'request_id' => $requestId,
            ], 404);

        } catch (ValidationException $e) {

            DomainLog::warning('license.lifecycle.update.rejected', [
                'reason' => 'license_not_found',
                'license_id' => $license_id,
                'action' => $action,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 404,
            ]);

            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
                'request_id' => $requestId,
            ], 422);

        } catch (\Throwable $e) {
            DomainLog::error('license.lifecycle.update.failed', [
                'reason' => 'unhandled_exception',
                'license_id' => $license_id,
                'action' => $action,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'http_status' => 500,
                'error_class' => get_class($e),
            ]);

            return response()->json([
                'message' => 'Internal error updating license',
                'request_id' => $requestId,
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }
}
