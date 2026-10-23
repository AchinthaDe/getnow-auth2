# Implementation Plan: ACC-T0 Token Stream Import & Wiring

- **Step ID**: `ACC-T0`
- **Stream**: Tokens (`[T]`)
- **Version**: `v2`

---

## 1. Goal
Complete the hand-merge wiring and setup steps for `ACC-T0` so all imported token tests pass on native PostgreSQL, the API routes are properly registered, and unauthenticated requests to `api/*` render JSON 401 responses.

---

## 2. Files to Modify
- `bootstrap/app.php`: Register `api: __DIR__.'/../routes/api.php'`, set `apiPrefix: 'api/v1'`, and render JSON for `api/*` requests via `shouldRenderJsonWhen`.
- `bootstrap/providers.php`: Register `TokenServiceProvider::class`.
- `config/auth.php`: Add guard `api` with driver `passport` and provider `users`.
- `app/Models/User.php`: Implement `OAuthenticatable` and use `HasApiTokens`.
- `phpunit.xml`: Remove the SQLite overrides so tests execute against PostgreSQL (`getnow_auth_test`).
- `tests/Pest.php`: Extend `TestCase::class` for `'Feature', 'Unit'` and enable `RefreshDatabase` for `'Feature'`.
- `.env.example`: Append Passport environment variable names (`PASSPORT_ACCESS_TOKEN_TTL_SECONDS`, `PASSPORT_REFRESH_TOKEN_TTL_DAYS`, `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY`).
- `composer.json`: Map standard commands (`stan`, `check`, `fix`) per `AGENTS.md`.

---

## 3. Database & Command Steps
1. Publish Passport migrations: `php artisan vendor:publish --tag=passport-migrations`
2. Run migrations: `php artisan migrate`
3. Verify route list: `php artisan route:list --path=api`
4. Run token test suite: `php artisan test tests/Feature/Tokens tests/Unit/Domain/Accounts/TokenPairDataTest.php`
5. Run lint & type checks: `composer check`
