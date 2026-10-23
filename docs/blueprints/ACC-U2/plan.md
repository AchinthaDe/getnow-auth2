# [ACC-U2 | PLAN v3 | Gemini]

Change log (v3):
- M1 -> §1, §3, §5 (Updated provider reference to `AccountsServiceProvider` in §1; removed `AppServiceProvider` from §3; added current code excerpts for `TokenServiceProvider`, `bootstrap/providers.php`, and `tests/Pest.php` to §3; stated in §5 that `bootstrap/providers.php` already has `declare(strict_types=1);` and provided the complete resulting file text)
- M2 -> §11, §13 (Pasted full ACC-U1 to ACC-U5 lines from `ARCHITECTURE.md` §10; provided exact before and after text rewording line 247 to remove `UserResponseData`, add `UserService` and protections, add `UserResponseData` to ACC-U3 line, and tick ACC-U2; added scope clarification note in §13)
- m1 -> §8 (Updated STD2 to state that `AccountsServiceProvider` is `final`, matching §5 and `TokenServiceProvider`)
- m2 -> §8 (Generalized ACT8 rule text to refer to "token revocation" without naming unverified methods)
- m3 -> §11 (Quoted the complete `ARCHITECTURE.md` §2 streams table; dropped the streams table row edit because stream status is tracked at stream level)
- m4 -> §10 (Added repository test in §10 verifying that a literal backslash `a\b` matches only itself)
- m5 -> §5 (Removed stray semicolon in `UserRepository::update` return description)

---

### 1. Understanding
Implement the Users stream domain layer: the single combined data transfer object `UserData` for creation and updates, the listing query filter object `UserFilterData`, the domain repository contract `UserRepositoryInterface`, the Eloquent implementation `UserRepository`, the domain business logic service `UserService`, and the domain exception `ProtectedUserException` enforcing superadmin protection rules. Bind `UserRepositoryInterface` to `UserRepository` in `AccountsServiceProvider`.
Out of scope: `UserResponseData`, `UserPolicy`, `UserController`, FormRequests, HTTP routes, route model binding, soft-delete architectural decision records, `AuthService`, `AuthController`, login, registration, token revocation on user deletion or role/password modification, user restoration, force deletion, database migrations, model modifications, and factory modifications.

### 2. Sources read

| File or doc section | What in it constrains this step |
|---|---|
| `AGENTS.md` | Mandates `declare(strict_types=1);` (STD1), typed parameters/returns and final classes (STD2), enums for fixed sets (STD3), single `UserData` DTO (STD4), partial updates preserving absent fields (STD5), no `Hash::make` in application code (ACT6), superadmin immutability (ACT4), PostgreSQL only (DBS1), ULID external identifiers (DBS5), stream boundaries (STM1, STM3), zero skipped tests (TST5), and `composer check` quality gates (ENV4). |
| `ARCHITECTURE.md` §§ 1, 2, 3, 4, 5, 8, 9, 10 | Establishes domain layer structure under `app/Domain/Accounts/`, defines `UserRole` values (`superadmin`, `retailer`, `customer`), identifies repository contract binding in `AccountsServiceProvider`, specifies method parity (`index`, `store`, `show`, `update`, `destroy`), and documents password hashing via `hashed` cast on `User`. |
| `docs/blueprints/RULE_REGISTER.md` | Governs all 75 rule IDs evaluated in the Section 8 compliance matrix and sets Layer A commands. |
| `docs/blueprints/ACC-U2/brief.md` | Specifies scope, acceptance criteria AC-1 through AC-10, size guard (10 files expected, split trigger at >10 files), decisions made, and step-specific Layer A checks 15a through 15h. |
| `app/Models/User.php` | Live model definition: `final class User`, uses `SoftDeletes`, casts `'password' => 'hashed'` and `'role' => UserRole::class`, fillable attributes `['public_id', 'name', 'email', 'password', 'role']`. |
| `app/Domain/Accounts/Enums/UserRole.php` | Live enum definition: backed by string, cases `Superadmin = 'superadmin'`, `Retailer = 'retailer'`, `Customer = 'customer'`. |
| `database/factories/UserFactory.php` | Live factory: provides states `customer()`, `retailer()`, `superadmin()`, default role `Customer`, plain string password `'password'`, and ULID `public_id`. |
| `database/seeders/SuperadminSeeder.php` | Live seeder: reads credentials from `config('auth.superadmin')` and sets role `UserRole::Superadmin`. |
| `database/migrations/0001_01_01_000000_create_users_table.php` | Baseline migration: defines table `users` with columns `id`, `name`, `email` (unique index), `password`, timestamps. |
| `database/migrations/2026_10_23_000000_add_accounts_columns_to_users_table.php` | Accounts migration: adds `public_id` (ulid, unique, no database default), `role` (varchar(16), indexed, default 'customer', check constraint), and `deleted_at` (soft deletes). |
| `app/Providers/TokenServiceProvider.php` | Live service provider: reference pattern for provider structure with `declare(strict_types=1);`, `final` class modifier, and container bindings in `register()`. |
| `bootstrap/providers.php` | Application provider registry array: location where `AccountsServiceProvider` is registered. |
| `tests/Pest.php` | Test configuration: configures `pest()->use(RefreshDatabase::class)->in('Feature');` ensuring isolated database transactions across PostgreSQL feature tests. |
| `tests/Arch/AccountsArchTest.php` | Arch tests: validates strict types, interface enforcement in `App\Domain\Accounts\Contracts`, Spatie Data extension in `App\Domain\Accounts\Data`, and forbids `App\Http` or `Laravel\Passport` imports in domain code. |
| `tests/Feature/Accounts/UserRoleTest.php` | Accounts feature test patterns: verifies role enum cases, factory defaults, cast hashing, PostgreSQL check constraints, and soft deletion. |
| `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md` | Documents user soft-deletion rationale and column cleanup accepted in `ACC-U1`. |
| `composer.json` | Project dependencies: confirms `"spatie/laravel-data": "^4.23"`, `"php": "^8.3"`, `"laravel/framework": "^13.17"`. |
| `vendor/spatie/laravel-data/src/Optional.php` | Confirms `Spatie\LaravelData\Optional` class structure and instantiation. |

### 3. Verified current state

Existing classes, methods, and enums verified by direct inspection:

- `App\Models\User` (`app/Models/User.php`):
  ```php
  namespace App\Models;
  #[Fillable(['public_id', 'name', 'email', 'password', 'role'])]
  #[Hidden(['password'])]
  final class User extends Authenticatable implements OAuthenticatable
  {
      use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
      protected function casts(): array; // ['email_verified_at' => 'datetime', 'password' => 'hashed', 'role' => UserRole::class]
      public function initials(): string;
  }
  ```
- `App\Domain\Accounts\Enums\UserRole` (`app/Domain/Accounts/Enums/UserRole.php`):
  ```php
  namespace App\Domain\Accounts\Enums;
  enum UserRole: string
  {
      case Superadmin = 'superadmin';
      case Retailer = 'retailer';
      case Customer = 'customer';
  }
  ```
