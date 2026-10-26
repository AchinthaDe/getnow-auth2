# [ACC-U3 | BRIEF | Claude]

## Goal

Build the Users-stream HTTP layer (`/api/v1/users`) on top of the `ACC-U2` domain layer. This step adds the output DTO `UserResponseData`, `UserPolicy`, the user form requests (`StoreUserRequest` and `UpdateUserRequest`), `UserController` (`index`, `store`, `show`, `update`, `destroy`), the `/api/v1/users` routes in `routes/api/users.php`, the soft-delete ADR (if not already written in `ACC-U1`), and feature tests covering authentication, policy authorization, validation, pagination/filtering, `ProtectedUserException` handling, and `public_id`-only lookup. No login or registration endpoints (`ACC-U4`), and no token revocation (`ACC-U5`).

---

## Depends on

- **ACC-U1** and **ACC-U2** (accepted per Achintha; Claude has not seen the repository directly, so Gemini must read the actual files in Section 3, not rely on this brief for current state).
- From **ACC-U1**, this step uses:
  - `App\Domain\Accounts\Enums\UserRole` (`Superadmin`, `Retailer`, `Customer`)
  - `App\Models\User` (`final`, `SoftDeletes`, `HasApiTokens`, `public_id`, `role` cast to `UserRole`, `password` with `hashed` cast)
  - `Database\Factories\UserFactory` (`customer()`, `retailer()`, `superadmin()` states)
- From **ACC-U2**, this step uses:
  - `App\Domain\Accounts\Data\UserData` (single combined input DTO for create and update with `<type>|Optional` fields)
  - `App\Domain\Accounts\Data\UserFilterData` (`?string $search = null`, `?UserRole $role = null`, `bool $includeTrashed = false`, `int $perPage = 15`)
  - `App\Domain\Accounts\Contracts\UserRepositoryInterface` and `App\Domain\Accounts\Repositories\UserRepository`
  - `App\Domain\Accounts\Services\UserService` (`list`, `get`, `create`, `update`, `delete`)
  - `App\Domain\Accounts\Exceptions\ProtectedUserException`
  - `App\Providers\AppServiceProvider` (`UserRepositoryInterface` binding)
- From **ACC-C0 / ACC-C1 / ACC-T0**:
  - `routes/api.php` (already requires `routes/api/users.php` under `/api/v1`)
  - `bootstrap/app.php` (API JSON exception rendering and guest redirect configuration)
  - `tests/Arch/AccountsArchTest.php` and `tests/Feature/Tokens/` â€” must remain green and untouched.

---

## In scope

1. `app/Domain/Accounts/Data/UserResponseData.php` â€” `final` `spatie/laravel-data` output DTO with `fromModel(User $user): self`. Maps `public_id`, `name`, `email`, `role` (`UserRole`), and timestamps per `ARCHITECTURE.md` Â§Â§6â€“7. Never exposes the database integer `id`, `password`, or `remember_token`.
2. `app/Domain/Accounts/Policies/UserPolicy.php` â€” `final` policy governing `viewAny`, `view`, `create`, `update`, and `delete` on `User` (see Decisions and OD-1).
3. `app/Http/Requests/Users/StoreUserRequest.php` (verify exact namespace/class name in `ARCHITECTURE.md` Â§Â§4â€“5) â€” validates create input (`name`, `email`, `password`, `role`), normalises `email` (trim + lowercase) prior to `Rule::unique('users', 'email')` validation so case/whitespace duplicates fail with `422` rather than a database constraint error, and provides `toDto(): UserData`.
4. `app/Http/Requests/Users/UpdateUserRequest.php` (verify exact namespace/class name in `ARCHITECTURE.md` Â§Â§4â€“5) â€” validates partial update input (`sometimes` rules; unique email ignoring the target user; password rules when present), normalises `email` when present, and provides `toDto(): UserData` leaving omitted fields as `Optional`.
5. `app/Http/Controllers/Api/V1/Users/UserController.php` â€” `final` thin controller (`index`, `store`, `show`, `update`, `destroy`) injecting `UserService`. Authorizes via `UserPolicy`, converts request inputs to `UserFilterData` / `UserData`, calls `UserService`, and returns `UserResponseData` JSON responses (`200`, `201`, `204`).
6. `routes/api/users.php` â€” registers the `/api/v1/users` routes under `auth:api` (and any rate limiter specified by `ARCHITECTURE.md`), matching `{user}` strictly by `public_id`.
7. `app/Providers/AppServiceProvider.php` (and `bootstrap/app.php` only if required to map `ProtectedUserException` to HTTP without importing `Illuminate\Http` into `app/Domain`) â€” registers `Gate::policy(User::class, UserPolicy::class)`.
8. `docs/adr/0003-user-soft-deletes.md` and `ARCHITECTURE.md` Â§11 (`OQ6`) â€” records the soft-delete ADR for `users` (skip only if already created in `ACC-U1`; verify `docs/adr/` in Section 3).
9. `tests/Feature/Accounts/UserControllerTest.php` (and/or `UserPolicyTest.php` â€” verify file naming in `ARCHITECTURE.md` Â§5 and `Size guard` below) â€” feature tests against PostgreSQL covering all five endpoints, role authorization, validation errors, `public_id` routing (including `404` on raw integer `id` and soft-deleted users), filtering/pagination, and `ProtectedUserException` responses.

