# [ACC-U4 | IMPL-REPORT | Gemini]

# Implementation Report: ACC-U4 Authentication Endpoints

- **Step ID**: `ACC-U4`
- **Stream**: Users (`[U]`)
- **Status**: VERIFY (r2 / Review r1 Response)
- **Date**: 2026-10-27
- **Related Plan**: `docs/blueprints/ACC-U4/plan.md` (v1)
- **Base Branch**: `main`
- **Base SHA**: `d6fa3e0f98859f25b2a040cc04f3c8b24fa1a932`
- **Feature Branch**: `feat/users-auth-endpoints`
- **Head SHA**: `b143cad927c37c0bbdd4918304a695e60e0b8669`
- **Gaps**: None

---

## 1. Summary of Changes

This step builds the Accounts-stream authentication endpoints `POST /api/v1/auth/register` and `POST /api/v1/auth/login` on top of `ACC-U1`..`ACC-U3` and the `TokenIssuer` contract delivered by `ACC-T0`:

1. **Input & Output DTOs (`app/Domain/Accounts/Data/LoginData.php`, `app/Domain/Accounts/Data/AuthResultData.php`)**:
   - `LoginData`: `final` class extending `Spatie\LaravelData\Data` holding `string $email` and `string $password`.
   - `AuthResultData`: `final` class extending `Spatie\LaravelData\Data` with `#[MapName(SnakeCaseMapper::class)]` wrapping `UserResponseData $user` and `TokenPairData $tokens`.

2. **Domain Exception (`app/Domain/Accounts/Exceptions/InvalidCredentialsException.php`)**:
   - `final` pure domain exception extending `RuntimeException` (no `Illuminate\Http` imports).

3. **Repository Contract & Implementation (`app/Domain/Accounts/Contracts/UserRepositoryInterface.php`, `app/Domain/Accounts/Repositories/UserRepository.php`)**:
   - Added method `findByEmail(string $email): ?User` performing exact match on normalized email and excluding soft-deleted rows by default.

4. **Domain Auth Service (`app/Domain/Accounts/Services/AuthService.php`)**:
   - `register(UserData $data)`: Wraps execution in `DB::transaction()`, enforces domain-level `role: UserRole::Customer` (Mandatory Guard 2), persists user via `UserService::store`, and issues tokens via `TokenIssuer::issue`.
   - `login(LoginData $data)`: Normalizes email, queries `UserRepositoryInterface::findByEmail`, performs constant `DUMMY_HASH` timing equalization (cost 12 matching production `BCRYPT_ROUNDS=12`) when user is not found or soft-deleted, checks password via `Hasher::check`, and issues tokens via `TokenIssuer::issue`.

5. **Form Requests (`app/Http/Requests/Auth/RegisterRequest.php`, `app/Http/Requests/Auth/LoginRequest.php`)**:
   - `RegisterRequest`: Validates `name`, `email`, `password` (8..72 chars), prohibits `role` (`422`), normalizes email in `prepareForValidation()`, and produces `UserData` with `UserRole::Customer`.
   - `LoginRequest`: Validates `email` and `password` (max 72 chars), normalizes email, and produces `LoginData`.

6. **Controller & Routes (`app/Http/Controllers/Api/V1/Auth/AuthController.php`, `routes/api/auth.php`, `routes/api.php`)**:
   - `AuthController`: Thin controller calling `AuthService::register` (returning 201) and `AuthService::login` (returning 200) with `$result->toArray()` parity.
   - `routes/api/auth.php`: Defines guest routes `POST auth/register` (`throttle:auth-register`) and `POST auth/login` (`throttle:auth-login`).
   - `routes/api.php`: Appends `require __DIR__.'/api/auth.php';` under approved CR-1 (`APPROVED: CR-1`).

7. **Rate Limiters & Exception Mapping (`app/Providers/AppServiceProvider.php`, `bootstrap/app.php`)**:
   - `AppServiceProvider`: Registers named rate limiters `auth-login` (5/min per email+IP with safe string handling per Mandatory Guard 1) and `auth-register` (10/min per IP).
   - `bootstrap/app.php`: Maps `InvalidCredentialsException` to uniform JSON 401 `{"message": "Invalid credentials."}`.

8. **Feature Tests (`tests/Feature/Accounts/AuthEndpointsTest.php`)**:
   - 11 feature tests covering AC-1 through AC-10 plus F4, including edge cases, rate limiting, and all mandatory guards.

---

## 2. Review Findings Resolution (r1)

| Finding | Severity | Resolution & Evidence |
|---|---|---|
| **F1 (Dummy Hash Cost)** | Major | Updated `DUMMY_HASH` in `AuthService.php` to a cost-12 bcrypt literal (`$2y$12$paGwAYoFIuju5tDSdHrPCOqL7KBasBpmD8nF4EN5nhyUQD0J24r6m`), matching production `BCRYPT_ROUNDS=12` in `.env.example`. Added architectural comment explaining that the cost must track production rounds to eliminate the email-existence oracle. Evidence below. |
| **F2 (Evidence Rule & Header)** | Major | Replaced placeholders with real SHAs: Base SHA `d6fa3e0f98859f25b2a040cc04f3c8b24fa1a932`, Head SHA `b143cad927c37c0bbdd4918304a695e60e0b8669`. Added unedited `git log --oneline main..HEAD`, `git rev-parse main HEAD`, and raw `git diff main...HEAD -- app/Domain/Accounts/Exceptions`. |
| **F3 (Report Template, Deviations & CR-1)** | Minor | Added AC-to-test mapping table, File Reconciliation, and Deviations sections. Documented CR-1 (`routes/api.php`) bringing code files to 14 (approved by Claude: `APPROVED: CR-1`), customer role re-binding in `AuthService::register`, and F1 dummy hash update. |
| **F4 (AuthService Customer Role Guard Test)** | Minor | Added direct domain test `it('enforces customer role in auth service register regardless of role passed in user data')` invoking `AuthService::register` with `UserRole::Superadmin` and asserting persisted and returned role is `Customer`. |

### F1 Evidence: Production Cost Tracking
```powershell
# 1. .env.example line 16
Select-String -Path .env.example -Pattern 'BCRYPT_ROUNDS'
```
```text
.env.example:16:BCRYPT_ROUNDS=12
```

