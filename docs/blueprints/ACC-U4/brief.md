# [ACC-U4 | BRIEF | Claude]

## Goal

Build the Accounts-stream authentication endpoints on top of the `ACC-U1` to `ACC-U3` Users stream: `POST /api/v1/auth/register` (guest, creates a `Customer` only) and `POST /api/v1/auth/login` (`Customer` and `Retailer`, `Superadmin` per OD-2). This step adds `AuthService`, the input DTO `LoginData`, the output DTO `AuthResultData`, the domain exception `InvalidCredentialsException`, `LoginRequest`, `RegisterRequest`, `AuthController`, `routes/api/auth.php`, `findByEmail` on the existing repository pair, rate limiting for both endpoints, and feature tests. Tokens are minted only through the `TokenIssuer` contract delivered by `ACC-T0`. No logout, refresh, `/auth/me`, or token revocation (`ACC-U5` and the Tokens stream).

Product rules enforced here: retailers cannot register (the superadmin creates retailer accounts through `POST /api/v1/users`, `ACC-U3`); customers can register; customers and retailers can both log in.

---

## Depends on

- **ACC-U1, ACC-U2, ACC-U3** (accepted per Achintha; `ACC-U3` is pushed to `feat/users-domain-layer`). Claude has not seen the repository directly, so Gemini must read the actual files in plan Section 3 and not rely on this brief for current state.
- From **ACC-U1**: `App\Domain\Accounts\Enums\UserRole`, `App\Models\User` (`final`, `SoftDeletes`, `HasApiTokens`, `public_id`, `password` with `hashed` cast), `Database\Factories\UserFactory` (`customer()`, `retailer()`, `superadmin()` states), `SuperadminSeeder`.
- From **ACC-U2**: `UserData` (combined input DTO, `<type>|Optional` fields), `UserRepositoryInterface` and `UserRepository` (this step adds ONE method to each), `UserService::create`, `ProtectedUserException`, the `AppServiceProvider` repository binding.
- From **ACC-U3**: `UserResponseData::fromModel(User): self` (reused as is, never modified), `StoreUserRequest` (password and email rule patterns to mirror), the `ProtectedUserException` to HTTP mapping in `bootstrap/app.php`, the `auth:api` guest JSON `401` behaviour, `routes/api/users.php`.
- From **ACC-T0**: the `TokenIssuer` contract and `TokenPairData` (verify the namespace, method names and return type by reading them), the ADR 0001 token shape (refresh token in the JSON body, 15-minute access token, 14-day refresh token, empty scopes), `routes/api/tokens.php` and `TokenController` (read only, for route and URI clash checks), `tests/Feature/Tokens/TokenEndpointsTest.php` (pattern for real Bearer-token tests).
- From **ACC-C0 / ACC-C1**: `routes/api.php`, `bootstrap/app.php`, `tests/Arch/AccountsArchTest.php`. The arch tests and `tests/Feature/Tokens/` must stay green and untouched.

---

## In scope

1. `app/Domain/Accounts/Data/LoginData.php`: `final` `spatie/laravel-data` input DTO (`string $email`, `string $password`).
2. `app/Domain/Accounts/Data/AuthResultData.php`: `final` output DTO holding the user (`UserResponseData`) and the token pair (`TokenPairData`, embedded as defined by `ACC-T0`, never redefined). Exact JSON shape per `ARCHITECTURE.md` section 7.
3. `app/Domain/Accounts/Exceptions/InvalidCredentialsException.php`: pure domain exception (no `Illuminate\Http`, no `App\Http`).
4. `app/Domain/Accounts/Services/AuthService.php`: `final`, methods `register(UserData $data): AuthResultData` and `login(LoginData $data): AuthResultData`. Depends on `UserService`, `UserRepositoryInterface` (`findByEmail`), the `TokenIssuer` contract and `Illuminate\Contracts\Hashing\Hasher`.
5. `app/Domain/Accounts/Contracts/UserRepositoryInterface.php` and `app/Domain/Accounts/Repositories/UserRepository.php`: add `findByEmail(string $email): ?User` (exact match on the already-normalised email, soft-deleted rows excluded). Nothing else in these two files changes.
6. `app/Http/Requests/Auth/RegisterRequest.php` and `app/Http/Requests/Auth/LoginRequest.php` (verify the exact namespace and class names in `ARCHITECTURE.md` sections 4 to 5): validate input, normalise `email` (trim + lowercase) in `prepareForValidation()`, provide `toDto()`.
7. `app/Http/Controllers/Api/V1/Auth/AuthController.php` (verify the name and namespace in `ARCHITECTURE.md`): `final`, thin, `register` returns `201`, `login` returns `200`.
8. `routes/api/auth.php`: `POST /register` and `POST /login` as guest routes (no `auth:api`) with their rate limiters.
9. `app/Providers/AppServiceProvider.php`: named rate limiters (OD-5).
10. `bootstrap/app.php`: render `InvalidCredentialsException` as `401` JSON (same pattern as `ProtectedUserException` in `ACC-U3`).
11. `tests/Feature/Accounts/AuthEndpointsTest.php`: feature tests against PostgreSQL covering every AC below.

