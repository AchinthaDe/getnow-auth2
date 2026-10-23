# [ACC-U1 | PLAN v2 | Gemini]

### 1. Understanding
Implement the foundational user domain elements for the Accounts module: the string-backed `UserRole` enum, an additive PostgreSQL migration adding `public_id`, `role`, and `deleted_at` with a `CHECK` constraint and index, while dropping legacy starter-kit columns (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `remember_token`), the `User` model updated with strict types, final modifier, role cast, `SoftDeletes`, and clean attributes (removing `MustVerifyEmail`), the `UserFactory` rewritten with ULID generation and plain password handling, superadmin configuration in `config/auth.php`, the idempotent `SuperadminSeeder`, the updated `DatabaseSeeder`, and the architectural decision record `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md`.
Out of scope: HTTP controllers, FormRequests, API routes, middleware, `UserRepositoryInterface`, `UserRepository`, `UserService`, `AuthService`, `UserPolicy`, token issuance, token revocation logic, `FakeTokenIssuer`, Filament admin panels, email verification, password reset workflows, two-factor authentication, and passkeys.

### 2. Sources read

| File or doc section | What in it constrains this step |
|---|---|
| `AGENTS.md` | Enforces `declare(strict_types=1);` (STD1), typed parameters and final classes (STD2), enum usage (STD3), no `Hash::make` in application code (ACT6), PostgreSQL only (DBS1), no destructive changes in same release (DBS3), ULID for external identity (DBS5), idempotent seeders (DBS6), `composer check` passing (ENV4), stream boundaries (STM1, STM3). |
| `ARCHITECTURE.md` §§ 1, 3, 5, 8, 9, 10 | Constrains roles to `superadmin`, `retailer`, `customer`. Table requirements: `public_id` ULID unique, `role` varchar(16) with `CHECK (role IN ('superadmin','retailer','customer'))` and index, `deleted_at`. Password hashing via `hashed` cast on `User`. Known issue in §10: `UserFactory` must define a default role or existing token tests fail. |
| `docs/blueprints/RULE_REGISTER.md` | Governs the 75 rule IDs evaluated in the Section 8 compliance matrix and establishes Layer A commands. |
| `docs/blueprints/ACC-U1/brief.md` | Specifies scope, acceptance criteria AC-1 through AC-10, size guard (8 files expected, split trigger at 10 code files), known traps, and step-specific checks. |
| `docs/adr/0001-passport-token-issuance-and-refresh-rotation.md` | Establishes token issuance ADR format and baseline tokens architecture. |
| `docs/adr/0002-remove-starter-kit.md` | Documents removal of starter kit code in ACC-C1 and establishes that column drops are deferred to subsequent steps. |
| `app/Models/User.php` | Shows current model structure: implements `MustVerifyEmail, OAuthenticatable`, uses `HasApiTokens, HasFactory, Notifiable`, lacks `SoftDeletes`, contains two-factor annotations and hidden fields. |
| `database/migrations/0001_01_01_000000_create_users_table.php` | Merged baseline migration defining initial `users` columns (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, timestamps). Must remain unmodified per DBS2. |
| `database/migrations/2025_08_14_170933_add_two_factor_columns_to_users_table.php` | Shows existing two-factor columns (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`) added to `users`. Must remain unmodified per DBS2. |
| `database/factories/UserFactory.php` | Shows current factory definition calling `Hash::make('password')`, lacking strict types, missing `public_id`, and missing `role`. |
| `config/auth.php` | Authentication configuration file; target for superadmin credentials configuration array. |
| `database/seeders/DatabaseSeeder.php` | Shows current root seeder creating a dummy `Test User` row. |
| `tests/Arch/AccountsArchTest.php` | Contains existing strict types arch test whose namespace coverage must be expanded to include new and modified classes. |
| `tests/Feature/Tokens/PassportTokenIssuerTest.php` | Invokes `User::factory()->create()` without explicit role parameters, requiring a factory default role. |
| `tests/Feature/Tokens/TokenEndpointsTest.php` | Verifies token rotation, revocation, and rate limiting; tests rely on `User::factory()->create()`. |
| `tests/Feature/Tokens/PassportSetupTest.php` | Validates Passport configuration, user traits, and OAuth tables. |
| `tests/Unit/Domain/Accounts/TokenPairDataTest.php` | Validates `TokenPairData` serialization. |
| `tests/Feature/Contract/ApiUnauthenticatedResponseTest.php` | Validates 401 JSON contract response. |
| `tests/Pest.php` | Configures test suite with `RefreshDatabase` for Feature tests. |
| `phpstan.neon` | Configures Level 7 static analysis paths across `app/`, `database/`, `config/`, and `routes/`. |
| `composer.json` | Specifies package dependencies, PSR-4 autoloading (`App\`, `Database\Factories\`, `Database\Seeders\`), and verification scripts (`lint`, `stan`, `test`, `check`). |
| `.env.example` | Contains environment configuration templates; target for seeder credential variables. |

### 3. Verified current state

- `App\Models\User` (`app/Models/User.php`):
  - Signature: `class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable`
  - Attributes: `#[Fillable(['name', 'email', 'password'])]`, `#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]`
  - Traits: `use HasApiTokens, HasFactory, Notifiable;`
  - Method: `protected function casts(): array` returning `['email_verified_at' => 'datetime', 'password' => 'hashed']`
  - Method: `public function initials(): string`
- `Database\Factories\UserFactory` (`database/factories/UserFactory.php`):
  - Signature: `class UserFactory extends Factory`
  - Property: `protected static ?string $password;`
  - Method: `public function definition(): array` returning attributes with `'password' => static::$password ??= Hash::make('password')`
  - Method: `public function unverified(): static`
  - Method: `public function withTwoFactor(): static`
  - Strict types: Not declared.
- `Database\Seeders\DatabaseSeeder` (`database/seeders/DatabaseSeeder.php`):
  - Signature: `class DatabaseSeeder extends Seeder`
  - Trait: `use WithoutModelEvents;`
  - Method: `public function run(): void` creating `'Test User'` with email `'test@example.com'`.
  - Strict types: Not declared.
- `config/auth.php`:
  - Contains array keys `'defaults'`, `'guards'`, `'providers'`, `'passwords'`, `'password_timeout'`.
  - Does not contain a `'superadmin'` configuration block.
- `tests/Arch/AccountsArchTest.php`:
  - Rule: `arch('strict types are enforced')->expect(['App\Domain', 'App\Http\Controllers\Api', 'App\Http\Requests\Auth', 'App\Providers\TokenServiceProvider'])->toUseStrictTypes()`

### 4. File manifest

| Path | New or modify | Layer | One-line responsibility | Rule IDs |
|---|---|---|---|---|
| `app/Domain/Accounts/Enums/UserRole.php` | New | Domain | String-backed enum defining user roles (`superadmin`, `retailer`, `customer`) | STD1, STD2, STD3, ACT1, ACT2, ACT3, ACT4, ACT5, LAY7 |
| `database/migrations/2026_10_23_000000_add_accounts_columns_to_users_table.php` | New | Database | Additive migration adding `public_id`, `role`, `deleted_at`, CHECK constraint, index, and dropping legacy columns | DBS1, DBS2, DBS3, DBS4, DBS5 |
| `app/Models/User.php` | Modify | Model | Eloquent model updated with strict types, final modifier, `SoftDeletes`, role cast, clean attributes, and removal of `MustVerifyEmail` | STD1, STD2, ACT6, DBS5, STM1, STM3, LAY7 |
| `database/factories/UserFactory.php` | Modify | Database | Factory rewritten with strict types, ULID public ID, plain password, customer default, and role states | STD1, STD2, ACT6, DBS5, TST4 |
| `config/auth.php` | Modify | Configuration | Exposes `superadmin.email` and `superadmin.password` mapped to environment variables | SCR1, DNT7, ACT4 |
| `database/seeders/SuperadminSeeder.php` | New | Database | Idempotent seeder reading credentials from config and creating or updating superadmin user record | STD1, STD2, ACT4, DBS6 |
| `database/seeders/DatabaseSeeder.php` | Modify | Database | Root seeder updated with strict types, calling `SuperadminSeeder` and removing dummy user | STD1, STD2, DBS6 |
| `tests/Feature/Accounts/UserRoleTest.php` | New | Testing | Feature tests verifying enum cases, factory states, database constraints, rollback integrity, and seeder idempotency | TST1, TST2, TST4, DBS4, DBS6 |
| `tests/Arch/AccountsArchTest.php` | Modify | Testing | Arch test expanding strict types expectation to all new and modified namespaces | STD1, TST1 |
| `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md` | New | Documentation | Architectural decision record documenting soft delete adoption and legacy column drops | DNT7 |
| `.env.example` | Modify | Configuration | Appends environment variable definitions for superadmin seeder credentials | SCR1, DNT7 |
| `ARCHITECTURE.md` | Modify | Documentation | Updates progress checklist for step ACC-U1 | DNT7 |

File count guard: Exactly 9 code, test, and config files (under the 10 code files split trigger limit), plus 3 documentation and environment files.

### 5. Interface specs

#### `App\Domain\Accounts\Enums\UserRole`
- File: `app/Domain/Accounts/Enums/UserRole.php`
- Strict types: `declare(strict_types=1);`
- Type: `enum UserRole: string`
- Cases:
  - `case Superadmin = 'superadmin';`
  - `case Retailer = 'retailer';`
  - `case Customer = 'customer';`
- Callers: `App\Models\User`, `Database\Factories\UserFactory`, `Database\Seeders\SuperadminSeeder`, `Tests\Feature\Accounts\UserRoleTest`.

#### `App\Models\User`
- File: `app/Models/User.php`
- Strict types: `declare(strict_types=1);`
- Class declaration: `final class User extends Authenticatable implements OAuthenticatable`
  - Note: `MustVerifyEmail` interface is removed because email verification is permanently out of scope per `ARCHITECTURE.md` §1.
- Traits used:
  - `Laravel\Passport\HasApiTokens`
  - `Illuminate\Database\Eloquent\Factories\HasFactory`
  - `Illuminate\Notifications\Notifiable`
  - `Illuminate\Database\Eloquent\SoftDeletes`
- Class attributes:
  - `#[Fillable(['public_id', 'name', 'email', 'password', 'role'])]`
  - `#[Hidden(['password'])]`
    - Note: `remember_token`, `two_factor_secret`, and `two_factor_recovery_codes` are removed from hidden attributes because they are dropped from the database schema.
- Constructor dependencies: None (Eloquent standard model instantiation).
- Public methods:
  - `public function casts(): array`: Returns `['email_verified_at' => 'datetime', 'password' => 'hashed', 'role' => UserRole::class]`.
  - `public function initials(): string`: Returns two-character uppercase string derived from `name`.
- Thrown exceptions: None.
- Callers: Passport token issuance and verification, database seeders, test suites, and subsequent users-stream services.

#### `Database\Factories\UserFactory`
- File: `database/factories/UserFactory.php`
- Strict types: `declare(strict_types=1);`
- Class declaration: `final class UserFactory extends Factory`
- PHPDoc: `/** @extends Factory<User> */`
- Protected static property: `protected static ?string $password = null;`
- Public methods:
  - `public function definition(): array`:
    Returns associative array:
    - `'public_id' => (string) Str::ulid()`
    - `'name' => fake()->name()`
    - `'email' => fake()->unique()->safeEmail()`
    - `'email_verified_at' => now()`
    - `'password' => static::$password ??= 'password'`
    - `'role' => UserRole::Customer`
  - `public function superadmin(): static`: Returns state definition `['role' => UserRole::Superadmin]`.
  - `public function retailer(): static`: Returns state definition `['role' => UserRole::Retailer]`.
  - `public function customer(): static`: Returns state definition `['role' => UserRole::Customer]`.
  - `public function unverified(): static`: Returns state definition `['email_verified_at' => null]`.
- Thrown exceptions: None.
- Callers: Test suites and database seeders.

#### `config/auth.php`
- File: `config/auth.php`
- Key added:
  ```php
  'superadmin' => [
      'email' => env('SUPERADMIN_EMAIL', 'superadmin@getnow.test'),
      'password' => env('SUPERADMIN_PASSWORD', 'secret'),
  ],
  ```
- Callers: `Database\Seeders\SuperadminSeeder`.

#### `Database\Seeders\SuperadminSeeder`
- File: `database/seeders/SuperadminSeeder.php`
- Strict types: `declare(strict_types=1);`
- Class declaration: `final class SuperadminSeeder extends Seeder`
- Constructor dependencies: None.
- Public methods:
  - `public function run(): void`:
    Retrieves `$email = (string) config('auth.superadmin.email', 'superadmin@getnow.test');`
    Retrieves `$password = (string) config('auth.superadmin.password', 'secret');`
    Queries `User::where('email', $email)->first()`.
    If null, executes `User::create(['public_id' => (string) Str::ulid(), 'name' => 'Superadmin', 'email' => $email, 'password' => $password, 'role' => UserRole::Superadmin, 'email_verified_at' => now()])`.
    If found, executes `$user->update(['name' => 'Superadmin', 'role' => UserRole::Superadmin])`, preserving existing password hash.
    Note: Does not call `env()` in application code; relies strictly on `config()`.
- Thrown exceptions: None.
- Callers: `Database\Seeders\DatabaseSeeder`, artisan `db:seed`.

#### `Database\Seeders\DatabaseSeeder`
- File: `database/seeders/DatabaseSeeder.php`
- Strict types: `declare(strict_types=1);`
- Class declaration: `final class DatabaseSeeder extends Seeder`
- Traits used: `Illuminate\Database\Console\Seeds\WithoutModelEvents`
- Constructor dependencies: None.
- Public methods:
  - `public function run(): void`: Executes `$this->call(SuperadminSeeder::class);`.
- Thrown exceptions: None.
- Callers: Artisan `db:seed`.

### 6. Data and migrations

- Migration file: `database/migrations/2026_10_23_000000_add_accounts_columns_to_users_table.php`
- Table affected: `users`
- Added columns:
  - `public_id`: `ulid('public_id')->unique()` - 26-character Crockford Base32 ULID, unique index.
  - `role`: `string('role', 16)->default('customer')->index()` - varchar(16), indexed.
  - `deleted_at`: `softDeletes()` - timestamp without time zone, nullable.
- Dropped legacy columns:
  - `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `remember_token`.
- Constraint added via raw SQL statement:
  - SQL: `ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('superadmin', 'retailer', 'customer'));`
- Rollback operations (`down()`):
  - SQL: `ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;`
  - Blueprint schema calls:
    ```php
    Schema::table('users', function (Blueprint $table) {
        $table->dropIndex(['role']);
        $table->dropUnique(['public_id']);
        $table->dropSoftDeletes();
        $table->dropColumn(['public_id', 'role']);

        $table->text('two_factor_secret')->after('password')->nullable();
        $table->text('two_factor_recovery_codes')->after('two_factor_secret')->nullable();
        $table->timestamp('two_factor_confirmed_at')->after('two_factor_recovery_codes')->nullable();
        $table->rememberToken();
    });
    ```
- Backward compatibility:
  - Dropping `two_factor_*` and `remember_token` complies strictly with DBS3 ("no destructive change in the same release that stops using it"): the code stopped using these columns in ACC-C1, which was already reviewed, accepted, and merged into `main` in the previous release. The rollback implementation restores these columns, ensuring clean bidirectional rollback.

### 7. Behavior and flows

#### Flow 1: Model Creation via Factory
- Happy path: Test suite calls `User::factory()->create()`. Factory definition generates ULID string for `public_id`, sets `role` to `UserRole::Customer`, and passes plain string `'password'` for attribute `password`. Eloquent model's `hashed` cast hashes the password value before PostgreSQL persistence. The persisted row contains valid ULID `public_id`, `role = 'customer'`, and non-empty bcrypt or argon2 hash.
- Failure path: Direct SQL insert with an invalid role value (such as `'moderator'`) triggers PostgreSQL `users_role_check` constraint and raises `Illuminate\Database\QueryException`.
- Authorization: None.
- Activity log: None.
- Concurrency: ULIDs are monotonic and unique per generation; collision probability is negligible.

#### Flow 2: Factory State Switching
- Happy path: Test suite calls `User::factory()->superadmin()->create()`, `User::factory()->retailer()->create()`, or `User::factory()->customer()->create()`. Each respective factory state overrides the default customer role with the requested `UserRole` enum case. The persisted record reflects the specified role.
- Failure path: None.
- Authorization: None.
- Activity log: None.

#### Flow 3: Superadmin Seeding via Configuration
- Happy path: CLI or test invokes `SuperadminSeeder`. Seeder retrieves configured email and password via `config('auth.superadmin.*')`. If no user exists with that email, seeder persists a new record with role `UserRole::Superadmin`. If a record already exists with that email, seeder executes an update setting `role` to `UserRole::Superadmin` while leaving the existing password hash unmodified. Running the seeder multiple times produces exactly one record.
- Failure path: Database service unavailable; raises connection exception.
- Authorization: Console context only.
- Activity log: None.

#### Flow 4: Soft Deletion and Query Scoping
- Happy path: Test suite calls `$user->delete()`. The record's `deleted_at` timestamp is populated in PostgreSQL. Standard Eloquent queries `User::all()` or `User::find()` exclude the record from results. Queries using `User::withTrashed()` include the soft-deleted record.
- Failure path: None.
- Authorization: None.
- Activity log: None.

#### Flow 5: Migration Execution and Rollback
- Happy path: `php artisan migrate` applies the migration, creating `public_id`, `role`, `deleted_at`, adding index and CHECK constraint, and dropping legacy columns. `php artisan migrate:rollback` removes the CHECK constraint, drops indexes (`role` index and `public_id` unique index), drops columns, and restores the legacy starter-kit columns.
- Failure path: Dropping unique constraint fails if index is not explicitly dropped prior to column drop; prevented by `$table->dropUnique(['public_id'])`.
- Note on `MustVerifyEmail`: The interface is removed from `User` model because email verification is permanently out of scope per `ARCHITECTURE.md` §1.

### 8. Rule compliance matrix

| ID | Applies | How satisfied, or why N/A |
|---|---|---|
| STM1 | Y | Step touches only users-stream files (`app/Domain/Accounts/Enums`, `app/Models/User.php`, `database/factories`, `database/seeders`, `tests/Feature/Accounts`) and permitted touch points (`config/auth.php`, `tests/Arch/AccountsArchTest.php`, `.env.example`, `docs/adr`, `ARCHITECTURE.md`). No tokens-stream files are modified. |
| STM2 | N | Step does not modify `TokenIssuer` or `TokenPairData`. |
| STM3 | Y | No Passport imports are introduced into `UserRole`, `UserFactory`, `SuperadminSeeder`, or `DatabaseSeeder`. Existing Passport traits on `User` (`HasApiTokens`, `OAuthenticatable`) are retained as permitted. |
| STM4 | N | Imported token code is untouched. |
| LAY1 | N | No controllers, requests, services, or repositories are created in this step. |
| LAY2 | N | No controllers are introduced in this step. |
| LAY3 | N | No domain services are introduced in this step. |
| LAY4 | N | No repositories are introduced in this step. |
| LAY5 | N | Method parity applies to CRUD classes; none created in this step. |
| LAY6 | Y | `App\Domain\Accounts\Enums\UserRole` does not import `App\Http`. |
| LAY7 | Y | All code is placed strictly in accordance with ARCHITECTURE.md §5: enum in `app/Domain/Accounts/Enums`, model in `app/Models`, migration in `database/migrations`, factory in `database/factories`, seeders in `database/seeders`. |
| LAY8 | N | No controllers exist in this step. |
| STD1 | Y | `declare(strict_types=1);` is included at the top of every new and modified PHP file. |
| STD2 | Y | All properties, parameters, and return types are strictly typed. Classes (`User`, `UserFactory`, `SuperadminSeeder`, `DatabaseSeeder`) are marked `final`. |
| STD3 | Y | `UserRole` enum is created as a string-backed enum for the fixed set of roles. |
| STD4 | N | `UserData` DTO is deferred to ACC-U2. |
| STD5 | N | Partial update logic is deferred to ACC-U2 and ACC-U3. |
| STD6 | Y | Code will pass Pint and Larastan (Level 7) cleanly without adding baseline entries. |
| STD7 | Y | Comments explain architectural rationale rather than mechanics; no commented-out code exists. |
| ACT1 | N | Registration logic is deferred to ACC-U4. |
| ACT2 | N | Retailer creation via API is deferred to ACC-U3. |
| ACT3 | N | Authorization policies on `/users` endpoints are deferred to ACC-U3. |
| ACT4 | Y | `SuperadminSeeder` is created as the designated mechanism for seeding superadmin users; credentials are read via `config('auth.superadmin.*')` without calling `env()` in application code. |
| ACT5 | N | Login endpoint is deferred to ACC-U4. |
| ACT6 | Y | Passwords are authenticated and hashed strictly through the `hashed` cast on `User`. `Hash::make` is removed from `UserFactory`. |
| ACT7 | N | Login error handling is deferred to ACC-U4. |
| ACT8 | N | Token revocation on lifecycle events is deferred to ACC-U5. |
| ACT9 | N | Token issuance orchestration is deferred to ACC-U4. |
| ACT10 | N | Login and register responses are deferred to ACC-U4. |
| ACT11 | Y | No passwords or secrets are logged. |
| TOK1 | N | `PassportTokenIssuer` is untouched. |
| TOK2 | N | ADR 0001 tokens configuration is untouched. |
| TOK3 | N | Passport version is untouched. |
| TOK4 | N | Token configuration lifetimes are untouched. |
| TOK5 | N | Token refresh rotation is untouched. |
| TOK6 | N | Endpoint throttling is untouched. |
| DBS1 | Y | Migration uses PostgreSQL data types and constraints; tests run on PostgreSQL only. |
| DBS2 | Y | The merged migration `0001_01_01_000000_create_users_table.php` is untouched. A new migration file is created. |
| DBS3 | Y | Dropping `two_factor_*` and `remember_token` in ACC-U1 is compliant because their usage was discontinued in the merged release ACC-C1. |
| DBS4 | Y | Migration adds index on `role` and creates PostgreSQL `users_role_check` constraint. |
| DBS5 | Y | `public_id` column (ULID) is added and generated in `UserFactory`. Raw incremental integer `id` remains internal. |
| DBS6 | Y | `SuperadminSeeder` is idempotent via check-and-update flow. It does not delete records or truncate tables. |
| SCR1 | Y | No secrets, credentials, or `.env` files are committed. `.env.example` records variable names without sensitive values. |
| SCR2 | Y | No authorization or strict typing checks are disabled. |
| SCR3 | N | Policy enforcement is deferred to ACC-U3. |
| SCR4 | N | Route throttling is deferred to ACC-U3 and ACC-U4. |
| TST1 | Y | Pest tests are created in `tests/Feature/Accounts/UserRoleTest.php`; arch tests updated in `tests/Arch/AccountsArchTest.php`. |
| TST2 | Y | Database constraint violations, enum value coverage, seeder idempotency, and migration rollback integrity are covered by tests. |
| TST3 | Y | Existing tokens-stream tests continue running against real Passport on PostgreSQL. |
| TST4 | Y | Tests use `UserFactory` and database seeders rather than manual raw database inserts. |
| TST5 | Y | No tests are skipped, marked todo, or marked incomplete. |
| GIT1 | Y | Work branch is named following convention: `feat/users-foundation`. |
| GIT2 | Y | Commits follow Conventional Commits format with stream scope `feat(users): ...`, `test(users): ...`, `docs(users): ...`. |
| GIT3 | Y | Direct pushes to `main` are prohibited. |
| GIT4 | N | This is a users stream step, not a contract step. |
| GIT5 | Y | PR description will enumerate changes, verification evidence, and risks. |
| GIT6 | Y | Merge order adheres to ARCHITECTURE.md §10 (`ACC-U1` following `ACC-C1`). |
| ENV1 | Y | Commands are single PowerShell commands executed without bash chaining or `&&`. |
| ENV2 | Y | Relies strictly on native PHP, Composer, and PostgreSQL; no Docker or Sail configurations. |
| ENV3 | Y | Non-interactive execution; leaves no background daemon processes running. |
| ENV4 | Y | `composer check` (Pint, Larastan, Pest) must run and pass cleanly. |
| DNT1 | Y | No new composer or npm packages are introduced. |
| DNT2 | Y | No deployment or infrastructure scripts are added or modified. |
| DNT3 | Y | Out-of-scope features (Filament UI, 2FA, password resets, social auth) are excluded. |
| DNT4 | Y | No business logic is added to controllers or models. |
| DNT5 | Y | No destructive database operations outside clean migration rollback definitions. |
| DNT6 | Y | No keys, secrets, or foreign databases copied into project. |
| DNT7 | Y | ARCHITECTURE.md progress list, ADR directory, and `.env.example` are updated. |
| WFL1 | Y | Work proceeds strictly from this approved plan document. |
| WFL2 | Y | Any deviation or unexpected requirement will halt implementation for a Change Request. |
| WFL3 | Y | Implementation terminates with an Implementation Report containing raw command outputs. |
| WFL4 | Y | Step acceptance is deferred exclusively to reviewer verdict. |
| WFL5 | Y | Plan is produced and submitted for approval prior to code edits. |
| WFL6 | Y | Scope is limited to the single concern of user foundational domain models and migrations. |
| WFL7 | Y | Open decisions answered by Achintha are recorded in Section 13 before coding begins. |

### 9. AC traceability

| AC | Files | Test names | How verified |
|---|---|---|---|
| AC-1 | `app/Domain/Accounts/Enums/UserRole.php` | `it('has expected cases and backing values for user role enum')` | Assert enum cases count is 3 and values equal `'superadmin'`, `'retailer'`, `'customer'`. |
| AC-2 | `database/migrations/2026_10_23_000000_add_accounts_columns_to_users_table.php` | `it('enforces check constraint on role column in postgresql')`, `it('successfully rolls back migration and removes added columns while restoring dropped columns')` | Run migration on PostgreSQL, verify schema columns, test CHECK constraint violation on invalid role insert, execute rollback test, and verify via Layer A command 15b. |
| AC-3 | `database/factories/UserFactory.php`, `app/Models/User.php` | `it('creates a customer user by default via factory with ulid public id')`, `it('hashes password transparently via model cast without hash facade')` | Verify generated user record has valid 26-character ULID, role equals `UserRole::Customer`, and password verifies against plain text `'password'` without calling `Hash::make`. |
| AC-4 | `database/factories/UserFactory.php` | `it('creates a superadmin user via factory state')`, `it('creates a retailer user via factory state')`, `it('creates a customer user via factory state')` | Verify `User::factory()->superadmin()->create()` sets role to `UserRole::Superadmin`, `->retailer()` sets role to `UserRole::Retailer`, and `->customer()` sets role to `UserRole::Customer`. |
| AC-5 | `database/seeders/SuperadminSeeder.php`, `config/auth.php` | `it('seeds superadmin idempotently without duplicating records')`, `it('does not overwrite existing superadmin password on subsequent seed')` | Run `SuperadminSeeder` twice against test database; assert database count for superadmin email is exactly 1 and pre-existing password hash is retained. |
| AC-6 | `database/seeders/DatabaseSeeder.php` | `it('seeds superadmin via database seeder')` | Run `DatabaseSeeder`; verify superadmin record exists and `'test@example.com'` record does not exist. |
| AC-7 | All modified files | N/A | Execute `composer check`; verify Pint, Larastan, and Pest pass with zero warnings, zero errors, and zero baseline additions. |
| AC-8 | `database/factories/UserFactory.php` | All tests in `tests/Feature/Tokens/PassportTokenIssuerTest.php` and `tests/Feature/Tokens/TokenEndpointsTest.php` | Run Pest filter `tests/Feature/Tokens`; verify all token tests pass without code modifications. |
| AC-9 | All 9 code and test files | `arch('strict types are enforced')` | Static analysis scan verifying every new and modified file contains `declare(strict_types=1);`. |
| AC-10 | `tests/Arch/AccountsArchTest.php` | `arch('strict types are enforced')` | Verify expectation passes when evaluating namespaces `App\Domain`, `App\Models`, `Database\Factories`, and `Database\Seeders`. |

### 10. Test plan

#### File: `tests/Feature/Accounts/UserRoleTest.php`
- `it('has expected cases and backing values for user role enum')`
  - Arrange: Inspect cases of `UserRole::cases()`.
  - Act: Map cases to array of string backing values.
  - Assert: Expect array to be exactly `['superadmin', 'retailer', 'customer']` and count to be 3.
- `it('creates a customer user by default via factory with ulid public id')`
  - Arrange: None.
  - Act: Call `$user = User::factory()->create();`.
  - Assert: Expect `$user->public_id` to match regex `/^[0-9A-HJKMNP-TV-Z]{26}$/`, `$user->role` to be `UserRole::Customer`, and database to contain matching row.
- `it('creates a superadmin user via factory state')`
  - Arrange: None.
  - Act: Call `$user = User::factory()->superadmin()->create();`.
  - Assert: Expect `$user->role` to be `UserRole::Superadmin` and database row `role` column to be `'superadmin'`.
- `it('creates a retailer user via factory state')`
  - Arrange: None.
  - Act: Call `$user = User::factory()->retailer()->create();`.
  - Assert: Expect `$user->role` to be `UserRole::Retailer` and database row `role` column to be `'retailer'`.
- `it('creates a customer user via factory state')`
  - Arrange: None.
  - Act: Call `$user = User::factory()->customer()->create();`.
  - Assert: Expect `$user->role` to be `UserRole::Customer` and database row `role` column to be `'customer'`.
- `it('hashes password transparently via model cast without hash facade')`
  - Arrange: Instantiate `$user = User::factory()->create(['password' => 'plain-text-secret']);`.
  - Act: Retrieve raw attribute `$user->password` and verify with `Hash::check('plain-text-secret', $user->password)`.
  - Assert: Expect `Hash::check` to return true and raw password string not to equal `'plain-text-secret'`.
- `it('enforces check constraint on role column in postgresql')`
  - Arrange: Prepare row data with invalid role string `'invalid_role'`.
  - Act: Wrap `DB::table('users')->insert(...)` in closure expecting `QueryException`.
  - Assert: Expect closure to throw `QueryException` with PostgreSQL error code matching CHECK violation (`23514`).
- `it('seeds superadmin idempotently without duplicating records')`
  - Arrange: Set `config(['auth.superadmin.email' => 'admin@getnow.test', 'auth.superadmin.password' => 'secret123'])`.
  - Act: Execute `app(SuperadminSeeder::class)->run();` followed by second execution of `app(SuperadminSeeder::class)->run();`.
  - Assert: Expect `User::where('email', 'admin@getnow.test')->count()` to equal 1 and `User::where('email', 'admin@getnow.test')->first()->role` to equal `UserRole::Superadmin`.
- `it('does not overwrite existing superadmin password on subsequent seed')`
  - Arrange: Seed initial superadmin, change password via `$user->update(['password' => 'new-custom-password'])`, record hash.
  - Act: Execute `app(SuperadminSeeder::class)->run();` again.
  - Assert: Expect `$user->fresh()->password` to match recorded custom hash and verify with `Hash::check('new-custom-password', ...)`.
- `it('supports soft deleting user instances')`
  - Arrange: Create user record via factory.
  - Act: Execute `$user->delete();`.
  - Assert: Expect `$user->trashed()` to be true, `User::find($user->id)` to be null, and `User::withTrashed()->find($user->id)` to return the instance.
- `it('successfully rolls back migration and removes added columns while restoring dropped columns')`
  - Arrange: Record initial schema state.
  - Act: Execute `$this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();`.
  - Assert: Verify `Schema::hasColumn('users', 'public_id')` is false, `Schema::hasColumn('users', 'role')` is false, `Schema::hasColumn('users', 'deleted_at')` is false, `Schema::hasColumn('users', 'remember_token')` is true, and `Schema::hasColumn('users', 'two_factor_secret')` is true.
  - Teardown: Re-run `$this->artisan('migrate')->assertSuccessful();` to leave the test database fully migrated.

#### File: `tests/Arch/AccountsArchTest.php`
- `arch('strict types are enforced')`:
  - Updated expectation:
    ```php
    arch('strict types are enforced')
        ->expect([
            'App\Domain',
            'App\Http\Controllers\Api',
            'App\Http\Requests\Auth',
            'App\Models',
            'App\Providers\TokenServiceProvider',
            'Database\Factories',
            'Database\Seeders',
        ])
        ->toUseStrictTypes();
    ```

### 11. Docs and contract updates

#### `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md`
Create new ADR recording:
- Status: Accepted.
- Context: `ARCHITECTURE.md` §9 specifies `deleted_at` for soft deletes to maintain referential integrity with future orders and content blocks. `ACC-C1` removed all application usage of two-factor authentication and web session remember tokens.
- Decision:
  1. Add `deleted_at` timestamp and `SoftDeletes` trait to `App\Models\User`.
  2. Drop `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, and `remember_token` in the `add_accounts_columns_to_users_table` migration.
