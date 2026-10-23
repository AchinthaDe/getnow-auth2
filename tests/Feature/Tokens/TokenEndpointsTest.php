<?php

declare(strict_types=1);

use App\Domain\Accounts\Contracts\TokenIssuer;
use App\Domain\Accounts\Data\TokenPairData;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Passport;
use League\OAuth2\Server\ResourceServer;

function ensureEndpointsPassportTestKeys(): void
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
    ensureEndpointsPassportTestKeys();
});

test('post api v1 auth refresh rotates token pair successfully', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $initialPair = $issuer->issue($user);

    $response = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $initialPair->refreshToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'tokens' => [
                    'access_token',
                    'refresh_token',
                    'token_type',
                    'expires_in',
                ],
            ],
        ]);

    /** @var string $newAccessToken */
    $newAccessToken = $response->json('data.tokens.access_token');
    /** @var string $newRefreshToken */
    $newRefreshToken = $response->json('data.tokens.refresh_token');

    expect($newAccessToken)->not->toBe($initialPair->accessToken)
        ->and($newRefreshToken)->not->toBe($initialPair->refreshToken);

    $logoutResponse = $this->postJson('/api/v1/auth/logout', [], [
        'Authorization' => 'Bearer '.$newAccessToken,
    ]);

    $logoutResponse->assertNoContent();
});

test('post api v1 auth refresh fails with 401 when refresh token is reused', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $initialPair = $issuer->issue($user);

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $initialPair->refreshToken,
    ])->assertOk();

    $secondAttempt = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $initialPair->refreshToken,
    ]);

    $secondAttempt->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('post api v1 auth refresh fails with 401 on expired, revoked, or invalid token', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => 'completely-invalid-token',
    ])->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);

    $expiredUser = User::factory()->create();
    $expiredPair = $issuer->issue($expiredUser);
    $expiredAccessToken = Passport::token()->newQuery()->where('user_id', $expiredUser->getAuthIdentifier())->firstOrFail();
    $refreshTokenRow = Passport::refreshToken()->newQuery()->where('access_token_id', $expiredAccessToken->id)->firstOrFail();
    $refreshTokenRow->forceFill(['expires_at' => Carbon::now()->subMinute()])->save();

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $expiredPair->refreshToken,
    ])->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);

    $revokedUser = User::factory()->create();
    $revokedPair = $issuer->issue($revokedUser);
    $issuer->revokeAll($revokedUser);

    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $revokedPair->refreshToken,
    ])->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});

test('post api v1 auth refresh validates refresh token parameter', function (): void {
    $response = $this->postJson('/api/v1/auth/refresh', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['refresh_token']);
});

test('post api v1 auth logout revokes current session', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $pair = $issuer->issue($user);

    $response = $this->postJson('/api/v1/auth/logout', [], [
        'Authorization' => 'Bearer '.$pair->accessToken,
    ]);

    $response->assertNoContent();

    Auth::forgetGuards();

    $retryLogout = $this->postJson('/api/v1/auth/logout', [], [
        'Authorization' => 'Bearer '.$pair->accessToken,
    ]);

    $retryLogout->assertUnauthorized();

    $refreshAttempt = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $pair->refreshToken,
    ]);

    $refreshAttempt->assertUnauthorized();
});

test('post api v1 auth logout leaves user other active sessions valid', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $sessionA = $issuer->issue($user);
    $sessionB = $issuer->issue($user);

    $logoutA = $this->postJson('/api/v1/auth/logout', [], [
        'Authorization' => 'Bearer '.$sessionA->accessToken,
    ]);

    $logoutA->assertNoContent();

    Auth::forgetGuards();

    $checkA = $this->postJson('/api/v1/auth/logout', [], [
        'Authorization' => 'Bearer '.$sessionA->accessToken,
    ]);
    $checkA->assertUnauthorized();

    Auth::forgetGuards();

    $logoutB = $this->postJson('/api/v1/auth/logout', [], [
        'Authorization' => 'Bearer '.$sessionB->accessToken,
    ]);
    $logoutB->assertNoContent();
});

test('post api v1 auth logout requires authentication', function (): void {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertUnauthorized();
});

test('refresh endpoint enforces rate limits', function (): void {
    $user = User::factory()->create();
    $issuer = app(TokenIssuer::class);

    $currentPair = $issuer->issue($user);

    for ($i = 0; $i < 10; $i++) {
        $res = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $currentPair->refreshToken,
        ]);
        $res->assertOk();
        /** @var string $rotatedRefreshToken */
        $rotatedRefreshToken = $res->json('data.tokens.refresh_token');
        $currentPair = new TokenPairData(
            accessToken: (string) $res->json('data.tokens.access_token'),
            refreshToken: $rotatedRefreshToken,
            tokenType: 'Bearer',
            expiresIn: 900,
        );
    }

    $rateLimitedResponse = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $currentPair->refreshToken,
    ]);

    $rateLimitedResponse->assertStatus(429);
});
