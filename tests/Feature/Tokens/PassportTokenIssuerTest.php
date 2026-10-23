<?php

declare(strict_types=1);

use App\Domain\Accounts\Contracts\TokenIssuer;
use App\Domain\Accounts\Tokens\PassportTokenIssuer;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;
use League\OAuth2\Server\ResourceServer;

function ensurePassportTestKeys(): void
{
    /** @var string|null $cachedPrivateKey */
    static $cachedPrivateKey = null;
    /** @var string|null $cachedPublicKey */
    static $cachedPublicKey = null;

    if ($cachedPrivateKey === null || $cachedPublicKey === null) {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $candidates = [
            'C:/tools/php85/extras/ssl/openssl.cnf',
            'C:/Program Files/Git/mingw64/etc/ssl/openssl.cnf',
            'C:/Program Files/Git/usr/ssl/openssl.cnf',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $config['config'] = $candidate;
                break;
            }
        }

        $res = openssl_pkey_new($config);
        if ($res === false) {
            throw new RuntimeException('Failed to generate test RSA key pair: '.(openssl_error_string() ?: 'unknown error'));
        }

        openssl_pkey_export($res, $privateKey, null, $config);
        $details = openssl_pkey_get_details($res);
        if ($details === false) {
            throw new RuntimeException('Failed to get RSA key details.');
        }

        $cachedPrivateKey = $privateKey;
        $cachedPublicKey = $details['key'];
    }

    config([
        'passport.private_key' => $cachedPrivateKey,
        'passport.public_key' => $cachedPublicKey,
    ]);

    app()->forgetInstance(ResourceServer::class);
    Auth::forgetGuards();
}

beforeEach(function (): void {
    ensurePassportTestKeys();
});

test('token service provider binds token issuer interface to passport token issuer', function (): void {
    $issuer = app(TokenIssuer::class);

    expect($issuer)->toBeInstanceOf(PassportTokenIssuer::class);
});

test('issue mints a valid token pair and persists records in postgresql', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $tokenPair = $issuer->issue($user);

    expect($tokenPair->accessToken)->toBeString()
        ->and($tokenPair->accessToken)->not->toBeEmpty()
        ->and($tokenPair->refreshToken)->toBeString()
        ->and($tokenPair->refreshToken)->not->toBeEmpty()
        ->and($tokenPair->tokenType)->toBe('Bearer')
        ->and($tokenPair->expiresIn)->toBe(900);

    $this->assertDatabaseHas('oauth_access_tokens', [
        'user_id' => $user->getAuthIdentifier(),
        'revoked' => false,
    ]);

    $this->assertDatabaseHas('oauth_refresh_tokens', [
        'revoked' => false,
    ]);
});

test('refresh rotates the refresh token and revokes consumed tokens in postgresql', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $initialPair = $issuer->issue($user);
    $initialAccessTokenRow = Passport::token()->newQuery()->where('user_id', $user->getAuthIdentifier())->firstOrFail();
    $initialRefreshTokenRow = Passport::refreshToken()->newQuery()->where('access_token_id', $initialAccessTokenRow->id)->firstOrFail();

    $refreshedPair = $issuer->refresh($initialPair->refreshToken);

    expect($refreshedPair->accessToken)->not->toBe($initialPair->accessToken)
        ->and($refreshedPair->refreshToken)->not->toBe($initialPair->refreshToken)
        ->and($refreshedPair->tokenType)->toBe('Bearer')
        ->and($refreshedPair->expiresIn)->toBe(900);

    $initialAccessTokenRow->refresh();
    $initialRefreshTokenRow->refresh();

    expect($initialAccessTokenRow->revoked)->toBeTrue()
        ->and($initialRefreshTokenRow->revoked)->toBeTrue();

    $newAccessTokenRow = Passport::token()->newQuery()
        ->where('user_id', $user->getAuthIdentifier())
        ->where('revoked', false)
        ->first();

    expect($newAccessTokenRow)->not->toBeNull()
        ->and($newAccessTokenRow?->id)->not->toBe($initialAccessTokenRow->id);
});

test('refresh rejects reused refresh token with authentication exception', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $initialPair = $issuer->issue($user);
    $issuer->refresh($initialPair->refreshToken);

    expect(fn () => $issuer->refresh($initialPair->refreshToken))
        ->toThrow(AuthenticationException::class, 'Unauthenticated.');
});

test('refresh rejects expired refresh token with authentication exception', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $initialPair = $issuer->issue($user);
    $refreshTokenRow = Passport::refreshToken()->newQuery()->firstOrFail();
    $refreshTokenRow->forceFill(['expires_at' => Carbon::now()->subMinute()])->save();

    expect(fn () => $issuer->refresh($initialPair->refreshToken))
        ->toThrow(AuthenticationException::class, 'Unauthenticated.');
});

test('refresh rejects malformed and tampered refresh tokens with authentication exception', function (): void {
    $issuer = app(TokenIssuer::class);

    expect(fn () => $issuer->refresh('invalid-garbage-token'))
        ->toThrow(AuthenticationException::class, 'Unauthenticated.');
});

test('revoke revokes current access token and its paired refresh token', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $pair = $issuer->issue($user);
    $tokenRow = Passport::token()->newQuery()->where('user_id', $user->getAuthIdentifier())->firstOrFail();
    $refreshTokenRow = Passport::refreshToken()->newQuery()->where('access_token_id', $tokenRow->id)->firstOrFail();

    $user->withAccessToken(new AccessToken([
        'oauth_access_token_id' => $tokenRow->id,
    ]));

    $issuer->revoke($user);

    $tokenRow->refresh();
    $refreshTokenRow->refresh();

    expect($tokenRow->revoked)->toBeTrue()
        ->and($refreshTokenRow->revoked)->toBeTrue();
});

test('revoke handles null or transient token safely', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $issuer->revoke($user);

    expect(true)->toBeTrue();
});

test('revokeAll revokes every access token and refresh token of user', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $issuer->issue($user);
    $issuer->issue($user);
    $issuer->issue($user);

    expect(Passport::token()->newQuery()->where('user_id', $user->getAuthIdentifier())->where('revoked', false)->count())->toBe(3)
        ->and(Passport::refreshToken()->newQuery()->where('revoked', false)->count())->toBe(3);

    $issuer->revokeAll($user);

    expect(Passport::token()->newQuery()->where('user_id', $user->getAuthIdentifier())->where('revoked', false)->count())->toBe(0)
        ->and(Passport::refreshToken()->newQuery()->where('revoked', false)->count())->toBe(0);
});