- `Database\Factories\UserFactory` (`database/factories/UserFactory.php`):
  ```php
  namespace Database\Factories;
  final class UserFactory extends Factory
  {
      public function definition(): array;
      public function superadmin(): static;
      public function retailer(): static;
      public function customer(): static;
      public function unverified(): static;
  }
  ```
- `App\Providers\TokenServiceProvider` (`app/Providers/TokenServiceProvider.php`):
  ```php
  namespace App\Providers;

  final class TokenServiceProvider extends ServiceProvider
  {
      public function register(): void
      {
          $this->app->bind(AccessTokenRepositoryInterface::class, BridgeAccessTokenRepository::class);
          $this->app->bind(RefreshTokenRepositoryInterface::class, BridgeRefreshTokenRepository::class);
          $this->app->bind(TokenIssuer::class, PassportTokenIssuer::class);
      }
  }
  ```
- `bootstrap/providers.php` (`bootstrap/providers.php`):
  ```php
  <?php

  declare(strict_types=1);

  use App\Providers\AppServiceProvider;
  use App\Providers\TokenServiceProvider;

  return [
      AppServiceProvider::class,
      TokenServiceProvider::class,
  ];
  ```
- `tests/Pest.php` (`tests/Pest.php`):
  ```php
  pest()->extend(TestCase::class)
      ->in('Feature', 'Unit');

  pest()->use(RefreshDatabase::class)
      ->in('Feature');
  ```
- `Database\Migrations\2026_10_23_000000_add_accounts_columns_to_users_table`:
  `public_id` column is added as `$table->ulid('public_id')->unique();` without a database default.
  `deleted_at` column is added via `$table->softDeletes();`.
  `role` column has index and `CHECK (role IN ('superadmin', 'retailer', 'customer'))`.

### 4. File manifest

| Path | New or Modify | Layer | One-line responsibility | Rule IDs it must satisfy |
|---|---|---|---|---|
| `app/Domain/Accounts/Data/UserData.php` | New | Domain (Data) | Single input transfer object for storing and updating user records with optional fields. | STD1, STD2, STD3, STD4, LAY6, LAY7, STM3 |
| `app/Domain/Accounts/Data/UserFilterData.php` | New | Domain (Data) | Query criteria transfer object for filtering and paginating user listings. | STD1, STD2, STD3, LAY6, LAY7, STM3 |
| `app/Domain/Accounts/Exceptions/ProtectedUserException.php` | New | Domain (Exceptions) | Domain exception signaling unauthorized mutations against protected superadmin accounts. | STD1, STD2, ACT4, LAY3, LAY7, STM3 |
| `app/Domain/Accounts/Contracts/UserRepositoryInterface.php` | New | Domain (Contracts) | Contract defining persistence operations for user entities with LAY5 method parity. | STD1, STD2, LAY3, LAY4, LAY5, LAY7, STM3 |
| `app/Domain/Accounts/Repositories/UserRepository.php` | New | Domain (Repositories) | Eloquent persistence implementation executing database queries and mutations with LAY5 method parity. | STD1, STD2, LAY4, LAY5, DBS1, DBS4, DBS5, ACT6, STD5 |
| `app/Domain/Accounts/Services/UserService.php` | New | Domain (Services) | Domain business logic orchestrator enforcing superadmin rules, normalization, and bounds with LAY5 method parity. | STD1, STD2, LAY3, LAY5, ACT4, ACT6, DBS5, STM3 |
| `app/Providers/AccountsServiceProvider.php` | New | Bootstrap / Providers | Service provider registering container binding linking `UserRepositoryInterface` to `UserRepository`. | STD1, STD2, LAY7, STM1 |
| `bootstrap/providers.php` | Modify | Bootstrap | Registers `AccountsServiceProvider` in the application provider array. | STD1, LAY7, STM1 |
| `tests/Feature/Accounts/UserRepositoryTest.php` | New | Tests (Feature) | Feature tests verifying Eloquent repository queries, wildcards, soft deletes, and partial updates on PostgreSQL. | STD1, TST1, TST2, TST4, DBS1, DBS4, DBS5, LAY5 |
| `tests/Feature/Accounts/UserServiceTest.php` | New | Tests (Feature) | Feature tests verifying domain service business logic, email normalization, bounds, and superadmin protections. | STD1, TST1, TST2, TST4, ACT4, ACT6, DBS5, LAY5 |

Total file count: 10 files (9 new, 1 modified).
Split trigger evaluation: Manifest does not exceed the 10-file budget and introduces no migrations, controllers, or non-domain classes.

### 5. Interface specs

#### `App\Domain\Accounts\Data\UserData`
- Namespace: `App\Domain\Accounts\Data`
- Class modifiers: `final class UserData extends \Spatie\LaravelData\Data`
- Constructor:
  ```php
  public function __construct(
      public string|\Spatie\LaravelData\Optional $name = new \Spatie\LaravelData\Optional(),
      public string|\Spatie\LaravelData\Optional $email = new \Spatie\LaravelData\Optional(),
      public string|\Spatie\LaravelData\Optional $password = new \Spatie\LaravelData\Optional(),
      public \App\Domain\Accounts\Enums\UserRole|\Spatie\LaravelData\Optional $role = new \Spatie\LaravelData\Optional(),
  )
  ```
- Validation rules / Schema attributes: None in this step per brief Decision 2 / OD-1 (input validation resides in FormRequests in ACC-U3).
- Callers: `UserService::store`, `UserService::update`, `UserRepository::store`, `UserRepository::update`, future `StoreUserRequest`, future `UpdateUserRequest`.

#### `App\Domain\Accounts\Data\UserFilterData`
- Namespace: `App\Domain\Accounts\Data`
- Class modifiers: `final class UserFilterData extends \Spatie\LaravelData\Data`
- Constructor:
  ```php
  public function __construct(
      public ?string $search = null,
      public ?\App\Domain\Accounts\Enums\UserRole $role = null,
      public bool $includeTrashed = false,
      public int $perPage = 15,
  )
  ```
- Callers: `UserService::index`, `UserRepository::index`, future `IndexUserRequest`.

#### `App\Domain\Accounts\Exceptions\ProtectedUserException`
- Namespace: `App\Domain\Accounts\Exceptions`
- Class modifiers: `final class ProtectedUserException extends \RuntimeException`
- Public named constructors:
  ```php
  public static function cannotCreateSuperadmin(): self;
  // returns new self('Cannot create a user with the superadmin role.')

  public static function cannotPromoteToSuperadmin(): self;
  // returns new self('Cannot promote a user to the superadmin role.')

  public static function cannotDeleteSuperadmin(): self;
  // returns new self('Cannot delete a user with the superadmin role.')

  public static function cannotChangeSuperadminRole(): self;
  // returns new self('Cannot change the role of a user with the superadmin role.')
  ```
- Thrown by: `UserService::store`, `UserService::update`, `UserService::destroy`.
- Callers: `UserService`.

