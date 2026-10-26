# [ACC-U1 | BRIEF | Claude]

## Goal

Lay the Users-stream foundation that every subsequent users step builds on. This step adds the `UserRole` enum, extends the `users` table with `public_id`, `role`, and `deleted_at` columns in a new additive migration, finalises the `User` model (phpdoc, fillable, casts, soft deletes, no starter-kit residue), rewrites `UserFactory` (role states, `public_id`, `declare(strict_types=1)`, no `Hash::make`), creates `SuperadminSeeder` (idempotent), and updates `DatabaseSeeder` to call it. No controller, no service, no repository, no routes: those arrive in later steps.

---

## Depends on

- **ACC-C1** (accepted) — Fortify, Livewire, passkeys, web routes and starter-kit views stripped; unauthenticated JSON contract test passes; `composer check` green on `main`.
- From ACC-C1, this step uses directly:
  - `app/Models/User.php` — existing model to modify
  - `database/migrations/0001_01_01_000000_create_users_table.php` — existing merged migration, read-only reference
  - `database/factories/UserFactory.php` — existing factory to rewrite
  - `database/seeders/DatabaseSeeder.php` — existing seeder to update
  - `tests/Arch/AccountsArchTest.php` — existing arch tests, must remain green; new `strict_types` scope added

---

## In scope

1. `app/Domain/Accounts/Enums/UserRole.php` — new `UserRole` backed enum (`superadmin`, `retailer`, `customer`)
2. `database/migrations/<timestamp>_add_accounts_columns_to_users_table.php` — new migration: `public_id` ULID unique, `role` varchar(16) with CHECK constraint, `deleted_at` nullable timestamp, index on `role`
3. `app/Models/User.php` — update: add `public_id` and `role` to fillable and casts, add `SoftDeletes`, update phpdoc, remove two-factor and passkey residue, add `declare(strict_types=1)`, make `final`
4. `database/factories/UserFactory.php` — rewrite: `declare(strict_types=1)`, remove `Hash::make`, generate `public_id` via `Str::ulid()`, set default `role = UserRole::Customer`, add `customer()`, `retailer()`, and `superadmin()` states
5. `database/seeders/SuperadminSeeder.php` — new idempotent seeder: `updateOrCreate` on email from env, role forced to `UserRole::Superadmin`, never overwrites password if already set
6. `database/seeders/DatabaseSeeder.php` — call `SuperadminSeeder`, remove the `Test User` default row
7. `tests/Feature/Accounts/UserRoleTest.php` — unit-style feature test: enum cases, `UserFactory` role states produce correct DB rows, `SuperadminSeeder` is idempotent (run twice, one row)
8. `tests/Arch/AccountsArchTest.php` — extend `strict_types` expectation to cover `App\Models`, `App\Domain\Accounts\Enums`, `Database\Factories`, `Database\Seeders`

---

## Out of scope

- `UserRepositoryInterface`, `UserRepository`, `UserData`, `UserFilterData`, `UserResponseData`, `UserService`, `UserPolicy`, `UserController`, form requests, routes — all arrive in ACC-U2 and ACC-U3
- `AuthService`, `AuthController`, login and register — ACC-U4
- Token revocation on delete / password / role change — ACC-U5
- `FakeTokenIssuer` — not needed until ACC-U4; create it there
- Any change to Tokens-stream files or the `TokenIssuer` / `TokenPairData` contract
- Email verification, password reset, 2FA, Filament UI — permanently out of scope (ARCHITECTURE.md §1)
- Removing `password_reset_tokens` or `sessions` tables — not in this step; no destructive migration

---

## Read first

- `ARCHITECTURE.md` §§ 1, 3, 4, 5, 8, 9, 10 (known issue: factory must set a default role or token tests break)
- `AGENTS.md` — Account rules and Coding standards sections
- `docs/blueprints/RULE_REGISTER.md` — full register (authoritative for the compliance matrix)
- `app/Models/User.php` — current state (has 2FA and passkey residue from starter kit)
- `database/migrations/0001_01_01_000000_create_users_table.php` — existing columns; the new migration must not duplicate them
- `database/factories/UserFactory.php` — current state (calls `Hash::make`, no role, no `public_id`, no strict types)
- `database/seeders/DatabaseSeeder.php` — current state
- `tests/Arch/AccountsArchTest.php` — existing rules and their current namespaces
- `tests/Feature/Tokens/PassportTokenIssuerTest.php` — verify this still passes after `UserFactory` changes (known issue from §10)