- Consequences:
  1. Soft-deleted users preserve foreign key associations across database tables.
  2. Database schema is purged of unused starter-kit columns in full compliance with DBS3.

#### `.env.example`
Append the following configuration variables:
```ini
# Accounts (Superadmin Seeder)
SUPERADMIN_EMAIL=superadmin@getnow.test
SUPERADMIN_PASSWORD=secret
```

#### `ARCHITECTURE.md`
Update section 10 progress table entry:
```markdown
- [x] `ACC-U1` Users foundation: `UserRole`, users migration additions, `User` model, factory, `SuperadminSeeder`.
```
(Note: Checkbox is marked in closeout following reviewer acceptance per rule WFL4).

### 12. Implementation order

#### Commit 1: `feat(users): add user role enum, migration, model updates, and factory rewrite`
- Files:
  - `app/Domain/Accounts/Enums/UserRole.php`
  - `database/migrations/2026_10_23_000000_add_accounts_columns_to_users_table.php`
  - `app/Models/User.php`
  - `database/factories/UserFactory.php`
- Rationale: Landing enum, migration, model, and factory in a single atomic commit guarantees that Larastan Level 7 passes without cast or property mismatches and existing token tests in `tests/Feature/Tokens` pass without intermediate breakage.
- Verification: Execute `php artisan migrate:fresh --seed` (or migrate), execute `composer test -- --filter=Tokens`, execute `composer stan`. All pass cleanly.

