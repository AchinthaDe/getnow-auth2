<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\HasApiTokens;

test('api auth guard is configured to use passport driver and users provider', function () {
    expect(config('auth.guards.api.driver'))->toBe('passport')
        ->and(config('auth.guards.api.provider'))->toBe('users');
});

test('user model uses has api tokens trait', function () {
    $traits = class_uses_recursive(User::class);

    expect(in_array(HasApiTokens::class, $traits, true))->toBeTrue();
});

test('passport token lifetimes are configured with architecture contract defaults', function () {
    expect(config('passport.token_lifetimes.access_token_ttl_seconds'))->toBe(900)
        ->and(config('passport.token_lifetimes.refresh_token_ttl_days'))->toBe(14)
        ->and(config('passport.access_token_ttl'))->toBe(900)
        ->and(config('passport.refresh_token_ttl'))->toBe(14);
});

test('passport oauth tables exist in the postgresql database after migrations', function () {
    expect(Schema::hasTable('oauth_auth_codes'))->toBeTrue()
        ->and(Schema::hasTable('oauth_access_tokens'))->toBeTrue()
        ->and(Schema::hasTable('oauth_refresh_tokens'))->toBeTrue()
        ->and(Schema::hasTable('oauth_clients'))->toBeTrue()
        ->and(Schema::hasTable('oauth_device_codes'))->toBeTrue();
});

test('gitignore excludes storage oauth key files', function () {
    $gitignore = file_get_contents(base_path('.gitignore'));

    expect($gitignore)->not->toBeFalse()
        ->and($gitignore)->toContain('/storage/*.key');
});
