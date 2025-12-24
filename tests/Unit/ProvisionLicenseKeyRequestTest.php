<?php

// test('example', function () {
//     expect(true)->toBeTrue();
// });

use App\Http\Requests\ProvisionLicenseKeyRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

it('accepts a valid payload', function () {
    $payload = [
        'customer_email' => 'User@example.com',
        'licenses' => [
            [
                'product_code' => 'rankmath-pro',
                'expires_at' => now()->addDay()->toDateString(),
                'max_seats' => 5,
            ],
        ],
    ];

    $validator = Validator::make($payload, (new ProvisionLicenseKeyRequest())->rules());

    expect($validator->passes())->toBeTrue();
});

it('accepts payload without max_seats', function () {
    $payload = [
        'customer_email' => 'user@example.com',
        'licenses' => [
            [
                'product_code' => 'rankmath-pro',
                'expires_at' => now()->addDay()->toDateString(),
            ],
        ],
    ];

    $validator = Validator::make($payload, (new ProvisionLicenseKeyRequest())->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects invalid payloads', function (array $payload, array $expectedErrors) {
    $validator = Validator::make($payload, (new ProvisionLicenseKeyRequest())->rules());

    expect($validator->fails())->toBeTrue();

    foreach ($expectedErrors as $errorKey) {
        expect($validator->errors()->has($errorKey))->toBeTrue();
    }
})->with([
    'missing customer email' => [
        [
            'licenses' => [
                ['product_code' => 'rankmath-pro', 'expires_at' => now()->addDay()->toDateString(), 'max_seats' => 1],
            ],
        ],
        ['customer_email'],
    ],
    'invalid email and empty licenses' => [
        [
            'customer_email' => 'not-an-email',
            'licenses' => [],
        ],
        ['customer_email', 'licenses'],
    ],
    'invalid license entry' => [
        [
            'customer_email' => 'user@example.com',
            'licenses' => [
                ['product_code' => '', 'expires_at' => 'not-a-date', 'max_seats' => 0],
            ],
        ],
        ['licenses.0.product_code', 'licenses.0.expires_at', 'licenses.0.max_seats'],
    ],
    'invalid email' => [
        [
            'customer_email' => 'userexample',
            'licenses' => [
                ['product_code' => 'rankmath-pro', 'expires_at' => now()->addDay()->toDateString(), 'max_seats' => 2],
            ],
        ],
        ['customer_email'],
    ],
]);
