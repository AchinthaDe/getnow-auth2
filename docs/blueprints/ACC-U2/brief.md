# [ACC-U2 | BRIEF | Claude]

## Goal

Build the Users-stream domain layer that the HTTP layer (ACC-U3) and the auth flow (ACC-U4) call. This step adds the single combined input DTO `UserData` (create and update), `UserFilterData`, `UserRepositoryInterface` and its Eloquent `UserRepository`, and `UserService` with `list`, `get`, `create`, `update` and `delete` (soft delete). It also enforces the superadmin protection rules and email normalisation in the service, and binds the repository interface in the container. No controller, no policy, no form request, no route, no response DTO, no migration: those arrive in later steps.

---

## Depends on

- **ACC-U1** (accepted per Achintha; Claude has not seen its VERIFY or final diff, so Gemini must read the actual files, not this brief, for current state).
- From ACC-U1, this step uses directly:
  - `App\Domain\Accounts\Enums\UserRole` (`Superadmin`, `Retailer`, `Customer`)
  - `App\Models\User` (final, `SoftDeletes`, `public_id`, `role` cast to `UserRole`, `password` with the `hashed` cast)
  - `Database\Factories\UserFactory` (default `customer`; `customer()`, `retailer()`, `superadmin()` states; plain `'password'`)
  - `tests/Arch/AccountsArchTest.php` — must remain green
- From ACC-C1 / ACC-T0: the Tokens stream is untouched and its tests (`tests/Feature/Tokens/`) must pass unchanged.

---

## In scope

1. `app/Domain/Accounts/Data/UserData.php` — one spatie/laravel-data class used for both create and update. Fields: `name`, `email`, `password`, `role`, each typed `<type>|Optional`. On update, an absent (`Optional`) field means "leave unchanged".
2. `app/Domain/Accounts/Data/UserFilterData.php` — list filter: `?string $search = null`, `?UserRole $role = null`, `bool $includeTrashed = false`, `int $perPage = 15`.
3. `app/Domain/Accounts/Contracts/UserRepositoryInterface.php` — methods: `paginate(UserFilterData): LengthAwarePaginator`, `findByPublicId(string): ?User`, `create(UserData): User`, `update(User, UserData): User`, `delete(User): void`. Nothing else (no `findByEmail`; ACC-U4 adds it).
4. `app/Domain/Accounts/Repositories/UserRepository.php` — `final`, Eloquent implementation of the interface. Contains no business rules.
5. `app/Domain/Accounts/Services/UserService.php` — `final`, depends on `UserRepositoryInterface`. Methods: `list(UserFilterData): LengthAwarePaginator`, `get(string $publicId): User`, `create(UserData): User`, `update(User, UserData): User`, `delete(User): void`. Owns all business rules (see Decisions).
6. `app/Domain/Accounts/Exceptions/ProtectedUserException.php` — one exception class with named constructors for the superadmin protection cases (see Decisions). No other new exception classes.
7. `app/Providers/AppServiceProvider.php` — modify: bind `UserRepositoryInterface` to `UserRepository` in `register()`. No new provider.
8. `tests/Feature/Accounts/UserRepositoryTest.php` — repository behaviour against PostgreSQL.
9. `tests/Feature/Accounts/UserServiceTest.php` — service rules against PostgreSQL, using the real repository.

---

## Out of scope

- `UserResponseData`, `UserPolicy`, `UserController`, form requests, routes, route model binding, `getRouteKeyName` — all ACC-U3
- Soft-delete ADR — ACC-U3 where `destroy` is exposed (unless OD-4 says it was already written)
- `AuthService`, `AuthController`, login, register, `findByEmail` — ACC-U4
- Token revocation on delete, password change or role change — ACC-U5. `UserService` must not import or call anything from the Tokens stream.
- Restore of soft-deleted users, force delete, bulk operations, sorting options, cursor pagination
- Any migration, any change to existing migrations, any change to `User`, `UserFactory`, `SuperadminSeeder` or `UserRole` (a need to touch them is a Change Request)
- Any Tokens-stream file (`PassportTokenIssuer`, `TokenController`, `TokenServiceProvider`, `config/passport.php`, `routes/api/tokens.php`)
- Email verification, password reset, 2FA, Filament UI — permanently out of scope (ARCHITECTURE.md §1)
- Activity logging, events, notifications, caching

---