---

## Out of scope

- Logout, refresh endpoint, `/auth/me`, token revocation (`TokenIssuer::revokeAll` on delete, password change, role change, or re-login), single-session policy: `ACC-U5` or the Tokens stream. If `ARCHITECTURE.md` section 7 assigns any of these to `ACC-U4`, stop and raise it in plan Section 13; do not build it.
- Email verification, password reset, 2FA, social login, Filament UI (permanently out of scope, `ARCHITECTURE.md` section 1).
- Password rehash on login (`needsRehash`), account lockout beyond rate limiting, login audit log.
- Any migration, factory or seeder change (`DBS2`). If `SuperadminSeeder` stores a non-normalised email (see trap 2), submit a Change Request.
- Any change to `User`, `UserRole`, `UserData`, `UserFilterData`, `UserResponseData`, `UserService`, `UserPolicy`, `ProtectedUserException`, the Users requests/controller/routes, or the `ACC-U2`/`ACC-U3` tests.
- Any Tokens-stream file (`PassportTokenIssuer`, `TokenController`, `TokenServiceProvider`, `config/passport.php`, `routes/api/tokens.php`) or Contract file (`TokenIssuer`, `TokenPairData`, `routes/api.php`). If `routes/api.php` does not already require `routes/api/auth.php`, that is a Change Request, not a silent edit.
- Editing `tests/Arch/*` to make an import pass. If `AccountsArchTest` forbids a required import, submit a Change Request.

---

## Read first

- `ARCHITECTURE.md`: sections 4 to 7 (folder and class names, route table, request/response JSON, status codes, rate limits), section 8 (security, passwords), section 9 (database, `public_id`), section 11 (open questions).
- `AGENTS.md` and `docs/blueprints/RULE_REGISTER.md`: layering, Account rules (registration and login rules), Security, Stream rules, Coding standards.
- `docs/adr/0001-*` (token system) and the other ADRs (check numbers).
- `app/Domain/Accounts/Services/UserService.php`, `UserData.php`, `UserResponseData.php`, `UserRepositoryInterface.php`, `UserRepository.php`: exact signatures.
- `app/Http/Requests/Users/StoreUserRequest.php`, `app/Http/Controllers/Api/V1/Users/UserController.php`: patterns to mirror (password rules, email normalisation, thin controller).
- The `TokenIssuer` contract, `TokenPairData`, `PassportTokenIssuer`, `TokenController`, `routes/api/tokens.php`, `routes/api.php`.
- `config/hashing.php`, `database/seeders/SuperadminSeeder.php`, `phpunit.xml` (cache driver for rate-limit tests).
- `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`.
- `tests/Arch/AccountsArchTest.php`: exactly which imports `App\Domain\Accounts` may use (Hashing contract, DB facade, the `TokenIssuer` namespace).
- `tests/Feature/Tokens/TokenEndpointsTest.php`, `tests/Feature/Accounts/UserControllerTest.php`: test patterns.

---

## Decisions made

Gemini must not re-decide these.