#### `App\Domain\Accounts\Contracts\UserRepositoryInterface`
- Namespace: `App\Domain\Accounts\Contracts`
- Modifiers: `interface UserRepositoryInterface`
- Public method signatures:
  ```php
  /**
   * @return \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\User>
   */
  public function index(\App\Domain\Accounts\Data\UserFilterData $filter): \Illuminate\Pagination\LengthAwarePaginator;

  public function show(string $publicId): ?\App\Models\User;

  public function store(\App\Domain\Accounts\Data\UserData $data): \App\Models\User;

  public function update(\App\Models\User $user, \App\Domain\Accounts\Data\UserData $data): \App\Models\User;

  public function destroy(\App\Models\User $user): void;
  ```
- Thrown exceptions: None (Repository contains no HTTP exceptions or business logic exceptions).
- Implementations: `App\Domain\Accounts\Repositories\UserRepository`.

#### `App\Domain\Accounts\Repositories\UserRepository`
- Namespace: `App\Domain\Accounts\Repositories`
- Class modifiers: `final class UserRepository implements \App\Domain\Accounts\Contracts\UserRepositoryInterface`
- Public method signatures:
  - `/** @return \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\User> */`
    `public function index(UserFilterData $filter): LengthAwarePaginator`:
    Builds query using `User::query()`.
    Applies `$query->withTrashed()` when `$filter->includeTrashed === true`.
    Applies `$query->where('role', $filter->role->value)` when `$filter->role !== null`.
    Applies search filter when `$filter->search !== null`: does not trim string (service is sole owner of trimming), escapes characters `\`, `%`, and `_` with backslash escaping via `addcslashes($filter->search, '\\%_')`, wraps with `%...%`, and executes parameter-bound ILIKE:
    `$query->where(function (Builder $builder) use ($pattern): void { $builder->where('name', 'ILIKE', $pattern)->orWhere('email', 'ILIKE', $pattern); });`.
    Applies deterministic ordering: `$query->orderBy('created_at', 'desc')->orderBy('id', 'desc');`.
    Executes `$query->paginate(perPage: $filter->perPage);`.
  - `public function show(string $publicId): ?User`:
    Executes `User::query()->where('public_id', $publicId)->first();`.
    Respects default `SoftDeletes` scope (soft-deleted users return `null`).
  - `public function store(UserData $data): User`:
    Generates ULID via `(string) \Illuminate\Support\Str::ulid()`.
    Builds attribute array explicitly:
    `$attributes = ['public_id' => (string) Str::ulid()];`
    `if (! $data->name instanceof Optional) { $attributes['name'] = $data->name; }`
    `if (! $data->email instanceof Optional) { $attributes['email'] = $data->email; }`
    `if (! $data->password instanceof Optional) { $attributes['password'] = $data->password; }`
    `if (! $data->role instanceof Optional) { $attributes['role'] = $data->role; }`
    Calls `User::create($attributes);`.
    Plain text password in `$attributes['password']` is hashed by `User` model `'password' => 'hashed'` cast without `Hash::make`.
  - `public function update(User $user, UserData $data): User`:
    Builds update payload explicitly:
    `$attributes = [];`
    `if (! $data->name instanceof Optional) { $attributes['name'] = $data->name; }`
    `if (! $data->email instanceof Optional) { $attributes['email'] = $data->email; }`
    `if (! $data->password instanceof Optional) { $attributes['password'] = $data->password; }`
    `if (! $data->role instanceof Optional) { $attributes['role'] = $data->role; }`
    Executes `$user->update($attributes);` only if `$attributes !== []`.
    Never writes `null` over unspecified fields.
    Returns `$user`.
  - `public function destroy(User $user): void`:
    Executes `$user->delete();`. Sets `deleted_at`.

#### `App\Domain\Accounts\Services\UserService`
- Namespace: `App\Domain\Accounts\Services`
- Class modifiers: `final class UserService`
- Constructor:
  ```php
  public function __construct(
      private readonly \App\Domain\Accounts\Contracts\UserRepositoryInterface $users,
  ) {}
  ```
- Public method signatures:
  - `/** @return \Illuminate\Pagination\LengthAwarePaginator<int, \App\Models\User> */`
    `public function index(UserFilterData $filter): LengthAwarePaginator`:
    Clamps `$filter->perPage` to range 1 through 100: `min(max($filter->perPage, 1), 100)`.
    Normalizes `$filter->search`: trims string; if resulting string is empty, treats as `null`.
    Instantiates normalized `UserFilterData`:
    `new UserFilterData(search: $search, role: $filter->role, includeTrashed: $filter->includeTrashed, perPage: $perPage)`.
    Calls `$this->users->index($normalizedFilter);`.
  - `public function show(string $publicId): User`:
    Calls `$this->users->show($publicId);`.
    If returned value is `null`, throws `(new \Illuminate\Database\Eloquent\ModelNotFoundException())->setModel(User::class, [$publicId]);`.
    Returns found `User`.
  - `public function store(UserData $data): User`:
    Validates presence of required fields (`name`, `email`, `password`, `role`). If any property is an instance of `\Spatie\LaravelData\Optional`, collects field names into array and throws `\InvalidArgumentException(sprintf('Missing required user fields: %s.', implode(', ', $missing)));`.
    Validates superadmin protection: if `$data->role === UserRole::Superadmin`, throws `ProtectedUserException::cannotCreateSuperadmin();`.
    Normalizes email: trims and converts to lowercase via `strtolower(trim((string) $data->email))`.
    Builds normalized `UserData(name: $data->name, email: $normalizedEmail, password: $data->password, role: $data->role)`.
    Calls `$this->users->store($normalizedData);`.
  - `public function update(User $user, UserData $data): User`:
    Validates superadmin protection on target model:
    Case A: Target model role is `UserRole::Superadmin`:
    If `! $data->role instanceof Optional`, throws `ProtectedUserException::cannotChangeSuperadminRole();`.
    Case B: Target model role is NOT `UserRole::Superadmin`:
    If `! $data->role instanceof Optional && $data->role === UserRole::Superadmin`, throws `ProtectedUserException::cannotPromoteToSuperadmin();`.
    Normalizes email if provided: if `! $data->email instanceof Optional`, lowercases and trims via `strtolower(trim($data->email))`; otherwise passes `new Optional()`.
    Builds normalized `UserData(name: $data->name, email: $normalizedEmail, password: $data->password, role: $data->role)`.
    Calls `$this->users->update($user, $normalizedData);`.
  - `public function destroy(User $user): void`:
    Validates superadmin protection: if `$user->role === UserRole::Superadmin`, throws `ProtectedUserException::cannotDeleteSuperadmin();`.
    Calls `$this->users->destroy($user);`.

#### `App\Providers\AccountsServiceProvider`
- Namespace: `App\Providers`
- File header: `declare(strict_types=1);`
- Class modifiers: `final class AccountsServiceProvider extends \Illuminate\Support\ServiceProvider`
- Register bindings in `register(): void`:
  ```php
  $this->app->bind(
      \App\Domain\Accounts\Contracts\UserRepositoryInterface::class,
      \App\Domain\Accounts\Repositories\UserRepository::class,
  );
  ```

#### `bootstrap/providers.php`
- Modify: `bootstrap/providers.php` currently contains `declare(strict_types=1);` on line 3. The file is modified to register `AccountsServiceProvider::class` in the returned array.
- Exact resulting file contents:
  ```php
  <?php

  declare(strict_types=1);

  use App\Providers\AccountsServiceProvider;
  use App\Providers\AppServiceProvider;
  use App\Providers\TokenServiceProvider;

  return [
      AccountsServiceProvider::class,
      AppServiceProvider::class,
      TokenServiceProvider::class,
  ];
  ```

### 6. Data and migrations

None.
No database tables, columns, constraints, or migrations are created or modified in this step. All required schema elements (`public_id`, `role`, `deleted_at`, check constraints, indexes) were created and verified in merged step `ACC-U1`.

Backward-compatibility statement:
Existing database schema is unchanged. All existing tests in `tests/Feature/Tokens/` continue to execute against PostgreSQL without modifications.

### 7. Behavior and flows

#### 1. User Store Flow
- Caller invokes `UserService::store(UserData $data)`.
- Step 1 (Presence Check): `UserService` checks `$data->name`, `$data->email`, `$data->password`, `$data->role`. If any field is an instance of `Optional`, `InvalidArgumentException` is thrown listing the missing field names. Database is not touched.
- Step 2 (Superadmin Protection): If `$data->role === UserRole::Superadmin`, `ProtectedUserException::cannotCreateSuperadmin()` is thrown. Database is not touched.
- Step 3 (Normalization): Email is trimmed and lowercased.
- Step 4 (Persistence): `UserRepository::store` generates a ULID for `public_id`, maps attributes, and calls `User::create()`.
- Step 5 (Password Hashing): Plain text password is cast to bcrypt hash via `User` model cast.
- Returns created `User` model.

#### 2. User Update Flow
- Caller invokes `UserService::update(User $user, UserData $data)`.
- Step 1 (Superadmin Protection):
  - If `$user->role === UserRole::Superadmin` and `$data->role` is not `Optional`: throws `ProtectedUserException::cannotChangeSuperadminRole()`.
  - If `$user->role !== UserRole::Superadmin` and `$data->role === UserRole::Superadmin`: throws `ProtectedUserException::cannotPromoteToSuperadmin()`.
- Step 2 (Normalization): If `$data->email` is present, it is trimmed and lowercased.
- Step 3 (Persistence): `UserRepository::update` inspects `$data`. Absent fields (`Optional`) are omitted from the update array.
- Step 4 (Model Cast): If `password` is present in `$data`, `User` cast hashes the new password. If `password` is absent, the existing hash in the database is untouched.
- Returns updated `User` model.

#### 3. User Index Flow
- Caller invokes `UserService::index(UserFilterData $filter)`.
- Step 1 (Parameter Sanitization in Service):
  - `perPage` is clamped between 1 and 100.
  - `search` string is trimmed by the service. If empty or whitespace-only, it is set to `null`.
- Step 2 (Query Execution in Repository):
  - `UserRepository::index` checks `$filter->includeTrashed`. If `true`, applies `withTrashed()`; otherwise applies default query excluding soft-deleted rows.
  - If `$filter->role` is set, filters by exact role value.
  - If `$filter->search` is set, escapes `%`, `_`, and `\` literals via `'\\%_'`, applying case-insensitive PostgreSQL `ILIKE` on `name` or `email`.
  - Applies deterministic sort `created_at DESC, id DESC`.
- Returns `LengthAwarePaginator<int, User>`.

#### 4. User Show Flow
- Caller invokes `UserService::show(string $publicId)`.
- Step 1 (Lookup): `UserRepository::show($publicId)` queries `User` table matching `public_id`.
- Step 2 (Soft-Delete Check): Eloquent `SoftDeletes` scope excludes soft-deleted records. If no active record matches, returns `null`.
- Step 3 (Exception Handling): If result is `null`, `UserService` throws `ModelNotFoundException` specifying `User` class and `$publicId`.
- Returns active `User` model.

#### 5. User Destroy Flow
- Caller invokes `UserService::destroy(User $user)`.
- Step 1 (Superadmin Protection): If `$user->role === UserRole::Superadmin`, throws `ProtectedUserException::cannotDeleteSuperadmin()`. Database is not touched.
- Step 2 (Soft Delete): `UserRepository::destroy` calls `$user->delete()`. PostgreSQL updates `deleted_at` timestamp. Record remains in table.
- Revocation note: Token revocation on deletion is deferred to `ACC-U5`.

#### Concurrency Note
User records follow last-write-wins without optimistic locking or database row locking. Concurrent updates overwrite columns based on the most recent completed database transaction.

### 8. Rule compliance matrix

| ID | Applies (Y/N) | How satisfied, or why N/A |
|---|---|---|
| STM1 | Y | Step modifies only Users stream paths (`app/Domain/Accounts/`, `app/Providers/AccountsServiceProvider.php`, `bootstrap/providers.php`, `tests/Feature/Accounts/`). Tokens stream files remain untouched. |
| STM2 | N | N/A. `TokenIssuer` and `TokenPairData` are not modified or called in this step. |
| STM3 | Y | Domain code under `app/Domain/Accounts/` does not import `Laravel\Passport`. Verified via Layer A command 15c. |
| STM4 | N | N/A. Tokens stream code is frozen and not touched. |
| LAY1 | Y | Request flow adheres to layering: Service calls `UserRepositoryInterface`, implemented by `UserRepository`. Controllers and FormRequests deferred to ACC-U3. |
| LAY2 | N | N/A. Controllers are out of scope for ACC-U2. |
| LAY3 | Y | `UserService` contains business rules and superadmin checks; depends strictly on `UserRepositoryInterface`; imports zero `Illuminate\Http` classes. |
| LAY4 | Y | `UserRepository` encapsulates all Eloquent queries; contains no business rules, HTTP exceptions, or authorization checks. |
| LAY5 | Y | Controller, Service, and Repository strictly adhere to method parity with the five names: `index`, `store`, `show`, `update`, `destroy`. |
| LAY6 | Y | `App\Domain` does not import `App\Http`. Verified via Layer A command 15c. |
| LAY7 | Y | All files reside in locations designated by `ARCHITECTURE.md` §5 (`app/Domain/Accounts/{Contracts,Repositories,Services,Data,Exceptions}/`, `app/Providers/AccountsServiceProvider.php`, `bootstrap/providers.php`). |
| LAY8 | N | N/A. Controllers are out of scope for ACC-U2. |
| STD1 | Y | Every new file and modified file (`bootstrap/providers.php`) includes `declare(strict_types=1);`. Verified via Layer A command 15f. |
| STD2 | Y | All classes are marked `final` (including `AccountsServiceProvider`, matching `TokenServiceProvider`), interfaces cannot be final. Every parameter, property, and return type is strictly typed. |
| STD3 | Y | Enums (`UserRole`) are used for fixed roles. Spatie Data classes (`UserData`, `UserFilterData`) are used for structured transfer. No string literals for roles in domain code. |
| STD4 | Y | A single `UserData` DTO serves both create and update operations with `Optional` properties. No secondary update DTO is created. |
| STD5 | Y | `UserRepository::update` builds update payload only from non-`Optional` fields, ensuring unspecified columns are never overwritten with `null`. |
| STD6 | Y | Code will pass Pint and Larastan without baseline entries or error suppressions. |
| STD7 | Y | Comments explain rationale, not code mechanics. Zero commented-out code. |
| ACT1 | N | N/A. Customer public registration is out of scope (handled in ACC-U4). |
| ACT2 | N | N/A. Retailer creation via API endpoint is out of scope (handled in ACC-U3). |
| ACT3 | N | N/A. User policy and endpoint authorization are out of scope (handled in ACC-U3). |
| ACT4 | Y | `UserService` prevents creation of superadmins and promotion to superadmin, throwing `ProtectedUserException`. Superadmins originate solely from seeders. |
| ACT5 | N | N/A. Login endpoints are out of scope (handled in ACC-U4). |
| ACT6 | Y | Zero calls to `Hash::make()` in domain code or tests. Password hashing is delegated entirely to the `hashed` cast on `User`. |
| ACT7 | N | N/A. Login error handling is out of scope (handled in ACC-U4). |
| ACT8 | Y | User deletion, password modification, and role modification will invoke token revocation once token revocation is introduced in `ACC-U5`. This gap is accepted for `ACC-U2` per brief Known Trap 9 and cross-referenced in §13. |
| ACT9 | N | N/A. Auth token issuance is out of scope (handled in ACC-U4). |
| ACT10 | N | N/A. Login/register responses are out of scope (handled in ACC-U4). |
| ACT11 | Y | No passwords or secrets are logged. |
| TOK1 | N | N/A. Tokens stream is out of scope. |
| TOK2 | N | N/A. Tokens stream is out of scope. |
| TOK3 | N | N/A. Tokens stream is out of scope. |
| TOK4 | N | N/A. Tokens stream is out of scope. |
| TOK5 | N | N/A. Tokens stream is out of scope. |
| TOK6 | N | N/A. Tokens stream is out of scope. |
| DBS1 | Y | PostgreSQL is the sole database engine. Tests execute against PostgreSQL, never SQLite. |
| DBS2 | Y | Zero migrations added or modified in this step. Verified via Layer A command 15a. |
| DBS3 | N | N/A. No columns or tables dropped or modified in this step. |
| DBS4 | Y | Query paths utilize existing indexes (`public_id`, `role`, `email`) and check constraints on PostgreSQL. |
| DBS5 | Y | Lookups use external `public_id` (ULID) only. Incremental database `id` is never accepted as input or exposed. |
| DBS6 | N | N/A. No seeders executed or modified in this step. |
| SCR1 | Y | No secrets, keys, or `.env` files committed. |
| SCR2 | Y | No policies, strict types, or static analysis rules disabled or weakened. |
| SCR3 | Y | Superadmin protection rules enforced strictly server-side in `UserService`. |
| SCR4 | N | N/A. Rate limiting is out of scope (handled in ACC-U3 and ACC-U4). |
| TST1 | Y | Pest feature tests implemented in `tests/Feature/Accounts/`. Arch tests in `tests/Arch/` continue to pass. |
| TST2 | Y | Tests cover failure paths, boundary conditions, and superadmin protection cases. |
| TST3 | N | N/A. Token issuer fakes are out of scope (used in ACC-U4 and ACC-U5). |
| TST4 | Y | Tests utilize `UserFactory` and factory states (`superadmin()`, `retailer()`, `customer()`), never raw database inserts. |
| TST5 | Y | Zero skipped tests, zero incomplete tests, zero lowered coverage. |
| GIT1 | Y | Branch cut following naming convention `feat/users-domain-layer`. |
| GIT2 | Y | Commits follow Conventional Commits with scope `feat(users):`. |
| GIT3 | Y | Direct pushes to main forbidden; code rebased on main. |
| GIT4 | N | N/A. This is a users stream step, not a contract PR. |
| GIT5 | Y | PR description documents changes, rationale, testing evidence, and risk analysis. |
| GIT6 | Y | Merge order adheres to ARCHITECTURE.md §10 sequence. |
| ENV1 | Y | PowerShell commands executed individually without command chaining (`&&`) or bash syntax. |
| ENV2 | Y | Native PHP 8.3, Composer, and PostgreSQL used. No Docker, Sail, or containers. |
| ENV3 | Y | Non-interactive execution; no background watchers left running. |
| ENV4 | Y | `composer check` (Pint, Larastan, Pest) executed and raw output evaluated before completion. |
| DNT1 | Y | Zero new dependencies added to `composer.json`. |
| DNT2 | Y | Zero infrastructure or pipeline modifications. |
| DNT3 | Y | Out-of-scope features (Filament, 2FA, password resets, social login) omitted. |
| DNT4 | Y | Business logic excluded from models and controllers; contained exclusively in `UserService`. |
| DNT5 | Y | No destructive file or database operations executed. |
| DNT6 | Y | No secrets, old databases, or Passport keys copied. |
| DNT7 | Y | Documentation updates specified in Section 11 for application at G4 closeout. |
| WFL1 | Y | Work proceeds strictly from approved plan `[ACC-U2 | PLAN v3 | Gemini]`. |
| WFL2 | Y | Any necessary deviation will be halted and raised via formal Change Request. |
| WFL3 | Y | Implementation ends with comprehensive report containing raw command outputs. |
| WFL4 | Y | Agent does not mark step done; final acceptance reserved for reviewer. |
| WFL5 | Y | Plan submitted and approved prior to writing implementation code. |
| WFL6 | Y | Scope constrained strictly to the 10 manifest files with single concern (domain layer). |
| WFL7 | Y | All conflicts and review findings addressed in Section 13. |

### 9. AC traceability

| AC | Files | Test names | How verified |
|---|---|---|---|
| AC-1 | `app/Domain/Accounts/Data/UserData.php`, `app/Domain/Accounts/Data/UserFilterData.php` | `it('initializes user filter data with default filter values')`, `it('preserves optional fields during create then update lifecycle')` | Pest feature tests in `tests/Feature/Accounts/UserServiceTest.php` asserting property types, defaults, and partial field preservation across store and update. |
| AC-2 | `app/Domain/Accounts/Contracts/UserRepositoryInterface.php`, `app/Domain/Accounts/Repositories/UserRepository.php`, `app/Domain/Accounts/Services/UserService.php`, `app/Providers/AccountsServiceProvider.php`, `bootstrap/providers.php` | `it('resolves user repository interface to user repository from container')`, `it('resolves user service from container via auto wiring')` | Pest container resolution tests in `tests/Feature/Accounts/UserRepositoryTest.php` and `UserServiceTest.php`. |
| AC-3 | `app/Domain/Accounts/Services/UserService.php`, `app/Domain/Accounts/Repositories/UserRepository.php` | `it('stores user with lowercased trimmed email, ulid public id, and hashed password')`, `it('throws invalid argument exception and writes no row when required fields are missing on store')` | Tests asserting database values, ULID regex format, `Hash::check()`, exception thrown naming missing fields, and `User::count()` unchanged. |
| AC-4 | `app/Domain/Accounts/Services/UserService.php`, `app/Domain/Accounts/Repositories/UserRepository.php` | `it('updates only name without altering email, role, or password hash')`, `it('updates password hash when password is provided on update')`, `it('lowercases and trims email when email is provided on update')` | Tests asserting untouched attributes remain identical and modified attributes update correctly. |
| AC-5 | `app/Domain/Accounts/Services/UserService.php`, `app/Domain/Accounts/Repositories/UserRepository.php` | `it('paginates users and filters by role in repository')`, `it('excludes soft deleted users by default in repository index')`, `it('includes soft deleted users when includeTrashed is true in repository index')`, `it('escapes percent wildcard matching literal percent character only in repository index')`, `it('escapes underscore wildcard matching literal underscore character only in repository index')`, `it('matches user email in repository index search')`, `it('searches user name case insensitively in repository index')`, `it('clamps per page between 1 and 100 on index')`, `it('treats empty or whitespace only search string as null on index')`, `it('trims search string in service so padded search matches user on index')` | Tests verifying paginated results, literal wildcards (`%` and `_`), email search, case-insensitivity, padded trimming in service, and default exclusion of soft-deleted records. |
| AC-6 | `app/Domain/Accounts/Services/UserService.php`, `app/Domain/Accounts/Repositories/UserRepository.php` | `it('destroys user via soft delete setting deleted_at in repository')`, `it('destroys user keeping row in database while excluding from show and default index')` | Test verifying `deleted_at` timestamp populated, model omitted from `index()` and `show()`, visible in `withTrashed()`. |
| AC-7 | `app/Domain/Accounts/Services/UserService.php`, `app/Domain/Accounts/Repositories/UserRepository.php` | `it('finds user by public id excluding soft deleted users in repository show')`, `it('shows user by public id')`, `it('throws model not found exception for unknown public id on show')`, `it('throws model not found exception for soft deleted user on show')` | Tests asserting retrieval of valid ULID and `ModelNotFoundException` for absent or trashed ULIDs. |
| AC-8 | `app/Domain/Accounts/Exceptions/ProtectedUserException.php`, `app/Domain/Accounts/Services/UserService.php` | `it('throws protected user exception and writes no row when attempting to store superadmin')`, `it('throws protected user exception and does not change role when attempting to promote user to superadmin')`, `it('throws protected user exception and does not change role when attempting to change superadmin role')`, `it('throws protected user exception and does not change role when updating superadmin with superadmin role provided')`, `it('allows updating name or email of existing superadmin')`, `it('throws protected user exception and does not destroy user when attempting to destroy superadmin')` | Tests verifying `ProtectedUserException` named constructors with `User::count()` unchanged and stored model roles untouched. |
| AC-9 | All files | `tests/Feature/Tokens/*`, `tests/Arch/*`, `tests/Feature/Accounts/*` | Execution of `composer check` (Pint + Larastan + Pest) with zero errors, zero warnings, zero skipped tests. |
| AC-10 | All files | Arch tests and Layer A check scripts | Execution of Layer A commands 15c, 15d, 15e, 15f confirming strict types, zero `Hash::make`, and zero role string literals. |

### 10. Test plan

#### Database Isolation Note
All feature tests extend `Tests\TestCase` and automatically leverage database transactions and cleanup through `pest()->use(RefreshDatabase::class)->in('Feature');` configured in `tests/Pest.php`.

#### `tests/Feature/Accounts/UserRepositoryTest.php`
- `it('resolves user repository interface to user repository from container')`:
  - Arrange: Bootstrap application container.
  - Act: Call `app(UserRepositoryInterface::class)`.
  - Assert: Expect resolved instance to be an instance of `UserRepository`.
- `it('paginates users and filters by role in repository')`:
  - Arrange: Create 2 customer users and 1 retailer user via `User::factory()`.
  - Act: Call `UserRepository::index` with `UserFilterData(role: UserRole::Retailer)`.
  - Assert: Expect paginator count to equal 1 with role `Retailer`.
- `it('excludes soft deleted users by default in repository index')`:
  - Arrange: Create 2 users; soft-delete 1 user via `$user->delete()`.
  - Act: Call `UserRepository::index` with `new UserFilterData()`.
  - Assert: Expect paginator total to equal 1.
- `it('includes soft deleted users when includeTrashed is true in repository index')`:
  - Arrange: Create 2 users; delete 1 user via `$user->delete()`.
  - Act: Call `UserRepository::index` with `UserFilterData(includeTrashed: true)`.
  - Assert: Expect paginator total to equal 2.
- `it('escapes percent wildcard matching literal percent character only in repository index')`:
  - Arrange: Create user with name `'Special%User'` and user with name `'SpecialOtherUser'`.
  - Act: Call `UserRepository::index` with `UserFilterData(search: 'Special%')`.
  - Assert: Expect paginator total to equal 1 and matched record to have name `'Special%User'`.
- `it('escapes underscore wildcard matching literal underscore character only in repository index')`:
  - Arrange: Create user with name `'a_b'` and user with name `'axb'`.
  - Act: Call `UserRepository::index` with `UserFilterData(search: 'a_b')`.
  - Assert: Expect paginator total to equal 1 and matched record to have name `'a_b'`.
- `it('escapes backslash wildcard matching literal backslash character only in repository index')`:
  - Arrange: Create user with name `'a\\b'` and user with name `'axb'`.
  - Act: Call `UserRepository::index` with `UserFilterData(search: 'a\\b')`.
  - Assert: Expect paginator total to equal 1 and matched record to have name `'a\\b'`.
- `it('matches user email in repository index search')`:
  - Arrange: Create user with email `'target.account@getnow.test'`.
  - Act: Call `UserRepository::index` with `UserFilterData(search: 'target.account')`.
  - Assert: Expect paginator total to equal 1 and matched record email to equal `'target.account@getnow.test'`.
- `it('searches user name case insensitively in repository index')`:
  - Arrange: Create user with name `'Alice Wonder'`.
  - Act: Call `UserRepository::index` with `UserFilterData(search: 'alice')`.
  - Assert: Expect paginator total to equal 1 and matched record name to equal `'Alice Wonder'`.
- `it('finds user by public id excluding soft deleted users in repository show')`:
  - Arrange: Create user, soft-delete via `$user->delete()`.
  - Act: Call `UserRepository::show($user->public_id)`.
  - Assert: Expect returned value to be `null`.
- `it('stores user record with generated ulid public id and hashed password in repository')`:
  - Arrange: Build `UserData` with plain password `'secret-pass'`, name, email, role.
  - Act: Call `UserRepository::store($userData)`.
  - Assert: Expect `public_id` to match ULID regex and `Hash::check('secret-pass', $user->password)` to be true.
- `it('updates only present fields leaving absent optional fields unmodified in repository')`:
  - Arrange: Create user with initial attributes; build `UserData(name: 'Updated Name')`.
  - Act: Call `UserRepository::update($user, $userData)`.
  - Assert: Expect database name to be `'Updated Name'` while email, role, and password hash remain identical.
- `it('destroys user via soft delete setting deleted_at in repository')`:
  - Arrange: Create user via factory.
  - Act: Call `UserRepository::destroy($user)`.
  - Assert: Expect `$user->trashed()` to be true and database row to persist with populated `deleted_at`.

#### `tests/Feature/Accounts/UserServiceTest.php`
- `it('resolves user service from container via auto wiring')`:
  - Arrange: Bootstrap container.
  - Act: Call `app(UserService::class)`.
  - Assert: Expect instance of `UserService`.
- `it('initializes user filter data with default filter values')`:
  - Arrange: Instantiate `new UserFilterData()`.
  - Act: Inspect properties.
  - Assert: Expect search `null`, role `null`, includeTrashed `false`, perPage `15`.
- `it('preserves optional fields during create then update lifecycle')`:
  - Arrange: Store user with initial name `'Original'`, email `'initial@getnow.test'`, password `'plain-pass'`, role `UserRole::Customer`.
  - Act: Update user with `UserData(name: 'Modified')`.
  - Assert: Expect stored name to equal `'Modified'`, email to remain `'initial@getnow.test'`, role to remain `UserRole::Customer`, and `Hash::check('plain-pass', $user->fresh()->password)` to be true.
- `it('throws invalid argument exception and writes no row when required fields are missing on store')`:
  - Arrange: Record initial `User::count()`; build `UserData` omitting `password` and `role`.
  - Act: Invoke `UserService::store($data)`.
  - Assert: Expect `InvalidArgumentException` naming `'password'` and `'role'` in message, and `User::count()` unchanged.
- `it('stores user with lowercased trimmed email, ulid public id, and hashed password')`:
  - Arrange: Build `UserData` with email `'  NEW.USER@EXAMPLE.COM  '` and plain password.
  - Act: Invoke `UserService::store($data)`.
  - Assert: Expect stored email to equal `'new.user@example.com'`, public_id to be ULID, and password hashed.
- `it('throws protected user exception and writes no row when attempting to store superadmin')`:
  - Arrange: Record initial `User::count()`; build `UserData` with role `UserRole::Superadmin`.
  - Act: Invoke `UserService::store($data)`.
  - Assert: Expect `ProtectedUserException` with message `'Cannot create a user with the superadmin role.'` and `User::count()` unchanged.
- `it('updates only name without altering email, role, or password hash')`:
  - Arrange: Create user; build `UserData(name: 'New Name')`.
  - Act: Invoke `UserService::update($user, $data)`.
  - Assert: Expect name changed; email, role, and password hash identical.
- `it('updates password hash when password is provided on update')`:
  - Arrange: Create user; record initial password hash; build `UserData(password: 'brand-new-pass')`.
  - Act: Invoke `UserService::update($user, $data)`.
  - Assert: Expect password hash changed and `Hash::check('brand-new-pass', $user->fresh()->password)` true.
- `it('lowercases and trims email when email is provided on update')`:
  - Arrange: Create user; build `UserData(email: '  UPDATED@EXAMPLE.COM ')`.
  - Act: Invoke `UserService::update($user, $data)`.
  - Assert: Expect stored email to be `'updated@example.com'`.
- `it('throws protected user exception and does not change role when attempting to promote user to superadmin')`:
  - Arrange: Create customer user; build `UserData(role: UserRole::Superadmin)`.
  - Act: Invoke `UserService::update($user, $data)`.
  - Assert: Expect `ProtectedUserException` with message `'Cannot promote a user to the superadmin role.'` and `$user->fresh()->role` remaining `UserRole::Customer`.
- `it('throws protected user exception and does not change role when attempting to change superadmin role')`:
  - Arrange: Create superadmin user; build `UserData(role: UserRole::Retailer)`.
  - Act: Invoke `UserService::update($superadmin, $data)`.
  - Assert: Expect `ProtectedUserException` with message `'Cannot change the role of a user with the superadmin role.'` and `$superadmin->fresh()->role` remaining `UserRole::Superadmin`.
- `it('throws protected user exception and does not change role when updating superadmin with superadmin role provided')`:
  - Arrange: Create superadmin user; build `UserData(name: 'New Name', role: UserRole::Superadmin)`.
  - Act: Invoke `UserService::update($superadmin, $data)`.
  - Assert: Expect `ProtectedUserException` with message `'Cannot change the role of a user with the superadmin role.'` and `$superadmin->fresh()->role` remaining `UserRole::Superadmin`.
- `it('allows updating name or email of existing superadmin')`:
  - Arrange: Create superadmin user; build `UserData(name: 'Updated Superadmin')`.
  - Act: Invoke `UserService::update($superadmin, $data)`.
  - Assert: Expect name updated to `'Updated Superadmin'` and role remaining `UserRole::Superadmin`.
- `it('shows user by public id')`:
  - Arrange: Create user via factory.
  - Act: Invoke `UserService::show($user->public_id)`.
  - Assert: Expect returned model id to equal created user id.
- `it('throws model not found exception for unknown public id on show')`:
  - Arrange: Generate random ULID.
  - Act: Invoke `UserService::show($nonExistentUlid)`.
  - Assert: Expect `ModelNotFoundException`.
- `it('throws model not found exception for soft deleted user on show')`:
  - Arrange: Create user, soft-delete via `$user->delete()`.
  - Act: Invoke `UserService::show($user->public_id)`.
  - Assert: Expect `ModelNotFoundException`.
- `it('destroys user keeping row in database while excluding from show and default index')`:
  - Arrange: Create customer user.
  - Act: Invoke `UserService::destroy($user)`.
  - Assert: Expect user excluded from `UserService::index()` and `UserService::show()`, present with `deleted_at` in DB.
- `it('throws protected user exception and does not destroy user when attempting to destroy superadmin')`:
  - Arrange: Create superadmin user.
  - Act: Invoke `UserService::destroy($superadmin)`.
  - Assert: Expect `ProtectedUserException` with message `'Cannot delete a user with the superadmin role.'` and `$superadmin->fresh()->trashed()` being false.
- `it('clamps per page between 1 and 100 on index')`:
  - Arrange: Create 5 users.
  - Act: Query `UserService::index` with `perPage: 200` and `perPage: -5`.
  - Assert: Expect paginator perPage to be 100 and 1 respectively.
- `it('treats empty or whitespace only search string as null on index')`:
  - Arrange: Create 3 users.
  - Act: Query `UserService::index` with `search: '   '`.
  - Assert: Expect all 3 users returned without filtering.
- `it('trims search string in service so padded search matches user on index')`:
  - Arrange: Create user with name `'Alice'`.
  - Act: Query `UserService::index` with `search: ' alice '`.
  - Assert: Expect paginator total to equal 1 and matched user name to equal `'Alice'`.

### 11. Docs and contract updates

Application of documentation updates is deferred to G4 closeout per workflow §11. At G4 closeout, the reviewer executes the following edits:

#### 1. `ARCHITECTURE.md` §10 (Delivery order and the token import)
Current lines 246 to 250 in `ARCHITECTURE.md`:
```markdown
- [x] `ACC-U1` Users foundation: `UserRole`, users migration additions, `User` model, factory, `SuperadminSeeder`.
- [ ] `ACC-U2` Repository interface and repository, `UserData`, `UserFilterData`, `UserResponseData`.
- [ ] `ACC-U3` `UserService`, `UserPolicy`, `UserController`, form requests, `/users` routes.
- [ ] `ACC-U4` `AuthService`, `AuthController`: register and login, wired to `TokenIssuer`.
- [ ] `ACC-U5` Revocation on delete, password change and role change; full authorization tests.
```

Exact Before text (lines 247-248):
```markdown
- [ ] `ACC-U2` Repository interface and repository, `UserData`, `UserFilterData`, `UserResponseData`.
- [ ] `ACC-U3` `UserService`, `UserPolicy`, `UserController`, form requests, `/users` routes.
```

Exact After text:
```markdown
- [x] `ACC-U2` Repository interface and repository, `UserService`, `UserData`, `UserFilterData`, protections.
- [ ] `ACC-U3` `UserResponseData`, `UserPolicy`, `UserController`, form requests, `/users` routes.
```
Rationale: Removes `UserResponseData` from `ACC-U2` (deferred to `ACC-U3`), notes that `UserService` and protections were delivered in `ACC-U2`, and ticks the `ACC-U2` checkbox.

#### 2. `ARCHITECTURE.md` §2 (Streams Table)
Current streams table in `ARCHITECTURE.md` §2 (lines 21-25):
```markdown
| Stream | Scope | Status |
|---|---|---|
| Users (`U`) | User CRUD, register, login credential check, roles, user DTO, controller, service, repository, repository interface, `UserPolicy` | To build |
| Tokens (`T`) | Passport install and config, token issue, refresh, revoke, token lifetimes, auth guard, `PassportTokenIssuer` | Built in the earlier project, to import (`ACC-T0`) |
| Contract (`C`) | `TokenIssuer` interface and `TokenPairData` (section 6) | To fix against the imported code |
```
Evaluation: The Users stream status reflects stream-level completion (`To build` until all `ACC-U` steps are completed). To match the wording pattern of the other rows, no edit is made to the §2 table during `ACC-U2`. All step-level progress is tracked exclusively in the §10 checklist.

#### 3. Other Documents
- `content-api.md`: None. Reason: Content APIs are out of scope.
- `fixtures`: None. Reason: No fixtures exist in the authentication module.
- `.env.example`: None. Reason: No new environment variables introduced in this step.
- `ADRs`: None. Reason: User soft deletes and schema cleanup were documented in ADR 0003 during `ACC-U1`.

### 12. Implementation order

#### Commit 1: `feat(users): add user data transfer objects and domain exceptions`
- Files:
  - `app/Domain/Accounts/Data/UserData.php`
  - `app/Domain/Accounts/Data/UserFilterData.php`
  - `app/Domain/Accounts/Exceptions/ProtectedUserException.php`
- Rationale: Establishes typed input data structures and exception types with named constructors for domain rules.
- State: `composer check` green (Larastan and Pint clean; existing token tests passing).

#### Commit 2: `feat(users): add user repository interface, eloquent repository, and service provider`
- Files:
  - `app/Domain/Accounts/Contracts/UserRepositoryInterface.php`
  - `app/Domain/Accounts/Repositories/UserRepository.php`
  - `app/Providers/AccountsServiceProvider.php`
  - `bootstrap/providers.php`
  - `tests/Feature/Accounts/UserRepositoryTest.php`
- Rationale: Implements repository contract, Eloquent persistence logic with LAY5 method names (`index`, `show`, `store`, `update`, `destroy`), `AccountsServiceProvider` container binding, provider registration in `bootstrap/providers.php`, and repository feature tests.
- State: `composer check` green (all tests pass, 100% strict types satisfied).

#### Commit 3: `feat(users): add user domain service and business rules`
- Files:
  - `app/Domain/Accounts/Services/UserService.php`
  - `tests/Feature/Accounts/UserServiceTest.php`
- Rationale: Implements core business logic, input validation, email normalization, boundary clamping, and superadmin protections adhering to LAY5 method names (`index`, `show`, `store`, `update`, `destroy`).
- State: `composer check` green (all unit, feature, and arch tests pass).

### 13. Risks, assumptions, conflicts

#### Conflict 1 Resolved: Method Parity (Rule LAY5 & ARCHITECTURE.md §4)
- In accordance with review amendment A1, method names across `UserRepositoryInterface`, `UserRepository`, and `UserService` strictly follow rule `LAY5` and `ARCHITECTURE.md` §4 method parity:
  `index`, `show`, `store`, `update`, `destroy`.
- Acceptance criteria map as follows: AC-3 maps to `store`, AC-4 maps to `update`, AC-5 maps to `index`, AC-6 maps to `destroy`, AC-7 maps to `show`, and AC-8 covers `store`, `update`, and `destroy`.

#### Conflict 2 Resolved: Service Provider Location (ARCHITECTURE.md §4/§5)
- In accordance with review amendment A2, container binding for `UserRepositoryInterface` is placed in new file `app/Providers/AccountsServiceProvider.php` and registered in `bootstrap/providers.php`. `AppServiceProvider.php` is untouched.

#### Scope Alignment: UserResponseData (Finding M2)
- `UserResponseData` is explicitly deferred to `ACC-U3` where HTTP controllers, policies, and API response formatting are introduced. The `ARCHITECTURE.md` §10 checklist line for `ACC-U2` is reworded at G4 closeout to reflect that `UserResponseData` ships in `ACC-U3` while `UserService` and superadmin protections ship in `ACC-U2`.

#### Known Gap: Token Revocation on User Mutation (Rule ACT8)
- Calling `UserService::destroy` or modifying a user's password or role in this step does not invoke token revocation. This is an accepted temporary gap until step `ACC-U5`, because HTTP endpoints exposing user mutations (`UserController`) are not introduced until `ACC-U3`.

#### Database Error Surface: Duplicate Email Unique Constraint
- Uniqueness on `email` is enforced at the database level by the unique index on `users(email)`. Attempting to store or update a user with an existing email without FormRequest validation will trigger `Illuminate\Database\UniqueConstraintViolationException` from PostgreSQL. HTTP validation with friendly 422 error responses will be implemented in `StoreUserRequest` and `UpdateUserRequest` during step `ACC-U3`.

#### Assumption: Superadmin Seeder Email Normalization
- `SuperadminSeeder` (implemented in `ACC-U1`) stores `config('auth.superadmin.email')` directly. `UserService` normalizes emails on all stores and updates. The seeder is out of scope for this step; superadmin credentials in `.env.example` must use lowercased emails (`superadmin@getnow.test`).

### 14. Dependencies

No new composer packages or npm packages are introduced. All functionality utilizes native PHP 8.3, Laravel 13 framework components, existing project dependency `spatie/laravel-data: ^4.23`, and existing dev dependencies (`pestphp/pest`, `larastan/larastan`, `laravel/pint`).
