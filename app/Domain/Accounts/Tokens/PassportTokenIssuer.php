<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Tokens;

use App\Domain\Accounts\Contracts\TokenIssuer;
use App\Domain\Accounts\Data\TokenPairData;
use App\Models\User;
use DateInterval;
use DateTimeImmutable;
use Defuse\Crypto\Crypto;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Bridge\AccessToken as BridgeAccessToken;
use Laravel\Passport\Bridge\Client as BridgeClient;
use Laravel\Passport\Bridge\RefreshToken as BridgeRefreshToken;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Laravel\Passport\TransientToken;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Throwable;

final class PassportTokenIssuer implements TokenIssuer
{
    public function __construct(
        private readonly AccessTokenRepositoryInterface $accessTokenRepository,
        private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly Encrypter $encrypter,
    ) {}

    public function issue(User $user): TokenPairData
    {
        return $this->mintTokenPair($user->getAuthIdentifier(), $this->resolveClient());
    }

    public function refresh(string $refreshToken): TokenPairData
    {
        $payload = $this->decryptRefreshToken($refreshToken);

        return DB::transaction(function () use ($payload): TokenPairData {
            /** @var RefreshToken|null $consumedRefreshToken */
            $consumedRefreshToken = Passport::refreshToken()->newQuery()
                ->whereKey($payload['refresh_token_id'])
                ->lockForUpdate()
                ->first();

            if ($consumedRefreshToken === null || $consumedRefreshToken->revoked) {
                throw new AuthenticationException('Unauthenticated.');
            }

            /** @var Token|null $priorAccessToken */
            $priorAccessToken = Passport::token()->newQuery()
                ->whereKey($payload['access_token_id'])
                ->lockForUpdate()
                ->first();

            if ($priorAccessToken === null || $priorAccessToken->revoked) {
                throw new AuthenticationException('Unauthenticated.');
            }

            /** @var Client|null $client */
            $client = Passport::client()->newQuery()
                ->whereKey($payload['client_id'])
                ->first();

            if ($client === null || $client->revoked) {
                throw new AuthenticationException('Unauthenticated.');
            }

            $now = time();
            $payloadExpired = $payload['expire_time'] < $now;
            $refreshExpired = $consumedRefreshToken->expires_at !== null && $consumedRefreshToken->expires_at->isPast();
            $accessExpired = $priorAccessToken->expires_at !== null && $priorAccessToken->expires_at->isPast();

            if ($payloadExpired || $refreshExpired || $accessExpired) {
                throw new AuthenticationException('Unauthenticated.');
            }

            if (
                $priorAccessToken->user_id === null ||
                (string) $priorAccessToken->user_id !== (string) $payload['user_id']
            ) {
                throw new AuthenticationException('Unauthenticated.');
            }

            $consumedRefreshToken->revoke();
            $priorAccessToken->revoke();

            return $this->mintTokenPair($priorAccessToken->user_id, $client);
        });
    }

    public function revoke(User $user): void
    {
        $token = $user->token();

        if ($token === null || $token instanceof TransientToken) {
            return;
        }

        if ($token instanceof Token) {
            $tokenId = (string) $token->getKey();
        } elseif ($token instanceof AccessToken) {
            $tokenId = (string) $token->oauth_access_token_id;
        } else {
            return;
        }

        Passport::token()->newQuery()->whereKey($tokenId)->update(['revoked' => true]);
        Passport::refreshToken()->newQuery()->where('access_token_id', $tokenId)->update(['revoked' => true]);
    }

    public function revokeAll(User $user): void
    {
        $userId = $user->getAuthIdentifier();

        /** @var array<int, string> $tokenIds */
        $tokenIds = Passport::token()->newQuery()
            ->where('user_id', $userId)
            ->where('revoked', false)
            ->pluck('id')
            ->all();

        Passport::token()->newQuery()
            ->where('user_id', $userId)
            ->where('revoked', false)
            ->update(['revoked' => true]);

        if (! empty($tokenIds)) {
            Passport::refreshToken()->newQuery()
                ->whereIn('access_token_id', $tokenIds)
                ->where('revoked', false)
                ->update(['revoked' => true]);
        }
    }