---

## Out of scope

- `AuthService`, `AuthController`, `LoginRequest`, `RegisterRequest`, `LoginData`, `AuthResultData`, `/api/v1/auth/login`, `/api/v1/auth/register`, `findByEmail` â€” all `ACC-U4`
- Token revocation (`TokenIssuer::revokeAll`) on user delete, password change, or role change â€” `ACC-U5`. Neither `UserController` nor `UserService` may import or call `TokenIssuer` or `Laravel\Passport`.
- Restoring soft-deleted users (`restore`), force delete (`forceDelete`), bulk user operations
- Any migration or change to existing migrations (`DBS2`)
- Any change to `User`, `UserRole`, `UserFactory`, `SuperadminSeeder`, `UserData`, `UserFilterData`, `UserRepositoryInterface`, `UserRepository`, `UserService`, or `ProtectedUserException` (if a change appears necessary, stop and submit a Change Request)
- Any Tokens-stream file (`PassportTokenIssuer`, `TokenController`, `TokenServiceProvider`, `config/passport.php`, `routes/api/tokens.php`) or Contract file (`TokenIssuer`, `TokenPairData`, `routes/api.php`)
- Email verification, password reset, 2FA, Filament UI â€” permanently out of scope (`ARCHITECTURE.md` Â§1)

---

## Read first