---

## Decisions made

Gemini must not re-decide these.

| Decision | Source |
|---|---|
| `public_id` is a ULID, exposed externally; raw incremental `id` is never exposed | ARCHITECTURE.md §9, DBS5 |
| `role` column: `varchar(16)`, `CHECK (role IN ('superadmin','retailer','customer'))`, indexed | ARCHITECTURE.md §9, DBS4 |
| `deleted_at` is added for soft deletes so future orders and content keep their foreign keys | ARCHITECTURE.md §9 |
| Passwords hashed via the `hashed` cast on `User` only; no `Hash::make` anywhere | ACT6, ARCHITECTURE.md §8 |
| One `UserRole` enum for the fixed set; no string literals for roles in application code | STD3 |
| `SuperadminSeeder` uses `updateOrCreate` keyed on the superadmin email; running it twice produces one row | DBS6 |
| `UserFactory` default state produces a `customer`; three named states (`customer`, `retailer`, `superadmin`) | ARCHITECTURE.md §10 known issue — token tests break without a default role |
| The existing `0001_01_01_000000_create_users_table.php` migration is merged and must not be edited | DBS2 |
| No destructive column change in this step; `two_factor_*` columns stay in the table even though the model no longer uses them | DBS3 |

---

## Open decisions

Achintha must answer these before the plan is written (G1 gate).

| ID | Question |
|---|---|
| OD-1 | **Two-factor columns**: `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` exist in the table from the stripped starter kit. Should this step drop them in the new migration, or leave them and note the drop for a later cleanup migration? (DBS3 means we cannot drop them in the same release that stopped using them — that already happened in ACC-C1, so a drop is now safe, but confirm.) |
| OD-2 | **`remember_token`**: is it still needed, or should it be dropped in this migration? |
| OD-3 | **Superadmin seeder credentials**: where do the superadmin email and password come from? `.env` variables (e.g., `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD`)? If so, confirm the variable names and whether they should be added to `.env.example`. |
| OD-4 | **OQ6 (hard vs soft delete)**: ARCHITECTURE.md §11 marks this open. `deleted_at` is in scope per §9. Confirm soft delete is the final decision so an ADR can be written in this step (or defer the ADR to ACC-U3 where `destroy` is built). |

---

## Acceptance criteria

| ID | Criterion |
|---|---|
| AC-1 | `UserRole` enum exists at `App\Domain\Accounts\Enums\UserRole` with exactly three string-backed cases: `Superadmin = 'superadmin'`, `Retailer = 'retailer'`, `Customer = 'customer'`. |
| AC-2 | The new migration, when run on a clean PostgreSQL database, adds `public_id` (unique), `role` (with CHECK constraint and index), and `deleted_at` to the `users` table, and rolls back cleanly. |
| AC-3 | `User::factory()->create()` produces a row with a non-null `public_id` (ULID format) and `role = 'customer'` without any test calling `Hash::make`. |
| AC-4 | `User::factory()->superadmin()->create()` produces `role = 'superadmin'`; `->retailer()` produces `role = 'retailer'`; `->customer()` produces `role = 'customer'`. |
| AC-5 | Running `SuperadminSeeder` twice on the same database produces exactly one superadmin row (idempotency). |
| AC-6 | `DatabaseSeeder` calls `SuperadminSeeder` and no longer creates the `test@example.com` test user. |
| AC-7 | `composer check` (Pint + Larastan + Pest) passes with zero errors, zero skipped tests, and zero baseline entries after the step. |
| AC-8 | All existing token-stream tests (`tests/Feature/Tokens/`) pass unchanged after the `UserFactory` rewrite. |
| AC-9 | `declare(strict_types=1)` is present in every new and modified PHP file in this step. |
| AC-10 | The arch test `strict_types` expectation covers the namespaces of all new files (`App\Domain\Accounts\Enums`, `App\Models`, `Database\Factories`, `Database\Seeders`). |

---

## Rules most at risk

