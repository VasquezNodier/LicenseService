<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'brand.auth' => \App\Http\Middleware\BrandAuth::class,
            'product.auth' => \App\Http\Middleware\ProductAuth::class,
        ]);
        $middleware->append(\App\Http\Middleware\RequestLogContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e, $request) {

            $requestId = $request->attributes->get('request_id');

            \Illuminate\Support\Facades\Log::warning('request.validation_failed', [
                'request_id' => $requestId,
                'http_context' => [
                    'http_method' => $request->method(),
                    'http_path' => $request->path(),
                ],
                'event' => 'request.validation_failed',
                'brand_id' => optional($request->attributes->get('brand'))->id,
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors(),
                'request_id' => $requestId,
            ], 422);
        });
    })->create();