| Decision | Source |
|---|---|
| Registration always creates a `Customer`. The role comes from `RegisterRequest::toDto()` setting `UserRole::Customer`, never from client input. Retailer and superadmin accounts cannot be created through `/auth/register` | Product rule, `ACC-U3` OD-2 |
| `AuthService` lives in `app/Domain/Accounts/Services`, is `final`, and is the only class that calls `TokenIssuer` in the Accounts stream. It depends on the `TokenIssuer` contract, never on `PassportTokenIssuer` or `Laravel\Passport` | `STM1`, `LAY2` |
| Credentials are verified manually: `findByEmail`, then `Hasher::check`. No `Auth::attempt`, no session guard (the `api` guard is token based and has no `attempt`) | `ACT6`, `SCR2` |
| Passwords are never hashed in requests, controllers or `AuthService`. The `hashed` cast on `User` hashes on create. `Hash::make` appears nowhere in `app/Domain/Accounts`, `app/Http` or `tests/Feature/Accounts` | `ACT6`, Layer A 15g |
| `email` is normalised (trim + lowercase) in `prepareForValidation()` of both requests before any rule or lookup runs. `RegisterRequest` uses `Rule::unique('users', 'email')` over the full table, soft-deleted rows included | `ACC-U2` OD-3, `DBS2` |
| Login password validation is `required` + `string` + the same max length as `StoreUserRequest`. No strength rules on login | Security |
| `InvalidCredentialsException` is a pure domain exception. Its HTTP mapping (`401` JSON) lives in `bootstrap/app.php`, never inside the exception class | `LAY2`, `AccountsArchTest` |
| Registration is atomic: user creation and token issuance run inside one database transaction, so a `TokenIssuer` failure leaves no user row | Data integrity |
| Responses return `AuthResultData` built from `UserResponseData::fromModel(...)` and the `TokenPairData` produced by `TokenIssuer`. Never a raw `User` model. Never `id`, `password` or `remember_token` | `DBS5`, `LAY6` |
| Login and register are guest routes, outside any `auth:api` group, throttled by named rate limiters | Security |
| Login does not revoke or invalidate previously issued tokens. Multiple concurrent tokens per user are accepted until `ACC-U5` | `ACC-U5` scope |
| Role checks use `UserRole` cases, never string literals | `STD3` |

---

## Open decisions

Achintha must answer these before the plan is frozen (G1 gate). My recommendation is marked for each. If `ARCHITECTURE.md` sections 6 to 8 already state the rule, `ARCHITECTURE.md` takes precedence and Gemini cites the section in the plan.

| ID | Question |
|---|---|
| OD-1 | **Branch base.** Is `ACC-U3` already merged into `main`? Recommended: merge the `ACC-U3` work into `main` first, then branch `feat/auth-endpoints` from `main`, so every `main...HEAD` check in this brief works unchanged. Alternative: branch from `feat/users-domain-layer` and replace `<BASE>` in every command below with that branch name. |
| OD-2 | **Can a `Superadmin` log in through `/auth/login`?** Recommended: yes. The superadmin needs a token to call `/api/v1/users`, and there is no separate login path. Alternative: none that keeps `ACC-U3` usable. |
| OD-3 | **Register response and `role` in the payload.** Recommended: `201` with the user and the token pair (auto-login; email verification is permanently out of scope), and a `role` field in the payload is rejected with `422` (`prohibited`) so a client trying to register as `retailer` or `superadmin` gets explicit feedback. Alternative: `201` with the user only (client logs in next), and `role` is silently ignored. |
| OD-4 | **Credential-failure contract.** Recommended: `401` with one fixed JSON message (for example `{"message":"Invalid credentials."}`), byte-identical for unknown email, wrong password and soft-deleted user, plus timing equalisation: when no user is found, run `Hasher::check` against a constant pre-hashed literal so response time does not reveal which emails exist. The literal must match the configured hashing driver (see trap 5); if it cannot, skip the equalisation and record that in plan Section 13. Alternative: Laravel-style `422` with `errors.email`, no timing equalisation. |
| OD-5 | **Rate limits.** Only if `ARCHITECTURE.md` section 7 is silent. Recommended: login `5/min` keyed by lowercased email + IP, register `10/min` keyed by IP, both returning the default JSON `429`. Alternative: inline `throttle:N,1` on the routes (IP only; avoids editing `AppServiceProvider`, but weaker against distributed guessing of one account). |
| OD-6 | **Step size.** This step has a 13-file ceiling (see Size guard), above the usual 10. Recommended: one step, because the extra files are small and tightly coupled. Alternative: split into `ACC-U4a` (DTOs, exception, `AuthService`, `findByEmail`, service-level tests) and `ACC-U4b` (requests, controller, routes, limiters, `bootstrap/app.php`, endpoint tests). |