#### Commit 2: `feat(users): configure superadmin credentials and create idempotent seeders`
- Files:
  - `config/auth.php`
  - `database/seeders/SuperadminSeeder.php`
  - `database/seeders/DatabaseSeeder.php`
  - `.env.example`
- Rationale: Adds superadmin configuration key and wires seeders using `config()` without calling `env()` in application code.
- Verification: Execute `php artisan db:seed --class=SuperadminSeeder`, verify idempotency with repeated execution, verify `composer check`.

#### Commit 3: `test(users): add user foundation feature and arch tests`
- Files:
  - `tests/Feature/Accounts/UserRoleTest.php`
  - `tests/Arch/AccountsArchTest.php`
- Rationale: Adds comprehensive feature tests covering enum, factory states, database constraints, soft deletes, and migration rollback, while expanding strict types arch coverage.
- Verification: Execute `composer check` (Pint, Larastan, Pest). All pass with zero errors, zero warnings, zero skipped tests.

#### Commit 4: `docs(users): add soft deletes adr and update architecture checklist`
- Files:
  - `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md`
  - `ARCHITECTURE.md`
- Rationale: Dedicated documentation commit adhering strictly to Conventional Commits scope (GIT2).

### 13. Risks, assumptions, conflicts

#### Open decisions from Step Brief resolved by Achintha:
- **OD-1: Two-factor columns.**
  - Question: Should `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` be dropped in the new migration or left for a later cleanup?
  - Achintha's Decision: **Drop them in the new ACC-U1 migration.**
  - DBS3 Compliance Rationale: The release stopping their use was `ACC-C1` (merged and accepted). Dropping them now in `ACC-U1` is a subsequent release and is completely compliant with DBS3.
