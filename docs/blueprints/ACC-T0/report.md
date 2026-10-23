# Implementation Report: ACC-T0 Token Stream Import & Wiring

- **Step ID**: `ACC-T0`
- **Stream**: Tokens (`[T]`)
- **Status**: Completed / Ready for Review
- **Date**: 2026-10-22
- **Related Plan**: `docs/blueprints/ACC-T0/plan.md` (v2)

---

## 1. Summary of Changes

This step imports and wires the tokens stream from the baseline repository (`C:\dev\getnow-auth`) into `getnow-auth2`, fulfilling all setup and hand-merge specifications from `ARCHITECTURE.md` section 10:

1. **Copied Baseline Token Files**
   - All 13 token-stream files and tests were copied as-is from the baseline project.
   - Hash comparison verified all 13 files are byte-for-byte identical (`SAME`) with no modifications or refactors.

2. **API Routing and JSON Rendering Wiring (`bootstrap/app.php`)**
   - Registered `api: __DIR__.'/../routes/api.php'` with `apiPrefix: 'api/v1'`.
   - Wired `routes/api.php` to require `routes/api/tokens.php`.
   - Exposes `/api/v1/auth/refresh` and `/api/v1/auth/logout`.
   - Configured `shouldRenderJsonWhen` in `withExceptions` with `Illuminate\Http\Request` imported:
     ```php
     ->withExceptions(function (Exceptions $exceptions): void {
         $exceptions->shouldRenderJsonWhen(
             fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
         );
     })
     ```
   - Verified that unauthenticated requests to `/api/v1/auth/logout` without an `Accept: application/json` header receive HTTP `401 Unauthorized` with JSON body `{"message":"Unauthenticated."}` rather than a 302 redirect.

3. **Service Provider Registration (`bootstrap/providers.php`)**
   - Registered `TokenServiceProvider::class`.
   - Binds `TokenIssuer` to `PassportTokenIssuer` and sets up `token-refresh` and `token-logout` rate limits.

4. **Authentication Guard Configuration (`config/auth.php`)**
   - Added the `api` guard with driver `passport` and provider `users`.

5. **User Model Integration (`app/Models/User.php`)**
   - Implemented `Laravel\Passport\Contracts\OAuthenticatable` and added `Laravel\Passport\HasApiTokens` trait.
   - Added `declare(strict_types=1);`.

6. **PostgreSQL Test Database Configuration (`phpunit.xml`, `tests/Pest.php`)**
   - Configured `phpunit.xml` with native PostgreSQL connection (`getnow_auth_test`).
   - Extended `TestCase::class` to `Unit` and enabled `RefreshDatabase` for `Feature` in `tests/Pest.php`.
   - Published Passport migrations (`vendor:publish --tag=passport-migrations`) and migrated `getnow_auth` and `getnow_auth_test`.

7. **Environment Declarations (`.env.example`)**
   - Added Passport environment variable declarations under `# Tokens (Achintha)`.

8. **Toolchain Quality Gates (`composer.json`)**
   - Added `lint`, `fix`, `stan`, `test`, and `check` scripts in `composer.json` conforming to `AGENTS.md`.

---

## 2. File Verification & Baseline Integrity

### 2.1 The 13 Copied Files (Hash Comparison against Baseline `C:\dev\getnow-auth`)

```powershell
$old = "C:\dev\getnow-auth"
$new = "C:\dev\getnow-auth2"
$files = @(
  "app\Domain\Accounts\Contracts\TokenIssuer.php",
  "app\Domain\Accounts\Data\TokenPairData.php",
  "app\Domain\Accounts\Tokens\PassportTokenIssuer.php",
  "app\Http\Controllers\Api\V1\Auth\TokenController.php",
  "app\Http\Requests\Auth\RefreshTokenRequest.php",
  "app\Providers\TokenServiceProvider.php",
  "config\passport.php",
  "routes\api\tokens.php",
  "docs\adr\0001-passport-token-issuance-and-refresh-rotation.md",
  "tests\Feature\Tokens\PassportSetupTest.php",
  "tests\Feature\Tokens\PassportTokenIssuerTest.php",
  "tests\Feature\Tokens\TokenEndpointsTest.php",
  "tests\Unit\Domain\Accounts\TokenPairDataTest.php"
)
foreach ($f in $files) {
  $same = (Get-FileHash "$old\$f").Hash -eq (Get-FileHash "$new\$f").Hash
  "{0}  {1}" -f ($(if ($same) {"SAME"} else {"DIFF"})), $f
}
```