---

## Acceptance criteria

| ID | Criterion |
|---|---|
| AC-1 | `POST /api/v1/auth/register` with a valid payload (`name`, `email`, `password`, plus any confirmation field `ARCHITECTURE.md` section 7 requires) creates exactly one `Customer` through `UserService::create` and returns `201` with `AuthResultData` (OD-3). The stored password is a hash that `Hasher::check` accepts, and the stored email is normalised. |
| AC-2 | Register rejections return `422` and leave the database unchanged: duplicate email (including case and whitespace variants, and the email of a soft-deleted user), missing or invalid fields, weak password per the `StoreUserRequest` rules, and any `role` key in the payload (OD-3). No request to `/auth/register` can create a `Retailer` or `Superadmin`. |
| AC-3 | Register is atomic: with a `TokenIssuer` fake that throws, no `users` row remains afterwards. |
| AC-4 | `POST /api/v1/auth/login` with valid credentials returns `200` with `AuthResultData` for a `Customer`, a `Retailer` and (OD-2) a `Superadmin`. A login with different case or surrounding whitespace in the email also succeeds. |
| AC-5 | Login failures: wrong password, unknown email and soft-deleted user each return the same `401` status and an identical response body (OD-4). Missing or malformed fields return `422`. No response or validation error ever contains the submitted password. |
| AC-6 | Response shape: the user object is produced by `UserResponseData` (so no integer `id`, `password`, `remember_token`), and the token fields match `TokenPairData` and `ARCHITECTURE.md` section 7 exactly. Token responses carry the same cache headers as `TokenController` (trap 11). |
| AC-7 | End to end: the access token returned by login authenticates `GET /api/v1/users/{public_id}` for that user (`200`) using a real `Authorization: Bearer` header (not `actingAs`), and the returned refresh token is accepted by the existing refresh endpoint from `routes/api/tokens.php`. If no refresh endpoint exists, drop that half and record it in plan Section 13. |
| AC-8 | Rate limiting: exceeding the login limit and the register limit returns `429` JSON. The limiters are cleared between tests so no test depends on another's state. |
| AC-9 | `UserRepositoryInterface::findByEmail(string $email): ?User` exists in the interface and `UserRepository`. It matches the normalised email exactly, returns `null` for a soft-deleted user, and no other line of either file changes. |
| AC-10 | `composer check` (Pint + Larastan + Pest) passes with zero errors. Layer A checks 15a to 15k pass. `tests/Feature/Tokens/`, `tests/Arch/` and the `ACC-U2`/`ACC-U3` tests pass unchanged. `php artisan route:list --path=api/v1/auth` shows the new routes with no URI or name clash against the Tokens stream. |

---

## Rules most at risk

| Rule ID | Why it is at risk in this step |
|---|---|
| ACT (registration and login rules, Gemini cites exact IDs) | Letting a client choose `role` on register, or letting a retailer register. |
| ACT6 / SCR2 | Hashing in the request or controller (double hashing with the model cast), logging or echoing the password, `Hash::make` creeping in for the dummy hash. |
| SCR (credential errors, Gemini cites exact IDs) | Different status or message for unknown email versus wrong password, which leaks which emails exist. |
| STM1 / STM3 | `AuthService` importing `PassportTokenIssuer` or `Laravel\Passport`, calling `revokeAll`, or tests using `Passport::actingAs` where stream rules forbid it. |
| LAY1 / LAY2 / LAY4 / LAY6 | Eloquent queries or `UserRepository` inside `AuthController`, `Illuminate\Http` inside `InvalidCredentialsException` or `AuthService`, returning a raw `User`. |
| DBS5 | Any response or error exposing the integer `id`. |
| STD1 / STD3 | Missing `declare(strict_types=1);` on any new or modified file, role string literals instead of `UserRole`. |
| TST2 / TST4 | Missing negative cases (`401`, `422`, `429`), rows not created through `UserFactory`. |
| WFL6 | Scope creep: logout, refresh, `/auth/me`, revocation, `needsRehash`, edits to `ACC-U2`/`ACC-U3` or Tokens-stream files. |