```powershell
# 2. password_get_info on new DUMMY_HASH literal
php -r "var_dump(password_get_info('`$2y`$12`$paGwAYoFIuju5tDSdHrPCOqL7KBasBpmD8nF4EN5nhyUQD0J24r6m'));"
```
```text
array(3) {
  ["algo"]=>
  string(2) "2y"
  ["algoName"]=>
  string(6) "bcrypt"
  ["options"]=>
  array(1) {
    ["cost"]=>
    int(12)
  }
}
```

```powershell
# 3. git diff main...HEAD -- app/Domain/Accounts/Exceptions
git diff main...HEAD -- app/Domain/Accounts/Exceptions
```
```diff
diff --git a/app/Domain/Accounts/Exceptions/InvalidCredentialsException.php b/app/Domain/Accounts/Exceptions/InvalidCredentialsException.php
new file mode 100644
index 0000000..7fae634
--- /dev/null
+++ b/app/Domain/Accounts/Exceptions/InvalidCredentialsException.php
@@ -0,0 +1,15 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Domain\Accounts\Exceptions;
+
+use RuntimeException;
+
+final class InvalidCredentialsException extends RuntimeException
+{
+    public function __construct(string $message = 'Invalid credentials.')
+    {
+        parent::__construct($message);
+    }
+}
```

---

## 3. Mutation Spot-Check Evidence

### Mutation 1: Remove `role: UserRole::Customer` from `AuthService::register`
Mutated `role: UserRole::Customer` to `role: $data->role` in `app/Domain/Accounts/Services/AuthService.php`.
```powershell
vendor/bin/pest --filter="enforces customer role in auth service register regardless of role passed in user data"
```
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":0,"duration_ms":2534,"errors":1,"error_details":[{"test":"P\\Tests\\Feature\\Accounts\\AuthEndpointsTest::__pest_evaluable_it_enforces_customer_role_in_auth_service_register_regardless_of_role_passed_in_user_data","file":"\\dev\\getnow-auth2\\app\\Domain\\Accounts\\Exceptions\\ProtectedUserException.php","line":13,"message":"Cannot create a user with the superadmin role.","trace":["C:\\dev\\getnow-auth2\\app\\Domain\\Accounts\\Exceptions\\ProtectedUserException.php:13","C:\\dev\\getnow-auth2\\app\\Domain\\Accounts\\Services\\UserService.php:83","C:\\dev\\getnow-auth2\\app\\Domain\\Accounts\\Services\\AuthService.php:43","C:\\dev\\getnow-auth2\\app\\Domain\\Accounts\\Services\\AuthService.php:35","C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\AuthEndpointsTest.php:343"]}]}
```
*Result*: Failed as expected (`ProtectedUserException: Cannot create a user with the superadmin role.`). Restored cleanly with `git checkout`.

### Mutation 2: Change `findByEmail` to use `withTrashed()`
Mutated `return User::query()->where('email', $email)->first();` to `return User::query()->withTrashed()->where('email', $email)->first();` in `app/Domain/Accounts/Repositories/UserRepository.php`.
```powershell
vendor/bin/pest --filter="finds user by exact email excluding soft-deleted records"
```
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":3,"duration_ms":1477,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Accounts\\AuthEndpointsTest::__pest_evaluable_it_finds_user_by_exact_email_excluding_soft_deleted_records","file":"\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\AuthEndpointsTest.php","line":306,"message":"Failed asserting that an instance of class App\\Models\\User is null.","trace":["C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\AuthEndpointsTest.php:306"]}]}
```
*Result*: Failed as expected (soft-deleted user was returned instead of null). Restored cleanly with `git checkout`.

### Mutation 3: Remove `'role' => ['prohibited']` from `RegisterRequest`
Removed `'role' => ['prohibited']` from `rules()` in `app/Http/Requests/Auth/RegisterRequest.php`.
```powershell
vendor/bin/pest --filter="rejects register request with 422 for validation failures duplicate emails and prohibited role leaving database unchanged"
```
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":21,"duration_ms":2129,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Accounts\\AuthEndpointsTest::__pest_evaluable_it_rejects_register_request_with_422_for_validation_failures_duplicate_emails_and_prohibited_role_leaving_database_unchanged","file":"\\dev\\getnow-auth2\\vendor\\laravel\\framework\\src\\Illuminate\\Testing\\TestResponseAssert.php","line":46,"message":"Expected response status code [422] but received 201.\nFailed asserting that 201 is identical to 422.","trace":["C:\\dev\\getnow-auth2\\vendor\\laravel\\framework\\src\\Illuminate\\Testing\\TestResponseAssert.php:46","C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\AuthEndpointsTest.php:95"]}]}
```
*Result*: Failed as expected (`Expected response status code [422] but received 201`). Restored cleanly with `git checkout`.

---

## 4. File Reconciliation & Deviations

### File Count Reconciliation
- Brief expected 12 to 13 files.
- Actual manifest: Exactly 14 code/config/test files (9 new, 5 modified), plus 3 blueprint deliverables in `docs/blueprints/ACC-U4/`:
  - **New (9)**:
    1. `app/Domain/Accounts/Data/LoginData.php`
    2. `app/Domain/Accounts/Data/AuthResultData.php`
    3. `app/Domain/Accounts/Exceptions/InvalidCredentialsException.php`
    4. `app/Domain/Accounts/Services/AuthService.php`
    5. `app/Http/Requests/Auth/LoginRequest.php`
    6. `app/Http/Requests/Auth/RegisterRequest.php`
    7. `app/Http/Controllers/Api/V1/Auth/AuthController.php`
    8. `routes/api/auth.php`
    9. `tests/Feature/Accounts/AuthEndpointsTest.php`
  - **Modified (5)**:
    1. `app/Domain/Accounts/Contracts/UserRepositoryInterface.php`
    2. `app/Domain/Accounts/Repositories/UserRepository.php`
    3. `app/Providers/AppServiceProvider.php`
    4. `bootstrap/app.php`
    5. `routes/api.php` (CR-1)
  - **Blueprints (3)**:
    1. `docs/blueprints/ACC-U4/brief.md`
    2. `docs/blueprints/ACC-U4/plan.md`
    3. `docs/blueprints/ACC-U4/report.md`

