# [ACC-U3 | IMPL-REPORT | Gemini]

# Implementation Report: ACC-U3 User HTTP Layer

- **Step ID**: `ACC-U3`
- **Stream**: Users (`[U]`)
- **Status**: VERIFY
- **Date**: 2026-10-26
- **Related Plan**: `docs/blueprints/ACC-U3/plan.md` (v1)
- **Base Branch**: `feat/users-domain-layer` (`07f7f2b`)
- **Head SHA**: `945a5f9`
- **Docs diffs**: Deferred to G4 closeout
- **Gaps**: None

---

## 1. Summary of Changes

This step implements the Users-stream HTTP layer exposing `/api/v1/users` endpoints in accordance with `ARCHITECTURE.md`, `RULE_REGISTER.md`, and the approved frozen plan `ACC-U3 v1`:

1. **User Response DTO (`app/Domain/Accounts/Data/UserResponseData.php`)**:
   - `UserResponseData`: Extends `Spatie\LaravelData\Data` with `#[MapName(SnakeCaseMapper::class)]`.
   - Maps public user representation: `id` (holds `public_id` ULID), `name`, `email`, `role` (`UserRole`), `created_at`, and `updated_at`.
   - Types `$createdAt` and `$updatedAt` with `?\Carbon\CarbonInterface` so both `CarbonImmutable` and `Carbon` instances satisfy strict typing and PHPStan Level 8 without runtime `TypeError`.
   - Excludes internal integer `id`, `password`, `remember_token`, and `deleted_at`.

2. **Authorization Policy & Policy Discovery (`app/Domain/Accounts/Policies/UserPolicy.php`, `app/Providers/AppServiceProvider.php`)**:
   - `UserPolicy`: Implements role authorization matrix per OD-1. `viewAny`, `create`, `delete` are strictly superadmin-only. `view` and `update` permit superadmin or self (`$actor->is($target)`).
   - `AppServiceProvider`: Registers `Gate::policy(User::class, UserPolicy::class)` explicitly in `boot()`, and enforces `declare(strict_types=1);`.

3. **Form Requests (`app/Http/Requests/Users/StoreUserRequest.php`, `app/Http/Requests/Users/UpdateUserRequest.php`)**:
   - `StoreUserRequest`: Validates input (`name`, `email`, `password`, `role`); normalises `email` (trim + lowercase) in `prepareForValidation()` prior to `Rule::unique('users', 'email')`; validates `role` with `Rule::enum(UserRole::class)` (OD-2); provides `toDto(): UserData`.
   - `UpdateUserRequest`: Validates partial updates using `sometimes` rules; normalises `email` when provided; ignores target user id in unique email rule; builds `UserData` with `Optional` for omitted properties (Rule `STD4`, `STD5`).

4. **HTTP Controller & Routes (`app/Http/Controllers/Api/V1/Users/UserController.php`, `routes/api/users.php`, `routes/api.php`, `bootstrap/app.php`)**:
   - `UserController`: Injects `UserService`. Implements `index`, `store`, `show`, `update`, `destroy` method parity (`LAY5`). Authorizes through `UserPolicy`.
   - In `update`, guards against role self-escalation: non-superadmins attempting to send a `role` field are rejected with 403 Forbidden.
   - Envelopes single resource responses in `{"data": UserResponseData}` matching paginator and test expectations (Execution Guard 2).
   - In `destroy`, returns 204 No Content with empty body.
   - `routes/api/users.php`: Groups routes under `users` with `auth:api` middleware and `{user:public_id}` route model binding.
   - `routes/api.php`: Requires `routes/api/users.php`.
   - `bootstrap/app.php`: Maps `ProtectedUserException` to 403 Forbidden JSON response and adds `declare(strict_types=1);`.

5. **Feature Tests (`tests/Feature/Accounts/UserControllerTest.php`)**:
   - 18 comprehensive Pest test cases covering AC-1 through AC-10 (serialization, unauthenticated 401 across all 5 endpoints, authorization matrix, self view/update, role modification ban, pagination/filtering, 422 validation, case/whitespace duplicate emails, soft-deleted emails, 404 on integer id/unknown ULID/soft-deleted user, partial updates, 204 soft deletion, and superadmin protections).

---

## 2. Commit Log

```text
945a5f9 test(users): add user controller endpoint and authorization feature tests
2de8659 feat(users): add user form requests, controller, and routes
1a11a54 feat(users): add user response data and authorization policy
```

---

## 3. Layer A Command Outputs

### 1. `git status --short`
```text
(empty - clean working directory)
```

### 2. `git log --oneline feat/users-domain-layer..HEAD`
```text
945a5f9 test(users): add user controller endpoint and authorization feature tests
2de8659 feat(users): add user form requests, controller, and routes
1a11a54 feat(users): add user response data and authorization policy
```