---

## Known traps

1. **No `Auth::attempt`.** The Passport `api` guard is token based and the starter kit (Fortify, Livewire) was removed in `ACC-C1`. Verify credentials manually with `findByEmail` and `Hasher::check`.
2. **Email normalisation on login and the seeded superadmin.** `findByEmail` does an exact match, so the request must lowercase and trim first. Read `SuperadminSeeder`: if it writes the email without normalising (for example mixed case from `.env`), the superadmin can never log in. Do not edit the seeder; submit a Change Request.
3. **`role` must never come from the client on register.** `RegisterRequest::toDto()` sets `UserRole::Customer` itself, and `role` is `prohibited` in the rules (OD-3). Test with `role: retailer`, `role: superadmin` and `role: customer`.
4. **Uniform credential failures.** Unknown email, wrong password and soft-deleted user must go through one code path that throws the same `InvalidCredentialsException`. The test compares the three response bodies and status codes for equality, not just the status.
5. **Dummy hash versus hashing driver (OD-4).** `Hasher::check` throws when the stored hash does not match the configured algorithm and `hashing.verify` is on. Read `config/hashing.php` and `phpunit.xml` (test runs may use a different driver or lower rounds). The dummy must be a constant literal, because `Hash::make` is banned in these namespaces. If a literal cannot be valid under both app and test config, skip the equalisation and say so in Section 13.
6. **Atomic register.** `UserService::create` and `TokenIssuer` must run in one `DB::transaction`. Check `AccountsArchTest` allows the `DB` facade inside `App\Domain\Accounts`, and check whether `UserService::create` opens its own transaction (nested transactions are fine in Laravel but the plan must state it). Test with a throwing `TokenIssuer` fake bound in the container.
7. **Route and prefix clash with the Tokens stream.** `routes/api/tokens.php` may already live under `/api/v1/auth/...` (for example a refresh route). `routes/api.php` is a Contract seam and may or may not already require `routes/api/auth.php`. Read both. If the include is missing, raise a Change Request. Never edit `routes/api.php` silently. Check route names for collisions.
8. **Guest routes.** Login and register must not sit inside an `auth:api` group. Also confirm an unauthenticated call is not redirected (`ACC-U3` AC-2 JSON guest handling still applies to every other route).
9. **Rate-limit tests.** Confirm the cache driver in `phpunit.xml` (array is fine), key the limiter on the lowercased email plus `$request->ip()`, and call `RateLimiter::clear(...)` or use `beforeEach` so tests stay independent.
10. **`TokenIssuer` import permission.** `AccountsArchTest` may restrict what `App\Domain\Accounts` can import from the Tokens or Contract namespace. Read it before planning. If it forbids the contract import, submit a Change Request; do not weaken the arch test.
11. **Token response headers.** Mirror whatever `TokenController` does for `Cache-Control` (token responses should not be cached). Do not invent a different policy.
12. **Duplicate password rules.** Mirror `StoreUserRequest`'s password rules exactly (same `Password::defaults()` or same explicit rules). Do not refactor `StoreUserRequest` to share them; that is a Change Request.
13. **Accepted trade-off.** Register returns `422` for an existing email, which reveals that the email exists. Accepted because there is no email verification flow; the register throttle limits abuse. Record in Section 13; do not try to hide it.
14. **Token revocation window.** After this step, a user can log in repeatedly and every token stays valid until `ACC-U5` adds revocation. Record as an expected deferral in Section 13; do not pull `ACC-U5` in.

---

