<?php

namespace App\Http\Middleware;

use App\Models\Brand;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BrandAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Brand-Key');

        if(!$key) return response()->json(['message' => 'Missing X-Brand-Key'], 401);

        $hashedKey = hash('sha256', $key);
        $keyHashPrefix = substr($hashedKey, 0, 8);

        $brand = Brand::where('api_key_hash', $hashedKey)->first();

        if (!$brand) {
            return response()->json(['message' => 'Invalid brand key'], 401);
        }

        $request->attributes->set('brand', $brand);

        Log::withContext([
            'tenant' => [
                'type' => 'brand',
                'id' => $brand->id,
                'key_hash_prefix' => $keyHashPrefix,
            ],
        ]);

        return $next($request);
    }
}