### Declared Deviations
1. **CR-1 Seam Approval**: Adding `require __DIR__.'/api/auth.php';` to `routes/api.php` brought the code manifest to 14 files (1 above the brief's 13-file ceiling). Formally approved by Claude in VERIFY r1 (`APPROVED: CR-1`).
2. **Domain-Level Customer Role Re-construction**: In plan §5.6, `AuthService::register` outlined passing `$data` directly to `UserService::store`. To satisfy `ARCHITECTURE.md` §8 and Mandatory Guard 2, `AuthService::register` explicitly reconstructs `UserData` binding `role: UserRole::Customer`. Verified via direct domain test for F4.
3. **Dummy Hash Production Cost Alignment (F1)**: Plan §5.6 proposed cost 4 literal `$2y$04$...`. Fixed in F1 to cost 12 literal `$2y$12$paGwAYoFIuju5tDSdHrPCOqL7KBasBpmD8nF4EN5nhyUQD0J24r6m` tracking `.env.example` `BCRYPT_ROUNDS=12` to prevent an email-existence timing oracle.

---

## 5. Acceptance Criteria to Test Mapping

Generated directly from `tests/Feature/Accounts/AuthEndpointsTest.php`:

| AC ID | Requirement | Exact Test in `tests/Feature/Accounts/AuthEndpointsTest.php` | Assertions | Result |
|---|---|---|---|---|
| **AC-1** | `POST /api/v1/auth/register` creates `Customer` via `UserService::create`, returns 201 with `AuthResultData`, hashes password via cast, and stores normalized email | `it('registers a new customer with normalized email and returns 201 with auth result data')` (line 24) | `assertCreated()`, `assertJsonPath` for name, normalized email, role customer, token type Bearer, expires_in 900, access/refresh tokens present, DB user has `UserRole::Customer` and hash checks | PASS |
| **AC-2** | Register rejects invalid payloads, duplicate emails (including case/whitespace and soft-deleted), weak passwords, and prohibited `role` (`'retailer'`, `'superadmin'`, `'customer'`) with 422 leaving DB unchanged | `it('rejects register request with 422 for validation failures duplicate emails and prohibited role leaving database unchanged')` (line 49) | `assertUnprocessable()` on missing fields, bad email, short password, long password, duplicate email, soft-deleted email, and all 3 role inputs; DB count unchanged | PASS |
| **AC-3** | Registration is atomic: if token issuance fails, database transaction rolls back leaving no user row | `it('rolls back customer creation atomically when token issuance fails')` (line 114) | Throws from fake `TokenIssuer`, `assertStatus(500)`, asserts `User::where('email', ...)->exists()` is false | PASS |
| **AC-4** | `POST /api/v1/auth/login` returns 200 with `AuthResultData` for Customer, Retailer, and Superadmin, supporting email casing and whitespace variants | `it('authenticates customer retailer and superadmin returning 200 with auth result data and email casing tolerance')` (line 144) | `assertOk()` across customer (lowercase), retailer (uppercase), superadmin (whitespace); asserts roles and token pair | PASS |
| **AC-5** | Login credential failures return byte-identical 401 JSON for wrong password, unknown email, and soft-deleted user; malformed inputs return 422; passwords never leaked | `it('returns identical 401 response for wrong password unknown email and soft-deleted user without leaking password')` (line 175) | `assertStatus(401)`, content equality across all 3 failure modes, `assertJson(['message' => 'Invalid credentials.'])`, password not in content; 422 on empty inputs and non-leakage of `'leaked-check-pass'` | PASS |
| **AC-6** | Response shapes strictly adhere to `UserResponseData` and `TokenPairData` per `ARCHITECTURE.md` Section 7 | `it('serializes login and register responses strictly matching architecture specifications without sensitive fields')` (line 218) | Validates exact keys for `data.user` and `data.tokens`, verifies ULID length 26, verifies no internal integer id, no password, no remember_token | PASS |
| **AC-7** | Access token authenticates `GET /api/v1/users/{public_id}` via Bearer header; refresh token is accepted by `/api/v1/auth/refresh` | `it('issues tokens that successfully authenticate user endpoint and rotate via refresh endpoint')` (line 246) | Real Bearer token header authenticates `GET /api/v1/users/{public_id}` with 200; refresh token accepted by `/api/v1/auth/refresh` with 200 and token rotation verified | PASS |
| **AC-8** | Rate limiting enforces 5/min on login and 10/min on register, returning 429 JSON | `it('throttles excessive login and register attempts returning 429')` (line 273) | 5 logins return 401, 6th returns 429; 10 registers return 422, 11th returns 429; deterministic `Cache::flush()` before and after loops | PASS |
| **AC-9** | `UserRepositoryInterface::findByEmail` matches exact normalized email and returns null for soft-deleted user | `it('finds user by exact email excluding soft-deleted records')` (line 298) | Exact normalized email matches active user; mixed case returns null; soft-deleted email returns null; non-existent email returns null | PASS |
| **AC-10** | Quality gates, Layer A checks 15a-15k, and regression suites pass cleanly with no route collisions | `it('registers auth routes without collision against token stream routes')` (line 311) | Inspects routing table for `register`, `login`, `refresh`, `logout` coexisting under `/api/v1/auth` without collisions | PASS |
| **F4** | `AuthService::register` enforces `UserRole::Customer` at the domain level | `it('enforces customer role in auth service register regardless of role passed in user data')` (line 336) | Calls `AuthService::register` directly with `UserRole::Superadmin`; asserts returned DTO and persisted database model both have `UserRole::Customer` | PASS |

---

## 6. Standard Layer A Command Outputs (at HEAD `b143cad`)

### Command 1: `git status --short`
```text
(clean, empty output)
```

### Command 2: `git log --oneline main..HEAD`
```text
b143cad fix(users): update dummy hash cost to 12 and add auth service customer role test
0deb80e docs(users): add ACC-U4 blueprint brief, plan, and report
2c05068 test(users): add comprehensive authentication endpoint feature tests
677ce9b feat(users): add auth form requests, controller, routes, and rate limiters
3ab7a7c feat(users): implement auth service with atomic registration and credential verification
25dbc3b feat(users): add authentication data transfer objects, domain exception, and repository method
```

```powershell
# git rev-parse main HEAD
git rev-parse main HEAD
```
```text
d6fa3e0f98859f25b2a040cc04f3c8b24fa1a932
b143cad927c37c0bbdd4918304a695e60e0b8669
```

### Command 3: `git diff --name-status main...HEAD`
```text
M	app/Domain/Accounts/Contracts/UserRepositoryInterface.php
A	app/Domain/Accounts/Data/AuthResultData.php
A	app/Domain/Accounts/Data/LoginData.php
A	app/Domain/Accounts/Exceptions/InvalidCredentialsException.php
M	app/Domain/Accounts/Repositories/UserRepository.php
A	app/Domain/Accounts/Services/AuthService.php
A	app/Http/Controllers/Api/V1/Auth/AuthController.php
A	app/Http/Requests/Auth/LoginRequest.php
A	app/Http/Requests/Auth/RegisterRequest.php
M	app/Providers/AppServiceProvider.php
M	bootstrap/app.php
A	docs/blueprints/ACC-U4/brief.md
A	docs/blueprints/ACC-U4/plan.md
A	docs/blueprints/ACC-U4/report.md
M	routes/api.php
A	routes/api/auth.php
A	tests/Feature/Accounts/AuthEndpointsTest.php
```

### Command 4: `git diff main...HEAD -- composer.json composer.lock package.json`
```text
(empty output)
```

### Command 5: `git diff main...HEAD --stat -- '*baseline*' phpstan.neon`
```text
(empty output)
```

### Command 6: `Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Http'`
```text
(empty output)
```

### Command 7: `Get-ChildItem app/Http/Controllers -Recurse -Filter *.php | Select-String -SimpleMatch 'Repositories','Facades\DB'`
```text
(empty output)
```

### Command 8: `Get-ChildItem app -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport' | Select-Object -ExpandProperty Path -Unique`
```text
C:\dev\getnow-auth2\app\Domain\Accounts\Tokens\PassportTokenIssuer.php
C:\dev\getnow-auth2\app\Models\User.php
C:\dev\getnow-auth2\app\Providers\TokenServiceProvider.php
```

### Command 9: Strict types verification
```powershell
Get-ChildItem app,bootstrap,routes,tests -Recurse -Filter *.php | Where-Object { -not (Select-String -Path $_.FullName -SimpleMatch 'declare(strict_types=1);' -Quiet) } | Select-Object -ExpandProperty FullName
```
```text
C:\dev\getnow-auth2\app\Http\Controllers\Controller.php
C:\dev\getnow-auth2\bootstrap\cache\packages.php
C:\dev\getnow-auth2\bootstrap\cache\services.php
C:\dev\getnow-auth2\routes\console.php
C:\dev\getnow-auth2\tests\Unit\ExampleTest.php
```

### Command 10: `Get-ChildItem app -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'`
```text
(empty output)
```

### Command 11: `Get-ChildItem tests -Recurse -Filter *.php | Select-String -SimpleMatch '->skip(','->todo(','markTestIncomplete','markTestSkipped'`
```text
(empty output)
```

### Command 12: `git ls-files | Select-String -SimpleMatch 'docker','compose','sail'`
```text
composer.json
composer.lock
```

### Command 13: `git ls-files .env` and `git ls-files storage`
```powershell
git ls-files .env
```
```text
(empty output)
```
```powershell
git ls-files storage
```
```text
storage/app/.gitignore
storage/app/private/.gitignore
storage/app/public/.gitignore
storage/framework/.gitignore
storage/framework/cache/.gitignore
storage/framework/cache/data/.gitignore
storage/framework/sessions/.gitignore
storage/framework/testing/.gitignore
storage/framework/views/.gitignore
storage/logs/.gitignore
```

### Command 14: `composer check`
```text
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pest","result":"passed","tests":107,"passed":107,"assertions":457,"duration_ms":64470}
```

---

## 7. Step-Specific Checks (15a – 15k)

### Check 15a: `git diff --name-only main...HEAD -- database`
```text
(empty output)
```

### Check 15b: Tokens-stream and Contract seam inspection
```powershell
git diff --name-only main...HEAD | Select-String -SimpleMatch 'PassportTokenIssuer','TokenController','TokenServiceProvider','config/passport.php','routes/api/tokens.php','TokenIssuer.php','TokenPairData.php','routes/api.php'
```
```text
routes/api.php
```
*(Only approved CR-1 route seam inclusion matched)*

### Check 15c: `ACC-U1` / `ACC-U2` / `ACC-U3` files modified
```powershell
git diff --name-only main...HEAD -- app/Models app/Domain/Accounts/Enums app/Domain/Accounts/Policies app/Domain/Accounts/Services/UserService.php app/Domain/Accounts/Exceptions/ProtectedUserException.php app/Domain/Accounts/Data/UserData.php app/Domain/Accounts/Data/UserFilterData.php app/Domain/Accounts/Data/UserResponseData.php app/Http/Requests/Users app/Http/Controllers/Api/V1/Users routes/api/users.php
```
```text
(empty output)
```

### Check 15d: Contracts and Repositories diff
```powershell
git diff --name-only main...HEAD -- app/Domain/Accounts/Contracts app/Domain/Accounts/Repositories
```
```text
app/Domain/Accounts/Contracts/UserRepositoryInterface.php
app/Domain/Accounts/Repositories/UserRepository.php
```

### Check 15e: Forbidden domain imports
```powershell
Get-ChildItem app/Domain/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport','App\Http','Illuminate\Http','App\Filament','PassportTokenIssuer'
```
```text
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:17:use Laravel\Passport\AccessToken;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:18:use Laravel\Passport\Bridge\AccessToken as BridgeAccessToken;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:19:use Laravel\Passport\Bridge\Client as BridgeClient;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:20:use Laravel\Passport\Bridge\RefreshToken as BridgeRefreshToken;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:21:use Laravel\Passport\Client;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:22:use Laravel\Passport\Passport;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:23:use Laravel\Passport\RefreshToken;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:24:use Laravel\Passport\Token;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:25:use Laravel\Passport\TransientToken;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:31:final class PassportTokenIssuer implements TokenIssuer
```
*(Only imported tokens implementation matched; non-tokens domain classes have zero forbidden imports)*

### Check 15f: Controller isolation
```powershell
Select-String -Path app/Http/Controllers/Api/V1/Auth/AuthController.php -SimpleMatch 'UserRepository','Illuminate\Database\Eloquent','Laravel\Passport','PassportTokenIssuer','Hash::','Hasher'
```
```text
(empty output)
```

### Check 15g: `Hash::make` in accounts domain, HTTP layer, or accounts tests
```powershell
Get-ChildItem app/Domain/Accounts,app/Http,tests/Feature/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'
```
```text
(empty output)
```

### Check 15h: Role string literals in accounts domain, requests, or controllers
```powershell
Get-ChildItem app/Domain/Accounts,app/Http/Controllers/Api/V1/Auth,app/Http/Requests -Recurse -Filter *.php | Select-String -SimpleMatch "'superadmin'","'retailer'","'customer'"
```
```text
app\Domain\Accounts\Enums\UserRole.php:9:    case Superadmin = 'superadmin';
app\Domain\Accounts\Enums\UserRole.php:10:    case Retailer = 'retailer';
app\Domain\Accounts\Enums\UserRole.php:11:    case Customer = 'customer';
```

### Check 15i: Logging and debug calls near credentials
```powershell
Get-ChildItem app/Domain/Accounts,app/Http -Recurse -Filter *.php | Select-String -SimpleMatch 'Log::','logger(','dd(','dump(','var_dump('
```
```text
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:150:        $accessExpiry = (new DateTimeImmutable)->add(new DateInterval('PT'.$accessTtlSeconds.'S'));
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:151:        $refreshExpiry = (new DateTimeImmutable)->add(new DateInterval('P'.$refreshTtlDays.'D'));
```

### Check 15j: Route list under `/api/v1/auth`
```powershell
php artisan route:list --path=api/v1/auth
```
```text
 POST api/v1/auth/login .. Api\V1\Auth\AuthController@login
 POST api/v1/auth/logout .. Api\V1\Auth\TokenController@logout
 POST api/v1/auth/refresh .. Api\V1\Auth\TokenController@refresh
 POST api/v1/auth/register .. Api\V1\Auth\AuthController@register

 Showing [4] routes
```

### Check 15k: Regression suite execution
```powershell
php artisan test --compact tests/Feature/Tokens tests/Arch tests/Feature/Accounts
```
```text
{"tool":"pest","result":"passed","tests":103,"passed":103,"assertions":442,"duration_ms":31204}
```

---

## 8. Raw Git Diff (`main...HEAD -- app bootstrap routes tests`)

```diff
diff --git a/app/Domain/Accounts/Contracts/UserRepositoryInterface.php b/app/Domain/Accounts/Contracts/UserRepositoryInterface.php
index 0e75535..3d06afa 100644
--- a/app/Domain/Accounts/Contracts/UserRepositoryInterface.php
+++ b/app/Domain/Accounts/Contracts/UserRepositoryInterface.php
@@ -18,6 +18,8 @@ public function index(UserFilterData $filter): LengthAwarePaginator;
 
     public function show(string $publicId): ?User;
 
+    public function findByEmail(string $email): ?User;
+
     public function store(UserData $data): User;
 
     public function update(User $user, UserData $data): User;
diff --git a/app/Domain/Accounts/Data/AuthResultData.php b/app/Domain/Accounts/Data/AuthResultData.php
new file mode 100644
index 0000000..96bb8ca
--- /dev/null
+++ b/app/Domain/Accounts/Data/AuthResultData.php
@@ -0,0 +1,18 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Domain\Accounts\Data;
+
+use Spatie\LaravelData\Attributes\MapName;
+use Spatie\LaravelData\Data;
+use Spatie\LaravelData\Mappers\SnakeCaseMapper;
+
+#[MapName(SnakeCaseMapper::class)]
+final class AuthResultData extends Data
+{
+    public function __construct(
+        public UserResponseData $user,
+        public TokenPairData $tokens,
+    ) {}
+}
diff --git a/app/Domain/Accounts/Data/LoginData.php b/app/Domain/Accounts/Data/LoginData.php
new file mode 100644
index 0000000..95d096d
--- /dev/null
+++ b/app/Domain/Accounts/Data/LoginData.php
@@ -0,0 +1,15 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Domain\Accounts\Data;
+
+use Spatie\LaravelData\Data;
+
+final class LoginData extends Data
+{
+    public function __construct(
+        public string $email,
+        public string $password,
+    ) {}
+}
diff --git a/app/Domain/Accounts/Exceptions/InvalidCredentialsException.php b/app/Domain/Accounts/Exceptions/InvalidCredentialsException.php
new file mode 100644
index 0000000..7fae634
--- /dev/null
+++ b/app/Domain/Accounts/Exceptions/InvalidCredentialsException.php
@@ -0,0 +1,15 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Domain\Accounts\Exceptions;
+
+use RuntimeException;
+
+final class InvalidCredentialsException extends RuntimeException
+{
+    public function __construct(string $message = 'Invalid credentials.')
+    {
+        parent::__construct($message);
+    }
+}
diff --git a/app/Domain/Accounts/Repositories/UserRepository.php b/app/Domain/Accounts/Repositories/UserRepository.php
index 52a1743..796f028 100644
--- a/app/Domain/Accounts/Repositories/UserRepository.php
+++ b/app/Domain/Accounts/Repositories/UserRepository.php
@@ -49,6 +49,11 @@ public function show(string $publicId): ?User
         return User::query()->where('public_id', $publicId)->first();
     }
 
+    public function findByEmail(string $email): ?User
+    {
+        return User::query()->where('email', $email)->first();
+    }
+
     public function store(UserData $data): User
     {
         $attributes = [
diff --git a/app/Domain/Accounts/Services/AuthService.php b/app/Domain/Accounts/Services/AuthService.php
new file mode 100644
index 0000000..239c236
--- /dev/null
+++ b/app/Domain/Accounts/Services/AuthService.php
@@ -0,0 +1,69 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Domain\Accounts\Services;
+
+use App\Domain\Accounts\Contracts\TokenIssuer;
+use App\Domain\Accounts\Contracts\UserRepositoryInterface;
+use App\Domain\Accounts\Data\AuthResultData;
+use App\Domain\Accounts\Data\LoginData;
+use App\Domain\Accounts\Data\UserData;
+use App\Domain\Accounts\Data\UserResponseData;
+use App\Domain\Accounts\Enums\UserRole;
+use App\Domain\Accounts\Exceptions\InvalidCredentialsException;
+use Illuminate\Contracts\Hashing\Hasher;
+use Illuminate\Support\Facades\DB;
+
+final class AuthService
+{
+    /**
+     * Constant dummy bcrypt hash matching production BCRYPT_ROUNDS (cost 12 per .env.example).
+     * The cost must track production rounds so timing equalization prevents an email-existence oracle.
+     */
+    private const string DUMMY_HASH = '$2y$12$paGwAYoFIuju5tDSdHrPCOqL7KBasBpmD8nF4EN5nhyUQD0J24r6m';
+
+    public function __construct(
+        private readonly UserService $userService,
+        private readonly UserRepositoryInterface $userRepository,
+        private readonly TokenIssuer $tokenIssuer,
+        private readonly Hasher $hasher,
+    ) {}
+
+    public function register(UserData $data): AuthResultData
+    {
+        return DB::transaction(function () use ($data): AuthResultData {
+            $customerData = new UserData(
+                name: $data->name,
+                email: $data->email,
+                password: $data->password,
+                role: UserRole::Customer,
+            );
+
+            $user = $this->userService->store($customerData);
+            $tokens = $this->tokenIssuer->issue($user);
+
+            return new AuthResultData(UserResponseData::fromModel($user), $tokens);
+        });
+    }
+
+    public function login(LoginData $data): AuthResultData
+    {
+        $email = strtolower(trim($data->email));
+        $user = $this->userRepository->findByEmail($email);
+
+        if ($user === null) {
+            $this->hasher->check($data->password, self::DUMMY_HASH);
+
+            throw new InvalidCredentialsException;
+        }
+
+        if (! $this->hasher->check($data->password, $user->password)) {
+            throw new InvalidCredentialsException;
+        }
+
+        $tokens = $this->tokenIssuer->issue($user);
+
+        return new AuthResultData(UserResponseData::fromModel($user), $tokens);
+    }
+}
diff --git a/app/Http/Controllers/Api/V1/Auth/AuthController.php b/app/Http/Controllers/Api/V1/Auth/AuthController.php
new file mode 100644
index 0000000..66f8dbf
--- /dev/null
+++ b/app/Http/Controllers/Api/V1/Auth/AuthController.php
@@ -0,0 +1,36 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Http\Controllers\Api\V1\Auth;
+
+use App\Domain\Accounts\Services\AuthService;
+use App\Http\Controllers\Controller;
+use App\Http\Requests\Auth\LoginRequest;
+use App\Http\Requests\Auth\RegisterRequest;
+use Illuminate\Http\JsonResponse;
+
+final class AuthController extends Controller
+{
+    public function __construct(
+        private readonly AuthService $authService,
+    ) {}
+
+    public function register(RegisterRequest $request): JsonResponse
+    {
+        $result = $this->authService->register($request->toDto());
+
+        return response()->json([
+            'data' => $result->toArray(),
+        ], 201);
+    }
+
+    public function login(LoginRequest $request): JsonResponse
+    {
+        $result = $this->authService->login($request->toDto());
+
+        return response()->json([
+            'data' => $result->toArray(),
+        ], 200);
+    }
+}
diff --git a/app/Http/Requests/Auth/LoginRequest.php b/app/Http/Requests/Auth/LoginRequest.php
new file mode 100644
index 0000000..89188de
--- /dev/null
+++ b/app/Http/Requests/Auth/LoginRequest.php
@@ -0,0 +1,44 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Http\Requests\Auth;
+
+use App\Domain\Accounts\Data\LoginData;
+use Illuminate\Foundation\Http\FormRequest;
+
+final class LoginRequest extends FormRequest
+{
+    public function authorize(): bool
+    {
+        return true;
+    }
+
+    protected function prepareForValidation(): void
+    {
+        if ($this->has('email') && is_string($this->input('email'))) {
+            $this->merge([
+                'email' => strtolower(trim((string) $this->input('email'))),
+            ]);
+        }
+    }
+
+    /**
+     * @return array<string, array<int, mixed>>
+     */
+    public function rules(): array
+    {
+        return [
+            'email' => ['required', 'string', 'email', 'max:255'],
+            'password' => ['required', 'string', 'max:72'],
+        ];
+    }
+
+    public function toDto(): LoginData
+    {
+        return new LoginData(
+            email: (string) $this->validated('email'),
+            password: (string) $this->validated('password'),
+        );
+    }
+}
diff --git a/app/Http/Requests/Auth/RegisterRequest.php b/app/Http/Requests/Auth/RegisterRequest.php
new file mode 100644
index 0000000..62af902
--- /dev/null
+++ b/app/Http/Requests/Auth/RegisterRequest.php
@@ -0,0 +1,56 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Http\Requests\Auth;
+
+use App\Domain\Accounts\Data\UserData;
+use App\Domain\Accounts\Enums\UserRole;
+use Illuminate\Foundation\Http\FormRequest;
+use Illuminate\Validation\Rule;
+
+final class RegisterRequest extends FormRequest
+{
+    public function authorize(): bool
+    {
+        return true;
+    }
+
+    protected function prepareForValidation(): void
+    {
+        if ($this->has('email') && is_string($this->input('email'))) {
+            $this->merge([
+                'email' => strtolower(trim((string) $this->input('email'))),
+            ]);
+        }
+    }
+
+    /**
+     * @return array<string, array<int, mixed>>
+     */
+    public function rules(): array
+    {
+        return [
+            'name' => ['required', 'string', 'max:255'],
+            'email' => [
+                'required',
+                'string',
+                'email',
+                'max:255',
+                Rule::unique('users', 'email'),
+            ],
+            'password' => ['required', 'string', 'min:8', 'max:72'],
+            'role' => ['prohibited'],
+        ];
+    }
+
+    public function toDto(): UserData
+    {
+        return new UserData(
+            name: (string) $this->validated('name'),
+            email: (string) $this->validated('email'),
+            password: (string) $this->validated('password'),
+            role: UserRole::Customer,
+        );
+    }
+}
diff --git a/app/Providers/AppServiceProvider.php b/app/Providers/AppServiceProvider.php
index 6d729fa..0f81b6e 100644
--- a/app/Providers/AppServiceProvider.php
+++ b/app/Providers/AppServiceProvider.php
@@ -7,9 +7,12 @@
 use App\Domain\Accounts\Policies\UserPolicy;
 use App\Models\User;
 use Carbon\CarbonImmutable;
+use Illuminate\Cache\RateLimiting\Limit;
+use Illuminate\Http\Request;
 use Illuminate\Support\Facades\Date;
 use Illuminate\Support\Facades\DB;
 use Illuminate\Support\Facades\Gate;
+use Illuminate\Support\Facades\RateLimiter;
 use Illuminate\Support\ServiceProvider;
 use Illuminate\Validation\Rules\Password;
 
@@ -31,6 +34,17 @@ public function boot(): void
         $this->configureDefaults();
 
         Gate::policy(User::class, UserPolicy::class);
+
+        RateLimiter::for('auth-login', function (Request $request): Limit {
+            $rawEmail = $request->input('email');
+            $email = is_string($rawEmail) ? strtolower(trim($rawEmail)) : '';
+
+            return Limit::perMinute(5)->by($email.'|'.$request->ip());
+        });
+
+        RateLimiter::for('auth-register', function (Request $request): Limit {
+            return Limit::perMinute(10)->by((string) $request->ip());
+        });
     }
 
     /**
diff --git a/bootstrap/app.php b/bootstrap/app.php
index 9034dab..a81230f 100644
--- a/bootstrap/app.php
+++ b/bootstrap/app.php
@@ -2,6 +2,7 @@
 
 declare(strict_types=1);
 
+use App\Domain\Accounts\Exceptions\InvalidCredentialsException;
 use App\Domain\Accounts\Exceptions\ProtectedUserException;
 use Illuminate\Foundation\Application;
 use Illuminate\Foundation\Configuration\Exceptions;
@@ -31,4 +32,12 @@
                 ], 403);
             }
         });
+
+        $exceptions->render(function (InvalidCredentialsException $e, Request $request) {
+            if ($request->is('api/*') || $request->expectsJson()) {
+                return response()->json([
+                    'message' => 'Invalid credentials.',
+                ], 401);
+            }
+        });
     })->create();
diff --git a/routes/api.php b/routes/api.php
index 166ae18..997921e 100644
--- a/routes/api.php
+++ b/routes/api.php
@@ -4,3 +4,4 @@
 
 require __DIR__.'/api/tokens.php';
 require __DIR__.'/api/users.php';
+require __DIR__.'/api/auth.php';
diff --git a/routes/api/auth.php b/routes/api/auth.php
new file mode 100644
index 0000000..93d7611
--- /dev/null
+++ b/routes/api/auth.php
@@ -0,0 +1,14 @@
+<?php
+
+declare(strict_types=1);
+
+use App\Http\Controllers\Api\V1\Auth\AuthController;
+use Illuminate\Support\Facades\Route;
+
+Route::prefix('auth')->group(function (): void {
+    Route::post('register', [AuthController::class, 'register'])
+        ->middleware('throttle:auth-register');
+
+    Route::post('login', [AuthController::class, 'login'])
+        ->middleware('throttle:auth-login');
+});
diff --git a/tests/Feature/Accounts/AuthEndpointsTest.php b/tests/Feature/Accounts/AuthEndpointsTest.php
new file mode 100644
index 0000000..8e52d26
--- /dev/null
+++ b/tests/Feature/Accounts/AuthEndpointsTest.php
@@ -0,0 +1,350 @@
+<?php
+
+declare(strict_types=1);
+
+use App\Domain\Accounts\Contracts\TokenIssuer;
+use App\Domain\Accounts\Contracts\UserRepositoryInterface;
+use App\Domain\Accounts\Data\TokenPairData;
+use App\Domain\Accounts\Data\UserData;
+use App\Domain\Accounts\Enums\UserRole;
+use App\Domain\Accounts\Services\AuthService;
+use App\Models\User;
+use Illuminate\Foundation\Testing\RefreshDatabase;
+use Illuminate\Support\Facades\Cache;
+use Illuminate\Support\Facades\Route;
+use Tests\Support\InteractsWithPassportKeys;
+
+uses(RefreshDatabase::class, InteractsWithPassportKeys::class);
+
+beforeEach(function (): void {
+    $this->ensurePassportKeys();
+    Cache::flush();
+});
+
+it('registers a new customer with normalized email and returns 201 with auth result data', function (): void {
+    $payload = [
+        'name' => 'John Doe',
+        'email' => '  JOHN.DOE@EXAMPLE.COM  ',
+        'password' => 'secret1234',
+    ];
+
+    $response = $this->postJson('/api/v1/auth/register', $payload);
+
+    $response->assertCreated();
+    $response->assertJsonPath('data.user.name', 'John Doe');
+    $response->assertJsonPath('data.user.email', 'john.doe@example.com');
+    $response->assertJsonPath('data.user.role', 'customer');
+    $response->assertJsonPath('data.tokens.token_type', 'Bearer');
+    $response->assertJsonPath('data.tokens.expires_in', 900);
+
+    expect($response->json('data.tokens.access_token'))->toBeString()
+        ->and($response->json('data.tokens.refresh_token'))->toBeString();
+
+    $persisted = User::where('email', 'john.doe@example.com')->first();
+    expect($persisted)->not->toBeNull()
+        ->and($persisted->role)->toBe(UserRole::Customer)
+        ->and(app('hash')->check('secret1234', $persisted->password))->toBeTrue();
+});
+
+it('rejects register request with 422 for validation failures duplicate emails and prohibited role leaving database unchanged', function (): void {
+    User::factory()->customer()->create(['email' => 'existing@example.com']);
+    $deleted = User::factory()->customer()->create(['email' => 'deleted@example.com']);
+    $deleted->delete();
+
+    $initialCount = User::withTrashed()->count();
+
+    $this->postJson('/api/v1/auth/register', [])
+        ->assertUnprocessable()
+        ->assertJsonValidationErrors(['name', 'email', 'password']);
+
+    $this->postJson('/api/v1/auth/register', [
+        'name' => 'Invalid Email',
+        'email' => 'not-an-email',
+        'password' => 'secret1234',
+    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
+
+    $this->postJson('/api/v1/auth/register', [
+        'name' => 'Short Pass',
+        'email' => 'short@example.com',
+        'password' => 'short',
+    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
+
+    $this->postJson('/api/v1/auth/register', [
+        'name' => 'Long Pass',
+        'email' => 'long@example.com',
+        'password' => str_repeat('a', 73),
+    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
+
+    $this->postJson('/api/v1/auth/register', [
+        'name' => 'Existing User',
+        'email' => 'EXISTING@example.com',
+        'password' => 'secret1234',
+    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
+
+    $this->postJson('/api/v1/auth/register', [
+        'name' => 'Deleted User',
+        'email' => 'deleted@example.com',
+        'password' => 'secret1234',
+    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
+
+    $this->postJson('/api/v1/auth/register', [
+        'name' => 'Retailer Role',
+        'email' => 'role1@example.com',
+        'password' => 'secret1234',
+        'role' => 'retailer',
+    ])->assertUnprocessable()->assertJsonValidationErrors(['role']);
+
+    $this->postJson('/api/v1/auth/register', [
+        'name' => 'Superadmin Role',
+        'email' => 'role2@example.com',
+        'password' => 'secret1234',
+        'role' => 'superadmin',
+    ])->assertUnprocessable()->assertJsonValidationErrors(['role']);
+
+    $this->postJson('/api/v1/auth/register', [
+        'name' => 'Customer Role',
+        'email' => 'role3@example.com',
+        'password' => 'secret1234',
+        'role' => 'customer',
+    ])->assertUnprocessable()->assertJsonValidationErrors(['role']);
+
+    expect(User::withTrashed()->count())->toBe($initialCount);
+});
+
+it('rolls back customer creation atomically when token issuance fails', function (): void {
+    $throwingIssuer = new class implements TokenIssuer
+    {
+        public function issue(User $user): TokenPairData
+        {
+            throw new RuntimeException('Token minting failed.');
+        }
+
+        public function refresh(string $refreshToken): TokenPairData
+        {
+            throw new RuntimeException('Unused');
+        }
+
+        public function revoke(User $user): void {}
+
+        public function revokeAll(User $user): void {}
+    };
+
+    $this->app->instance(TokenIssuer::class, $throwingIssuer);
+
+    $response = $this->postJson('/api/v1/auth/register', [
+        'name' => 'Fail User',
+        'email' => 'fail@example.com',
+        'password' => 'secret1234',
+    ]);
+
+    $response->assertStatus(500);
+    expect(User::where('email', 'fail@example.com')->exists())->toBeFalse();
+});
+
+it('authenticates customer retailer and superadmin returning 200 with auth result data and email casing tolerance', function (): void {
+    $customer = User::factory()->customer()->create(['email' => 'cust@example.com']);
+    $retailer = User::factory()->retailer()->create(['email' => 'ret@example.com']);
+    $admin = User::factory()->superadmin()->create(['email' => 'admin@example.com']);
+
+    $resCust = $this->postJson('/api/v1/auth/login', [
+        'email' => $customer->email,
+        'password' => 'password',
+    ])->assertOk();
+
+    expect($resCust->json('data.user.role'))->toBe('customer')
+        ->and($resCust->json('data.tokens.token_type'))->toBe('Bearer')
+        ->and($resCust->json('data.tokens.expires_in'))->toBe(900)
+        ->and($resCust->json('data.tokens.access_token'))->toBeString()
+        ->and($resCust->json('data.tokens.refresh_token'))->toBeString();
+
+    $resRet = $this->postJson('/api/v1/auth/login', [
+        'email' => strtoupper($retailer->email),
+        'password' => 'password',
+    ])->assertOk();
+
+    expect($resRet->json('data.user.role'))->toBe('retailer');
+
+    $resAdmin = $this->postJson('/api/v1/auth/login', [
+        'email' => '  '.$admin->email.'  ',
+        'password' => 'password',
+    ])->assertOk();
+
+    expect($resAdmin->json('data.user.role'))->toBe('superadmin');
+});
+
+it('returns identical 401 response for wrong password unknown email and soft-deleted user without leaking password', function (): void {
+    $active = User::factory()->customer()->create(['email' => 'active@example.com']);
+    $deleted = User::factory()->customer()->create(['email' => 'deleted@example.com']);
+    $deleted->delete();
+
+    $res1 = $this->postJson('/api/v1/auth/login', [
+        'email' => 'unknown@example.com',
+        'password' => 'mypassword',
+    ]);
+
+    $res2 = $this->postJson('/api/v1/auth/login', [
+        'email' => $active->email,
+        'password' => 'wrongpassword',
+    ]);
+
+    $res3 = $this->postJson('/api/v1/auth/login', [
+        'email' => $deleted->email,
+        'password' => 'password',
+    ]);
+
+    $res1->assertStatus(401);
+    $res2->assertStatus(401);
+    $res3->assertStatus(401);
+
+    expect($res1->getContent())->toBe($res2->getContent())
+        ->and($res2->getContent())->toBe($res3->getContent())
+        ->and($res1->json('message'))->toBe('Invalid credentials.')
+        ->and($res1->getContent())->not->toContain('mypassword');
+
+    $this->postJson('/api/v1/auth/login', [])
+        ->assertUnprocessable()
+        ->assertJsonValidationErrors(['email', 'password']);
+
+    $resLeak = $this->postJson('/api/v1/auth/login', [
+        'email' => 'invalid-email',
+        'password' => 'leaked-check-pass',
+    ])->assertUnprocessable();
+
+    expect($resLeak->getContent())->not->toContain('leaked-check-pass');
+});
+
+it('serializes login and register responses strictly matching architecture specifications without sensitive fields', function (): void {
+    $user = User::factory()->customer()->create(['email' => 'spec-user@example.com']);
+
+    $loginRes = $this->postJson('/api/v1/auth/login', [
+        'email' => $user->email,
+        'password' => 'password',
+    ])->assertOk();
+
+    $regRes = $this->postJson('/api/v1/auth/register', [
+        'name' => 'Spec Register',
+        'email' => 'spec-reg@example.com',
+        'password' => 'password123',
+    ])->assertCreated();
+
+    foreach ([$loginRes, $regRes] as $res) {
+        /** @var array<string, mixed> $userData */
+        $userData = $res->json('data.user');
+        /** @var array<string, mixed> $tokensData */
+        $tokensData = $res->json('data.tokens');
+
+        expect(array_keys($userData))->toBe(['id', 'name', 'email', 'role', 'created_at', 'updated_at'])
+            ->and(array_keys($tokensData))->toBe(['access_token', 'refresh_token', 'token_type', 'expires_in'])
+            ->and(strlen((string) $userData['id']))->toBe(26)
+            ->and($res->getContent())->not->toContain('remember_token')
+            ->and(isset($userData['password']))->toBeFalse();
+    }
+});
+
+it('issues tokens that successfully authenticate user endpoint and rotate via refresh endpoint', function (): void {
+    $admin = User::factory()->superadmin()->create(['email' => 'admin-e2e@example.com']);
+
+    $loginRes = $this->postJson('/api/v1/auth/login', [
+        'email' => $admin->email,
+        'password' => 'password',
+    ])->assertOk();
+
+    /** @var string $accessToken */
+    $accessToken = $loginRes->json('data.tokens.access_token');
+    /** @var string $refreshToken */
+    $refreshToken = $loginRes->json('data.tokens.refresh_token');
+
+    $this->withHeader('Authorization', 'Bearer '.$accessToken)
+        ->getJson('/api/v1/users/'.$admin->public_id)
+        ->assertOk()
+        ->assertJsonPath('data.id', $admin->public_id);
+
+    $refreshRes = $this->postJson('/api/v1/auth/refresh', [
+        'refresh_token' => $refreshToken,
+    ])->assertOk();
+
+    expect($refreshRes->json('data.tokens.access_token'))->toBeString()
+        ->and($refreshRes->json('data.tokens.refresh_token'))->toBeString()
+        ->and($refreshRes->json('data.tokens.refresh_token'))->not->toBe($refreshToken);
+});
+
+it('throttles excessive login and register attempts returning 429', function (): void {
+    Cache::flush();
+
+    for ($i = 0; $i < 5; $i++) {
+        $this->postJson('/api/v1/auth/login', [
+            'email' => 'throttle@example.com',
+            'password' => 'wrong',
+        ])->assertStatus(401);
+    }
+
+    $this->postJson('/api/v1/auth/login', [
+        'email' => 'throttle@example.com',
+        'password' => 'wrong',
+    ])->assertStatus(429);
+
+    Cache::flush();
+
+    for ($i = 0; $i < 10; $i++) {
+        $this->postJson('/api/v1/auth/register', [])->assertStatus(422);
+    }
+
+    $this->postJson('/api/v1/auth/register', [])->assertStatus(429);
+
+    Cache::flush();
+});
+
+it('finds user by exact email excluding soft-deleted records', function (): void {
+    $active = User::factory()->customer()->create(['email' => 'target@example.com']);
+    $deleted = User::factory()->customer()->create(['email' => 'deleted-repo@example.com']);
+    $deleted->delete();
+
+    $repo = app(UserRepositoryInterface::class);
+
+    expect($repo->findByEmail('target@example.com')?->id)->toBe($active->id)
+        ->and($repo->findByEmail('TARGET@EXAMPLE.COM'))->toBeNull()
+        ->and($repo->findByEmail('deleted-repo@example.com'))->toBeNull()
+        ->and($repo->findByEmail('nonexistent@example.com'))->toBeNull();
+});
+
+it('registers auth routes without collision against token stream routes', function (): void {
+    $routes = collect(Route::getRoutes()->getRoutes())
+        ->map(fn ($route) => [
+            'uri' => $route->uri(),
+            'methods' => $route->methods(),
+            'action' => $route->getActionName(),
+        ]);
+
+    $registerRoute = $routes->firstWhere('uri', 'api/v1/auth/register');
+    $loginRoute = $routes->firstWhere('uri', 'api/v1/auth/login');
+    $refreshRoute = $routes->firstWhere('uri', 'api/v1/auth/refresh');
+    $logoutRoute = $routes->firstWhere('uri', 'api/v1/auth/logout');
+
+    expect($registerRoute)->not->toBeNull()
+        ->and($registerRoute['action'])->toContain('AuthController@register')
+        ->and($loginRoute)->not->toBeNull()
+        ->and($loginRoute['action'])->toContain('AuthController@login')
+        ->and($refreshRoute)->not->toBeNull()
+        ->and($refreshRoute['action'])->toContain('TokenController@refresh')
+        ->and($logoutRoute)->not->toBeNull()
+        ->and($logoutRoute['action'])->toContain('TokenController@logout');
+});
+
+it('enforces customer role in auth service register regardless of role passed in user data', function (): void {
+    $service = app(AuthService::class);
+
+    $userData = new UserData(
+        name: 'Attacker Trying Superadmin',
+        email: 'attacker@example.com',
+        password: 'password123',
+        role: UserRole::Superadmin,
+    );
+
+    $result = $service->register($userData);
+
+    expect($result->user->role)->toBe(UserRole::Customer);
+
+    $persisted = User::where('email', 'attacker@example.com')->first();
+    expect($persisted)->not->toBeNull()
+        ->and($persisted->role)->toBe(UserRole::Customer);
+});
+```
+