## Size guard

**Expected file count: 12 to 13** (excluding `docs/blueprints/ACC-U4/*`)

- New (9): `LoginData.php`, `AuthResultData.php`, `InvalidCredentialsException.php`, `AuthService.php`, `RegisterRequest.php`, `LoginRequest.php`, `AuthController.php`, `routes/api/auth.php`, `tests/Feature/Accounts/AuthEndpointsTest.php`.
- Modified (3 to 4): `UserRepositoryInterface.php`, `UserRepository.php`, `bootstrap/app.php`, and `AppServiceProvider.php` (omit only if OD-5 chooses inline throttle).

Split trigger (OD-6): if the plan manifest exceeds **13 files**, touches any migration, factory or seeder, modifies any `ACC-U2`/`ACC-U3` file other than the two repository files, touches a Tokens-stream or Contract file, or needs a second ADR, stop and submit a Change Request. No ADR is expected in this step unless an OD answer contradicts `ARCHITECTURE.md` section 7.

---

## Step-specific Layer A checks

Run these in addition to the standard 15 Layer A commands from `docs/blueprints/RULE_REGISTER.md`. Replace `<BASE>` with the branch agreed in OD-1 (`main` if `ACC-U3` is merged).

```powershell
# 15a. No migration, factory or seeder touched (expect empty)
git diff --name-only <BASE>...HEAD -- database

# 15b. No Tokens-stream or Contract seam files touched (expect empty)
git diff --name-only <BASE>...HEAD | Select-String -SimpleMatch 'PassportTokenIssuer','TokenController','TokenServiceProvider','config/passport.php','routes/api/tokens.php','TokenIssuer.php','TokenPairData.php','routes/api.php'

# 15c. No ACC-U1 / ACC-U2 / ACC-U3 files modified (expect empty)
git diff --name-only <BASE>...HEAD -- app/Models app/Domain/Accounts/Enums app/Domain/Accounts/Policies app/Domain/Accounts/Services/UserService.php app/Domain/Accounts/Exceptions/ProtectedUserException.php app/Domain/Accounts/Data/UserData.php app/Domain/Accounts/Data/UserFilterData.php app/Domain/Accounts/Data/UserResponseData.php app/Http/Requests/Users app/Http/Controllers/Api/V1/Users routes/api/users.php

# 15d. Only the two repository files changed in Contracts and Repositories (expect exactly UserRepositoryInterface.php and UserRepository.php)
git diff --name-only <BASE>...HEAD -- app/Domain/Accounts/Contracts app/Domain/Accounts/Repositories

# 15e. Accounts domain imports nothing from Passport, Http, Filament or the Passport token implementation (expect none)
Get-ChildItem app/Domain/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport','App\Http','Illuminate\Http','App\Filament','PassportTokenIssuer'

# 15f. AuthController touches no repository, Eloquent, Passport or hashing (expect none)
Select-String -Path app/Http/Controllers/Api/V1/Auth/AuthController.php -SimpleMatch 'UserRepository','Illuminate\Database\Eloquent','Laravel\Passport','PassportTokenIssuer','Hash::','Hasher'

# 15g. No Hash::make in Accounts domain, HTTP layer, or Accounts tests (expect none)
Get-ChildItem app/Domain/Accounts,app/Http,tests/Feature/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'

# 15h. No role string literals in Accounts domain, Requests, or Controllers (expect none)
Get-ChildItem app/Domain/Accounts,app/Http/Controllers/Api/V1/Auth,app/Http/Requests -Recurse -Filter *.php | Select-String -SimpleMatch "'superadmin'","'retailer'","'customer'"

# 15i. No logging or debug output near credentials (every hit needs an explanation)
Get-ChildItem app/Domain/Accounts,app/Http -Recurse -Filter *.php | Select-String -SimpleMatch 'Log::','logger(','dd(','dump(','var_dump('

# 15j. Auth routes registered, no clash with the Tokens stream
php artisan route:list --path=api/v1/auth

# 15k. Token, arch and Users tests still pass unchanged
php artisan test --compact tests/Feature/Tokens tests/Arch tests/Feature/Accounts
```