## Read first

- `ARCHITECTURE.md` — the Accounts structure section (confirm the folder layout for `Contracts/`, `Repositories/`, `Services/`, `Data/`, `Exceptions/`), the data-layer and DTO sections, §9 (database), §10 (known issue)
- `AGENTS.md` — Account rules and Coding standards sections
- `docs/blueprints/RULE_REGISTER.md` — full register (authoritative for the compliance matrix)
- `app/Models/User.php`, `app/Domain/Accounts/Enums/UserRole.php`, `database/factories/UserFactory.php`, `database/seeders/SuperadminSeeder.php` — as left by ACC-U1
- `database/migrations/` — the users table migration and the ACC-U1 migration: check the real column list, the unique index on `email`, and whether `public_id` has a database default
- `app/Providers/AppServiceProvider.php` — current state, including whether it already has `declare(strict_types=1);`
- `tests/Arch/AccountsArchTest.php` — existing rules and namespaces
- `tests/Feature/Accounts/UserRoleTest.php` — pattern for Accounts feature tests

---

## Decisions made

Gemini must not re-decide these.

| Decision | Source |
|---|---|
| One combined `UserData` for create and update, with `Optional` fields on update (answers the senior's "merge insert and update DTO" suggestion) | ACC-U1 brief out-of-scope list (single `UserData`) |
| `UserData` carries no validation attributes in this step. Format, length and uniqueness rules live in the ACC-U3 form requests (subject to OD-1) | OD-1 recommendation |
| `UserService::create` requires `name`, `email`, `password` and `role` to be present (not `Optional`); a missing field throws `InvalidArgumentException` naming the missing field names, never values | Combined-DTO consequence |
| `UserService::update` writes only the fields that are present; an absent field never nulls or rewrites a column. An absent `password` must not touch the stored hash | Combined-DTO consequence |
| Repository and service return `App\Models\User` models. Mapping to `UserResponseData` happens in ACC-U3. Raw `id` is never returned in any Data object and never used as a lookup key | ARCHITECTURE.md §9, DBS5 |
| Lookups use `public_id` only. `UserService::get` throws `Illuminate\Database\Eloquent\ModelNotFoundException` when the user does not exist or is soft-deleted | DBS5 |
| Passwords are plain strings in `UserData` and hashed only by the `hashed` cast on `User`. No `Hash::make` anywhere | ACT6, ARCHITECTURE.md §8 |
| Emails are trimmed and lowercased in `UserService` (the single place), before every create and update. ACC-U4 login lookup must normalise the same way | New, this step |
| Default list excludes soft-deleted users. `includeTrashed = true` includes them. List order is `created_at DESC, id DESC` (the `id` is only a tie-breaker and is never exposed) | New, this step |
| `search` matches `name` or `email` with PostgreSQL `ILIKE`; `%`, `_` and `\` in the search text are escaped so they match literally. Empty or whitespace-only search is treated as null | New, this step |
| `perPage` is clamped in `UserService::list` to 1–100; default 15 | New, this step |
| `delete` is a soft delete (`SoftDeletes`) through the repository. There is no force delete method | ARCHITECTURE.md §9 |
| Superadmin protection (final per OD-2): the service refuses to create or update a user with role `Superadmin`, and refuses to delete a user whose role is `Superadmin` or change that user's role. Each refusal throws `ProtectedUserException` via a named constructor. Superadmins are created only by `SuperadminSeeder` | OD-2 recommendation |
| The interface is bound in `AppServiceProvider::register()`. No new service provider | Size guard |
| Role values come only from `UserRole`; no `'superadmin'`, `'retailer'` or `'customer'` string literals in application code | STD3 |
| No migration in this step. The unique index on `email` stays as is | DBS2, OD-3 recommendation |

---

## Open decisions

Achintha must answer these before the plan is written (G1 gate). My recommendation is marked for each.

| ID | Question |
|---|---|
| OD-1 | **Where do validation rules live?** With a combined `UserData`, "email unique, ignoring the current user on update" is awkward as Data attributes. Recommended: no validation on `UserData`; ACC-U3 Form Requests hold the rules (create: all fields required; update: all optional, unique email ignoring the route user's `public_id`) and build `UserData`. Alternative: Data attributes with validation context. |
| OD-2 | **Superadmin protection.** Recommended: superadmins exist only via the seeder; the service cannot create or promote anyone to `Superadmin`, and cannot delete or change the role of a superadmin. This also removes the "last superadmin" problem. Alternative: allow superadmin creation through CRUD and add a "cannot remove the last superadmin" guard. |
| OD-3 | **Email reuse after soft delete.** The unique index on `email` includes soft-deleted rows, so a deleted user's email can never be registered again. Recommended: accept this (emails stay reserved), no migration. Alternative: a new migration with a partial unique index on `email` where `deleted_at IS NULL`; that adds a migration and moves the step to 10+ files, so it would need a CR. |
| OD-4 | **Soft-delete ADR (OQ6 from ACC-U1).** Was the ADR written in ACC-U1? If not, recommended: write it in ACC-U3 with `destroy`. |

---

## Acceptance criteria

| ID | Criterion |
|---|---|
| AC-1 | `UserData` is a single class used for create and update; each of `name`, `email`, `password`, `role` is `<type>|Optional`. `UserFilterData` has the four fields and defaults listed in scope. |
| AC-2 | Resolving `UserRepositoryInterface` from the container returns `UserRepository`; resolving `UserService` from the container succeeds with no manual wiring. |
| AC-3 | `UserService::create` persists a row with the given name and role, a lowercased and trimmed email, a ULID `public_id`, and a password that is hashed (`Hash::check` passes, stored value is not the plain text). A missing required field throws `InvalidArgumentException` and writes no row. |
| AC-4 | `UserService::update` with only `name` present changes only `name`: email, role and password hash are unchanged. With `password` present, the stored hash changes and verifies against the new password. With `email` present, it is lowercased and trimmed. |
| AC-5 | `UserService::list` excludes soft-deleted users by default and includes them when `includeTrashed = true`; filters by `role`; `search` matches `name` or `email` case-insensitively; a search of `%` or `_` matches only literal characters; `perPage` above 100 returns 100 per page and below 1 returns 1. |
| AC-6 | `UserService::delete` sets `deleted_at`, keeps the row in the table, and the user no longer appears in `get` or the default `list`. |
| AC-7 | `UserService::get` returns the user for a valid `public_id` and throws `ModelNotFoundException` for an unknown or soft-deleted one. |
| AC-8 | `ProtectedUserException` is thrown, with no change to the database, when the service is asked to: create a user with role `Superadmin`; update a user to role `Superadmin`; delete a superadmin; change a superadmin's role. Updating a superadmin's name or email without a role change is allowed. |
| AC-9 | `composer check` (Pint + Larastan + Pest) passes with zero errors, zero skipped tests, zero baseline entries; `tests/Feature/Tokens/` pass unchanged; `tests/Arch` passes (Domain does not import `Laravel\Passport`, `App\Http` or `App\Filament`). |
| AC-10 | `declare(strict_types=1);` is present in every new and modified PHP file; no `Hash::make` and no role string literals in `app/Domain/Accounts`. |

---

## Rules most at risk

| Rule ID | Why it is at risk in this step |
|---|---|
| DBS5 | Models come back from the repository; the easy mistake is adding `id` to a Data class, filtering by `id`, or exposing it in an exception message. |
| ACT6 | Create and update take a plain password. Hashing it in the repository or service "to be safe" double-hashes it through the `hashed` cast. |
| STD3 | The filter takes `?UserRole`, and the protection rules compare against `UserRole::Superadmin`. String comparisons on `role` are the most likely slip. |
| STM1 / STM3 | Delete, password change and role change are tempting places to revoke tokens. That is ACC-U5. No `Laravel\Passport` import anywhere under `app/Domain/Accounts`, and no Tokens-stream file touched. |
| DBS2 | A migration will look useful (email partial index, extra column). None is in scope; a need for one is a CR. |
| TST4 | Tests build users with `UserFactory` and its states, never hand-built arrays or `DB::table` inserts. |
| STD1 | `AppServiceProvider.php` is a pre-existing file and may lack `declare(strict_types=1);`. Modifying it makes that a blocker. |
| WFL6 | Scope creep: response DTO, policy, controller, routes, `findByEmail`, restore, force delete. None belong here. |

---

## Known traps

1. **Where `public_id` is generated.** ACC-U1 allowed a database default and a PHP value in the factory. The repository `create` path has neither factory nor controller, so the plan must state how `public_id` gets set (read the migration for a DB default first). Do not add a model boot hook or observer to do it; business logic stays out of model boot methods.
2. **Combined DTO and `Optional`.** `UserData::toArray()` drops `Optional` fields only if the plan verifies it for the installed spatie/laravel-data version. The repository `update` must be proven (by test) not to null out absent fields. Do not pass `toArray()` blindly to `fill()`; build the attribute array from the present fields explicitly.
3. **`role` is fillable on `User`.** Mass assignment of `role` is safe only because the input is a typed DTO and the service enforces OD-2. The repository must never accept an array from callers.
4. **Double hashing.** Do not hash in the service or repository. The test must prove it with `Hash::check($plain, $user->password)` after reading the row back from the database.
5. **Soft deletes and queries.** `User` now has a global soft-delete scope. `findByPublicId` must exclude trashed users, `includeTrashed` must use `withTrashed()` explicitly, and a test must cover both.
6. **`ILIKE` wildcards.** `%` and `_` in user input act as wildcards unless escaped. Escape `\`, `%` and `_` before building the pattern; bind the pattern as a parameter, never concatenate it into raw SQL.
7. **Protection rules need the stored role.** For "change a superadmin's role", compare the model's current `role` before the write, not the DTO role. An update that sets a superadmin to `Superadmin` again with no real change must still be decided explicitly by the plan (recommended: allowed only when role is absent from the DTO, so any present role on a superadmin is refused).
8. **Email normalisation lives in the service only.** If the repository or DTO also lowercases, the rule is in two places. Also note `SuperadminSeeder` stores the env email as given; the plan must state whether that is acceptable (the seeder is out of scope here).
9. **Token revocation gap.** After this step `delete` and `update` exist without revoking tokens. This is accepted until ACC-U5 because no route reaches them yet. The plan must record it in section 13 as a known gap, not fix it.
10. **Binding location.** `AppServiceProvider` already exists. Do not create `AccountsServiceProvider`; it pushes the file count and adds a provider-registration change.

---

## Size guard

**Expected file count: 9 files** (new: `UserData.php`, `UserFilterData.php`, `UserRepositoryInterface.php`, `UserRepository.php`, `UserService.php`, `ProtectedUserException.php`, `UserRepositoryTest.php`, `UserServiceTest.php`; modified: `AppServiceProvider.php`).

Split trigger: if the plan manifest exceeds 10 files, adds any migration, or adds any class that is not a Data class, interface, repository, service or exception, stop and send a Change Request.

---

## Step-specific Layer A checks

Run these in addition to the standard Layer A sequence from `RULE_REGISTER.md §Layer A commands`:

```powershell
# 15a. No migration touched or added
git diff --name-only main...HEAD -- database/migrations

# 15b. No Tokens-stream, config or route files touched
git diff --name-only main...HEAD | Select-String -SimpleMatch 'Token','passport','routes/'

# 15c. Accounts domain imports nothing from Passport, Http or Filament
Get-ChildItem app/Domain/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport','App\Http','App\Filament'

# 15d. No Hash::make in Accounts domain or Accounts tests
Get-ChildItem app/Domain/Accounts,tests/Feature/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'

# 15e. No role string literals in the Accounts domain
Get-ChildItem app/Domain/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch "'superadmin'","'retailer'","'customer'"

# 15f. Every new/modified file has strict_types
Get-ChildItem app/Domain/Accounts,app/Providers,tests/Feature/Accounts -Recurse -Filter *.php | Where-Object { -not (Select-String -Path $_.FullName -SimpleMatch 'declare(strict_types=1);' -Quiet) } | Select-Object -ExpandProperty FullName

# 15g. No routes added for users (expect no output)
php artisan route:list --path=users

# 15h. Token tests still pass unchanged
php artisan test --compact tests/Feature/Tokens
```

---

## Notes for the next steps (not part of this brief's scope)

- **ACC-U3** (HTTP): `UserResponseData`, `UserPolicy`, form requests, `UserController` (index, store, update, show, destroy), routes, route binding on `public_id`, soft-delete ADR if not already written.
- **ACC-U4**: `AuthService`, login, register (customers only), `findByEmail` with the same email normalisation.
- **ACC-U5**: token revocation on delete, password change and role change.
- Because U3 exposes delete and role change before U5 revokes tokens, consider doing U5 before U3, or accept the window while everything is unmerged.
