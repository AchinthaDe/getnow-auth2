# [ACC-U3 | PLAN v1 | Gemini]

### 1. Understanding
Implement the Users-stream HTTP layer exposing `/api/v1/users` endpoints (`index`, `store`, `show`, `update`, `destroy`) backed by `UserService`. The layer includes `UserResponseData`, `UserPolicy`, `StoreUserRequest`, `UpdateUserRequest`, `UserController`, route bindings in `routes/api/users.php`, policy registration in `AppServiceProvider`, exception translation in `bootstrap/app.php`, and comprehensive feature tests.

Out of scope:
- Public registration (`POST /api/v1/auth/register`) and login (`POST /api/v1/auth/login`), which belong to `ACC-U4`.
- Token revocation on user deletion, password update, or role modification via `TokenIssuer::revokeAll`, which belongs to `ACC-U5`.
- User restoration (`restore`), permanent deletion (`forceDelete`), and bulk operations.
- Database schema changes, migrations, or modification of existing migrations.
- Modifications to `app/Models/User.php` or `ACC-U2` domain classes (`UserData`, `UserFilterData`, `UserRepositoryInterface`, `UserRepository`, `UserService`, `ProtectedUserException`).
- Tokens-stream files (`app/Domain/Accounts/Tokens/*`, `app/Http/Controllers/Api/V1/Auth/TokenController.php`, `config/passport.php`, `routes/api/tokens.php`).

---

### 2. Sources read

