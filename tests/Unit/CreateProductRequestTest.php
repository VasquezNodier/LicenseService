<?php

use App\Http\Requests\CreateProductRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

it('accepts a valid product payload', function () {
    $payload = ['code' => 'rankmath-pro', 'name' => 'RankMath Pro'];

    $validator = Validator::make($payload, (new CreateProductRequest())->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects invalid product payloads', function (array $payload, array $errors) {
    $validator = Validator::make($payload, (new CreateProductRequest())->rules());

    expect($validator->fails())->toBeTrue();
    foreach ($errors as $key) {
        expect($validator->errors()->has($key))->toBeTrue();
    }
})->with([
    'missing fields' => [
        [],
        ['code', 'name'],
    ],
    'code too long' => [
        ['code' => str_repeat('x', 65), 'name' => 'Prod'],
        ['code'],
    ],
    'name too long' => [
        ['code' => 'prod', 'name' => str_repeat('y', 256)],
        ['name'],
    ],
]);