    private function mintTokenPair(int|string $userId, Client $client): TokenPairData
    {
        $accessTtlSeconds = (int) config('passport.token_lifetimes.access_token_ttl_seconds', 900);
        $refreshTtlDays = (int) config('passport.token_lifetimes.refresh_token_ttl_days', 14);

        $accessExpiry = (new DateTimeImmutable)->add(new DateInterval('PT'.$accessTtlSeconds.'S'));
        $refreshExpiry = (new DateTimeImmutable)->add(new DateInterval('P'.$refreshTtlDays.'D'));

        $clientId = (string) $client->id;
        $userIdString = (string) $userId;

        if ($clientId === '' || $userIdString === '') {
            throw new AuthenticationException('Unauthenticated.');
        }

        $clientEntity = new BridgeClient($clientId, $client->name, []);

        $accessTokenEntity = new BridgeAccessToken($userIdString, [], $clientEntity);
        $accessTokenEntity->setIdentifier(bin2hex(random_bytes(40)));
        $accessTokenEntity->setExpiryDateTime($accessExpiry);
        $accessTokenEntity->setPrivateKey($this->getPrivateKey());

        $this->accessTokenRepository->persistNewAccessToken($accessTokenEntity);

        $refreshTokenEntity = new BridgeRefreshToken;
        $refreshTokenEntity->setIdentifier(bin2hex(random_bytes(40)));
        $refreshTokenEntity->setExpiryDateTime($refreshExpiry);
        $refreshTokenEntity->setAccessToken($accessTokenEntity);

        $this->refreshTokenRepository->persistNewRefreshToken($refreshTokenEntity);

        $payload = json_encode([
            'client_id' => (string) $client->id,
            'refresh_token_id' => $refreshTokenEntity->getIdentifier(),
            'access_token_id' => $accessTokenEntity->getIdentifier(),
            'scopes' => [],
            'user_id' => (string) $userId,
            'expire_time' => $refreshExpiry->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        $encryptedRefreshToken = Crypto::encryptWithPassword($payload, $this->getEncryptionKey());

        return new TokenPairData(
            accessToken: $accessTokenEntity->toString(),
            refreshToken: $encryptedRefreshToken,
            tokenType: 'Bearer',
            expiresIn: $accessTtlSeconds,
        );
    }

    /**
     * @return array{client_id: string, refresh_token_id: string, access_token_id: string, user_id: string, expire_time: int}
     */
    private function decryptRefreshToken(string $encryptedToken): array
    {
        try {
            $decrypted = Crypto::decryptWithPassword($encryptedToken, $this->getEncryptionKey());
            /** @var array<string, mixed> $payload */
            $payload = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if (
            ! isset($payload['refresh_token_id'], $payload['access_token_id'], $payload['client_id'], $payload['user_id'], $payload['expire_time']) ||
            ! is_string($payload['refresh_token_id']) ||
            ! is_string($payload['access_token_id']) ||
            ! is_string($payload['client_id']) ||
            ! is_int($payload['expire_time'])
        ) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return [
            'client_id' => $payload['client_id'],
            'refresh_token_id' => $payload['refresh_token_id'],
            'access_token_id' => $payload['access_token_id'],
            'user_id' => (string) $payload['user_id'],
            'expire_time' => $payload['expire_time'],
        ];
    }

    private function resolveClient(): Client
    {
        /** @var Client|null $client */
        $client = Passport::client()->newQuery()
            ->where('revoked', false)
            ->where('name', 'GetNow First-Party Auth Client')
            ->first();

        if ($client !== null) {
            return $client;
        }

        /** @var Client $client */
        $client = Passport::client()->newQuery()->forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'GetNow First-Party Auth Client',
            'secret' => null,
            'provider' => 'users',
            'redirect_uris' => [],
            'grant_types' => ['refresh_token'],
            'revoked' => false,
        ]);

        return $client;
    }

    private function getPrivateKey(): CryptKey
    {
        $configuredKey = config('passport.private_key');

        if (is_string($configuredKey) && $configuredKey !== '') {
            $key = str_replace('\\n', "\n", $configuredKey);

            return new CryptKey($key, null, false);
        }

        return new CryptKey('file://'.Passport::keyPath('oauth-private.key'), null, Passport::$validateKeyPermissions);
    }

    private function getEncryptionKey(): string
    {
        return Passport::tokenEncryptionKey($this->encrypter);
    }
}