Output:
```
SAME  app\Domain\Accounts\Contracts\TokenIssuer.php
SAME  app\Domain\Accounts\Data\TokenPairData.php
SAME  app\Domain\Accounts\Tokens\PassportTokenIssuer.php
SAME  app\Http\Controllers\Api\V1\Auth\TokenController.php
SAME  app\Http\Requests\Auth\RefreshTokenRequest.php
SAME  app\Providers\TokenServiceProvider.php
SAME  config\passport.php
SAME  routes\api\tokens.php
SAME  docs\adr\0001-passport-token-issuance-and-refresh-rotation.md
SAME  tests\Feature\Tokens\PassportSetupTest.php
SAME  tests\Feature\Tokens\PassportTokenIssuerTest.php
SAME  tests\Feature\Tokens\TokenEndpointsTest.php
SAME  tests\Unit\Domain\Accounts\TokenPairDataTest.php
```

All 13 files match the baseline byte-for-byte.

### 2.2 Hand-Merged & Modified Files

- `bootstrap/app.php`: Registered API routing (`apiPrefix: 'api/v1'`) and `shouldRenderJsonWhen` for `api/*`.
- `bootstrap/providers.php`: Registered `TokenServiceProvider`.
- `config/auth.php`: Added `api` guard (`passport` driver, `users` provider).
- `app/Models/User.php`: Added `HasApiTokens` and implemented `OAuthenticatable`.
- `phpunit.xml`: Configured PostgreSQL `getnow_auth_test` database.
- `tests/Pest.php`: Bound `TestCase` for `Unit` and `RefreshDatabase` for `Feature`.
- `.env.example`: Declared Passport environment variables without values.
- `composer.json`: Added `lint`, `fix`, `stan`, `test`, `check` scripts.
- `routes/api.php`: Created single-line include: `require __DIR__.'/api/tokens.php';`.

---

## 3. Raw Verification Evidence

### 3.1 Route Verification
```powershell
php artisan route:list --path=api
```
```
 POST api/v1/auth/logout .. Api\V1\Auth\TokenController@logout
 POST api/v1/auth/refresh .. Api\V1\Auth\TokenController@refresh

 Showing [2] routes
```

### 3.2 Layer C Check: JSON Exception Rendering on Unauthenticated Request (No Accept Header)
```powershell
curl.exe -i -X POST http://127.0.0.1:8000/api/v1/auth/logout
```
```
HTTP/1.1 401 Unauthorized
Host: 127.0.0.1:8000
Connection: close
X-Powered-By: PHP/8.5.9
Cache-Control: no-cache, private
Date: Thu, 22 Oct 2026 09:42:47 GMT
Content-Type: application/json
Access-Control-Allow-Origin: *

{"message":"Unauthenticated."}
```
Returns HTTP 401 with JSON body (not a 302 redirect).

### 3.3 Token Suite Tests (PostgreSQL `getnow_auth_test`)
```powershell
php artisan test tests/Feature/Tokens tests/Unit/Domain/Accounts/TokenPairDataTest.php
```
```json
{"tool":"pest","result":"passed","tests":24,"passed":24,"assertions":98,"duration_ms":6528}
```

### 3.4 Full Quality Gate Suite (`composer check`)
```powershell
composer check
```
```
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pest","result":"passed","tests":58,"passed":58,"assertions":181,"duration_ms":13127}
```

### 3.5 Installed Package Versions
```powershell
composer show laravel/passport
composer show spatie/laravel-data
```
- `laravel/passport`: `v13.10.0`
- `spatie/laravel-data`: `4.23.0`

---

## 4. Definition of Done Checklist

- [x] `composer check` passes and raw output is recorded
- [x] All 24 imported token tests pass on native PostgreSQL (`getnow_auth_test`)
- [x] Hash comparison proves all 13 copied files are byte-for-byte identical to baseline (`C:\dev\getnow-auth`)
- [x] `bootstrap/app.php` renders JSON 401 on unauthenticated `api/*` requests without Accept header (verified via curl)
- [x] Hand-merge wiring matches `ARCHITECTURE.md` section 10
- [x] Passport migrations published and migrated
- [x] `.env.example` updated with token configuration variable names
- [x] `phpunit.xml` and `.env.example` contain no secrets or private keys
- [x] `phpstan.neon` contains no new baseline or ignore entries
