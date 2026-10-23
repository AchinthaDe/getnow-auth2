<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Accounts\Contracts\TokenIssuer;
use App\Domain\Accounts\Tokens\PassportTokenIssuer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\AccessTokenRepository as BridgeAccessTokenRepository;
use Laravel\Passport\Bridge\RefreshTokenRepository as BridgeRefreshTokenRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

final class TokenServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AccessTokenRepositoryInterface::class, BridgeAccessTokenRepository::class);
        $this->app->bind(RefreshTokenRepositoryInterface::class, BridgeRefreshTokenRepository::class);
        $this->app->bind(TokenIssuer::class, PassportTokenIssuer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $accessTtlSeconds = (int) config('passport.token_lifetimes.access_token_ttl_seconds', 900);
        $refreshTtlDays = (int) config('passport.token_lifetimes.refresh_token_ttl_days', 14);

        Passport::tokensExpireIn(Date::now()->addSeconds($accessTtlSeconds));
        Passport::refreshTokensExpireIn(Date::now()->addDays($refreshTtlDays));

        RateLimiter::for('token-refresh', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('token-logout', function (Request $request): Limit {
            return Limit::perMinute(30)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });
    }
}
