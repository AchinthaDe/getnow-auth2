# [ACC-U3 | IMPL-REPORT | Gemini]

# Implementation Report: ACC-U3 User HTTP Layer

- **Step ID**: `ACC-U3`
- **Stream**: Users (`[U]`)
- **Status**: VERIFY (r4 / Review r3 Response)
- **Date**: 2026-10-26
- **Related Plan**: `docs/blueprints/ACC-U3/plan.md` (v1)
- **Base Branch**: `feat/users-domain-layer` (`07f7f2b`)
- **Head SHA**: `16d5ae0`
- **Gaps**: None

---

## 1. Summary of Changes

This step implements the Users-stream HTTP layer exposing `/api/v1/users` endpoints in strict compliance with `ARCHITECTURE.md` §§3, 6, 7, 8, 9, `RULE_REGISTER.md`, and the approved frozen plan `ACC-U3 v1`:

1. **User Response DTO (`app/Domain/Accounts/Data/UserResponseData.php`)**:
   - `UserResponseData`: `final` class extending `Spatie\LaravelData\Data` with `#[MapName(SnakeCaseMapper::class)]`.
   - Maps public user representation: `id` (holds `public_id` ULID), `name`, `email`, `role` (`UserRole`), `created_at`, and `updated_at`.
   - Factory method: `public static function fromModel(User $user): self`.
   - Types `$createdAt` and `$updatedAt` with `?\Carbon\CarbonInterface` so both `CarbonImmutable` and `Carbon` instances satisfy strict typing and PHPStan Level 8 without runtime `TypeError`.
   - Excludes internal integer `id`, `password`, `remember_token`, and `deleted_at`.

2. **Authorization Policy & Registration (`app/Domain/Accounts/Policies/UserPolicy.php`, `app/Providers/AppServiceProvider.php`)**:
   - `UserPolicy`: `final` policy enforcing strict `Superadmin`-only authorization across all 5 actions (`viewAny`, `view`, `create`, `update`, `delete`) in accordance with `ARCHITECTURE.md` §3 ("Only the superadmin can use the `/users` endpoints") and §7 table.
   - `AppServiceProvider`: Registers `Gate::policy(User::class, UserPolicy::class)` explicitly in `boot()`, and enforces `declare(strict_types=1);`.

3. **Form Requests (`app/Http/Requests/Users/StoreUserRequest.php`, `app/Http/Requests/Users/UpdateUserRequest.php`)**:
   - `StoreUserRequest`:
     - `authorize()`: Enforces `$actor !== null && $actor->can('create', User::class)` prior to validation, preventing unauthenticated/unauthorized callers from probing email uniqueness.
     - `prepareForValidation()`: Normalises `email` (`strtolower(trim(...))`) prior to `Rule::unique('users', 'email')`.
     - `rules()`: Validates `name`, `email`, `password` (`min:8`, `max:72`), and `role` (`Rule::enum(UserRole::class)`).
     - `toDto(): UserData`: Builds the combined DTO.
   - `UpdateUserRequest`:
     - `authorize()`: Enforces `$actor !== null && $actor->can('update', $user)` prior to validation.
     - `prepareForValidation()`: Normalises `email` when provided.
     - `rules()`: Uses `sometimes` rules with `max:72` on password; ignores target user model directly via `Rule::unique('users', 'email')->ignore($user instanceof User ? $user : null)`.
     - `toDto(): UserData`: Builds `UserData` with `Optional` for omitted properties (Rules `STD4`, `STD5`).

4. **HTTP Controller & Routes (`app/Http/Controllers/Api/V1/Users/UserController.php`, `routes/api/users.php`, `routes/api.php`, `bootstrap/app.php`)**:
   - `UserController`: Injects `UserService`. Implements `index`, `store`, `show`, `update`, `destroy` method parity (`LAY5`).
   - In `show`, maps `$user` model directly without redundant database queries.
   - Envelopes single resource responses in `{"data": UserResponseData}` matching paginator and `ARCHITECTURE.md` §7.
   - In `destroy`, returns `204 No Content` with empty body.
   - `routes/api/users.php`: Groups routes under `users` with `auth:api` middleware and `{user:public_id}` route model binding. Matches `PUT` and `PATCH`.
   - `routes/api.php`: Requires `routes/api/users.php` (CR-1).
   - `bootstrap/app.php`: Maps `ProtectedUserException` to 403 Forbidden JSON response and adds `declare(strict_types=1);`.