| File or Document Section | What in it constrains this step |
|---|---|
| [AGENTS.md](file:///c:/dev/getnow-auth2/AGENTS.md) | Layering flow (FormRequest -> Controller -> Service -> RepositoryInterface); controllers thin; method name parity (`index`, `store`, `show`, `update`, `destroy`); no Eloquent or DB queries in controllers; no HTTP classes in Domain; `strict_types=1` everywhere; single `UserData` DTO for store and update with `Optional` properties; never expose raw integer `id`; PostgreSQL only; Pest test guidelines. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L45-L72) §4 | Layering diagram, layer responsibilities, method parity table, container binding in service providers. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L73-L139) §5 | Namespace and file tree; stream ownership tags; shared touch points (`routes/api.php` one require line per stream). |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L177-L200) §7 | Endpoint definitions: base path `/api/v1`, route paths (`/users`, `/users/{user}`), JSON response shapes, query filters (`role`, `search`, `page`, `per_page` max 100), ULID lookup (`public_id`). |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L202-L229) §8 | Combined `UserData` pattern: validation rules in FormRequests, `Optional` properties, no `Hash::make`. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L230-L237) §9 | Schema details: `public_id` (ULID), `role` check constraint, soft deletes via `deleted_at`. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L302-L327) §11 | Security constraints, test requirements, resolution of OQ6 (soft delete resolved in ADR 0003). |
| [RULE_REGISTER.md](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md) | Full 75-rule register (STM, LAY, STD, ACT, TOK, DBS, SCR, TST, GIT, ENV, DNT, WFL) and Layer A commands. |
| [ACC-U3 brief.md](file:///c:/dev/getnow-auth2/docs/blueprints/ACC-U3/brief.md) | Acceptance criteria AC-1 through AC-10, size guard (<= 10 manifest files), open decisions OD-1 through OD-4 resolutions, rules at risk, known traps 1 through 10. |
| [app/Domain/Accounts/Enums/UserRole.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Enums/UserRole.php) | `UserRole` backing string values: `Superadmin = 'superadmin'`, `Retailer = 'retailer'`, `Customer = 'customer'`. |
| [app/Models/User.php](file:///c:/dev/getnow-auth2/app/Models/User.php) | `final class User extends Authenticatable implements OAuthenticatable`; traits `HasApiTokens`, `HasFactory`, `Notifiable`, `SoftDeletes`; casts for `role => UserRole::class`, `password => 'hashed'`, `email_verified_at => 'datetime'`. No `getRouteKeyName()` defined; model must not be edited (OD-3). |
| [database/factories/UserFactory.php](file:///c:/dev/getnow-auth2/database/factories/UserFactory.php) | Factory states `superadmin()`, `retailer()`, `customer()`, `unverified()`; default role is `UserRole::Customer`; default ULID `public_id`. |
| [app/Domain/Accounts/Data/UserData.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Data/UserData.php) | Constructor: `public string|Optional $name`, `public string|Optional $email`, `public string|Optional $password`, `public UserRole|Optional $role`. |
| [app/Domain/Accounts/Data/UserFilterData.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Data/UserFilterData.php) | Constructor: `public ?string $search = null`, `public ?UserRole $role = null`, `public bool $includeTrashed = false`, `public int $perPage = 15`. |
| [app/Domain/Accounts/Contracts/UserRepositoryInterface.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Contracts/UserRepositoryInterface.php) | Signatures: `index(UserFilterData): LengthAwarePaginator`, `show(string $publicId): ?User`, `store(UserData): User`, `update(User, UserData): User`, `destroy(User): void`. |
| [app/Domain/Accounts/Repositories/UserRepository.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Repositories/UserRepository.php) | Eloquent query logic; ILIKE search with parameter binding; ordering by `created_at DESC, id DESC`; ULID generation on create; partial updates with `Optional`. |
| [app/Domain/Accounts/Services/UserService.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Services/UserService.php) | Method signatures: `index(UserFilterData): LengthAwarePaginator`, `show(string $publicId): User`, `store(UserData): User`, `update(User, UserData): User`, `destroy(User): void`. Bounds checking (`perPage` 1-100), trimming, lowercase email, throws `ProtectedUserException` and `ModelNotFoundException`. |
| [app/Domain/Accounts/Exceptions/ProtectedUserException.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Exceptions/ProtectedUserException.php) | `final class ProtectedUserException extends RuntimeException`; named constructors `cannotCreateSuperadmin()`, `cannotPromoteToSuperadmin()`, `cannotDeleteSuperadmin()`, `cannotChangeSuperadminRole()`. |
| [app/Providers/AppServiceProvider.php](file:///c:/dev/getnow-auth2/app/Providers/AppServiceProvider.php) | Application service provider boot configuration. Target for `Gate::policy(User::class, UserPolicy::class)`. |
| [app/Providers/AccountsServiceProvider.php](file:///c:/dev/getnow-auth2/app/Providers/AccountsServiceProvider.php) | Users-stream provider binding `UserRepositoryInterface` to `UserRepository`. |
| [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php) | API prefix `api/v1`, guest redirect `null`, JSON exception rendering `shouldRenderJsonWhen`. Target for `ProtectedUserException` 403 HTTP translation. |
| [bootstrap/providers.php](file:///c:/dev/getnow-auth2/bootstrap/providers.php) | Registered provider list containing `AccountsServiceProvider`, `AppServiceProvider`, and `TokenServiceProvider`. |
| [routes/api.php](file:///c:/dev/getnow-auth2/routes/api.php) | Root API router requiring stream route files. Currently requires `routes/api/tokens.php`. Target for `require __DIR__.'/api/users.php';`. |
| [routes/api/tokens.php](file:///c:/dev/getnow-auth2/routes/api/tokens.php) | Tokens-stream routes under `auth` prefix with throttling and `auth:api` middleware. |
| [config/auth.php](file:///c:/dev/getnow-auth2/config/auth.php) | Guard configuration: `api` guard uses `passport` driver with `users` provider. |
| [docs/adr/0003-user-soft-deletes-and-schema-cleanup.md](file:///c:/dev/getnow-auth2/docs/adr/0003-user-soft-deletes-and-schema-cleanup.md) | Existing ADR 0003 recording soft delete adoption and column cleanups from `ACC-U1`. |
| [tests/Arch/AccountsArchTest.php](file:///c:/dev/getnow-auth2/tests/Arch/AccountsArchTest.php) | Enforces: `App\Domain` does not use `App\Http`; `Laravel\Passport` used only in `Tokens`, `TokenServiceProvider`, `User`; contracts are interfaces; data objects extend `Data`; strict types on `App\Domain`, `App\Http\Controllers\Api`, `App\Models`; API controllers do not use `Repositories` or `DB` facade. |
| [tests/Feature/Accounts/UserServiceTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Accounts/UserServiceTest.php) | Feature test patterns testing domain service behavior against PostgreSQL with factory helpers. |
| [tests/Feature/Tokens/TokenEndpointsTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Tokens/TokenEndpointsTest.php) | Token endpoint feature tests verifying `auth:api` behavior and route responses. |

---

### 3. Verified current state

1. `App\Domain\Accounts\Enums\UserRole`:
   ```php
   namespace App\Domain\Accounts\Enums;

   enum UserRole: string
   {
       case Superadmin = 'superadmin';
       case Retailer = 'retailer';
       case Customer = 'customer';
   }
   ```
2. `App\Models\User`:
   - Class declaration: `final class User extends Authenticatable implements OAuthenticatable`
   - Traits: `use HasApiTokens, HasFactory, Notifiable, SoftDeletes;`
   - Attributes: `#[Fillable(['public_id', 'name', 'email', 'password', 'role'])]`, `#[Hidden(['password'])]`
   - Casts: `'email_verified_at' => 'datetime'`, `'password' => 'hashed'`, `'role' => UserRole::class`
   - `getRouteKeyName()`: Not declared on `User`.
3. `App\Domain\Accounts\Data\UserData`:
   ```php
   namespace App\Domain\Accounts\Data;

   final class UserData extends Data
   {
       public function __construct(
           public string|Optional $name = new Optional,
           public string|Optional $email = new Optional,
           public string|Optional $password = new Optional,
           public UserRole|Optional $role = new Optional,
       ) {}
   }
   ```
4. `App\Domain\Accounts\Data\UserFilterData`:
   ```php
   namespace App\Domain\Accounts\Data;

   final class UserFilterData extends Data
   {
       public function __construct(
           public ?string $search = null,
           public ?UserRole $role = null,
           public bool $includeTrashed = false,
           public int $perPage = 15,
       ) {}
   }
   ```
5. `App\Domain\Accounts\Exceptions\ProtectedUserException`:
   - Class declaration: `final class ProtectedUserException extends RuntimeException`
   - Named constructors:
     - `public static function cannotCreateSuperadmin(): self`
     - `public static function cannotPromoteToSuperadmin(): self`
     - `public static function cannotDeleteSuperadmin(): self`
     - `public static function cannotChangeSuperadminRole(): self`
6. `App\Domain\Accounts\Contracts\UserRepositoryInterface`:
   - `public function index(UserFilterData $filter): LengthAwarePaginator;`
   - `public function show(string $publicId): ?User;`
   - `public function store(UserData $data): User;`
   - `public function update(User $user, UserData $data): User;`
   - `public function destroy(User $user): void;`
7. `App\Domain\Accounts\Repositories\UserRepository`:
   - Implements `UserRepositoryInterface`.
   - `index`: filters by `withTrashed()` when `$filter->includeTrashed`, filters by `role`, applies ILIKE search with `%pattern%` escaped by `addcslashes($filter->search, '\\%_')`, orders by `created_at DESC, id DESC`, calls `paginate(perPage: $filter->perPage)`.
   - `show`: queries `User::query()->where('public_id', $publicId)->first()`.
   - `store`: sets `public_id = (string) Str::ulid()`, populates non-`Optional` fields, calls `User::create($attributes)`.
   - `update`: updates non-`Optional` fields on `$user`, calls `$user->update($attributes)`.
   - `destroy`: calls `$user->delete()`.
8. `App\Domain\Accounts\Services\UserService`:
   - Constructor: `public function __construct(private readonly UserRepositoryInterface $users)`
   - `index(UserFilterData $filter): LengthAwarePaginator`: bounds `perPage` between 1 and 100, trims `search`, delegates to `$this->users->index($normalizedFilter)`.
   - `show(string $publicId): User`: retrieves via `$this->users->show($publicId)`, throws `(new ModelNotFoundException)->setModel(User::class, [$publicId])` if `null`.
   - `store(UserData $data): User`: enforces non-`Optional` properties, throws `ProtectedUserException::cannotCreateSuperadmin()` when `$data->role === UserRole::Superadmin`, normalises email with `strtolower(trim($data->email))`, delegates to `$this->users->store`.
   - `update(User $user, UserData $data): User`: guards superadmin role immutability and promotion, normalises email when present, delegates to `$this->users->update`.
   - `destroy(User $user): void`: guards superadmin deletion, delegates to `$this->users->destroy`.
9. `routes/api.php`:
   - Contains `require __DIR__.'/api/tokens.php';`. Does not contain `users.php`.
10. `routes/api/users.php`:
   - File does not exist in repository.
11. `app/Providers/AppServiceProvider.php`:
   - Lacks `declare(strict_types=1);`.
   - `boot()` configures defaults: `Date::use(CarbonImmutable::class)`, `DB::prohibitDestructiveCommands`, `Password::defaults`. Does not register `Gate::policy`.
12. `bootstrap/app.php`:
   - Configures `apiPrefix: 'api/v1'`.
   - Configures `redirectGuestsTo(fn () => null)`.
   - Configures `shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson())`. Does not register custom exception renderer for `ProtectedUserException`.
13. `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md`:
   - File exists on disk, created and accepted in `ACC-U1`. Status: Accepted.
14. `tests/Arch/AccountsArchTest.php`:
   - Contains 6 architecture tests. Tests pass with zero errors on baseline.

---

### 4. File manifest

| Path | New or Modify | Layer | One-line responsibility | Rule IDs it must satisfy |
|---|---|---|---|---|
| `app/Domain/Accounts/Data/UserResponseData.php` | New | Domain (Data) | Output data transfer object exposing sanitized public user representation. | STD1, STD2, STD3, LAY6, LAY7, LAY8, DBS5, ACT10, STM3 |
| `app/Domain/Accounts/Policies/UserPolicy.php` | New | Domain (Policies) | Authorization policy defining permission rules for user endpoints per role. | STD1, STD2, STD3, LAY2, LAY6, LAY7, ACT3, SCR3, STM3 |
| `app/Http/Requests/Users/StoreUserRequest.php` | New | HTTP (Requests) | Form request validating user creation input, normalising email, and building `UserData`. | STD1, STD2, STD3, STD4, LAY1, LAY7, ACT2, ACT4, ACT6, STM3 |
| `app/Http/Requests/Users/UpdateUserRequest.php` | New | HTTP (Requests) | Form request validating partial update input, ignoring route user in unique email check, and building `UserData`. | STD1, STD2, STD3, STD4, STD5, LAY1, LAY7, ACT4, ACT6, STM3 |
| `app/Http/Controllers/Api/V1/Users/UserController.php` | New | HTTP (Controllers) | Controller handling HTTP routing, policy authorization, input extraction, and response mapping. | STD1, STD2, STD3, LAY1, LAY2, LAY5, LAY7, LAY8, DBS5, DNT4, STM3 |
| `routes/api/users.php` | New | Routing | Endpoint route definitions for `/users` resource scoped by `auth:api` and `{user:public_id}`. | STD1, LAY7, DBS5, STM1 |
| `routes/api.php` | Modify | Routing | Mounts users stream routes file via `require` statement. | STD1, LAY7, STM1 |
| `app/Providers/AppServiceProvider.php` | Modify | Bootstrap / Providers | Registers explicit policy mapping `User` to `UserPolicy` and adds strict types. | STD1, LAY7, SCR3 |
| `bootstrap/app.php` | Modify | Bootstrap | Registers JSON exception renderer translating `ProtectedUserException` to 403 Forbidden. | STD1, LAY2, LAY6, ACT4 |
| `tests/Feature/Accounts/UserControllerTest.php` | New | Tests (Feature) | Feature tests verifying all five endpoints, authorization matrix, validation errors, and 404 behavior. | STD1, TST1, TST2, TST3, TST4, DBS1, DBS5, SCR3, ACT3 |

Total file count: 10 files (6 new, 4 modified).
Split trigger evaluation: Manifest equals the 10-file budget and introduces no migrations, no token code, and no edits to `User.php` or `ACC-U2` domain files.

---

### 5. Interface specs

#### 1. `App\Domain\Accounts\Data\UserResponseData`
- Namespace: `App\Domain\Accounts\Data`
- Class declaration: `final class UserResponseData extends \Spatie\LaravelData\Data`
- Constructor:
  ```php
  public function __construct(
      public string $id,
      public string $name,
      public string $email,
      public \App\Domain\Accounts\Enums\UserRole $role,
      public ?\Illuminate\Support\Carbon $created_at = null,
      public ?\Illuminate\Support\Carbon $updated_at = null,
  )
  ```
- Public method:
  ```php
  public static function fromModel(\App\Models\User $user): self
  {
      return new self(
          id: $user->public_id,
          name: $user->name,
          email: $user->email,
          role: $user->role,
          created_at: $user->created_at,
          updated_at: $user->updated_at,
      );
  }
  ```
- Thrown exceptions: None.
- Callers: `UserController::index`, `UserController::store`, `UserController::show`, `UserController::update`.
- Rules: Never exposes database integer `$user->id`, `$user->password`, `$user->remember_token`, or `$user->deleted_at`.

#### 2. `App\Domain\Accounts\Policies\UserPolicy`
- Namespace: `App\Domain\Accounts\Policies`
- Class declaration: `final class UserPolicy`
- Public methods:
  ```php
  public function viewAny(\App\Models\User $actor): bool
  {
      return $actor->role === \App\Domain\Accounts\Enums\UserRole::Superadmin;
  }

  public function view(\App\Models\User $actor, \App\Models\User $target): bool
  {
      return $actor->role === \App\Domain\Accounts\Enums\UserRole::Superadmin || $actor->is($target);
  }

  public function create(\App\Models\User $actor): bool
  {
      return $actor->role === \App\Domain\Accounts\Enums\UserRole::Superadmin;
  }

  public function update(\App\Models\User $actor, \App\Models\User $target): bool
  {
      return $actor->role === \App\Domain\Accounts\Enums\UserRole::Superadmin || $actor->is($target);
  }

  public function delete(\App\Models\User $actor, \App\Models\User $target): bool
  {
      return $actor->role === \App\Domain\Accounts\Enums\UserRole::Superadmin;
  }
  ```
- Thrown exceptions: None.
- Callers: Invoked via `$this->authorize()` in `UserController`.

#### 3. `App\Http\Requests\Users\StoreUserRequest`
- Namespace: `App\Http\Requests\Users`
- Class declaration: `final class StoreUserRequest extends \Illuminate\Foundation\Http\FormRequest`
- Public methods:
  ```php
  public function authorize(): bool
  {
      return true; // Authorization handled explicitly via UserPolicy in UserController
  }

  protected function prepareForValidation(): void
  {
      if ($this->has('email') && is_string($this->input('email'))) {
          $this->merge([
              'email' => strtolower(trim((string) $this->input('email'))),
          ]);
      }
  }

  public function rules(): array
  {
      return [
          'name' => ['required', 'string', 'max:255'],
          'email' => [
              'required',
              'string',
              'email',
              'max:255',
              \Illuminate\Validation\Rule::unique('users', 'email'),
          ],
          'password' => ['required', 'string', 'min:8'],
          'role' => ['required', \Illuminate\Validation\Rule::enum(\App\Domain\Accounts\Enums\UserRole::class)],
      ];
  }

  public function toDto(): \App\Domain\Accounts\Data\UserData
  {
      return new \App\Domain\Accounts\Data\UserData(
          name: (string) $this->validated('name'),
          email: (string) $this->validated('email'),
          password: (string) $this->validated('password'),
          role: \App\Domain\Accounts\Enums\UserRole::from((string) $this->validated('role')),
      );
  }
  ```
- Thrown exceptions: `Illuminate\Validation\ValidationException` on invalid input.
- Callers: Injected into `UserController::store`.

#### 4. `App\Http\Requests\Users\UpdateUserRequest`
- Namespace: `App\Http\Requests\Users`
- Class declaration: `final class UpdateUserRequest extends \Illuminate\Foundation\Http\FormRequest`
- Public methods:
  ```php
  public function authorize(): bool
  {
      return true; // Authorization handled explicitly via UserPolicy in UserController
  }

  protected function prepareForValidation(): void
  {
      if ($this->has('email') && is_string($this->input('email'))) {
          $this->merge([
              'email' => strtolower(trim((string) $this->input('email'))),
          ]);
      }
  }

  public function rules(): array
  {
      /** @var \App\Models\User|string|null $user */
      $user = $this->route('user');
      $userId = $user instanceof \App\Models\User ? $user->id : null;

      return [
          'name' => ['sometimes', 'required', 'string', 'max:255'],
          'email' => [
              'sometimes',
              'required',
              'string',
              'email',
              'max:255',
              \Illuminate\Validation\Rule::unique('users', 'email')->ignore($userId),
          ],
          'password' => ['sometimes', 'required', 'string', 'min:8'],
          'role' => ['sometimes', 'required', \Illuminate\Validation\Rule::enum(\App\Domain\Accounts\Enums\UserRole::class)],
      ];
  }

  public function toDto(): \App\Domain\Accounts\Data\UserData
  {
      $name = array_key_exists('name', $this->validated())
          ? (string) $this->validated('name')
          : new \Spatie\LaravelData\Optional();

      $email = array_key_exists('email', $this->validated())
          ? (string) $this->validated('email')
          : new \Spatie\LaravelData\Optional();

      $password = array_key_exists('password', $this->validated())
          ? (string) $this->validated('password')
          : new \Spatie\LaravelData\Optional();

      $role = array_key_exists('role', $this->validated())
          ? \App\Domain\Accounts\Enums\UserRole::from((string) $this->validated('role'))
          : new \Spatie\LaravelData\Optional();

      return new \App\Domain\Accounts\Data\UserData(
          name: $name,
          email: $email,
          password: $password,
          role: $role,
      );
  }
  ```
- Thrown exceptions: `Illuminate\Validation\ValidationException` on invalid input.
- Callers: Injected into `UserController::update`.

#### 5. `App\Http\Controllers\Api\V1\Users\UserController`
- Namespace: `App\Http\Controllers\Api\V1\Users`
- Class declaration: `final class UserController extends \App\Http\Controllers\Controller`
- Constructor:
  ```php
  public function __construct(
      private readonly \App\Domain\Accounts\Services\UserService $userService,
  ) {}
  ```
- Public methods:
  - `public function index(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse`:
    - Checks authorization: `$this->authorize('viewAny', \App\Models\User::class);`
    - Validates query parameters:
      ```php
      $validated = $request->validate([
          'search' => ['nullable', 'string', 'max:255'],
          'role' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Domain\Accounts\Enums\UserRole::class)],
          'include_trashed' => ['nullable', 'boolean'],
          'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
      ]);
      ```
    - Extracts typed query parameters:
      - `$search = isset($validated['search']) ? (string) $validated['search'] : null;`
      - `$role = isset($validated['role']) ? \App\Domain\Accounts\Enums\UserRole::from((string) $validated['role']) : null;`
      - `$includeTrashed = $request->boolean('include_trashed');`
      - `$perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;`
    - Constructs `UserFilterData(search: $search, role: $role, includeTrashed: $includeTrashed, perPage: $perPage)`.
    - Calls `$paginator = $this->userService->index($filter);`.
    - Transforms items: `$paginator->through(fn (\App\Models\User $user) => \App\Domain\Accounts\Data\UserResponseData::fromModel($user));`.
    - Returns `response()->json($paginator);`.
  - `public function store(\App\Http\Requests\Users\StoreUserRequest $request): \Illuminate\Http\JsonResponse`:
    - Checks authorization: `$this->authorize('create', \App\Models\User::class);`
    - Calls `$user = $this->userService->store($request->toDto());`
    - Returns `response()->json(\App\Domain\Accounts\Data\UserResponseData::fromModel($user), 201);`
  - `public function show(\App\Models\User $user): \Illuminate\Http\JsonResponse`:
    - Checks authorization: `$this->authorize('view', $user);`
    - Calls `$found = $this->userService->show($user->public_id);`
    - Returns `response()->json(\App\Domain\Accounts\Data\UserResponseData::fromModel($found));`
  - `public function update(\App\Http\Requests\Users\UpdateUserRequest $request, \App\Models\User $user): \Illuminate\Http\JsonResponse`:
    - Checks authorization: `$this->authorize('update', $user);`
    - Enforces non-superadmin role modification guard:
      ```php
      /** @var \App\Models\User $actor */
      $actor = $request->user('api');
      if ($actor->role !== \App\Domain\Accounts\Enums\UserRole::Superadmin && $request->has('role')) {
          abort(403, 'Non-superadmins cannot modify roles.');
      }
      ```
    - Calls `$updated = $this->userService->update($user, $request->toDto());`
    - Returns `response()->json(\App\Domain\Accounts\Data\UserResponseData::fromModel($updated));`
  - `public function destroy(\App\Models\User $user): \Illuminate\Http\Response`:
    - Checks authorization: `$this->authorize('delete', $user);`
    - Calls `$this->userService->destroy($user);`
    - Returns `response()->noContent();` (HTTP 204 No Content with empty body).

#### 6. `routes/api/users.php`
- Path: `routes/api/users.php`
- Code structure:
  ```php
  <?php

  declare(strict_types=1);

  use App\Http\Controllers\Api\V1\Users\UserController;
  use Illuminate\Support\Facades\Route;

  Route::prefix('users')->middleware('auth:api')->group(function (): void {
      Route::get('/', [UserController::class, 'index']);
      Route::post('/', [UserController::class, 'store']);
      Route::get('{user:public_id}', [UserController::class, 'show']);
      Route::match(['put', 'patch'], '{user:public_id}', [UserController::class, 'update']);
      Route::delete('{user:public_id}', [UserController::class, 'destroy']);
  });
  ```

#### 7. `routes/api.php`
- Path: `routes/api.php`
- Code structure:
  ```php
  <?php

  declare(strict_types=1);

  require __DIR__.'/api/tokens.php';
  require __DIR__.'/api/users.php';
  ```

#### 8. `app/Providers/AppServiceProvider.php`
- Add `declare(strict_types=1);` at file top.
- In `boot()`:
  ```php
  \Illuminate\Support\Facades\Gate::policy(\App\Models\User::class, \App\Domain\Accounts\Policies\UserPolicy::class);
  ```

#### 9. `bootstrap/app.php`
- Add `declare(strict_types=1);` at file top.
- In `withExceptions()`:
  ```php
  $exceptions->render(function (\App\Domain\Accounts\Exceptions\ProtectedUserException $e, \Illuminate\Http\Request $request) {
      if ($request->is('api/*') || $request->expectsJson()) {
          return response()->json([
              'message' => $e->getMessage(),
          ], 403);
      }
  });
  ```

---

### 6. Data and migrations

None. This step does not add, alter, or roll back database migrations. The database schema established in `ACC-U1` (`users` table with `public_id`, `role`, `deleted_at`) is unchanged.

Backward-compatibility statement: All database tables and columns remain strictly backward compatible.

---

### 7. Behavior and flows

#### Use Case 1: List Users (`GET /api/v1/users`)
- **Happy Path:**
  1. Authenticated `Superadmin` issues `GET /api/v1/users` with optional query parameters (`search`, `role`, `include_trashed`, `per_page`).
  2. Request reaches `UserController::index`.
  3. `UserController` executes `$this->authorize('viewAny', User::class)` -> `UserPolicy::viewAny` returns `true`.
  4. Query parameters are validated. `UserFilterData` is instantiated.
  5. `UserService::index` bounds `perPage` and normalises `search`.
  6. `UserRepository::index` executes paginated query ordered by `created_at DESC, id DESC`.
  7. Items are mapped to `UserResponseData`.
  8. Returns HTTP 200 with paginated JSON envelope containing `data`, `current_page`, `per_page`, `total`, `last_page`.
- **Failure Paths:**
  - Unauthenticated request: returns HTTP 401 Unauthorized `{"message": "Unauthenticated."}`.
  - Invalid query parameter (for example `role=guest`, `include_trashed=invalid`): returns HTTP 422 Unprocessable Content.
- **Authorization Outcome:**
  - `Retailer` caller: `UserPolicy::viewAny` returns `false` -> HTTP 403 Forbidden.
  - `Customer` caller: `UserPolicy::viewAny` returns `false` -> HTTP 403 Forbidden.
- **Activity log / Concurrency:** No mutations performed.

#### Use Case 2: Create User (`POST /api/v1/users`)
- **Happy Path:**
  1. Authenticated `Superadmin` posts JSON with `name`, `email`, `password`, `role` (`customer` or `retailer`).
  2. `StoreUserRequest` executes `prepareForValidation()` -> trims and lowercases `email`.
  3. Validation passes. Unique check on `users.email` succeeds.
  4. `UserController::store` executes `$this->authorize('create', User::class)` -> returns `true`.
  5. `StoreUserRequest::toDto()` returns `UserData`.
  6. `UserService::store` validates role is not `Superadmin`. Delegates to `UserRepository::store`.
  7. `UserRepository::store` mints ULID `public_id` and persists `User`. Model cast hashes `password`.
  8. Returns HTTP 201 Created with `UserResponseData` JSON.
- **Failure Paths:**
  - Unauthenticated request: returns HTTP 401 Unauthorized.
  - Missing field or invalid format (for example `password` < 8 chars, invalid `email`): returns HTTP 422 Unprocessable Content.
  - Duplicate email (exact, case variation, whitespace variation, or soft-deleted user email): caught by FormRequest unique rule -> returns HTTP 422 Unprocessable Content with validation errors on `email`.
  - Non-existent role string (for example `"admin"`): caught by FormRequest `Rule::enum` -> returns HTTP 422 Unprocessable Content.
  - Attempt to supply `role: "superadmin"`: passes validation, reaches `UserService::store`, which throws `ProtectedUserException::cannotCreateSuperadmin()`. `bootstrap/app.php` renders HTTP 403 Forbidden `{"message": "Cannot create a user with the superadmin role."}`. Database is untouched.
- **Authorization Outcome:**
  - `Retailer` or `Customer` caller: `UserPolicy::create` returns `false` -> HTTP 403 Forbidden.
- **Activity log / Concurrency:** User creation is unique on `email` and `public_id`.

#### Use Case 3: Show User (`GET /api/v1/users/{user:public_id}`)
- **Happy Path:**
  1. Authenticated caller issues `GET /api/v1/users/{public_id}`.
  2. Router resolves `{user:public_id}` using `User::where('public_id', $publicId)->firstOrFail()`.
  3. `UserController::show` executes `$this->authorize('view', $user)`.
  4. Authorization evaluates: `true` if actor is `Superadmin` or if `$actor->is($user)`.
  5. Calls `UserService::show($user->public_id)`.
  6. Returns HTTP 200 OK with `UserResponseData`.
- **Failure Paths:**
  - Unauthenticated request: returns HTTP 401 Unauthorized.
  - Request with integer database ID (for example `GET /api/v1/users/1`): route model binding does not find row with `public_id = '1'` -> returns HTTP 404 Not Found.
  - Non-existent ULID: returns HTTP 404 Not Found.
  - Soft-deleted user ULID: default `SoftDeletes` scope excludes soft-deleted row -> returns HTTP 404 Not Found.
- **Authorization Outcome:**
  - `Customer` or `Retailer` accessing their own `public_id`: HTTP 200 OK.
  - `Customer` or `Retailer` accessing another user's `public_id`: HTTP 403 Forbidden.
- **Activity log / Concurrency:** No mutations performed.

#### Use Case 4: Update User (`PATCH/PUT /api/v1/users/{user:public_id}`)
- **Happy Path:**
  1. Authenticated caller issues `PATCH /api/v1/users/{public_id}` with subset of fields (`name`, `email`, `password`, `role`).
  2. Route model binding resolves active `User`.
  3. `UserController::update` executes `$this->authorize('update', $user)`.
  4. Authorization evaluates: `true` if actor is `Superadmin` or if `$actor->is($user)`.
  5. Controller verifies role modification rule: if caller is not `Superadmin` and `$request->has('role')`, controller aborts with HTTP 403 Forbidden.
  6. `UpdateUserRequest` executes: normalises `email` when provided, validates inputs with `Rule::unique('users', 'email')->ignore($user->id)`.
  7. `UpdateUserRequest::toDto()` constructs `UserData` with `Optional` for absent fields.
  8. `UserService::update` verifies superadmin protections (cannot promote to superadmin; cannot change superadmin role).
  9. `UserRepository::update` persists provided attributes. Untouched fields are preserved.
  10. Returns HTTP 200 OK with updated `UserResponseData`.
- **Failure Paths:**
  - Unauthenticated request: returns HTTP 401 Unauthorized.
  - Target user is soft-deleted or non-existent: returns HTTP 404 Not Found.
  - Request uses integer database ID: returns HTTP 404 Not Found.
  - Validation failures (invalid email format, password < 8 chars): returns HTTP 422 Unprocessable Content.
  - Duplicate email belonging to another user: returns HTTP 422 Unprocessable Content.
  - Superadmin promotion or role change on superadmin: throws `ProtectedUserException` -> rendered as HTTP 403 Forbidden.
- **Authorization Outcome:**
  - Non-superadmin updating another user: HTTP 403 Forbidden.
  - Non-superadmin updating self without `role`: HTTP 200 OK.
  - Non-superadmin updating self with `role`: HTTP 403 Forbidden.
- **Activity log / Concurrency:** Row update executes on target user id.

#### Use Case 5: Delete User (`DELETE /api/v1/users/{user:public_id}`)
- **Happy Path:**
  1. Authenticated `Superadmin` issues `DELETE /api/v1/users/{public_id}`.
  2. Route model binding resolves active `User`.
  3. `UserController::destroy` executes `$this->authorize('delete', $user)`.
  4. `UserPolicy::delete` returns `true`.
  5. `UserService::destroy` verifies `$user->role !== UserRole::Superadmin`.
  6. `UserRepository::destroy` executes `$user->delete()`. `deleted_at` timestamp is set.
  7. Returns HTTP 204 No Content with empty body.
  8. Subsequent `GET /api/v1/users/{public_id}` returns HTTP 404 Not Found.
- **Failure Paths:**
  - Unauthenticated request: returns HTTP 401 Unauthorized.
  - Target user is already soft-deleted or does not exist: returns HTTP 404 Not Found.
  - Attempting to delete a `Superadmin` user: `UserService::destroy` throws `ProtectedUserException::cannotDeleteSuperadmin()`. Rendered as HTTP 403 Forbidden `{"message": "Cannot delete a user with the superadmin role."}`. Database is untouched.
- **Authorization Outcome:**
  - `Retailer` or `Customer` caller: `UserPolicy::delete` returns `false` -> HTTP 403 Forbidden.
- **Activity log / Concurrency:** Soft delete updates `deleted_at`.

---

### 8. Rule compliance matrix

| ID | Applies (Y/N) | How satisfied, or why N/A |
|---|---|---|
| STM1 | Y | Touches only users-stream paths and named touch points (`routes/api.php`, `AppServiceProvider.php`, `bootstrap/app.php`). No token-stream files modified. |
| STM2 | Y | N/A. `TokenIssuer` and `TokenPairData` are not modified in this step. |
| STM3 | Y | Users-stream HTTP classes do not import `Laravel\Passport`. Authentication in tests uses `actingAs($user, 'api')`. |
| STM4 | Y | N/A. Imported token code is untouched. |
| LAY1 | Y | Flow follows `StoreUserRequest` / `UpdateUserRequest` -> `UserController` -> `UserService` -> `UserRepositoryInterface` -> `UserRepository`. |
| LAY2 | Y | `UserController` authorizes via `UserPolicy`, calls `UserService`, and returns `UserResponseData`. Does not touch Eloquent or repositories. |
| LAY3 | Y | `UserService` contains business rules and depends on `UserRepositoryInterface`. Uses zero `Illuminate\Http` classes. |
| LAY4 | Y | `UserRepository` contains all query code. Does not contain HTTP exceptions or business logic. |
| LAY5 | Y | `UserController`, `UserService`, and `UserRepository` share method parity: `index`, `store`, `show`, `update`, `destroy`. |
| LAY6 | Y | `App\Domain` imports zero classes from `App\Http` or `Illuminate\Http`. Exception rendering is wired in `bootstrap/app.php`. |
| LAY7 | Y | All classes follow paths in `ARCHITECTURE.md` §5. No new top-level folders created. |
| LAY8 | Y | Controller methods return `UserResponseData` and HTTP status responses. No Eloquent models returned to HTTP client. |
| STD1 | Y | `declare(strict_types=1);` included as first statement in every new and modified PHP file. |
| STD2 | Y | All parameters, properties, and return types explicitly typed. All new classes declared `final`. |
| STD3 | Y | `UserRole` enum cases used for roles. `Rule::enum(UserRole::class)` used in FormRequests. `UserResponseData` extends `Data`. |
| STD4 | Y | Single `UserData` DTO serves both create and update with `Optional` properties. Create versus update rules reside in `StoreUserRequest` and `UpdateUserRequest`. |
| STD5 | Y | `UpdateUserRequest::toDto()` constructs `UserData` passing `Optional` for omitted fields. Unspecified fields remain untouched. |
| STD6 | Y | Code will pass `composer lint` (Pint) and `composer stan` (Larastan level 8) with zero baseline entries. |
| STD7 | Y | Code contains explanatory architectural comments where rationale is needed. No commented-out code. |
| ACT1 | Y | N/A in this step. Public customer self-registration belongs to `ACC-U4`. |
| ACT2 | Y | Retailers are created by superadmin via `POST /api/v1/users` through `StoreUserRequest` and `UserService::store`. |
| ACT3 | Y | Authorization enforced server-side via `UserPolicy`: `viewAny`, `create`, `delete` are superadmin-only; `view` and `update` allow self-access for retailer/customer without role changes. |
| ACT4 | Y | API prevents creation or promotion of superadmin: `StoreUserRequest` and `UpdateUserRequest` route to `UserService` which throws `ProtectedUserException`, returning 403 Forbidden. |
| ACT5 | Y | N/A in this step. Login endpoint belongs to `ACC-U4`. |
| ACT6 | Y | Password hashing performed exclusively by `User` model `'password' => 'hashed'` cast. No call to `Hash::make`. |
| ACT7 | Y | N/A in this step. Login credential validation belongs to `ACC-U4`. |
| ACT8 | Y | N/A in this step. Token revocation on user deletion, password change, and role change is deferred to `ACC-U5`. |
| ACT9 | Y | N/A in this step. Token issuance belongs to `ACC-U4`. |
| ACT10 | Y | API responses return `UserResponseData`. Raw internal incremental `id` is never exposed. |
| ACT11 | Y | Passwords and tokens are never logged or returned in responses. |
| TOK1 | Y | N/A. `TokenIssuer` implementation is not modified. |
| TOK2 | Y | N/A. Grant, refresh delivery, lifetimes, and scopes are untouched. |
| TOK3 | Y | N/A. Passport package is untouched. |
| TOK4 | Y | N/A. Token lifetimes remain in `config/passport.php`. |
| TOK5 | Y | N/A. Refresh token rotation belongs to tokens stream. |
| TOK6 | Y | N/A. Token refresh and logout belong to tokens stream. |
| DBS1 | Y | All tests run against PostgreSQL test database. SQLite is not used. |
| DBS2 | Y | Merged migrations are untouched. No new migrations introduced in this step. |
| DBS3 | Y | No destructive database schema modifications introduced. |
| DBS4 | Y | Database constraints (`role` CHECK constraint, unique indexes) respected. |
| DBS5 | Y | URLs and payloads use `public_id` (ULID). Integer primary key is never exposed or bound in routes. |
| DBS6 | Y | N/A. No seeders executed or modified. |
| SCR1 | Y | No secrets, credentials, or `.env` files committed. |
| SCR2 | Y | Policies, authorization checks, and strict types are fully enforced. |
| SCR3 | Y | Authorization is strictly evaluated server-side via `UserPolicy` and controller authorization checks. |
| SCR4 | Y | N/A in this step. Throttling for `register`, `login`, and `refresh` belongs to `ACC-U4` and tokens stream. |
| TST1 | Y | Tests written in Pest in `tests/Feature/Accounts/UserControllerTest.php`. |
| TST2 | Y | Covers all 401 unauthenticated, 403 forbidden, 404 not found, and 422 validation failure cases. |
| TST3 | Y | Users-stream tests use database factories and `actingAs($user, 'api')` authentication. |
| TST4 | Y | Tests use `UserFactory` states (`superadmin()`, `retailer()`, `customer()`). No raw hand-built database rows. |
| TST5 | Y | No skipped, todo, or incomplete tests. Existing tests remain green. |
| GIT1 | Y | Branch will be `feat/users-http-layer`. |
| GIT2 | Y | Conventional Commits with `feat(users):` and `docs(users):` stream scopes. |
| GIT3 | Y | Never push directly to main. |
| GIT4 | Y | N/A. This is a users-stream step, not a contract change. |
| GIT5 | Y | PR description will document changes, rationale, testing evidence, and risk analysis. |
| GIT6 | Y | Delivery order follows `ARCHITECTURE.md` §10: `ACC-U3` follows accepted `ACC-U2`. |
| ENV1 | Y | Windows PowerShell commands executed one at a time without chaining. |
| ENV2 | Y | Native PHP, Composer, and PostgreSQL used. No Docker, Sail, or containers. |
| ENV3 | Y | Non-interactive execution flags used. No background servers left running. |
| ENV4 | Y | `composer check` (Pint, PHPStan, Pest) must pass cleanly before closeout. |
| DNT1 | Y | No new packages or dependencies added to `composer.json`. |
| DNT2 | Y | No deployment or infrastructure changes introduced. |
| DNT3 | Y | No out-of-scope features built (Filament UI, email verification, password reset, 2FA). |
| DNT4 | Y | No business logic in controllers or models. Controllers are thin. |
| DNT5 | Y | No destructive database or file operations performed. |
| DNT6 | Y | No keys, tokens, or environment files copied into project. |
| DNT7 | Y | Section 11 specifies documentation updates for `ARCHITECTURE.md`. |
| WFL1 | Y | Implementation proceeds only from this approved plan version. |
| WFL2 | Y | Any deviation will trigger a formal Change Request. |
| WFL3 | Y | Implementation will conclude with a raw Implementation Report. |
| WFL4 | Y | Agent will not mark step done. |
| WFL5 | Y | Plan written and submitted for review before code implementation. |
| WFL6 | Y | Scope restricted strictly to Users HTTP layer (`ACC-U3`). |
| WFL7 | Y | Reconciles `ARCHITECTURE.md` and brief via OD-1 through OD-4 without guessing. |

---

### 9. AC traceability

| AC | Files | Test Names in `tests/Feature/Accounts/UserControllerTest.php` | How Verified |
|---|---|---|---|
| AC-1 | `app/Domain/Accounts/Data/UserResponseData.php` | `it('serializes user model to user response data without internal id or password')` | Unit/Feature assertion verifying JSON keys contain `id`, `name`, `email`, `role`, `created_at`, `updated_at` and do not contain integer `id`, `password`, `remember_token`, `deleted_at`. |
| AC-2 | `routes/api/users.php`, `app/Http/Controllers/Api/V1/Users/UserController.php` | `it('returns 401 unauthorized on unauthenticated requests across all user endpoints')` | Requests to `GET /api/v1/users`, `POST /api/v1/users`, `GET /api/v1/users/{id}`, `PATCH /api/v1/users/{id}`, `DELETE /api/v1/users/{id}` without token assert 401 JSON response even without `Accept: application/json`. |
| AC-3 | `app/Domain/Accounts/Policies/UserPolicy.php`, `app/Providers/AppServiceProvider.php`, `UserController.php` | `it('enforces authorization matrix forbidding customers and retailers from admin actions')`, `it('allows customers and retailers to view and update their own record without role changes')`, `it('forbids customers and retailers from changing role on update')` | Asserts 403 when non-superadmin calls `index`, `store`, `destroy`, or accesses another user; asserts 200 when accessing own record; asserts 403 when updating self with `role`. |
| AC-4 | `UserController.php`, `app/Domain/Accounts/Data/UserFilterData.php` | `it('returns paginated users list ordered by created_at desc with filtering for superadmin')`, `it('returns 422 on invalid query parameters on index')` | Superadmin calls `GET /api/v1/users` with `search`, `role`, `include_trashed`, `per_page`; asserts deterministic pagination envelope; asserts 422 when passing invalid `role` or `include_trashed`. |
| AC-5 | `app/Http/Requests/Users/StoreUserRequest.php`, `UserController.php` | `it('creates a customer or retailer user via post users endpoint')`, `it('validates store user input rejecting missing fields, malformed emails, and invalid roles')`, `it('returns 422 when storing user with duplicate email including case and whitespace variants')`, `it('returns 422 when storing user with email belonging to soft-deleted user')` | Superadmin calls `POST /api/v1/users`; asserts 201 with `UserResponseData`; asserts 422 on missing fields, short passwords, invalid role string, and case/whitespace duplicate emails. |
| AC-6 | `routes/api/users.php`, `UserController.php` | `it('returns user by public_id and returns 404 on integer id, unknown ulid, or soft-deleted user')` | Calls `GET /api/v1/users/{public_id}` asserting 200; calls `GET /api/v1/users/{user->id}` asserting 404; calls unknown ULID asserting 404; calls soft-deleted user ULID asserting 404. |
| AC-7 | `app/Http/Requests/Users/UpdateUserRequest.php`, `UserController.php` | `it('partially updates user fields preserving untouched attributes')`, `it('allows updating user while retaining own email including casing variants')`, `it('returns 422 when updating email to an existing email of another user')` | Calls `PATCH /api/v1/users/{public_id}` with `name`; asserts other fields preserved; calls with existing email asserting 200; calls with other user's email asserting 422. |
| AC-8 | `routes/api/users.php`, `UserController.php` | `it('soft deletes user and returns 204 no content')` | Superadmin calls `DELETE /api/v1/users/{public_id}`; asserts 204 with empty body; asserts `deleted_at` populated in database; subsequent `GET` asserts 404. |
| AC-9 | `bootstrap/app.php`, `UserController.php` | `it('rejects superadmin creation, promotion, demotion, and deletion with 403 forbidden')`, `it('allows updating superadmin name and email without role change')` | Calls `POST /users` with `role: superadmin` -> 403; calls `PATCH` promoting user -> 403; calls `PATCH` changing superadmin role -> 403; calls `DELETE` on superadmin -> 403; calls `PATCH` updating superadmin name -> 200. |
| AC-10 | `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md`, `ARCHITECTURE.md` | `it('verifies all tests pass and arch rules remain clean')` | Runs `composer check` (Pint, PHPStan, Pest); asserts `tests/Arch/AccountsArchTest.php` and `tests/Feature/Tokens/` pass cleanly. |

---

### 10. Test plan

#### Test File: `tests/Feature/Accounts/UserControllerTest.php`

1. `it('serializes user model to user response data without internal id or password')`
   - Arrange: Create a `User` via factory.
   - Act: Call `UserResponseData::fromModel($user)->toArray()`.
   - Assert: Array has keys `id`, `name`, `email`, `role`, `created_at`, `updated_at`. `id` equals `$user->public_id`. Array lacks keys `password`, `remember_token`, and integer primary key.
2. `it('returns 401 unauthorized on unauthenticated requests across all user endpoints')`
   - Arrange: Create target customer user via factory.
   - Act & Assert:
     - `get('/api/v1/users')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.'])`
     - `post('/api/v1/users', [])->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.'])`
     - `get("/api/v1/users/{$target->public_id}")->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.'])`
     - `patch("/api/v1/users/{$target->public_id}", [])->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.'])`
     - `delete("/api/v1/users/{$target->public_id}")->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.'])`
3. `it('enforces authorization matrix forbidding customers and retailers from admin actions')`
   - Arrange: Create `$admin` (superadmin), `$retailer` (retailer), `$customer` (customer), `$other` (customer).
   - Act & Assert:
     - As `$customer`: `getJson('/api/v1/users')->assertForbidden()`
     - As `$retailer`: `getJson('/api/v1/users')->assertForbidden()`
     - As `$customer`: `postJson('/api/v1/users', [...])->assertForbidden()`
     - As `$retailer`: `postJson('/api/v1/users', [...])->assertForbidden()`
     - As `$customer`: `getJson("/api/v1/users/{$other->public_id}")->assertForbidden()`
     - As `$retailer`: `getJson("/api/v1/users/{$other->public_id}")->assertForbidden()`
     - As `$customer`: `patchJson("/api/v1/users/{$other->public_id}", ['name' => 'Hacked'])->assertForbidden()`
     - As `$customer`: `deleteJson("/api/v1/users/{$other->public_id}")->assertForbidden()`
     - As `$retailer`: `deleteJson("/api/v1/users/{$other->public_id}")->assertForbidden()`
4. `it('allows customers and retailers to view and update their own record without role changes')`
   - Arrange: Create `$customer` via factory.
   - Act & Assert:
     - `actingAs($customer, 'api')->getJson("/api/v1/users/{$customer->public_id}")->assertOk()->assertJsonPath('data.id', $customer->public_id)`
     - `actingAs($customer, 'api')->patchJson("/api/v1/users/{$customer->public_id}", ['name' => 'New Name'])->assertOk()->assertJsonPath('data.name', 'New Name')`
5. `it('forbids customers and retailers from changing role on update')`
   - Arrange: Create `$customer` via factory.
   - Act: `actingAs($customer, 'api')->patchJson("/api/v1/users/{$customer->public_id}", ['role' => 'retailer'])`
   - Assert: Response status is 403 Forbidden with message `'Non-superadmins cannot modify roles.'`. Database role remains `customer`.
6. `it('returns paginated users list ordered by created_at desc with filtering for superadmin')`
   - Arrange: Create superadmin; create 3 users with names `'Alpha'`, `'Beta'`, `'Gamma'`.
   - Act: Superadmin calls `GET /api/v1/users?search=alp&per_page=10`.
   - Assert: Response status 200 OK. `data` contains 1 item matching `'Alpha'`. Response contains pagination metadata `current_page`, `per_page`, `total`.
7. `it('returns 422 on invalid query parameters on index')`
   - Arrange: Create superadmin.
   - Act: Superadmin calls `GET /api/v1/users?role=invalid_role&include_trashed=not_bool&per_page=999`.
   - Assert: Response status 422 Unprocessable Content. Validation error keys include `role`, `include_trashed`, `per_page`.
8. `it('creates a customer or retailer user via post users endpoint')`
   - Arrange: Create superadmin.
   - Act: Superadmin calls `POST /api/v1/users` with `['name' => 'Alice', 'email' => 'alice@test.com', 'password' => 'password123', 'role' => 'retailer']`.
   - Assert: Response status 201 Created. Response JSON has `data.id` (ULID), `data.role` `'retailer'`, `data.email` `'alice@test.com'`. Database row exists with hashed password.
9. `it('validates store user input rejecting missing fields, malformed emails, and invalid roles')`
   - Arrange: Create superadmin.
   - Act: Superadmin calls `POST /api/v1/users` with `['name' => '', 'email' => 'bad-email', 'password' => 'short', 'role' => 'unknown']`.
   - Assert: Response status 422 Unprocessable Content with validation errors for `name`, `email`, `password`, `role`.
10. `it('returns 422 when storing user with duplicate email including case and whitespace variants')`
    - Arrange: Create user with email `'existing@test.com'`. Create superadmin.
    - Act: Superadmin calls `POST /api/v1/users` with `['name' => 'Dup', 'email' => '  EXISTING@TEST.COM  ', 'password' => 'password123', 'role' => 'customer']`.
    - Assert: Response status 422 Unprocessable Content with validation error on `email`. No 500 error thrown.
11. `it('returns 422 when storing user with email belonging to soft-deleted user')`
    - Arrange: Create user with email `'deleted@test.com'`; soft-delete that user. Create superadmin.
    - Act: Superadmin calls `POST /api/v1/users` with `['name' => 'Reborn', 'email' => 'deleted@test.com', 'password' => 'password123', 'role' => 'customer']`.
    - Assert: Response status 422 Unprocessable Content on `email`.
12. `it('returns user by public_id and returns 404 on integer id, unknown ulid, or soft-deleted user')`
    - Arrange: Create superadmin and target user. Soft-delete a third user.
    - Act & Assert:
      - `actingAs($admin, 'api')->getJson("/api/v1/users/{$target->public_id}")->assertOk()`
      - `actingAs($admin, 'api')->getJson("/api/v1/users/{$target->id}")->assertNotFound()`
      - `actingAs($admin, 'api')->getJson('/api/v1/users/01JNONEXISTENTULID000000000')->assertNotFound()`
      - `actingAs($admin, 'api')->getJson("/api/v1/users/{$deletedUser->public_id}")->assertNotFound()`
13. `it('partially updates user fields preserving untouched attributes')`
    - Arrange: Create superadmin and target customer with known name and email.
    - Act: Superadmin calls `PATCH /api/v1/users/{$target->public_id}` with `['name' => 'Updated Name']`.
    - Assert: Response 200 OK. `data.name` is `'Updated Name'`. `data.email` remains unchanged.
14. `it('allows updating user while retaining own email including casing variants')`
    - Arrange: Create superadmin and target customer with email `'target@test.com'`.
    - Act: Superadmin calls `PATCH /api/v1/users/{$target->public_id}` with `['name' => 'New Name', 'email' => '  TARGET@TEST.COM  ']`.
    - Assert: Response 200 OK. No duplicate email validation error.
15. `it('returns 422 when updating email to an existing email of another user')`
    - Arrange: Create `$user1` (`user1@test.com`), `$user2` (`user2@test.com`), and superadmin.
    - Act: Superadmin calls `PATCH /api/v1/users/{$user1->public_id}` with `['email' => 'user2@test.com']`.
    - Assert: Response 422 Unprocessable Content on `email`.
16. `it('soft deletes user and returns 204 no content')`
    - Arrange: Create superadmin and target user.
    - Act: Superadmin calls `DELETE /api/v1/users/{$target->public_id}`.
    - Assert: Response 204 No Content with empty body. `$target->fresh()->trashed()` is true. Subsequent `GET /api/v1/users/{$target->public_id}` returns 404 Not Found.
17. `it('rejects superadmin creation, promotion, demotion, and deletion with 403 forbidden')`
    - Arrange: Create superadmin and target user.
    - Act & Assert:
      - `POST /api/v1/users` with `role: 'superadmin'` -> returns 403 Forbidden `{"message": "Cannot create a user with the superadmin role."}`.
      - `PATCH /api/v1/users/{$target->public_id}` with `role: 'superadmin'` -> returns 403 Forbidden `{"message": "Cannot promote a user to the superadmin role."}`.
      - `PATCH /api/v1/users/{$admin->public_id}` with `role: 'retailer'` -> returns 403 Forbidden `{"message": "Cannot change the role of a user with the superadmin role."}`.
      - `DELETE /api/v1/users/{$admin->public_id}` -> returns 403 Forbidden `{"message": "Cannot delete a user with the superadmin role."}`.
18. `it('allows updating superadmin name and email without role change')`
    - Arrange: Create superadmin `$admin`.
    - Act: Call `PATCH /api/v1/users/{$admin->public_id}` with `['name' => 'Chief Admin']`.
    - Assert: Response 200 OK. Database `name` is `'Chief Admin'`. Role remains `superadmin`.

#### Arch Tests:
`tests/Arch/AccountsArchTest.php` must pass without modifications. The test verifies:
- `App\Domain` does not use `App\Http`.
- `App\Http\Controllers\Api` does not use `App\Domain\Accounts\Repositories` or `DB` facade.
- Strict types enforced on `App\Domain`, `App\Http\Controllers\Api`, and `App\Models`.

---

### 11. Docs and contract updates

#### 1. `ARCHITECTURE.md` §10 (Delivery order and the token import)
Update lines 247-248 in `ARCHITECTURE.md` at closeout:
Exact Before text:
```markdown
- [x] `ACC-U2` Repository interface and repository, `UserService`, `UserData`, `UserFilterData`, protections.
- [ ] `ACC-U3` `UserResponseData`, `UserPolicy`, `UserController`, form requests, `/users` routes.
```
Exact After text:
```markdown
- [x] `ACC-U2` Repository interface and repository, `UserService`, `UserData`, `UserFilterData`, protections.
- [x] `ACC-U3` `UserResponseData`, `UserPolicy`, `UserController`, form requests, `/users` routes.
```

#### 2. ADRs and `.env.example`
- Soft-delete ADR: Already recorded as `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md` in `ACC-U1`. No new ADR required for this step.
- `.env.example`: No new environment variables required.

---

### 12. Implementation order

#### Commit 1: `feat(users): add user response data and authorization policy`
- Files:
  - `app/Domain/Accounts/Data/UserResponseData.php`
  - `app/Domain/Accounts/Policies/UserPolicy.php`
  - `app/Providers/AppServiceProvider.php`
- State: `declare(strict_types=1);` in all files; `Gate::policy(User::class, UserPolicy::class)` registered; Pint and PHPStan green.

#### Commit 2: `feat(users): add user form requests, controller, and routes`
- Files:
  - `app/Http/Requests/Users/StoreUserRequest.php`
  - `app/Http/Requests/Users/UpdateUserRequest.php`
  - `app/Http/Controllers/Api/V1/Users/UserController.php`
  - `routes/api/users.php`
  - `routes/api.php`
  - `bootstrap/app.php`
- State: Routes registered under `api/v1/users`; `ProtectedUserException` mapped to 403 Forbidden in `bootstrap/app.php`; `php artisan route:list --path=api/v1/users` displays all five endpoints; Pint and PHPStan green.

#### Commit 3: `test(users): add user controller endpoint and authorization feature tests`
- Files:
  - `tests/Feature/Accounts/UserControllerTest.php`
- State: 18 feature test cases covering AC-1 through AC-10; `composer check` passes with 100% green tests (Pint, PHPStan 0 errors, Pest 95+ passing tests including token and arch tests).

---

### 13. Risks, assumptions, conflicts

#### Assumptions & Resolutions:
1. **Reconciliation of `ARCHITECTURE.md` §3/§7 and OD-1:**
   - Factual state: `ARCHITECTURE.md` §3 (Rule 3) and §7 table list `/users` endpoints for `superadmin` only, noting in §11 OQ7 that self-service profile endpoints (`/me`) are a later phase decision.
   - Resolution: Follow Achintha's explicit G0 decision on OD-1 and AC-3: `viewAny`, `create`, and `delete` are strictly superadmin-only; `view` and `update` allow self-access for `Retailer` and `Customer` (`$actor->is($target)`), but non-superadmins are strictly forbidden (403) from changing their role or supplying a `role` field.
2. **Token Revocation Deferral (`ACC-U5`):**
   - After `ACC-U3`, calling `DELETE /api/v1/users/{user}` or updating a user's password/role over HTTP will mutate the user record but will not revoke active Passport tokens. This is an accepted temporary gap until `ACC-U5`, where `TokenIssuer::revokeAll` is wired to user mutation lifecycle events. Neither `UserController` nor `UserService` imports `TokenIssuer` in this step.
3. **Route Model Binding and ULID Format:**
   - `{user:public_id}` route binding relies on Laravel Eloquent implicit binding on column `public_id`. A request with an integer ID (for example `GET /api/v1/users/1`) fails to find any row with `public_id = '1'` and triggers `ModelNotFoundException`, resulting in a clean 404 Not Found response as required by `DBS5`.
4. **ADR 0003 Status:**
   - The brief noted writing `docs/adr/0003-user-soft-deletes.md` if not already created in `ACC-U1`. Verified in Section 3 that `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md` already exists and is accepted. No duplicate ADR file will be created.

---

### 14. Dependencies

New packages: None. All functionality is implemented using existing dependencies (`laravel/framework`, `spatie/laravel-data`, `laravel/passport`).
