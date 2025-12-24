<?php

namespace App\Http\Controllers\Api\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLicenseLifecycleRequest;
use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateLicenseLifecycleController extends Controller
{
    public function __invoke(UpdateLicenseLifecycleRequest $request, int $license_id)
    {
        /** @var \App\Models\Brand $brand */
        $brand = $request->attributes->get('brand');

        $license = License::query()
            ->with(['licenseKey.brand', 'product'])
            ->whereKey($license_id)
            ->firstOrFail();

        // tenant boundary: the brand of license_key must be the same brand auth
        if ($license->licenseKey->brand_id !== $brand->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $action = $request->string('action')->toString();

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
        ]);
    }
}
