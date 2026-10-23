# 0002. Remove Laravel starter kit

- **Status**: Accepted
- **Date**: 2026-10-23
- **Step**: `ACC-C1`

## Context

The `getnow-auth2` repository was bootstrapped from the Laravel Livewire starter kit, containing Laravel Fortify, Livewire, Flux UI, WebAuthn passkeys, web routing, Blade views, and session-based authentication controllers.

`ARCHITECTURE.md` section 1 defines this module as an API-only service providing:
1. Superadmin user CRUD (`/api/v1/users`).
2. Public customer registration (`/api/v1/auth/register`).
3. Unified login for superadmin, retailer, and customer (`/api/v1/auth/login`).
4. Access and refresh token lifecycle on Laravel Passport (`/api/v1/auth/refresh`, `/api/v1/auth/logout`).

Web UI, password reset screens, email verification flows, 2FA challenges, and passkeys are explicitly out of scope. Retaining starter-kit web routes creates unmanaged authentication vectors that bypass `AuthService`, `UserPolicy`, and `TokenIssuer`.

## Decision

Remove all server-side starter kit scaffolding, routes, views, config, packages, and tests:
1. Prune `laravel/fortify`, `livewire/livewire`, `livewire/flux`, and `livewire/blaze` from `composer.json` and regenerate `composer.lock`.
2. Unregister and delete `FortifyServiceProvider`, Fortify actions (`app/Actions/Fortify/*`), and unused validation traits (`app/Concerns/*`).
3. Delete all Livewire components (`app/Livewire/*`) and starter Blade views (`resources/views/*`).
4. Empty `routes/web.php` of all routes (preserving `declare(strict_types=1);`) and delete `routes/settings.php`.
5. Remove Fortify interfaces (`PasskeyUser`) and traits (`PasskeyAuthenticatable`, `TwoFactorAuthenticatable`) from `App\Models\User`, while preserving `HasApiTokens` and `OAuthenticatable`.
6. Retain starter database tables (`sessions`, `password_reset_tokens`, `passkeys`) and two-factor columns on `users` (`DBS3`).
7. Prune broken Livewire/Flux imports from `resources/css/app.css` to allow `composer setup` and `npm run build` to pass cleanly in CI while leaving remaining frontend configuration untouched (CR-2).
8. Configure `$middleware->redirectGuestsTo(fn () => null);` in `bootstrap/app.php` to prevent unauthenticated requests from attempting redirects to a non-existent `login` route (CR-1).
9. Add a contract regression test in `tests/Feature/Contract/ApiUnauthenticatedResponseTest.php` proving unauthenticated API requests without an `Accept` header receive HTTP 401 JSON.

## Consequences

1. No web UI or named `login` route exists on the server.
2. Unauthenticated requests to `/api/*` receive HTTP 401 Unauthorized JSON via `bootstrap/app.php` exception rendering and `$middleware->redirectGuestsTo(fn () => null)` instead of triggering a `RouteNotFoundException` or 302 redirect.
3. Database migrations and `php artisan migrate:status` remain completely backward-compatible and unchanged.
4. Frontend build commands (`npm run build`) succeed cleanly in CI and local environments without referencing purged Livewire or Flux vendor stylesheets (CR-2).
5. Revisit unused database tables and columns when merging this module into the GetNow main repository (`ARCHITECTURE.md` section 12).
