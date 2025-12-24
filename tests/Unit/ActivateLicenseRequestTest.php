<?php

use App\Http\Requests\ActivateLicenseRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

it('accepts a valid activation payload', function () {
    $payload = [
        'license_key' => 'KEY-123',
        'product_code' => 'rankmath-pro',
        'instance_type' => 'url',
        'instance_identifier' => 'https://example.com',
    ];

    $validator = Validator::make($payload, (new ActivateLicenseRequest())->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects invalid activation payloads', function (array $payload, array $errors) {
    $validator = Validator::make($payload, (new ActivateLicenseRequest())->rules());

    expect($validator->fails())->toBeTrue();

    foreach ($errors as $key) {
        expect($validator->errors()->has($key))->toBeTrue();
    }
})->with([
    'missing fields' => [
        [],
        ['license_key', 'product_code', 'instance_type', 'instance_identifier'],
    ],
    'invalid instance type' => [
        [
            'license_key' => 'KEY-123',
            'product_code' => 'rankmath-pro',
            'instance_type' => 'domain',
            'instance_identifier' => 'example.com',
        ],
        ['instance_type'],
    ],
    'identifier too long' => [
        [
            'license_key' => 'KEY-123',
            'product_code' => 'rankmath-pro',
            'instance_type' => 'host',
            'instance_identifier' => str_repeat('a', 256),
        ],
        ['instance_identifier'],
    ],
]);