### 3. `git diff --name-status feat/users-domain-layer...HEAD`
```text
A	app/Domain/Accounts/Data/UserResponseData.php
A	app/Domain/Accounts/Policies/UserPolicy.php
A	app/Http/Controllers/Api/V1/Users/UserController.php
A	app/Http/Requests/Users/StoreUserRequest.php
A	app/Http/Requests/Users/UpdateUserRequest.php
M	app/Providers/AppServiceProvider.php
M	bootstrap/app.php
A	docs/blueprints/ACC-U3/brief.md
A	docs/blueprints/ACC-U3/plan.md
M	routes/api.php
A	routes/api/users.php
A	tests/Feature/Accounts/UserControllerTest.php
```

### 4. `git diff feat/users-domain-layer...HEAD -- composer.json composer.lock package.json`
```text
(empty)
```

### 5. `git diff feat/users-domain-layer...HEAD --stat -- '*baseline*' phpstan.neon`
```text
(empty)
```

### 6. `Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Http'`
```text
(empty)
```

### 7. `Get-ChildItem app/Http/Controllers -Recurse -Filter *.php | Select-String -SimpleMatch 'Repositories','Facades\DB'`
```text
(empty)
```

### 8. `Get-ChildItem app -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport' | Select-Object -ExpandProperty Path -Unique`
```text
C:\dev\getnow-auth2\app\Domain\Accounts\Tokens\PassportTokenIssuer.php
C:\dev\getnow-auth2\app\Models\User.php
C:\dev\getnow-auth2\app\Providers\TokenServiceProvider.php
```

### 9. Strict types check
`Get-ChildItem app,bootstrap,routes,tests -Recurse -Filter *.php | Where-Object { -not (Select-String -Path $_.FullName -SimpleMatch 'declare(strict_types=1);' -Quiet) } | Select-Object -ExpandProperty FullName`
```text
C:\dev\getnow-auth2\app\Http\Controllers\Controller.php
C:\dev\getnow-auth2\bootstrap\cache\packages.php
C:\dev\getnow-auth2\bootstrap\cache\services.php
C:\dev\getnow-auth2\routes\console.php
C:\dev\getnow-auth2\tests\Pest.php
C:\dev\getnow-auth2\tests\Unit\ExampleTest.php
```
*(All 6 files are pre-existing baseline files from ACC-C1. Zero new or modified files lack strict types.)*

### 10. `Get-ChildItem app -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'`
```text
(empty)
```

### 11. `Get-ChildItem tests -Recurse -Filter *.php | Select-String -SimpleMatch '->skip(','->todo(','markTestIncomplete','markTestSkipped'`
```text
(empty)
```

### 12. `git ls-files | Select-String -SimpleMatch 'docker','compose','sail'`
```text
composer.json
composer.lock
```

### 13. `git ls-files .env; git ls-files storage`
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

### 14. `composer check`
```text
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pest","result":"passed","tests":95,"passed":95,"assertions":334,"duration_ms":13894}
```

---

## 4. Step-Specific Checks (15a–15i)

### 15a. `git diff --name-only feat/users-domain-layer...HEAD -- database/migrations`
```text
(empty)
```

### 15b. `git diff --name-only feat/users-domain-layer...HEAD | Select-String -SimpleMatch 'Token','passport'`
```text
(empty)
```

### 15c. `git diff --name-only feat/users-domain-layer...HEAD -- app/Models/User.php app/Domain/Accounts/Enums app/Domain/Accounts/Contracts app/Domain/Accounts/Repositories app/Domain/Accounts/Services app/Domain/Accounts/Exceptions app/Domain/Accounts/Data/UserData.php app/Domain/Accounts/Data/UserFilterData.php`
```text
(empty)
```

### 15d. `Get-ChildItem app/Domain/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport','App\Http','Illuminate\Http','App\Filament'`
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
```

### 15e. `Select-String -Path app/Http/Controllers/Api/V1/Users/UserController.php -SimpleMatch 'UserRepository','Illuminate\Database\Eloquent\Builder'`
```text
(empty)
```

### 15f. `Get-ChildItem app/Domain/Accounts,app/Http,tests/Feature/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'`
```text
(empty)
```

### 15g. `Get-ChildItem app/Domain/Accounts,app/Http/Controllers/Api/V1/Users,app/Http/Requests -Recurse -Filter *.php | Select-String -SimpleMatch "'superadmin'","'retailer'","'customer'"`
```text
app\Domain\Accounts\Enums\UserRole.php:9:    case Superadmin = 'superadmin';
app\Domain\Accounts\Enums\UserRole.php:10:    case Retailer = 'retailer';
app\Domain\Accounts\Enums\UserRole.php:11:    case Customer = 'customer';
```

### 15h. `php artisan route:list --path=api/v1/users`
```text
 GET|HEAD api/v1/users .. Api\V1\Users\UserController@index
 POST api/v1/users .. Api\V1\Users\UserController@store
 GET|HEAD api/v1/users/{user:public_id} .. Api\V1\Users\UserController@show
 PUT|PATCH api/v1/users/{user:public_id} .. Api\V1\Users\UserController@update
 DELETE api/v1/users/{user:public_id} .. Api\V1\Users\UserController@destroy

 Showing [5] routes
```

### 15i. `php artisan test --compact tests/Feature/Tokens tests/Arch`
```text
{"tool":"pest","result":"passed","tests":28,"passed":28,"assertions":98,"duration_ms":5581}
```
