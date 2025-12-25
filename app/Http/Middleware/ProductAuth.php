<?php

namespace App\Http\Middleware;

use App\Models\Product;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ProductAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Product-Token');
        if (!$token) {
            return response()->json(['message' => 'Missing X-Product-Token'], 401);
        }

        $hash = hash('sha256', $token);
        $product = Product::with('brand')
            ->where('product_token_hash', $hash)
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Invalid product token'], 401);
        }

        $request->attributes->set('product', $product);

        Log::shareContext([
            'tenant' => [
                'type' => 'product',
                'id' => $product->id,
            ],
        ]);

        return $next($request);
    }
}