- `ARCHITECTURE.md` â€” Â§Â§4â€“7 (exact folder/class names for `Policies`, `Requests`, `Controllers`, `Data`, route table, request/response JSON shapes, status codes, rate limits), Â§8 (security/passwords), Â§9 (database & `public_id`), Â§11 (`OQ6` soft-delete ADR)
- `AGENTS.md` â€” Layering rules, Account rules, Security, and Coding standards
- `docs/blueprints/RULE_REGISTER.md` â€” full 75-rule register and 15 Layer A commands
- `app/Domain/Accounts/Data/UserData.php`, `app/Domain/Accounts/Data/UserFilterData.php`, `app/Domain/Accounts/Services/UserService.php`, `app/Domain/Accounts/Exceptions/ProtectedUserException.php` â€” exact signatures and constructor parameters from `ACC-U2`
- `app/Models/User.php` â€” check whether `getRouteKeyName()` exists or if route binding on `public_id` should be declared in `routes/api/users.php`
- `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `routes/api.php`, `routes/api/users.php`
- `docs/adr/` â€” check existing ADR files and numbers
- `tests/Arch/AccountsArchTest.php` â€” check exact namespace and layering assertions enforced on `App\Http\Controllers\Api\V1\Users`, `App\Domain\Accounts\Policies`, and `App\Domain`
- `tests/Feature/Accounts/UserServiceTest.php` and `tests/Feature/Tokens/TokenEndpointsTest.php` â€” existing test patterns

---

## Decisions made

Gemini must not re-decide these.

| Decision | Source |
|---|---|
| Input validation lives in the `ACC-U3` Form Requests (`StoreUserRequest`, `UpdateUserRequest`), which build and return the combined `UserData` DTO (`Optional` for omitted fields on update) | `ACC-U2` OD-1 |
| `StoreUserRequest` and `UpdateUserRequest` normalise `email` (trim + lowercase via `prepareForValidation()` or rule preparation) before checking `Rule::unique('users', 'email')` so case-variant duplicate emails return `422 Unprocessable Entity` instead of hitting the PostgreSQL unique index with `500` | `ACC-U2` email normalisation + `DBS2` |
| Unique email validation checks the full `users` table (including soft-deleted rows); `UpdateUserRequest` ignores the target user's row | `ACC-U2` OD-3 |
| Controllers never touch Eloquent queries or `UserRepositoryInterface` directly; they authorize via `UserPolicy`, call `UserService`, and return `UserResponseData` (never raw `User` models) | `LAY1`â€“`LAY8`, `ARCHITECTURE.md` Â§4 |
| Raw database `id` is never exposed in `UserResponseData`, pagination metadata, or error messages, and is never accepted as a route parameter (`GET /api/v1/users/1` must return `404`) | `DBS5`, `ARCHITECTURE.md` Â§9 |
| `app/Domain` must never import `Illuminate\Http` or `App\Http`. `ProtectedUserException` stays a pure domain exception; its HTTP status mapping belongs in `bootstrap/app.php` (or controller layer), not inside `ProtectedUserException` | `LAY2`, `tests/Arch/AccountsArchTest.php` |
| Passwords are never returned in responses, never logged, and never hashed with `Hash::make` in requests or controllers | `ACT6`, `SCR2` |
| Role values use `UserRole` enum cases (`Rule::enum(UserRole::class)`), never hardcoded `'superadmin'`, `'retailer'`, or `'customer'` strings in application logic | `STD3` |
| `DELETE /api/v1/users/{user}` performs a soft delete via `UserService::delete` and returns `204 No Content` with an empty body | `ARCHITECTURE.md` Â§Â§7, 9 |
| No token revocation occurs in `ACC-U3`; `UserService` and `UserController` do not import `TokenIssuer` or `Laravel\Passport` | `STM1`, `ACC-U5` scope |

---

## Open decisions

Achintha must answer these before the plan is frozen (G1 gate). My recommendation is marked for each (if `ARCHITECTURE.md` Â§Â§6â€“7 already states the rule explicitly, `ARCHITECTURE.md` takes precedence and Gemini must cite the section in the plan).

| ID | Question |
|---|---|
| OD-1 | **Authorization matrix (`UserPolicy`) for `Retailer` and `Customer`.** Recommended: `viewAny` (list), `create`, and `delete` are `Superadmin`-only. For `view` (show) and `update`, `Superadmin` is allowed on any user, while `Retailer` and `Customer` are allowed only on their own record (`$actor->id === $target->id` / `$actor->is($target)`). Furthermore, a non-superadmin updating their own record is forbidden (`403`) from supplying a `role` field (or changing their role). Alternative: all `/api/v1/users/*` endpoints are strictly `Superadmin`-only. |
| OD-2 | **HTTP status for `ProtectedUserException` and `role = superadmin` input.** Recommended: `StoreUserRequest` and `UpdateUserRequest` validate `role` with `Rule::enum(UserRole::class)` (so bogus strings like `'admin'` fail with `422`), while attempts to create/promote to `Superadmin` or delete/demote an existing `Superadmin` throw `ProtectedUserException` from `UserService` and render as `403 Forbidden` via `bootstrap/app.php`. Alternative: reject `UserRole::Superadmin` in FormRequest validation (`422`) and render `ProtectedUserException` as `403` (or `422`). |
| OD-3 | **How `{user}` is resolved in `UserController` (`{user:public_id}` route binding vs `UserService::get`).** Recommended: do not modify `app/Models/User.php`. Either declare `{user:public_id}` in `routes/api/users.php` (so Laravel binds non-trashed `User` models by `public_id`) or accept `string $publicId` in `show`/`update`/`destroy` and call `$this->userService->get($publicId)` before `$this->authorize(...)`. Both avoid touching `User.php` and guarantee `404` for soft-deleted users and raw integer IDs. |
| OD-4 | **List filter validation (`GET /api/v1/users`).** To stay well inside the 10-file size guard, should query parameter validation (`search`, `role`, `include_trashed`, `per_page`) be done inline in `UserController::index` (or a small method on `UserFilterData`), or via a third `IndexUserRequest` class? Recommended: validate query parameters cleanly in `UserController::index` (or `IndexUserRequest` only if total manifest files remain $\le 10$). |

---

## Acceptance criteria

| ID | Criterion |
|---|---|
| AC-1 | `UserResponseData::fromModel(User $user)` maps `public_id`, `name`, `email`, `role`, and timestamps matching `ARCHITECTURE.md` Â§7, and its serialized output never contains the integer `id`, `password`, or `remember_token`. |
| AC-2 | Unauthenticated requests to any `/api/v1/users` endpoint (`GET /users`, `POST /users`, `GET /users/{user}`, `PATCH /users/{user}`, `DELETE /users/{user}`) return `401 Unauthorized` as JSON (even without an `Accept: application/json` header). |
| AC-3 | `UserPolicy` is registered and enforced on every `UserController` action: unauthorized callers (`Customer` or `Retailer` calling `index`, `store`, `destroy`, or accessing/updating another user's `public_id`, or attempting to change their own `role` per OD-1) receive `403 Forbidden`. |
| AC-4 | `GET /api/v1/users` (as `Superadmin`) returns a paginated JSON response of `UserResponseData` items ordered by `created_at DESC, id DESC`, supporting `search`, `role`, `include_trashed`, and `per_page` filters via `UserService::list`. Invalid `role` or `include_trashed` query values return `422`. |
| AC-5 | `POST /api/v1/users` (as `Superadmin`) validates input via `StoreUserRequest`, creates a `Customer` or `Retailer` via `UserService::create`, and returns `201 Created` with `UserResponseData`. Duplicate emails (including case/whitespace variants or emails belonging to soft-deleted users) and invalid payloads return `422`. |
| AC-6 | `GET /api/v1/users/{user}` returns `200 OK` with `UserResponseData` when looked up by valid `public_id`, and returns `404 Not Found` when looked up by raw integer `id`, unknown `public_id`, or a soft-deleted user's `public_id`. |
| AC-7 | `PATCH /api/v1/users/{user}` (and `PUT` if defined in `ARCHITECTURE.md` Â§7) validates partial input via `UpdateUserRequest`, updates only the provided fields via `UserService::update`, allows keeping the target user's own email without a false unique error, and returns `200 OK` with the updated `UserResponseData`. |
| AC-8 | `DELETE /api/v1/users/{user}` (as `Superadmin`) soft-deletes the target user via `UserService::delete` and returns `204 No Content`. Subsequent `GET /api/v1/users/{user}` for that `public_id` returns `404`. |
| AC-9 | Superadmin protection over HTTP: attempting via `POST`, `PATCH`, or `DELETE /api/v1/users*` to create a `Superadmin`, promote a user to `Superadmin`, change a `Superadmin`'s role, or delete a `Superadmin` fails with the agreed HTTP status (OD-2: `403 Forbidden`) and leaves the database unchanged. Updating a `Superadmin`'s `name` or `email` without changing `role` succeeds with `200 OK`. |
| AC-10 | Soft-delete ADR is present in `docs/adr/` and linked in `ARCHITECTURE.md` Â§11 (`OQ6`); `composer check` (Pint + Larastan + Pest) passes with zero errors; `tests/Feature/Tokens/` and `tests/Arch/` pass unchanged. |

---

## Rules most at risk

| Rule ID | Why it is at risk in this step |
|---|---|
| DBS5 | Exposing `$user->id` instead of `$user->public_id` in `UserResponseData`, or allowing `/api/v1/users/{id}` to resolve by integer primary key, or ignoring soft-deleted rows by raw `id` in route binding. |
| LAY1 / LAY4 / LAY6 | Putting Eloquent queries or `User::query()` inside `UserController`, calling `UserRepositoryInterface` from `UserController`, or returning a `User` model directly instead of `UserResponseData`. |
| LAY2 | Adding a `render(Request $request): JsonResponse` method directly onto `App\Domain\Accounts\Exceptions\ProtectedUserException`, which imports `Illuminate\Http` into `app/Domain` and breaks `AccountsArchTest`. |
| STD1 | Every new file (`UserResponseData`, `UserPolicy`, Form Requests, `UserController`, `UserControllerTest`) and modified file (`routes/api/users.php`, `AppServiceProvider.php`, `bootstrap/app.php`) must have `declare(strict_types=1);`. |
| STD3 | Using string literals `'superadmin'`, `'retailer'`, `'customer'` in `UserPolicy`, Form Requests, or `UserController` instead of `UserRole` cases and `Rule::enum(UserRole::class)`. |
| STM1 / STM3 | Temptation to inject `TokenIssuer` to revoke tokens on `update` or `destroy` (belongs to `ACC-U5`), or using `Passport::actingAs` if forbidden by stream rules (verify in Section 3 how `auth:api` is authenticated in Accounts feature tests without importing `Laravel\Passport` into forbidden namespaces). |
| ACT6 / SCR2 | Hashing passwords in `StoreUserRequest` / `UpdateUserRequest` / `UserController` (causing double hashing with the `User` model cast) or leaking `password` in `UserResponseData` or validation exception payloads. |
| TST2 / TST4 | Omitting negative/authorization test cases (`401`, `403`, `404` on raw integer `id`, `422`), or creating test rows without `UserFactory`. |
| WFL6 | Scope creep: adding `/auth/login`, `/auth/register`, `findByEmail`, token revocation, or editing `ACC-U2` domain classes without a Change Request. |

---

## Known traps

1. **Email normalisation before `Rule::unique` in Form Requests.** `UserService` lowercases and trims emails before writing, so the database always stores normalised emails. If `StoreUserRequest` or `UpdateUserRequest` runs `Rule::unique('users', 'email')` on raw input `'  ALICE@Example.com '` without normalising it first in `prepareForValidation()`, validation will pass on a duplicate email and PostgreSQL will throw a `UniqueConstraintViolationException` (`500`) inside `UserService`! Normalise `email` in the Form Request before validation runs.
2. **Unique email ignore rule on `UpdateUserRequest`.** When updating a user with `{user:public_id}` (or a `public_id` route parameter), `Rule::unique('users', 'email')->ignore(...)` must ignore the target user cleanly (e.g., `->ignore($targetPublicId, 'public_id')` or `->ignore($targetUserModel)`). Test updating a user's `name` while sending their existing `email` (including uppercase/whitespace variants of their own email) to prove it returns `200`, not `422`.
3. **Combined `UserData` and `Optional` in `UpdateUserRequest::toDto()`.** In `UpdateUserRequest`, any field not present in the validated payload (`! $this->has(...)` / `! array_key_exists(...)`) must be passed as `Optional::create()` to `UserData`, never `null`. Passing `null` or `''` will either fail strict typing or overwrite existing database columns.
4. **Domain isolation when rendering `ProtectedUserException` (`LAY2`).** `tests/Arch/AccountsArchTest.php` forbids `App\Domain` from importing `Illuminate\Http` or `App\Http`. Do not import `Illuminate\Http\Request` or `Illuminate\Http\JsonResponse` inside `ProtectedUserException`. Map `ProtectedUserException` to its HTTP response in `bootstrap/app.php` (`$exceptions->render(...)`) or in the HTTP layer.
5. **Policy discovery for `App\Domain\Accounts\Policies\UserPolicy`.** Because `User` is in `App\Models\User` and `UserPolicy` is in `App\Domain\Accounts\Policies\UserPolicy` (not `App\Policies\UserPolicy`), Laravel's default policy auto-discovery may not find it unless `Gate::policy(User::class, UserPolicy::class)` is registered in `AppServiceProvider::boot()`.
6. **Authenticating users in `tests/Feature/Accounts/` without violating Arch rules.** Check `tests/Arch/AccountsArchTest.php` first: `AccountsArchTest` checks `app/` namespaces for `Laravel\Passport` imports, and `actingAs($user, 'api')` works directly with Laravel's `auth:api` guard in feature tests without needing `Passport::actingAs()`. Verify how `actingAs($user, 'api')` behaves with `$user` in `UserControllerTest`.
7. **Paginated response transformation.** `UserService::list` returns a `LengthAwarePaginator` of `User` models. `UserController::index` must transform the items through `UserResponseData::fromModel(...)` while preserving pagination metadata (`current_page`, `per_page`, `total`, `last_page`, etc. per `ARCHITECTURE.md` Â§7) and must never serialize raw `User` models (which would leak `id` or `deleted_at` unexpectedly).
8. **Raw integer `id` vs `public_id` in URLs (`DBS5`).** A test must explicitly create a user, take `$user->id` (e.g., `1`), request `GET /api/v1/users/{$user->id}`, and assert `404 Not Found`.
9. **Role self-escalation in `UpdateUserRequest` / `UserPolicy`.** If OD-1 allows a `Customer` or `Retailer` to update their own profile, ensure they cannot pass `"role": "retailer"` or `"role": "superadmin"` to change their own role.
10. **Token revocation window (`ACC-U5`).** After `ACC-U3`, `DELETE /api/v1/users/{user}` and password/role updates via `PATCH /api/v1/users/{user}` are reachable over HTTP but do not revoke tokens until `ACC-U5`. Record this as an expected deferral in Section 13 of the plan; do not pull `ACC-U5` into `ACC-U3`.

---

## Size guard

**Expected file count: 9â€“10 files**
- New (6â€“7): `UserResponseData.php`, `UserPolicy.php`, `StoreUserRequest.php`, `UpdateUserRequest.php`, `UserController.php`, `docs/adr/0003-user-soft-deletes.md` (if not already written), `UserControllerTest.php` (or `UserPolicyTest.php` + `UserControllerTest.php` if ADR was already written).
- Modified (2â€“3): `routes/api/users.php`, `app/Providers/AppServiceProvider.php`, and optionally `bootstrap/app.php` / `ARCHITECTURE.md`.

Split trigger: if the plan manifest exceeds **10 files** (excluding `docs/blueprints/ACC-U3/*`), touches any migration, or modifies any `ACC-U2` domain file or Tokens-stream file, stop and submit a Change Request.

---

## Step-specific Layer A checks

Run these in addition to the standard 15 Layer A commands from `docs/blueprints/RULE_REGISTER.md`:

```powershell
# 15a. No migration touched or added
git diff --name-only main...HEAD -- database/migrations

# 15b. No Tokens-stream or Contract seam files touched
git diff --name-only main...HEAD | Select-String -SimpleMatch 'Token','passport','routes/api.php'

# 15c. No ACC-U1 / ACC-U2 domain files modified without a CR
git diff --name-only main...HEAD -- app/Models/User.php app/Domain/Accounts/Enums app/Domain/Accounts/Contracts app/Domain/Accounts/Repositories app/Domain/Accounts/Services app/Domain/Accounts/Exceptions app/Domain/Accounts/Data/UserData.php app/Domain/Accounts/Data/UserFilterData.php

# 15d. Accounts domain imports nothing from Passport, Http or Filament
Get-ChildItem app/Domain/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport','App\Http','Illuminate\Http','App\Filament'

# 15e. UserController does not import UserRepository or Eloquent Builder directly
Select-String -Path app/Http/Controllers/Api/V1/Users/UserController.php -SimpleMatch 'UserRepository','Illuminate\Database\Eloquent\Builder'

# 15f. No Hash::make in Accounts domain, HTTP layer, or Accounts tests
Get-ChildItem app/Domain/Accounts,app/Http,tests/Feature/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'

# 15g. No role string literals in Accounts domain, Policies, Requests, or Controllers
Get-ChildItem app/Domain/Accounts,app/Http/Controllers/Api/V1/Users,app/Http/Requests -Recurse -Filter *.php | Select-String -SimpleMatch "'superadmin'","'retailer'","'customer'"

# 15h. Users routes registered under api/v1/users
php artisan route:list --path=api/v1/users

# 15i. Token tests and Arch tests still pass unchanged
php artisan test --compact tests/Feature/Tokens tests/Arch
