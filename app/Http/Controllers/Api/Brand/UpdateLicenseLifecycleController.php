<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLicenseLifecycleRequest;
use App\Models\License;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UpdateLicenseLifecycleController extends Controller
{
    public function __invoke(UpdateLicenseLifecycleRequest $request, int $license_id)
    {
        $requestId = $request->attributes->get('request_id');
        $brand = $request->attributes->get('brand');
        $action = $request->string('action')->toString();

        Log::info('Update license lifecycle started', [
            'request_id' => $requestId,
            'brand_id' => $brand->id ?? null,
            'license_id' => $license_id,
            'action' => $action,
        ]);

        try {

            $license = License::query()
                ->with(['licenseKey.brand', 'product'])
                ->whereKey($license_id)
                ->firstOrFail();

            // tenant boundary: the brand of license_key must be the same brand auth
            if ($license->licenseKey->brand_id !== $brand->id) {
                Log::warning('Update license lifecycle forbidden (tenant boundary)', [
                    'request_id' => $requestId,
                    'brand_id' => $brand->id ?? null,
                    'license_id' => $license_id,
                    'license_brand_id' => $license->licenseKey->brand_id ?? null,
                    'action' => $action,
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

            return response()->json([
                'license_id' => $license->id,
                'product_code' => $license->product->code,
                'status' => $license->status,
                'expires_at' => $license->expires_at->toIso8601String(),
                'max_seats' => $license->max_seats,
                'request_id' => $requestId,
            ]);
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
            // acción inválida u otros errores de validación "esperables"
            Log::warning('Update license lifecycle validation failed', [
                'request_id' => $requestId,
                'brand_id' => $brand->id ?? null,
                'license_id' => $license_id,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
                'request_id' => $requestId,
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Update license lifecycle failed', [
                'request_id' => $requestId,
                'brand_id' => $brand->id ?? null,
                'license_id' => $license_id,
                'action' => $action,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Internal error updating license',
                'request_id' => $requestId,
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
    }
}
