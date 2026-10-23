<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\TokenPairData;

test('token pair data can be constructed', function () {
    $data = new TokenPairData('access-123', 'refresh-456', 'Bearer', 900);

    expect($data->accessToken)->toBe('access-123')
        ->and($data->refreshToken)->toBe('refresh-456')
        ->and($data->tokenType)->toBe('Bearer')
        ->and($data->expiresIn)->toBe(900);
});

test('token pair data serializes to snake_case array and json', function () {
    $data = new TokenPairData('access-123', 'refresh-456', 'Bearer', 900);
    $array = $data->toArray();

    expect($array)->toBe([
        'access_token' => 'access-123',
        'refresh_token' => 'refresh-456',
        'token_type' => 'Bearer',
        'expires_in' => 900,
    ]);

    expect(array_key_exists('accessToken', $array))->toBeFalse();

    $json = $data->toJson();
    expect($json)->toContain('"access_token":"access-123"')
        ->and($json)->toContain('"refresh_token":"refresh-456"')
        ->and($json)->toContain('"token_type":"Bearer"')
        ->and($json)->toContain('"expires_in":900');
});