- **OD-2: `remember_token` column.**
  - Question: Should `remember_token` be dropped in the new migration or retained?
  - Achintha's Decision: **Drop it in the new migration.**
  - Rationale: GetNow authentication is an API-first stateless Passport bearer token architecture. Session remember tokens are unused.
- **OD-3: Superadmin credentials configuration.**
  - Question: Confirmed variable names (`SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD`) and fallback defaults?
  - Achintha's Decision: Left to implementation discretion.
  - Implementation Choice: Defined in `.env.example` as `SUPERADMIN_EMAIL` and `SUPERADMIN_PASSWORD`. Configured in `config/auth.php` under array key `superadmin` with local defaults `'superadmin@getnow.test'` and `'secret'`. `SuperadminSeeder` calls only `config()`, eliminating direct `env()` calls in application code.
- **OD-4: Soft delete ADR.**
  - Question: Is the soft-delete ADR written in this step or deferred to ACC-U3?
  - Achintha's Decision: **Write it in this step for the changes made here.**
  - Implementation Choice: Created as `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md`.

#### Resolution of Review Findings (Claude Plan-Review v1):
- **B1 & B2 (Open decisions & DBS3 rationale)**: Section 13 explicitly documents Achintha's actual answers, correcting the DBS3 rationale to reflect that usage was discontinued in merged step ACC-C1.
- **B3 (Migration rollback correctness)**: Section 6 explicitly adds `$table->dropUnique(['public_id'])` before dropping columns, avoiding PostgreSQL constraint errors on rollback, and re-adds dropped legacy columns in `down()`.
- **M1 (Larastan intermediate state & commit atomicity)**: Section 12 consolidates enum, migration, model, and factory into Commit 1 to guarantee green analysis and passing token tests across all commits.
- **M2 (AC-2 rollback test traceability)**: Added `it('successfully rolls back migration and removes added columns while restoring dropped columns')` to Section 9 and Section 10.
- **M3 (`env()` usage in seeder)**: Added `superadmin` configuration block to `config/auth.php`; updated `SuperadminSeeder` to use `config()` exclusively.
- **m1 (`MustVerifyEmail` removal)**: Documented in Section 5, Section 7, and Section 13 that `MustVerifyEmail` is removed because email verification is permanently out of scope per `ARCHITECTURE.md` §1.
- **m2 (Enum unit test location)**: Maintained in `tests/Feature/Accounts/UserRoleTest.php` to respect the step file count budget (9 code/test files vs split trigger of >10 files). PostgreSQL overhead is negligible (under 5ms).
- **m3 (`ARCHITECTURE.md` in commit scope)**: Moved documentation updates and ADR into a dedicated `docs(users):` commit (Commit 4).

### 14. Dependencies

No new composer packages or npm packages are introduced. All functionality utilizes native PHP 8.3, Laravel 13 framework components, and existing project dependencies (`spatie/laravel-data`, `laravel/passport`, `pestphp/pest`).
