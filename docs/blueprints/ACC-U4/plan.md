# [ACC-U4 | PLAN v1 | Gemini]

### 1. Understanding
Implement the Accounts-stream public authentication endpoints: `POST /api/v1/auth/register` (guest endpoint, creating a `Customer` only) and `POST /api/v1/auth/login` (guest endpoint allowing `Customer`, `Retailer`, and `Superadmin` to authenticate). The implementation introduces `AuthService`, `LoginData` input DTO, `AuthResultData` output DTO, `InvalidCredentialsException` domain exception, `LoginRequest`, `RegisterRequest`, `AuthController`, `routes/api/auth.php`, `findByEmail` on `UserRepositoryInterface` and `UserRepository`, named rate limiters for login and register in `AppServiceProvider`, exception translation in `bootstrap/app.php`, route inclusion in `routes/api.php` (CR-1), and feature tests in `tests/Feature/Accounts/AuthEndpointsTest.php`. Tokens are minted strictly through the `TokenIssuer` contract delivered by `ACC-T0`.

Out of scope:
- Logout, refresh rotation endpoint, `/auth/me`, and token revocation (`TokenIssuer::revokeAll` on user delete, password change, or role change), which belong to `ACC-U5` or the Tokens stream.
- Email verification, password reset workflows, two-factor authentication, and social authentication (permanently out of scope per `ARCHITECTURE.md` Section 1).
- Password rehash on login (`needsRehash`), account lockout beyond HTTP rate limiting, and login audit logs.
- Modifications to database migrations, model definitions (`app/Models/User.php`), enum definitions (`UserRole`), seeders, or factories (`DBS2`).
- Modifications to `UserData`, `UserFilterData`, `UserResponseData`, `UserService`, `UserPolicy`, `ProtectedUserException`, or existing tests in `tests/Feature/Accounts/UserControllerTest.php`.
- Modifications to Tokens-stream files (`app/Domain/Accounts/Tokens/*`, `app/Http/Controllers/Api/V1/Auth/TokenController.php`, `app/Providers/TokenServiceProvider.php`, `config/passport.php`, `routes/api/tokens.php`).

---

### 2. Sources read

