<?php

use App\Http\Requests\UpdateLicenseLifecycleRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

it('accepts a valid renew payload', function () {
    $payload = [
        'action' => 'renew',
        'expires_at' => now()->addMonth()->toIso8601String(),
    ];

    $validator = Validator::make($payload, (new UpdateLicenseLifecycleRequest())->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects invalid lifecycle payloads', function (array $payload, array $errors) {
    $validator = Validator::make($payload, (new UpdateLicenseLifecycleRequest())->rules());

    expect($validator->fails())->toBeTrue();

    foreach ($errors as $key) {
        expect($validator->errors()->has($key))->toBeTrue();
    }
})->with([
    'missing action' => [
        [],
        ['action'],
    ],
    'invalid action' => [
        ['action' => 'pause'],
        ['action'],
    ],
    'renew missing expires_at' => [
        ['action' => 'renew'],
        ['expires_at'],
    ],
]);