| Rule ID | Why it is at risk in this step |
|---|---|
| DBS2 | The existing `create_users_table` migration is merged. Editing it instead of adding a new one is the most likely mistake. |
| DBS3 | Dropping `two_factor_*` or `remember_token` in the new migration is only safe if OD-1 / OD-2 are answered yes. Do not drop without that answer. |
| DBS4 | The CHECK constraint for `role` and the index on `role` are easy to forget. |
| DBS5 | `public_id` must be the only identifier that ever leaves the model. The `UserFactory` and `SuperadminSeeder` must never expose or return the raw `id`. |
| ACT6 | `UserFactory` currently calls `Hash::make` directly. The rewrite must remove this; the `hashed` cast on `User` handles hashing. |
| STD1 | `UserFactory` currently lacks `declare(strict_types=1)`. All new and modified files must have it. |
| STM1 | This is a Users-stream step. No Tokens-stream files (`PassportTokenIssuer`, `TokenController`, `TokenServiceProvider`, `config/passport.php`, `routes/api/tokens.php`) may be edited. |
| STM3 | `UserRole` and any new Domain code must not import `Laravel\Passport`. |
| TST4 | `SuperadminSeeder` and the unit tests must use the factory and `updateOrCreate`, not hand-built arrays. |

---

## Known traps

1. **Token tests break if `role` has no default.** ARCHITECTURE.md §10 calls this out explicitly. The factory's `definition()` must include `'role' => UserRole::Customer` before the token tests are re-run.
2. **`public_id` must be generated in `definition()`, not as a database default.** Laravel's `ulid()` column helper can set a DB default, but the factory also needs it in PHP so tests can reference `$user->public_id` synchronously. Both is fine; neither alone is.
3. **`Hash::make` in `UserFactory` bypasses the `hashed` cast.** Because the factory currently sets `password` to a pre-hashed string via `Hash::make`, removing it must not break tests that rely on logging in with `'password'`. The fix: set `'password' => 'password'` (plain) and let the `hashed` cast hash it on save.
4. **Strict-types arch test scope is narrow today.** `AccountsArchTest.php` currently only covers `App\Domain`, `App\Http\Controllers\Api`, `App\Http\Requests\Auth`, and `App\Providers\TokenServiceProvider`. `App\Models`, `Database\Factories`, and `Database\Seeders` are not covered. Expanding the scope is in scope for this step; any file in those namespaces that currently lacks the declaration becomes a blocker.
5. **Soft delete and `SoftDeletes` trait.** Adding `SoftDeletes` to `User` changes the model's query scope globally. The token tests do not use soft-deleted users, but the plan must confirm the trait is added and that existing tests still pass.
6. **Idempotency of `SuperadminSeeder` in CI.** If `SUPERADMIN_EMAIL` is not set, the seeder must either fail loudly (preferred: throw with a clear message) or skip gracefully, never create a row with a null email. The plan must specify this.
7. **Do not create `FakeTokenIssuer` here.** It belongs in the step that first needs it (ACC-U4). Adding it now is scope creep (STM1 does not apply but WFL6 does).

---

## Size guard

**Expected file count: 8 files** (new: `UserRole.php`, migration, `SuperadminSeeder.php`, `UserRoleTest.php`; modified: `User.php`, `UserFactory.php`, `DatabaseSeeder.php`, `AccountsArchTest.php`).

Split trigger: if the plan manifest exceeds 10 files or introduces any new service-layer class, stop and send a Change Request.

---

## Step-specific Layer A checks

Run these in addition to the standard 15-command Layer A sequence from `RULE_REGISTER.md §Layer A commands`:

```powershell
# 15a. Verify public_id and role columns exist in the database after migration
php artisan migrate:status

# 15b. Verify the CHECK constraint for role exists on the users table
# (paste raw output of the information_schema query or \d users in psql)

# 15c. No Hash::make in any new or modified file
Get-ChildItem database/factories,database/seeders,app/Models,app/Domain/Accounts/Enums -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'

# 15d. Every new/modified file has strict_types
Get-ChildItem app/Models,app/Domain/Accounts/Enums,database/factories,database/seeders -Recurse -Filter *.php | Where-Object { -not (Select-String -Path $_.FullName -SimpleMatch 'declare(strict_types=1);' -Quiet) } | Select-Object -ExpandProperty FullName

# 15e. SuperadminSeeder idempotency: run the seeder twice and count rows
php artisan db:seed --class=SuperadminSeeder
php artisan db:seed --class=SuperadminSeeder
# Then verify row count = 1 via psql or tinker
```