| File or Document Section | What in it constrains this step |
|---|---|
| [AGENTS.md](file:///c:/dev/getnow-auth2/AGENTS.md) | Layering flow (FormRequest -> Controller -> Service -> RepositoryInterface); controllers thin; services contain business rules only; no `Illuminate\Http` in Domain; `declare(strict_types=1);` in every file; strict type hints and final classes; role string literals forbidden; no `Hash::make` in application code; public registration creates Customer only; login returns uniform error for unknown email and wrong password; `AuthService` calls `TokenIssuer::issue` once per login or register; responses return `UserResponseData` and token pair; never expose internal integer `id`; rate-limit login and register. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L45-L72) §4 | Layering definitions: FormRequest validates and normalizes, Controller delegates to Service, Service orchestrates domain logic and depends on `UserRepositoryInterface` and `TokenIssuer`. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L73-L139) §5 | Namespace map: `App\Domain\Accounts\Services\AuthService`, `App\Domain\Accounts\Data\LoginData`, `App\Domain\Accounts\Data\AuthResultData`, `App\Domain\Accounts\Exceptions\InvalidCredentialsException`, `App\Http\Controllers\Api\V1\Auth\AuthController`, `App\Http\Requests\Auth\LoginRequest`, `App\Http\Requests\Auth\RegisterRequest`, `routes/api/auth.php`. Touch point rule for `routes/api.php`: one require line per stream. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L140-L176) §6 | `TokenIssuer` contract interface and `TokenPairData` class definition. Rules: users stream codes against interface; tokens stream never reads credentials; `AuthService` calls `issue()` exactly once per login or register. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L177-L201) §7 | Endpoint definitions: base path `/api/v1`; `POST /auth/register` (none, throttled, customer only, U); `POST /auth/login` (none, throttled, superadmin, retailer, customer, U); success response JSON structure `{"data": {"user": {...}, "tokens": {...}}}`. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L202-L229) §8 | Security guidelines: passwords hashed only via `hashed` cast on `User`; no `Hash::make` in services or controllers. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L230-L237) §9 | Schema details: external identifier is `public_id` (ULID); soft deletes via `deleted_at`. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L287-L292) §10 | Token revocation on delete/password/role change deferred to `ACC-U5`. Multiple concurrent tokens accepted in the interim. |
| [docs/blueprints/RULE_REGISTER.md](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md) | Governs the 75 rule IDs evaluated in the Section 8 compliance matrix and establishes Layer A commands 15a through 15k. |
| [docs/blueprints/ACC-U4/brief.md](file:///c:/dev/getnow-auth2/docs/blueprints/ACC-U4/brief.md) | Scope, dependencies, acceptance criteria AC-1 through AC-10, size guard (13 files limit), open decisions OD-1 through OD-6, rules at risk, known traps 1 through 14. |
| [docs/adr/0001-passport-token-issuance-and-refresh-rotation.md](file:///c:/dev/getnow-auth2/docs/adr/0001-passport-token-issuance-and-refresh-rotation.md) | Defines token lifetimes (access token 900s, refresh token 14 days), refresh token rotation, and bearer token delivery in response body. |
| [docs/adr/0003-user-soft-deletes-and-schema-cleanup.md](file:///c:/dev/getnow-auth2/docs/adr/0003-user-soft-deletes-and-schema-cleanup.md) | Confirms soft deletion via `deleted_at`; unique email index covers full table, preventing duplicate registration of soft-deleted email. |
| [app/Domain/Accounts/Contracts/TokenIssuer.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Contracts/TokenIssuer.php) | Method signature: `public function issue(User $user): TokenPairData;`. |
| [app/Domain/Accounts/Data/TokenPairData.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Data/TokenPairData.php) | Properties: `public string $accessToken`, `public string $refreshToken`, `public string $tokenType`, `public int $expiresIn`. Attribute `#[MapName(SnakeCaseMapper::class)]`. |
| [app/Domain/Accounts/Data/UserResponseData.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Data/UserResponseData.php) | Signature: `public static function fromModel(User $user): self`. Properties: `id` (ULID), `name`, `email`, `role`, `createdAt`, `updatedAt`. Attribute `#[MapName(SnakeCaseMapper::class)]`. |
| [app/Domain/Accounts/Data/UserData.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Data/UserData.php) | Combined DTO: `public string|Optional $name`, `public string|Optional $email`, `public string|Optional $password`, `public UserRole|Optional $role`. |
| [app/Domain/Accounts/Contracts/UserRepositoryInterface.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Contracts/UserRepositoryInterface.php) | Target for `public function findByEmail(string $email): ?User;`. Existing methods: `index`, `show`, `store`, `update`, `destroy`. |
| [app/Domain/Accounts/Repositories/UserRepository.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Repositories/UserRepository.php) | Target for implementing `findByEmail(string $email): ?User` via `User::query()->where('email', $email)->first()`. Excludes soft-deleted rows by default. |
| [app/Domain/Accounts/Services/UserService.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Services/UserService.php) | Method `store(UserData $data): User`. Validates required fields, forbids `UserRole::Superadmin`, normalizes email with `strtolower(trim($data->email))`, and delegates to `UserRepository::store`. |
| [app/Domain/Accounts/Enums/UserRole.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Enums/UserRole.php) | Backed enum cases: `Superadmin = 'superadmin'`, `Retailer = 'retailer'`, `Customer = 'customer'`. |
| [app/Models/User.php](file:///c:/dev/getnow-auth2/app/Models/User.php) | Implements `OAuthenticatable`, uses `HasApiTokens`, `SoftDeletes`. Casts: `role => UserRole::class`, `password => 'hashed'`. |
| [app/Http/Requests/Users/StoreUserRequest.php](file:///c:/dev/getnow-auth2/app/Http/Requests/Users/StoreUserRequest.php) | Validation rule patterns: password `['required', 'string', 'min:8', 'max:72']`, email normalization in `prepareForValidation()`, `Rule::unique('users', 'email')`. |
| [app/Http/Controllers/Api/V1/Users/UserController.php](file:///c:/dev/getnow-auth2/app/Http/Controllers/Api/V1/Users/UserController.php) | Thin controller pattern returning `response()->json(['data' => ...])`. |
| [app/Http/Controllers/Api/V1/Auth/TokenController.php](file:///c:/dev/getnow-auth2/app/Http/Controllers/Api/V1/Auth/TokenController.php) | Existing tokens controller. Route prefix `auth`. Returns `response()->json(['data' => ['tokens' => $tokenPair->toArray()]])`. |
| [routes/api/tokens.php](file:///c:/dev/getnow-auth2/routes/api/tokens.php) | Defines `POST auth/refresh` with `throttle:token-refresh` and `POST auth/logout` with `throttle:token-logout`. |
| [routes/api.php](file:///c:/dev/getnow-auth2/routes/api.php) | API route manifest requiring `routes/api/tokens.php` and `routes/api/users.php`. Target for `require __DIR__.'/api/auth.php';` under CR-1. |
| [routes/api/users.php](file:///c:/dev/getnow-auth2/routes/api/users.php) | Defines `api/v1/users` resource endpoints under `auth:api` middleware. |
| [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php) | Target for `$exceptions->render` mapping `InvalidCredentialsException` to HTTP 401 JSON. |
| [app/Providers/AppServiceProvider.php](file:///c:/dev/getnow-auth2/app/Providers/AppServiceProvider.php) | Target for named rate limiters `auth-login` and `auth-register`. |
| [tests/Arch/AccountsArchTest.php](file:///c:/dev/getnow-auth2/tests/Arch/AccountsArchTest.php) | Architectural rules: `App\Domain` does not use `App\Http`; `Laravel\Passport` restricted to Tokens stream and `User`; strict types on `App\Http\Requests\Auth` and `App\Domain`; controllers do not use repositories or `DB`. |
| [tests/Feature/Tokens/TokenEndpointsTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Tokens/TokenEndpointsTest.php) | Demonstrates testing Passport endpoints and Bearer token headers. |
| [tests/Feature/Accounts/UserControllerTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Accounts/UserControllerTest.php) | Feature test patterns for user endpoints, unauthenticated handling, and validation error assertions. |
| [tests/Support/InteractsWithPassportKeys.php](file:///c:/dev/getnow-auth2/tests/Support/InteractsWithPassportKeys.php) | Shared test trait supplying in-memory RSA keys to Passport in CI environments. |
| [database/seeders/SuperadminSeeder.php](file:///c:/dev/getnow-auth2/database/seeders/SuperadminSeeder.php) | Seeds superadmin with email from `config('auth.superadmin.email')`. |
| [phpunit.xml](file:///c:/dev/getnow-auth2/phpunit.xml) | Test environment configuration: `CACHE_STORE = array`, `BCRYPT_ROUNDS = 4`, `DB_CONNECTION = pgsql`. |

---

### 3. Verified current state

1. `App\Domain\Accounts\Contracts\TokenIssuer` (`app/Domain/Accounts/Contracts/TokenIssuer.php`):
   ```php
   namespace App\Domain\Accounts\Contracts;

   interface TokenIssuer
   {
       public function issue(User $user): TokenPairData;
       public function refresh(string $refreshToken): TokenPairData;
       public function revoke(User $user): void;
       public function revokeAll(User $user): void;
   }
   ```
2. `App\Domain\Accounts\Data\TokenPairData` (`app/Domain/Accounts/Data/TokenPairData.php`):
   ```php
   namespace App\Domain\Accounts\Data;

   #[MapName(SnakeCaseMapper::class)]
   final class TokenPairData extends Data
   {
       public function __construct(
           public string $accessToken,
           public string $refreshToken,
           public string $tokenType,
           public int $expiresIn,
       ) {}
   }
   ```
3. `App\Domain\Accounts\Data\UserResponseData` (`app/Domain/Accounts/Data/UserResponseData.php`):
   ```php
   namespace App\Domain\Accounts\Data;

   #[MapName(SnakeCaseMapper::class)]
   final class UserResponseData extends Data
   {
       public function __construct(
           public string $id,
           public string $name,
           public string $email,
           public UserRole $role,
           public ?CarbonInterface $createdAt = null,
           public ?CarbonInterface $updatedAt = null,
       ) {}

       public static function fromModel(User $user): self { ... }
   }
   ```
4. `App\Domain\Accounts\Contracts\UserRepositoryInterface` (`app/Domain/Accounts/Contracts/UserRepositoryInterface.php`):
   ```php
   namespace App\Domain\Accounts\Contracts;

   interface UserRepositoryInterface
   {
       public function index(UserFilterData $filter): LengthAwarePaginator;
       public function show(string $publicId): ?User;
       public function store(UserData $data): User;
       public function update(User $user, UserData $data): User;
       public function destroy(User $user): void;
   }
   ```
5. `App\Domain\Accounts\Repositories\UserRepository` (`app/Domain/Accounts/Repositories/UserRepository.php`):
   - Implements `UserRepositoryInterface`.
   - Uses `User::query()`.
   - Does not currently define `findByEmail`.
6. `App\Domain\Accounts\Services\UserService` (`app/Domain/Accounts/Services/UserService.php`):
   ```php
   public function store(UserData $data): User
   ```
   - Checks that `name`, `email`, `password`, `role` are not `Optional`.
   - Forbids `UserRole::Superadmin` with `ProtectedUserException::cannotCreateSuperadmin()`.
   - Normalizes email via `strtolower(trim($data->email))`.
   - Calls `$this->users->store($normalizedData)`.
7. `routes/api.php` (`routes/api.php`):
   ```php
   require __DIR__.'/api/tokens.php';
   require __DIR__.'/api/users.php';
   ```
   - Currently lacks `require __DIR__.'/api/auth.php';`.
8. `routes/api/tokens.php` (`routes/api/tokens.php`):
   ```php
   Route::prefix('auth')->group(function (): void {
       Route::post('refresh', [TokenController::class, 'refresh'])->middleware('throttle:token-refresh');
       Route::post('logout', [TokenController::class, 'logout'])->middleware(['auth:api', 'throttle:token-logout']);
   });
   ```
9. `bootstrap/app.php` (`bootstrap/app.php`):
   - Configures `apiPrefix: 'api/v1'`.
   - Renders `ProtectedUserException` as JSON 403.
   - Does not currently handle `InvalidCredentialsException`.
10. `app/Providers/AppServiceProvider.php` (`app/Providers/AppServiceProvider.php`):
    - Registers `Gate::policy(User::class, UserPolicy::class)`.
    - Does not currently register rate limiters for `auth-login` or `auth-register`.
11. `tests/Arch/AccountsArchTest.php` (`tests/Arch/AccountsArchTest.php`):
    - Strict types expectation explicitly covers `App\Http\Requests\Auth`.
    - Domain isolation forbids `App\Http` inside `App\Domain`.
    - Controllers cannot use `Repositories` or `DB` facade.

---

### 4. File manifest

| Path | New or Modify | Layer | Responsibility | Rule IDs |
|---|---|---|---|---|
| `app/Domain/Accounts/Data/LoginData.php` | New | Domain (Data) | Input DTO holding verified email and plain password | STD1, STD2, LAY7 |
| `app/Domain/Accounts/Data/AuthResultData.php` | New | Domain (Data) | Output DTO containing `UserResponseData $user` and `TokenPairData $tokens` | STD1, STD2, LAY7, DBS5 |
| `app/Domain/Accounts/Exceptions/InvalidCredentialsException.php` | New | Domain (Exception) | Pure domain exception thrown when login authentication fails | STD1, STD2, LAY2, LAY7 |
| `app/Domain/Accounts/Contracts/UserRepositoryInterface.php` | Modify | Domain (Contract) | Adds method signature `public function findByEmail(string $email): ?User;` | STD1, STD2, LAY4 |
| `app/Domain/Accounts/Repositories/UserRepository.php` | Modify | Domain (Repository) | Implements `findByEmail` via `User::query()->where('email', $email)->first()` | STD1, STD2, LAY4, DBS2 |
| `app/Domain/Accounts/Services/AuthService.php` | New | Domain (Service) | Orchestrates atomic registration (`UserService::store` + `TokenIssuer::issue`) and login (`findByEmail` + `Hasher::check` + `TokenIssuer::issue`) | STD1, STD2, LAY3, ACT1, ACT5, ACT6, ACT7, ACT9, ACT10, STM1 |
| `app/Http/Requests/Auth/RegisterRequest.php` | New | HTTP (Request) | Validates registration payload (`name`, `email`, `password`, `role` prohibited), normalizes email, produces `UserData` DTO | STD1, STD2, LAY1, ACT1, ACT6 |
| `app/Http/Requests/Auth/LoginRequest.php` | New | HTTP (Request) | Validates login payload (`email`, `password`), normalizes email, produces `LoginData` DTO | STD1, STD2, LAY1, ACT5 |
| `app/Http/Controllers/Api/V1/Auth/AuthController.php` | New | HTTP (Controller) | Thin API controller exposing `register` (201) and `login` (200) actions | STD1, STD2, LAY1, LAY8, ACT1, ACT5 |
| `routes/api/auth.php` | New | HTTP (Routing) | Defines guest routes `POST /register` and `POST /login` with rate limiters | STD1, SCR4, ACT1, ACT5 |
| `routes/api.php` | Modify | Contract Seam | Appends `require __DIR__.'/api/auth.php';` (CR-1) | STM1, GIT4 |
| `app/Providers/AppServiceProvider.php` | Modify | Provider | Registers named rate limiters `auth-login` (5/min per email+IP) and `auth-register` (10/min per IP) | STD1, SCR4 |
| `bootstrap/app.php` | Modify | Bootstrap | Configures exception renderer mapping `InvalidCredentialsException` to JSON 401 | STD1, LAY2, ACT7, SCR |
| `tests/Feature/Accounts/AuthEndpointsTest.php` | New | Testing | Comprehensive feature tests covering AC-1 through AC-10 | TST1, TST2, TST4, ACT1, ACT5, ACT7 |

File count guard: Exactly 10 code/routing/config files (9 new, 4 modified, including the route seam CR-1 and test file), respecting the 13-file ceiling established in the brief size guard.

---

### 5. Interface specs

#### 5.1 `App\Domain\Accounts\Data\LoginData`
- Path: `app/Domain/Accounts/Data/LoginData.php`
- Strict types: `declare(strict_types=1);`
- Class declaration: `final class LoginData extends Data`
- Properties:
  - `public string $email`
  - `public string $password`
- Constructor:
  ```php
  public function __construct(
      public string $email,
      public string $password,
  ) {}
  ```
- Thrown exceptions: None.

#### 5.2 `App\Domain\Accounts\Data\AuthResultData`
- Path: `app/Domain/Accounts/Data/AuthResultData.php`
- Strict types: `declare(strict_types=1);`
- Class declaration:
  ```php
  #[MapName(SnakeCaseMapper::class)]
  final class AuthResultData extends Data
  {
      public function __construct(
          public UserResponseData $user,
          public TokenPairData $tokens,
      ) {}
  }
  ```
- Properties:
  - `public UserResponseData $user`
  - `public TokenPairData $tokens`
- Thrown exceptions: None.

#### 5.3 `App\Domain\Accounts\Exceptions\InvalidCredentialsException`
- Path: `app/Domain/Accounts/Exceptions/InvalidCredentialsException.php`
- Strict types: `declare(strict_types=1);`
- Class declaration: `final class InvalidCredentialsException extends RuntimeException`
- Constructor:
  ```php
  public function __construct(string $message = 'Invalid credentials.')
  {
      parent::__construct($message);
  }
  ```
- Thrown exceptions: None.

#### 5.4 `App\Domain\Accounts\Contracts\UserRepositoryInterface`
- Path: `app/Domain/Accounts/Contracts/UserRepositoryInterface.php`
- Strict types: `declare(strict_types=1);`
- Method added:
  ```php
  public function findByEmail(string $email): ?User;
  ```
- Existing methods retained unmodified: `index`, `show`, `store`, `update`, `destroy`.

#### 5.5 `App\Domain\Accounts\Repositories\UserRepository`
- Path: `app/Domain/Accounts/Repositories/UserRepository.php`
- Strict types: `declare(strict_types=1);`
- Method added:
  ```php
  public function findByEmail(string $email): ?User
  {
      return User::query()->where('email', $email)->first();
  }
  ```
- Behavior: Queries PostgreSQL table `users` by exact `email`. Soft-deleted records are excluded by `SoftDeletes` query scope on `User`.

#### 5.6 `App\Domain\Accounts\Services\AuthService`
- Path: `app/Domain/Accounts/Services/AuthService.php`
- Strict types: `declare(strict_types=1);`
- Class declaration: `final class AuthService`
- Constants:
  - `private const string DUMMY_HASH = '$2y$04$ebgElBN/lGg9/9VJwLY1t.S5psdZy3y1W/E3EahnHCEnu8hO333eW';` (Valid 4-round bcrypt hash constant used for timing equalization on unknown email or soft-deleted user lookups without invoking `Hash::make`).
- Constructor dependencies:
  ```php
  public function __construct(
      private readonly UserService $userService,
      private readonly UserRepositoryInterface $userRepository,
      private readonly TokenIssuer $tokenIssuer,
      private readonly Hasher $hasher,
  ) {}
  ```
- Public methods:
  - `public function register(UserData $data): AuthResultData`:
    Wraps execution in `DB::transaction()`:
    1. `$user = $this->userService->store($data);`
    2. `$tokens = $this->tokenIssuer->issue($user);`
    3. Returns `new AuthResultData(UserResponseData::fromModel($user), $tokens);`
  - `public function login(LoginData $data): AuthResultData`:
    1. Normalizes email: `$email = strtolower(trim($data->email));`
    2. Retrieves user: `$user = $this->userRepository->findByEmail($email);`
    3. If `$user === null`:
       Executes timing equalization: `$this->hasher->check($data->password, self::DUMMY_HASH);`
       Throws `new InvalidCredentialsException();`
    4. If `$user !== null`:
       Verifies password: `if (! $this->hasher->check($data->password, $user->password)) { throw new InvalidCredentialsException(); }`
    5. Issues tokens: `$tokens = $this->tokenIssuer->issue($user);`
    6. Returns `new AuthResultData(UserResponseData::fromModel($user), $tokens);`

#### 5.7 `App\Http\Requests\Auth\RegisterRequest`
- Path: `app/Http/Requests/Auth/RegisterRequest.php`
- Strict types: `declare(strict_types=1);`
- Class declaration: `final class RegisterRequest extends FormRequest`
- Methods:
  - `public function authorize(): bool`: Returns `true` (guest endpoint).
  - `protected function prepareForValidation(): void`:
    ```php
    if ($this->has('email') && is_string($this->input('email'))) {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }
    ```
  - `public function rules(): array`:
    ```php
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => [
            'required',
            'string',
            'email',
            'max:255',
            Rule::unique('users', 'email'),
        ],
        'password' => ['required', 'string', 'min:8', 'max:72'],
        'role' => ['prohibited'],
    ];
    ```
  - `public function toDto(): UserData`:
    ```php
    return new UserData(
        name: (string) $this->validated('name'),
        email: (string) $this->validated('email'),
        password: (string) $this->validated('password'),
        role: UserRole::Customer,
    );
    ```

#### 5.8 `App\Http\Requests\Auth\LoginRequest`
- Path: `app/Http/Requests/Auth/LoginRequest.php`
- Strict types: `declare(strict_types=1);`
- Class declaration: `final class LoginRequest extends FormRequest`
- Methods:
  - `public function authorize(): bool`: Returns `true` (guest endpoint).
  - `protected function prepareForValidation(): void`:
    ```php
    if ($this->has('email') && is_string($this->input('email'))) {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }
    ```
  - `public function rules(): array`:
    ```php
    return [
        'email' => ['required', 'string', 'email', 'max:255'],
        'password' => ['required', 'string', 'max:72'],
    ];
    ```
  - `public function toDto(): LoginData`:
    ```php
    return new LoginData(
        email: (string) $this->validated('email'),
        password: (string) $this->validated('password'),
    );
    ```

#### 5.9 `App\Http\Controllers\Api\V1\Auth\AuthController`
- Path: `app/Http/Controllers/Api/V1/Auth/AuthController.php`
- Strict types: `declare(strict_types=1);`
- Class declaration: `final class AuthController extends Controller`
- Constructor:
  ```php
  public function __construct(
      private readonly AuthService $authService,
  ) {}
  ```
- Public methods:
  - `public function register(RegisterRequest $request): JsonResponse`:
    `$result = $this->authService->register($request->toDto());`
    `return response()->json(['data' => $result], 201);`
  - `public function login(LoginRequest $request): JsonResponse`:
    `$result = $this->authService->login($request->toDto());`
    `return response()->json(['data' => $result], 200);`

#### 5.10 `routes/api/auth.php`
- Path: `routes/api/auth.php`
- Strict types: `declare(strict_types=1);`
- Route group definition:
  ```php
  use App\Http\Controllers\Api\V1\Auth\AuthController;
  use Illuminate\Support\Facades\Route;

  Route::prefix('auth')->group(function (): void {
      Route::post('register', [AuthController::class, 'register'])
          ->middleware('throttle:auth-register');

      Route::post('login', [AuthController::class, 'login'])
          ->middleware('throttle:auth-login');
  });
  ```

#### 5.11 `app/Providers/AppServiceProvider`
- Path: `app/Providers/AppServiceProvider.php`
- Modifications in `boot()`:
  ```php
  RateLimiter::for('auth-login', function (Request $request): Limit {
      $email = strtolower(trim((string) $request->input('email')));

      return Limit::perMinute(5)->by($email.'|'.$request->ip());
  });

  RateLimiter::for('auth-register', function (Request $request): Limit {
      return Limit::perMinute(10)->by((string) $request->ip());
  });
  ```

#### 5.12 `bootstrap/app.php`
- Path: `bootstrap/app.php`
- Exception rendering registration:
  ```php
  $exceptions->render(function (InvalidCredentialsException $e, Request $request) {
      if ($request->is('api/*') || $request->expectsJson()) {
          return response()->json([
              'message' => 'Invalid credentials.',
          ], 401);
      }
  });
  ```

---

### 6. Data and migrations

No migrations, table additions, or column schema changes are made in this step (`DBS2`). The `users` PostgreSQL table schema established in `ACC-U1` (`id`, `public_id`, `name`, `email`, `password`, `role`, `created_at`, `updated_at`, `deleted_at`) is sufficient and complete. `findByEmail` executes indexed queries over existing PostgreSQL column `email`.

---

### 7. Behavior and flows

#### Flow 1: Customer Registration (`POST /api/v1/auth/register`)
- Happy path:
  1. Unauthenticated client submits `POST /api/v1/auth/register` with valid `name`, `email`, and `password`.
  2. `RegisterRequest` normalizes email to lowercase and trimmed string. Validation verifies `name`, valid `email`, unique email across `users` table, password length (8 to 72 characters), and absence of `role`.
  3. `RegisterRequest::toDto()` constructs `UserData` with `role = UserRole::Customer`.
  4. `AuthController::register` calls `AuthService::register($userData)`.
  5. `AuthService` initiates `DB::transaction`.
  6. `UserService::store` persists new `User` with generated ULID `public_id` and hashed password via model cast.
  7. `TokenIssuer::issue($user)` mints access token and refresh token via Passport.
  8. `AuthResultData` transforms model to `UserResponseData` and wraps tokens.
  9. Response returns HTTP 201 Created with JSON envelope:
     ```json
     {
         "data": {
             "user": {
                 "id": "01J...",
                 "name": "Jane Customer",
                 "email": "jane@example.com",
                 "role": "customer",
                 "created_at": "...",
                 "updated_at": "..."
             },
             "tokens": {
                 "access_token": "...",
                 "refresh_token": "...",
                 "token_type": "Bearer",
                 "expires_in": 900
             }
         }
     }
     ```
- Failure paths:
  - Missing field, malformed email, or password under 8 characters: `RegisterRequest` returns HTTP 422 with validation errors.
  - Client sends `"role": "retailer"` or `"role": "superadmin"`: `RegisterRequest` rejects with HTTP 422 (`The role field is prohibited.`).
  - Email already exists in `users` (including soft-deleted users): `RegisterRequest` rejects with HTTP 422 (`The email has already been taken.`).
  - Token issuer fails: `DB::transaction` rolls back; no user row is persisted.
  - Rate limit exceeded (more than 10 requests per minute from same IP): returns HTTP 429 Too Many Requests.

#### Flow 2: User Login (`POST /api/v1/auth/login`)
- Happy path:
  1. Client sends `POST /api/v1/auth/login` with `email` and `password`.
  2. `LoginRequest` normalizes email and validates presence of `email` and `password`.
  3. `AuthController::login` calls `AuthService::login($loginData)`.
  4. `AuthService` queries `$userRepository->findByEmail($email)`.
  5. User is found (`Customer`, `Retailer`, or `Superadmin`).
  6. `$hasher->check($password, $user->password)` returns `true`.
  7. `TokenIssuer::issue($user)` mints token pair.
  8. Response returns HTTP 200 OK with `AuthResultData` envelope.
- Failure paths:
  - Unknown email: `findByEmail` returns `null`. `AuthService` executes timing equalization (`$hasher->check($password, self::DUMMY_HASH)`) and throws `InvalidCredentialsException`. `bootstrap/app.php` renders HTTP 401 with `{"message": "Invalid credentials."}`.
  - Wrong password: `findByEmail` finds user, but `$hasher->check($password, $user->password)` returns `false`. `AuthService` throws `InvalidCredentialsException`. `bootstrap/app.php` renders byte-identical HTTP 401 `{"message": "Invalid credentials."}`.
  - Soft-deleted user: `findByEmail` executes standard Eloquent query excluding soft deletes, returning `null`. Code enters unknown email branch, performs timing equalization, and returns byte-identical HTTP 401 `{"message": "Invalid credentials."}`.
  - Missing or malformed email / password: `LoginRequest` returns HTTP 422 Unprocessable Entity. Submitted password is never echoed in validation errors.
  - Rate limit exceeded (more than 5 requests per minute for same email + IP): returns HTTP 429 Too Many Requests.

---

### 8. Rule compliance matrix

| Rule ID | Applies | How satisfied, or why N/A |
|---|---|---|
| STM1 | Y | Step modifies users-stream files (`app/Domain/Accounts`, `app/Http`, `routes/api/auth.php`, `tests/Feature/Accounts`). `routes/api.php` touched strictly as authorized route seam CR-1. No Tokens-stream files modified. |
| STM2 | Y | `TokenIssuer` contract interface and `TokenPairData` are imported from `App\Domain\Accounts\Contracts` and `App\Domain\Accounts\Data` without modification. |
| STM3 | Y | `AuthService` imports `App\Domain\Accounts\Contracts\TokenIssuer`. It never imports `Laravel\Passport` or `PassportTokenIssuer`. |
| STM4 | Y | No modifications to imported token code. |
| LAY1 | Y | Flow follows `FormRequest` -> `AuthController` -> `AuthService` -> `UserRepositoryInterface` -> `UserRepository`. |
| LAY2 | Y | Domain layer (`AuthService`, `LoginData`, `AuthResultData`, `InvalidCredentialsException`) imports no `Illuminate\Http` or `App\Http` classes. Exception HTTP translation handled in `bootstrap/app.php`. |
| LAY3 | Y | `AuthService` depends on `UserRepositoryInterface`, `UserService`, `TokenIssuer`, and `Hasher`. It contains business rules and never calls Eloquent directly. |
| LAY4 | Y | `UserRepository::findByEmail` handles query execution. It throws no HTTP exceptions. |
| LAY5 | N | Method parity applies to CRUD classes; `AuthService` and `AuthController` are authentication actions (`register`, `login`). |
| LAY6 | Y | `AuthController` returns `JsonResponse` wrapping `AuthResultData`. It never returns Eloquent models. |
| LAY7 | Y | All files placed in paths designated by `ARCHITECTURE.md` Section 5. |
| LAY8 | Y | `AuthController` does not call Eloquent or Repositories directly. |
| STD1 | Y | `declare(strict_types=1);` in every new and modified PHP file. |
| STD2 | Y | Strictly typed properties, parameters, and returns. Classes marked `final`. |
| STD3 | Y | Role assignments and checks utilize `UserRole::Customer`, `UserRole::Retailer`, `UserRole::Superadmin` enum cases, never string literals. |
| STD4 | Y | `RegisterRequest::toDto()` constructs `UserData` setting `role = UserRole::Customer`. |
| STD5 | N | `LoginData` is an immutable non-partial DTO; partial updates not applicable to login. |
| STD6 | Y | Code will pass `composer lint` (Pint) and `composer stan` (PHPStan Level 8) cleanly without baseline entries. |
| STD7 | Y | Comments explain architectural rationale; no commented-out code. |
| ACT1 | Y | Public registration creates `Customer` only via `RegisterRequest::toDto()`. `role` in payload rejected with 422. |
| ACT2 | Y | Retailers cannot register; created exclusively by superadmin via `POST /api/v1/users`. |
| ACT3 | Y | Superadmin endpoints remain protected in `ACC-U3`. |
| ACT4 | Y | Superadmin created via `SuperadminSeeder`. Registration never promotes or creates superadmin. |
| ACT5 | Y | `Customer`, `Retailer`, and `Superadmin` can all log in through `POST /api/v1/auth/login`. |
| ACT6 | Y | Passwords hashed only via `hashed` cast on `User`. `Hash::make` is not called anywhere in application code or tests. |
| ACT7 | Y | Login returns identical HTTP 401 `{"message": "Invalid credentials."}` for unknown email, wrong password, and soft-deleted user. |
| ACT8 | N | Token revocation deferred to `ACC-U5`. |
| ACT9 | Y | `AuthService` invokes `TokenIssuer::issue` exactly once per successful registration or login. |
| ACT10 | Y | Register and login responses serialize `UserResponseData` and `TokenPairData`. Raw integer `id` is never exposed. |
| ACT11 | Y | Passwords, tokens, and refresh tokens are never logged. |
| TOK1 | Y | `TokenIssuer` implemented by `PassportTokenIssuer` in Tokens stream; bound in `TokenServiceProvider`. Untouched here. |
| TOK2 | Y | Lifetimes and scopes adhere to ADR 0001. |
| TOK3 | Y | Passport package configuration untouched. |
| TOK4 | Y | Lifetimes configured in `config/passport.php`. |
| TOK5 | Y | Token refresh rotation verified via end-to-end integration test (AC-7). |
| TOK6 | Y | Endpoints throttled via named rate limiters `auth-login` and `auth-register`. |
| DBS1 | Y | PostgreSQL database utilized exclusively. |
| DBS2 | Y | No migrations added or modified. |
| DBS3 | N | No schema drops in this step. |
| DBS4 | Y | Queries utilize indexed `email` column. |
| DBS5 | Y | External identifier is ULID `public_id`. |
| DBS6 | Y | `SuperadminSeeder` remains idempotent. |
| SCR1 | Y | No credentials or tokens committed. |
| SCR2 | Y | Authorization and validation rules remain active on all paths. |
| SCR3 | Y | Server-side validation enforced via FormRequests. |
| SCR4 | Y | Throttle middleware attached to both routes. |
| TST1 | Y | Pest feature tests in `tests/Feature/Accounts/AuthEndpointsTest.php`. |
| TST2 | Y | Failure cases covered: 401 credentials, 422 validation / prohibited role / duplicate email, 429 throttling, atomic rollback on failure. |
| TST3 | Y | Tests run against PostgreSQL with real Passport via `InteractsWithPassportKeys`. |
| TST4 | Y | Test fixtures created via `UserFactory`. |
| TST5 | Y | Zero skipped, todo, or incomplete tests. |
| GIT1 | Y | Feature branch named `feat/users-auth-endpoints`. |
| GIT2 | Y | Conventional Commits with `feat(users):`, `test(users):`, `docs(users):`. |
| GIT3 | Y | No direct pushes to `main`. |
| GIT4 | N | Users stream step; route seam CR-1 documented. |
| GIT5 | Y | PR description documents changes, tests, and security considerations. |
| GIT6 | Y | Follows merge order (`ACC-U3` -> `ACC-U4`). |
| ENV1 | Y | Windows PowerShell commands executed one at a time without `&&`. |
| ENV2 | Y | Native PHP and PostgreSQL; no Docker or Sail. |
| ENV3 | Y | Non-interactive command flags used. |
| ENV4 | Y | `composer check` must pass cleanly. |
| DNT1 | Y | No new composer or npm dependencies added. |
| DNT2 | Y | No infrastructure or CI pipeline modifications. |
| DNT3 | Y | Out-of-scope features (email verify, password reset, 2FA) excluded. |
| DNT4 | Y | Business logic encapsulated in `AuthService`, not controller. |
| DNT5 | Y | No destructive database commands. |
| DNT6 | Y | No secrets or external keys copied into repository. |
| DNT7 | Y | Updates `ARCHITECTURE.md` checklist and blueprint docs. |
| WFL1 | Y | Work proceeds strictly from this approved plan. |
| WFL2 | Y | Any unexpected requirement will trigger a Change Request. |
| WFL3 | Y | Step concludes with raw command output report. |
| WFL4 | Y | Done state determined by reviewer verdict. |
| WFL5 | Y | Plan approved before code edits. |
| WFL6 | Y | Scope limited strictly to registration and login endpoints. |
| WFL7 | Y | Open decisions recorded in Section 13. |

---

### 9. AC traceability

| AC ID | Requirement | Exact Test Name in `tests/Feature/Accounts/AuthEndpointsTest.php` | How Verified |
|---|---|---|---|
| **AC-1** | `POST /api/v1/auth/register` creates `Customer` via `UserService::create`, returns 201 with `AuthResultData`, hashes password via cast, and stores normalized email | `it('registers a new customer with normalized email and returns 201 with auth result data')` | Submits valid payload; asserts 201 status; asserts `data.user.role === 'customer'`; asserts `data.tokens.access_token` and `refresh_token` exist; verifies persisted user in DB has normalized email and hash matches via `Hasher::check`. |
| **AC-2** | Register rejects invalid payloads, duplicate emails (including casing/whitespace and soft-deleted emails), weak passwords, and any `role` in payload with 422 leaving DB unchanged | `it('rejects register request with 422 for validation failures duplicate emails and prohibited role leaving database unchanged')` | Tests missing fields, malformed email, password < 8 chars, existing email, soft-deleted email, and payload containing `role => 'retailer'` or `role => 'superadmin'`. Asserts 422 status and confirms database count remains unchanged. |
| **AC-3** | Registration is atomic: if token issuance fails, database transaction rolls back leaving no user row | `it('rolls back customer creation atomically when token issuance fails')` | Binds a throwing `TokenIssuer` implementation into container; calls register; asserts exception or 500 status; asserts `User::where('email', ...)->exists()` is false. |
| **AC-4** | `POST /api/v1/auth/login` returns 200 with `AuthResultData` for Customer, Retailer, and Superadmin, supporting email casing and whitespace variants | `it('authenticates customer retailer and superadmin returning 200 with auth result data and email casing tolerance')` | Creates users in all three roles via factory; logs in each with valid credentials and mixed-case/whitespace email variants; asserts 200 status, correct role in `data.user`, and valid token pair. |
| **AC-5** | Login credential failures return byte-identical 401 JSON for wrong password, unknown email, and soft-deleted user; malformed inputs return 422; passwords never leaked | `it('returns identical 401 response for wrong password unknown email and soft-deleted user without leaking password')` | Sends request for non-existent email, wrong password, and soft-deleted user; asserts exact status 401 and asserts response body equality across all three; asserts 422 on empty fields; asserts submitted password is not present in response content. |
| **AC-6** | Response shapes strictly adhere to `UserResponseData` and `TokenPairData` per `ARCHITECTURE.md` Section 7 | `it('serializes login and register responses strictly matching architecture specifications without sensitive fields')` | Inspects keys of `data.user` and `data.tokens`; asserts presence of `id`, `name`, `email`, `role`, `created_at`, `updated_at`, `access_token`, `refresh_token`, `token_type`, `expires_in`; asserts absence of internal integer `id`, `password`, and `remember_token`. |
| **AC-7** | Access token authenticates `GET /api/v1/users/{public_id}` via Bearer header; refresh token is accepted by `/api/v1/auth/refresh` | `it('issues tokens that successfully authenticate user endpoint and rotate via refresh endpoint')` | Performs login as superadmin; extracts `access_token` and calls `GET /api/v1/users/{public_id}` with `Authorization: Bearer <token>`, asserting 200; extracts `refresh_token` and calls `POST /api/v1/auth/refresh`, asserting 200 and successful rotation. |
| **AC-8** | Rate limiting enforces 5/min on login and 10/min on register, returning 429 JSON | `it('throttles excessive login and register attempts returning 429')` | Clears rate limiters; sends 5 login attempts, asserts 6th attempt returns 429; sends 10 register attempts, asserts 11th attempt returns 429; clears rate limiters after test. |
| **AC-9** | `UserRepositoryInterface::findByEmail` matches exact normalized email and returns null for soft-deleted user | `it('finds user by exact email excluding soft-deleted records')` | Creates active user and soft-deleted user; invokes `findByEmail` directly on repository; asserts active user returned, unknown email returns null, and soft-deleted email returns null. |
| **AC-10** | Quality gates, Layer A checks 15a-15k, and regression suites pass cleanly with no route name clashes | `it('registers auth routes without collision against token stream routes')` | Runs `php artisan route:list --path=api/v1/auth`; verifies `register`, `login`, `refresh`, `logout` coexist cleanly without collisions; runs token and arch suites. |

---

### 10. Test plan

#### File: `tests/Feature/Accounts/AuthEndpointsTest.php`

1. `it('registers a new customer with normalized email and returns 201 with auth result data')`:
   - Arrange: Prepare payload `['name' => 'John Doe', 'email' => '  JOHN.DOE@EXAMPLE.COM  ', 'password' => 'secret1234']`.
   - Act: `$response = $this->postJson('/api/v1/auth/register', $payload);`
   - Assert:
     - `$response->assertCreated();`
     - `$response->assertJsonPath('data.user.name', 'John Doe');`
     - `$response->assertJsonPath('data.user.email', 'john.doe@example.com');`
     - `$response->assertJsonPath('data.user.role', 'customer');`
     - Expect `$response->json('data.tokens.access_token')` to be string.
     - Expect `$response->json('data.tokens.token_type')` to be `'Bearer'`.
     - `$persisted = User::where('email', 'john.doe@example.com')->first();`
     - Expect `$persisted->role` to be `UserRole::Customer`.
     - Expect `app('hash')->check('secret1234', $persisted->password)` to be `true`.

2. `it('rejects register request with 422 for validation failures duplicate emails and prohibited role leaving database unchanged')`:
   - Arrange: Create existing user `existing@example.com` and soft-deleted user `deleted@example.com`.
   - Act & Assert:
     - Missing name, email, password: `$this->postJson('/api/v1/auth/register', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);`
     - Short password: `$this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'a@b.com', 'password' => 'short'])->assertUnprocessable()->assertJsonValidationErrors(['password']);`
     - Password > 72 chars: `$this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'a@b.com', 'password' => str_repeat('a', 73)])->assertUnprocessable()->assertJsonValidationErrors(['password']);`
     - Duplicate email: `$this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'EXISTING@example.com', 'password' => 'secret1234'])->assertUnprocessable()->assertJsonValidationErrors(['email']);`
     - Soft-deleted email: `$this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'deleted@example.com', 'password' => 'secret1234'])->assertUnprocessable()->assertJsonValidationErrors(['email']);`
     - Prohibited role field: `$this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => 'retailer@example.com', 'password' => 'secret1234', 'role' => 'retailer'])->assertUnprocessable()->assertJsonValidationErrors(['role']);`
     - Assert `User::where('email', 'retailer@example.com')->exists()` is `false`.

3. `it('rolls back customer creation atomically when token issuance fails')`:
   - Arrange:
     ```php
     $throwingIssuer = new class implements TokenIssuer {
         public function issue(User $user): TokenPairData { throw new RuntimeException('Token minting failed.'); }
         public function refresh(string $refreshToken): TokenPairData { throw new RuntimeException('Unused'); }
         public function revoke(User $user): void {}
         public function revokeAll(User $user): void {}
     };
     $this->app->instance(TokenIssuer::class, $throwingIssuer);
     ```
   - Act:
     - Run request `$this->postJson('/api/v1/auth/register', ['name' => 'Fail User', 'email' => 'fail@example.com', 'password' => 'secret1234']);`
   - Assert:
     - Expect response status 500.
     - Expect `User::where('email', 'fail@example.com')->exists()` to be `false`.

4. `it('authenticates customer retailer and superadmin returning 200 with auth result data and email casing tolerance')`:
   - Arrange: Create customer, retailer, and superadmin via `User::factory()`.
   - Act & Assert:
     - Customer login with lowercase: `$this->postJson('/api/v1/auth/login', ['email' => $customer->email, 'password' => 'password'])->assertOk()->assertJsonPath('data.user.role', 'customer');`
     - Retailer login with uppercase: `$this->postJson('/api/v1/auth/login', ['email' => strtoupper($retailer->email), 'password' => 'password'])->assertOk()->assertJsonPath('data.user.role', 'retailer');`
     - Superadmin login with surrounding whitespace: `$this->postJson('/api/v1/auth/login', ['email' => '  '.$admin->email.'  ', 'password' => 'password'])->assertOk()->assertJsonPath('data.user.role', 'superadmin');`

5. `it('returns identical 401 response for wrong password unknown email and soft-deleted user without leaking password')`:
   - Arrange: Create active user with password `'secret1234'`. Create user and soft-delete it.
   - Act:
     - `$res1 = $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'mypassword']);`
     - `$res2 = $this->postJson('/api/v1/auth/login', ['email' => $active->email, 'password' => 'wrongpassword']);`
     - `$res3 = $this->postJson('/api/v1/auth/login', ['email' => $deleted->email, 'password' => 'secret1234']);`
   - Assert:
     - `$res1->assertStatus(401);`
     - `$res2->assertStatus(401);`
     - `$res3->assertStatus(401);`
     - Expect `$res1->getContent()` to equal `$res2->getContent()`.
     - Expect `$res2->getContent()` to equal `$res3->getContent()`.
     - Expect `$res1->json('message')` to be `'Invalid credentials.'`.
     - Expect `$res1->getContent()` not to contain `'mypassword'`.

6. `it('serializes login and register responses strictly matching architecture specifications without sensitive fields')`:
   - Arrange: Create user. Log in.
   - Act: `$response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();`
   - Assert:
     - Expect array keys of `data.user` to be `['id', 'name', 'email', 'role', 'created_at', 'updated_at']`.
     - Expect array keys of `data.tokens` to be `['access_token', 'refresh_token', 'token_type', 'expires_in']`.
     - Expect `$response->getContent()` not to contain `password`.
     - Expect `$response->getContent()` not to contain `remember_token`.

7. `it('issues tokens that successfully authenticate user endpoint and rotate via refresh endpoint')`:
   - Arrange: Create superadmin user.
   - Act:
     - Login: `$loginRes = $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertOk();`
     - Extract `$accessToken = $loginRes->json('data.tokens.access_token');`
     - Extract `$refreshToken = $loginRes->json('data.tokens.refresh_token');`
     - Call users endpoint with Bearer token:
       `$this->withHeader('Authorization', 'Bearer '.$accessToken)->getJson("/api/v1/users/{$admin->public_id}")->assertOk();`
     - Call refresh endpoint with refresh token:
       `$refreshRes = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken])->assertOk();`
   - Assert:
     - Expect `$refreshRes->json('data.tokens.access_token')` to be string.
     - Expect `$refreshRes->json('data.tokens.refresh_token')` not to equal `$refreshToken`.

8. `it('throttles excessive login and register attempts returning 429')`:
   - Arrange: `RateLimiter::clear('auth-login:test@example.com|127.0.0.1'); RateLimiter::clear('auth-register:127.0.0.1');`
   - Act & Assert:
     - Send 5 invalid login requests: all return 401. 6th attempt returns 429.
     - Send 10 invalid register requests: all return 422. 11th attempt returns 429.
     - `RateLimiter::clear(...)` after test completion.

9. `it('finds user by exact email excluding soft-deleted records')`:
   - Arrange: Create user with email `'target@example.com'`. Create and soft-delete user `'deleted@example.com'`.
   - Act:
     - `$repo = app(UserRepositoryInterface::class);`
   - Assert:
     - Expect `$repo->findByEmail('target@example.com')?->id` to equal `$user->id`.
     - Expect `$repo->findByEmail('TARGET@EXAMPLE.COM')` to be `null` (exact match).
     - Expect `$repo->findByEmail('deleted@example.com')` to be `null`.
     - Expect `$repo->findByEmail('nonexistent@example.com')` to be `null`.

10. `it('registers auth routes without collision against token stream routes')`:
    - Arrange: Inspect routes registered in Laravel routing table.
    - Act: Map routes under `api/v1/auth`.
    - Assert:
      - Contains `POST api/v1/auth/register` (`AuthController::register`).
      - Contains `POST api/v1/auth/login` (`AuthController::login`).
      - Contains `POST api/v1/auth/refresh` (`TokenController::refresh`).
      - Contains `POST api/v1/auth/logout` (`TokenController::logout`).

---

### 11. Manual verification

1. Run `php artisan route:list --path=api/v1/auth`:
   Verify that `register`, `login`, `refresh`, and `logout` all appear without URI or route name collisions.
2. Run `php artisan test tests/Feature/Accounts/AuthEndpointsTest.php`:
   Verify all 10 feature tests pass.
3. Run `composer check`:
   Verify `pint`, `phpstan` (Level 8, 0 errors), and all Pest tests pass.
4. Run Layer A step-specific checks 15a through 15k.

---

### 12. Commit sequence

#### Commit 1: `feat(users): add authentication data transfer objects, domain exception, and repository method`
- Files:
  - `app/Domain/Accounts/Data/LoginData.php`
  - `app/Domain/Accounts/Data/AuthResultData.php`
  - `app/Domain/Accounts/Exceptions/InvalidCredentialsException.php`
  - `app/Domain/Accounts/Contracts/UserRepositoryInterface.php`
  - `app/Domain/Accounts/Repositories/UserRepository.php`
- Rationale: Establishes the foundational DTOs, domain exception, and repository contract addition before service orchestration.
- Verification: `composer stan` passes.

#### Commit 2: `feat(users): implement auth service with atomic registration and credential verification`
- Files:
  - `app/Domain/Accounts/Services/AuthService.php`
- Rationale: Implements core business logic orchestrating registration within database transactions and credential verification with timing equalization.
- Verification: `composer stan` passes.

#### Commit 3: `feat(users): add auth form requests, controller, routes, and rate limiters`
- Files:
  - `app/Http/Requests/Auth/RegisterRequest.php`
  - `app/Http/Requests/Auth/LoginRequest.php`
  - `app/Http/Controllers/Api/V1/Auth/AuthController.php`
  - `routes/api/auth.php`
  - `routes/api.php` (CR-1)
  - `app/Providers/AppServiceProvider.php`
  - `bootstrap/app.php`
- Rationale: Implements HTTP delivery layer, validation, rate limiting, exception translation, and route mounting.
- Verification: `php artisan route:list --path=api/v1/auth` shows routes; `composer lint` passes.

#### Commit 4: `test(users): add comprehensive authentication endpoint feature tests`
- Files:
  - `tests/Feature/Accounts/AuthEndpointsTest.php`
- Rationale: Adds feature tests covering AC-1 through AC-10.
- Verification: `composer check` passes.

#### Commit 5: `docs(users): add ACC-U4 blueprint plan and report documentation`
- Files:
  - `docs/blueprints/ACC-U4/plan.md`
  - `docs/blueprints/ACC-U4/report.md`
  - `docs/blueprints/ACC-U4/closeout.md`
- Rationale: Records blueprint deliverables and completion evidence.

---

### 13. Risks, assumptions, conflicts

#### Open Decisions from Step Brief:
- **OD-1: Branch base.**
  - Recommended: Branch `feat/users-auth-endpoints` from `feat/users-http-layer` (or `main` once `ACC-U3` is merged).
  - Confirmation required from Achintha: Confirm branch base.
- **OD-2: Can a Superadmin log in through `/auth/login`?**
  - Resolved per `ARCHITECTURE.md` §3 rule 5 and §7 table: **Yes**. Superadmin, retailer, and customer can all authenticate through `POST /api/v1/auth/login`.
- **OD-3: Register response and `role` in payload.**
  - Resolved: **201 with `AuthResultData`** (auto-login with token pair). Passing `role` in the request payload is rejected with **422** via `['role' => ['prohibited']]` in `RegisterRequest` to provide immediate feedback that public self-registration cannot create retailer or superadmin accounts.
- **OD-4: Credential-failure contract.**
  - Resolved: Uniform **401** with byte-identical payload `{"message": "Invalid credentials."}` across unknown email, wrong password, and soft-deleted user. Timing equalization is performed via constant pre-hashed 4-round bcrypt literal (`self::DUMMY_HASH = '$2y$04$ebgElBN/lGg9/9VJwLY1t.S5psdZy3y1W/E3EahnHCEnu8hO333eW'`), which executes cleanly under test and production bcrypt settings without invoking `Hash::make`.
- **OD-5: Rate limits.**
  - Resolved: `auth-login` limited to **5 requests per minute** keyed by `strtolower(trim($email)).'|'.$request->ip()`. `auth-register` limited to **10 requests per minute** keyed by `$request->ip()`. Configured as named rate limiters in `AppServiceProvider`.
- **OD-6: Step size.**
  - Resolved: **One single step** (`ACC-U4`) within the 13-file ceiling.

#### Known Traps and Architectural Considerations:
- **CR-1 (Route Seam Inclusion)**: `routes/api.php` currently includes `routes/api/tokens.php` and `routes/api/users.php`. Adding `require __DIR__.'/api/auth.php';` is an authorized route seam modification per `ARCHITECTURE.md` §5.
- **Atomic Registration**: `UserService::store` and `TokenIssuer::issue` execute within `DB::transaction()`. If Passport client lookup or token generation fails, no user record is persisted.
- **Accepted Trade-off (Email Enumeration on Register)**: `POST /api/v1/auth/register` returns 422 when an email already exists. As documented in `ARCHITECTURE.md` and brief trap 13, this is an accepted trade-off because there is no email verification flow; abuse is mitigated by the 10/min rate limiter.
- **Token Revocation Window**: Multiple logins produce multiple valid token pairs. Token revocation on logout/deletion is deferred to `ACC-U5`.
- **SuperadminSeeder Email Format**: `SuperadminSeeder` writes `config('auth.superadmin.email')` directly. The default value `'superadmin@getnow.test'` is lowercase and trimmed. If an environment sets a mixed-case `SUPERADMIN_EMAIL`, the seeder would write that exact value without `strtolower(trim())`.

---

### 14. Dependencies

No new composer packages or npm packages are introduced. All functionality utilizes native PHP 8.4, Laravel framework components (`Hasher`, `RateLimiter`, `DB`, `FormRequest`), and existing project dependencies (`spatie/laravel-data`, `laravel/passport`, `pestphp/pest`).