5. **Feature Tests (`tests/Feature/Accounts/UserControllerTest.php`)**:
   - 19 comprehensive Pest test cases covering AC-1 through AC-10.

---

## 2. Review Findings Resolution (r1–r3)

| Finding | Severity | Resolution & Evidence |
|---|---|---|
| **Fix 1 (Policy Spec Alignment)** | Major | `UserPolicy` strictly aligned with `ARCHITECTURE.md` §3 ("Only the superadmin can use the `/users` endpoints") and §7 table. All 5 actions (`viewAny`, `view`, `create`, `update`, `delete`) require `UserRole::Superadmin`. Removed self-service role branching and `current_password` rule from `UpdateUserRequest`. Self-service profile updates belong to a future `/me` endpoint. |
| **Fix 2 (Layer A Freshness)** | Major | Layer A commands 1–14 and 15a–15i rerun cleanly at HEAD `16d5ae0`. `composer check` passes with 96 tests, 340 assertions, 0 errors. `UserControllerTest.php` passes with 19 tests, 111 assertions. |
| **Fix 3 (Exact AC-to-Test Mapping)** | Minor | Regenerated AC-to-test mapping in Section 3 from `Select-String -Path tests/Feature/Accounts/UserControllerTest.php -Pattern '^it\('` ensuring exact 1-to-1 fidelity with test definitions. |
| **Fix 4 (§7 Contract Documentation)** | Minor | Updated `ARCHITECTURE.md` §7 line 200 documenting that `GET /users` supports `role`, `search`, `include_trashed`, `page`, and `per_page`, returning `{"data": [...]}` envelope, and single user endpoints return `{"data": UserResponseData}`. |
| **Fix 5 (Closeout Note)** | Minor | Updated `docs/blueprints/ACC-U3/closeout.md` detailing 19 tests, CR-1 route seam inclusion, and Fix 1 policy alignment. |
| **Fix 6 (Test Gaps Closed)** | Minor | AC-4 test explicitly asserts ordering by `created_at DESC` across 4 records before search filter; AC-9 test explicitly asserts that the database remains completely unchanged (uncreated, unpromoted, undemoted, undeleted) following 403 rejections; redundant lookup in `show()` eliminated. |

---

## 3. Acceptance Criteria to Test Mapping

Generated directly from `Select-String -Path tests/Feature/Accounts/UserControllerTest.php -Pattern '^it\('`:

| AC ID | Requirement | Exact Test in `tests/Feature/Accounts/UserControllerTest.php` | Assertions |
|---|---|---|---|
| **AC-1** | `UserResponseData::fromModel` maps `public_id` to `id`, `name`, `email`, `role`, timestamps; never integer `id`, `password`, or `remember_token` | `it('serializes user model to user response data without internal id or password')` | `$data['id'] === $user->public_id`, not integer `id`, no password, no remember_token, no deleted_at |
| **AC-2** | Unauthenticated requests to all 5 endpoints return `401 Unauthorized` JSON | `it('returns 401 unauthorized on unauthenticated requests across all user endpoints')` | `assertUnauthorized()`, `assertJson(['message' => 'Unauthenticated.'])` on GET, POST, GET {id}, PATCH {id}, DELETE {id} |
| **AC-3** | `UserPolicy` strictly enforced on all actions: customers and retailers forbidden from all `/users` endpoints, including self view and update | `it('enforces authorization matrix forbidding customers and retailers from admin actions')`, `it('forbids customers and retailers from viewing or updating their own record')`, `it('forbids customers from storing users even when payload contains an existing email preventing probe')`, `it('forbids customers from updating another user even with an invalid body preventing validation leak')` | `assertForbidden()` across index, store, show, update, delete for retailer and customer; authorization evaluated prior to validation rules |
| **AC-4** | `GET /api/v1/users` returns paginated list ordered by `created_at DESC` with filters; `422` on invalid params | `it('returns paginated users list ordered by created_at desc with filtering for superadmin')`, `it('returns 422 on invalid query parameters on index')` | Asserts ordering by `created_at DESC` across multiple records; asserts search filter; asserts `assertUnprocessable()` on invalid role/include_trashed/per_page |
| **AC-5** | `POST /api/v1/users` creates user with `201`; invalid payloads and duplicate emails return `422` | `it('creates a customer or retailer user via post users endpoint')`, `it('validates store user input rejecting missing fields, malformed emails, and invalid roles')`, `it('returns 422 when storing user with duplicate email including case and whitespace variants')`, `it('returns 422 when storing user with email belonging to soft-deleted user')` | `assertCreated()`, password hashed once via cast, `assertUnprocessable()` on validation errors, case-variant duplicates, soft-deleted emails |
| **AC-6** | `GET /api/v1/users/{user}` returns `200` by `public_id`; returns `404` for raw integer ID, unknown ULID, or soft-deleted user | `it('returns user by public_id and returns 404 on integer id, unknown ulid, or soft-deleted user')` | `assertOk()`, `assertNotFound()` on integer id, random ULID, soft-deleted ULID |
| **AC-7** | `PATCH /api/v1/users/{user}` partial update with `200`; self email allowed without conflict; other duplicate returns `422` | `it('partially updates user fields preserving untouched attributes')`, `it('allows updating user while retaining own email including casing variants')`, `it('returns 422 when updating email to an existing email of another user')` | `assertOk()`, untouched fields remain unchanged, no false unique error on own email, `assertUnprocessable()` on conflicting email |
| **AC-8** | `DELETE /api/v1/users/{user}` soft-deletes with `204 No Content`; subsequent `GET` returns `404` | `it('soft deletes user and returns 204 no content')` | `assertNoContent()`, empty body, `assertSoftDeleted()`, subsequent show returns `assertNotFound()` |
| **AC-9** | Superadmin protections over HTTP: cannot create superadmin, promote, change role, or delete superadmin (`403`); database left unchanged; superadmin name/email update succeeds | `it('rejects superadmin creation, promotion, demotion, and deletion with 403 forbidden leaving database unchanged')`, `it('allows updating superadmin name and email without role change')` | `assertForbidden()` with `ProtectedUserException` messages on create/promote/demote/delete; asserts database unchanged (user not created, roles unchanged, admin not deleted); `assertOk()` on name update |
| **AC-10** | Soft-delete ADR 0003 present in `docs/adr/` and linked in `ARCHITECTURE.md` §11 (OQ6); `composer check` passes; token/arch tests pass | Layer A check 14, check 15i, and ADR verification | `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md`, `ARCHITECTURE.md:325`, `composer check` 96 tests pass |

