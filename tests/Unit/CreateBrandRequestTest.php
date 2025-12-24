<?php

use App\Http\Requests\CreateBrandRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

it('accepts a valid brand payload', function () {
    $payload = ['name' => 'RankMath', 'role' => 'standard'];

    $validator = Validator::make($payload, (new CreateBrandRequest())->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects invalid brand payloads', function (array $payload, array $errors) {
    $validator = Validator::make($payload, (new CreateBrandRequest())->rules());

    expect($validator->fails())->toBeTrue();
    foreach ($errors as $key) {
        expect($validator->errors()->has($key))->toBeTrue();
    }
})->with([
    'missing fields' => [
        [],
        ['name', 'role'],
    ],
    'invalid role' => [
        ['name' => 'Brand', 'role' => 'invalid'],
        ['role'],
    ],
    'name too long' => [
        ['name' => str_repeat('a', 256), 'role' => 'standard'],
        ['name'],
    ],
]);