---

## 4. Layer A Command Outputs (at HEAD `16d5ae0`)

### 1. `git status --short`
```text
(empty - clean working directory)
```

### 2. `git log --oneline feat/users-domain-layer..HEAD`
```text
16d5ae0 feat(users): align UserPolicy with superadmin-only spec and update documentation
1e93973 docs(users): add Section 8 to ACC-U3 report with r2 review evidence
bf3ad14 fix(users): enforce current_password on self-service updates and max password length
79d164c docs(users): update ACC-U3 report with r1 verify findings, AC mapping, and raw outputs
1ca6304 fix(users): enforce policy authorization in form requests before validation
46c08f8 docs(users): record ACC-U3 completion
80e31ff docs(users): add ACC-U3 implementation report
945a5f9 test(users): add user controller endpoint and authorization feature tests
2de8659 feat(users): add user form requests, controller, and routes
1a11a54 feat(users): add user response data and authorization policy
```

### 3. `git diff --name-status feat/users-domain-layer...HEAD`
```text
M	ARCHITECTURE.md
A	app/Domain/Accounts/Data/UserResponseData.php
A	app/Domain/Accounts/Policies/UserPolicy.php
A	app/Http/Controllers/Api/V1/Users/UserController.php
A	app/Http/Requests/Users/StoreUserRequest.php
A	app/Http/Requests/Users/UpdateUserRequest.php
M	app/Providers/AppServiceProvider.php
M	bootstrap/app.php
A	docs/blueprints/ACC-U3/brief.md
A	docs/blueprints/ACC-U3/closeout.md
A	docs/blueprints/ACC-U3/plan.md
A	docs/blueprints/ACC-U3/report.md
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
{"tool":"pest","result":"passed","tests":96,"passed":96,"assertions":340,"duration_ms":7056}
```

---

## 5. Step-Specific Checks (15a–15i)

### 15a. `git diff --name-only feat/users-domain-layer...HEAD -- database/migrations`
```text
(empty)
```

### 15b. `git diff --name-only feat/users-domain-layer...HEAD | Select-String -SimpleMatch 'Token','passport','routes/api.php'`
```text
routes/api.php
```
*(CR-1: `routes/api.php` modified solely to add `require __DIR__.'/api/users.php';` to mount the users route group.)*

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
{"tool":"pest","result":"passed","tests":28,"passed":28,"assertions":98,"duration_ms":3957}
```
