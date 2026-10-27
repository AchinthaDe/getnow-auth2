# [ACC-U5 | PLAN v3 | Gemini]

### Change log (v3)
- M1 > Section 10 (Test 8): Specified full Arrange in exact order: create `superadmin`, `user1` to `user3`, capture `$originalHash1`, issue superadmin token with real issuer, then bind throwing fake.
- M2 > Section 10 (Test 7): Issued retailer token with real issuer prior to binding spy; stated "issue tokens, then bind the spy"; added unchanged-row assertions for `otherCustomer` (name, deleted_at) and for `retailer`.
- M3 > Section 5.1: Replaced diff fragments with complete final bodies of `update` and `destroy`, explicitly stating the sequence: guards, `$shouldRevoke`, email normalization, and `DB::transaction`.
- M4 > Section 3 (Item 4): Quoted lines 48-98 of `PassportTokenIssuer::refresh` verbatim, demonstrating that it queries only `oauth_*` tables and never queries `users`, confirming that `POST /api/v1/auth/refresh` returning 401 discriminates Mutation 1.
- M5 > Section 13 (Item 2): Converted OD-1 to OD-6 from adopted decisions to open questions awaiting Achintha's answers, recording raw git outputs for OD-1.
- M6 > Section 7 (Flow 2), Section 13 (Item 4): Removed the unverified `ARCHITECTURE.md` Section 1 ellipsis citation and stated the concurrency window strictly as the brief's trap 10 decision.
- m1 > Section 10: Typed `$test` as `Tests\TestCase`, added `void` return types to helpers, renamed helpers to `assertUserTokenWorks` and `assertUserTokenRevoked` to eliminate collision risk, dropped JSON message body assertions, and verified `'refresh_token'` key against `RefreshTokenRequest.php:25`.
- m2 > Section 10: Stated the `token-refresh` rate limit (10 per minute per IP via `TokenServiceProvider.php:43-45`), provided worst-case refresh call counts per test (max 3), and verified that `Cache::flush()` in `beforeEach` prevents HTTP 429.
- m3 > Section 3 (Item 3): Cited the exact trait `Illuminate\Foundation\Testing\RefreshDatabase` in `tests/Pest.php:23-24` for test transaction and savepoint behavior.
- m4 > Sections 4, 11: Aligned manifest to `ARCHITECTURE.md` Sections 7 and 11, removed the Section 10 non-edit, quoted exact table rows from `ARCHITECTURE.md:323-327`, and used free ID `OQ8`.
- m5 > Section 9 (AC-9): Listed Layer A checks 15a through 15j and the named test suites (`tests/Arch`, `tests/Feature/Tokens`, `tests/Feature/Accounts`).
- m6 > Section 10 (Test 8): Replaced try/catch blocks with `expect(fn () => ...)->toThrow(RuntimeException::class, 'Revocation failed.')`.
- m7 > Section 10: Configured `assertUserTokenRevoked` to probe `/api/v1/users` instead of `/api/v1/users/{public_id}`, eliminating route model binding resolution and avoiding HTTP 404 risk.

---

### 1. Understanding
Close the token-revocation window in `UserService` by calling `TokenIssuer::revokeAll($user)` inside an atomic database transaction on user soft deletion, on updates where a `password` is supplied, and on updates where `role` differs from the persisted role. HTTP endpoint statuses, payload shapes, and responses remain unchanged (`200` with `UserResponseData` on update, `204` No Content on delete).

Out of scope:
- Single-session or revoke-on-login behavior (OD-6), logout-all endpoints, per-device session management, and revocation on name-only or email-only updates.
- Restore, force delete, bulk operations, and token database row pruning or access-token deny-lists.
- Modifications to `TokenIssuer`, `TokenPairData`, `PassportTokenIssuer`, `TokenController`, `TokenServiceProvider`, `config/passport.php`, `routes/api.php`, or `routes/api/tokens.php`.
- Modifications to `UserController`, `UserPolicy`, `StoreUserRequest`, `UpdateUserRequest`, `UserResponseData`, `AuthService`, authentication requests or controllers, `routes/api/users.php`, or `routes/api/auth.php`.
- Database migrations, model alterations, factory changes, or seeder changes (`DBS2`).
- Email verification, password reset workflows, two-factor authentication, and admin panel interfaces.

---

### 2. Sources read

| File or Document Section | What in it constrains this step |
|---|---|
| [AGENTS.md](file:///c:/dev/getnow-auth2/AGENTS.md) | Layering flow (FormRequest -> Controller -> Service -> RepositoryInterface); controllers thin; services hold business rules only; no `Illuminate\Http` in Domain; `declare(strict_types=1);` in every file; strict type hints and final classes; role string literals forbidden; no `Hash::make` in application code; deleting a user, changing a password, or changing a role calls `TokenIssuer::revokeAll`; users stream tests use fakes while tokens stream tests use real Passport. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L45-L72) §4 | Layering definitions: `UserService` orchestrates domain logic and depends on `UserRepositoryInterface` and `TokenIssuer`. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L73-L139) §5 | Namespace boundaries: `App\Domain\Accounts\Services\UserService` belongs to Users stream. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L140-L176) §6 | `TokenIssuer` contract interface and `TokenPairData`. Users stream codes against interface. `revokeAll(User $user): void` revokes all active access and refresh tokens for that user. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L177-L201) §7 | Endpoint definitions: `PATCH /users/{user}` and `DELETE /users/{user}` are restricted to `superadmin`. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L202-L229) §8 | Security guidelines: passwords hashed only via `hashed` cast on `User`; partial updates write only non-`Optional` fields. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L287-L292) §10 | Checkpoint for `ACC-U5`: token revocation on delete, password change, and role change. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L302-L337) §11 | Security decisions: tokens are revoked on delete, password change, and role change; authorization is server-side through `UserPolicy`. |
| [docs/blueprints/RULE_REGISTER.md](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md) | Governs the 75 rule IDs evaluated in Section 8 and establishes Layer A commands. |
| [docs/blueprints/ACC-U5/brief.md](file:///c:/dev/getnow-auth2/docs/blueprints/ACC-U5/brief.md) | Scope, dependencies, acceptance criteria AC-1 through AC-10, size guard (5 files ceiling), open decisions OD-1 through OD-6, rules at risk, known traps 1 through 12, Layer A checks 15a through 15j, and mutation spot-checks. |
| [docs/adr/0001-passport-token-issuance-and-refresh-rotation.md](file:///c:/dev/getnow-auth2/docs/adr/0001-passport-token-issuance-and-refresh-rotation.md) | Tokens carry empty default scopes; authorization is enforced server-side via `UserPolicy` on `User::$role`. `revokeAll(User $user)` revokes all active access tokens and associated refresh tokens in `oauth_access_tokens` and `oauth_refresh_tokens`. |
| [docs/adr/0002-remove-starter-kit.md](file:///c:/dev/getnow-auth2/docs/adr/0002-remove-starter-kit.md) | Documents removal of Fortify and starter kit scaffolding. |
| [docs/adr/0003-user-soft-deletes-and-schema-cleanup.md](file:///c:/dev/getnow-auth2/docs/adr/0003-user-soft-deletes-and-schema-cleanup.md) | Documents soft-delete mechanics via `deleted_at` column. |
| [routes/api/auth.php](file:///c:/dev/getnow-auth2/routes/api/auth.php) | Defines guest authentication endpoints `POST /api/v1/auth/register` and `POST /api/v1/auth/login`. |
| [routes/api/users.php](file:///c:/dev/getnow-auth2/routes/api/users.php) | Defines `users` resource endpoints under `auth:api` middleware. Every route requires authentication; `UserPolicy` restricts access to `superadmin`. |
| [database/factories/UserFactory.php](file:///c:/dev/getnow-auth2/database/factories/UserFactory.php) | Defines model factory states: `superadmin()`, `retailer()`, `customer()`, `unverified()`, and default password `'password'`. Used for test fixtures. |
| [app/Domain/Accounts/Services/UserService.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Services/UserService.php) | Target file: contains `__construct(UserRepositoryInterface $users)`, `index`, `show`, `store`, `update`, `destroy`. Currently lacks `TokenIssuer` injection and revocation logic. |
| [app/Domain/Accounts/Data/UserData.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Data/UserData.php) | Properties: `public string|Optional $name`, `public string|Optional $email`, `public string|Optional $password`, `public UserRole|Optional $role`. |
| [app/Domain/Accounts/Exceptions/ProtectedUserException.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Exceptions/ProtectedUserException.php) | Factory methods: `cannotCreateSuperadmin()`, `cannotPromoteToSuperadmin()`, `cannotDeleteSuperadmin()`, `cannotChangeSuperadminRole()`. |
| [app/Domain/Accounts/Contracts/UserRepositoryInterface.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Contracts/UserRepositoryInterface.php) | Method signatures: `update(User $user, UserData $data): User;`, `destroy(User $user): void;`. |
| [app/Domain/Accounts/Repositories/UserRepository.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Repositories/UserRepository.php) | Implements `update` (writes non-`Optional` fields to `$user->update($attributes)`) and `destroy` (executes `$user->delete()`). |
| [app/Domain/Accounts/Services/AuthService.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Services/AuthService.php) | Precedent for importing `App\Domain\Accounts\Contracts\TokenIssuer` and wrapping domain operations in `Illuminate\Support\Facades\DB::transaction`. |
| [app/Domain/Accounts/Contracts/TokenIssuer.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Contracts/TokenIssuer.php) | Interface declaring `public function revokeAll(User $user): void;`. |
| [app/Domain/Accounts/Tokens/PassportTokenIssuer.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Tokens/PassportTokenIssuer.php) | Implementation of `revokeAll`: queries `oauth_access_tokens` for `$userId`, sets `revoked = true` on access tokens and matching `oauth_refresh_tokens`. Implementation of `refresh`: throws `AuthenticationException` when access token or refresh token is revoked. |
| [app/Http/Controllers/Api/V1/Auth/TokenController.php](file:///c:/dev/getnow-auth2/app/Http/Controllers/Api/V1/Auth/TokenController.php) | Endpoints `POST /api/v1/auth/refresh` and `POST /api/v1/auth/logout`. Catches `AuthenticationException` and returns 401 JSON. |
| [app/Http/Controllers/Api/V1/Users/UserController.php](file:///c:/dev/getnow-auth2/app/Http/Controllers/Api/V1/Users/UserController.php) | Thin resource controller: `update` delegates to `UserService::update`, `destroy` authorizes `delete` and delegates to `UserService::destroy`. |
| [app/Domain/Accounts/Policies/UserPolicy.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Policies/UserPolicy.php) | Authorization policy: `update` and `delete` return `$actor->role === UserRole::Superadmin;`. |
| [app/Http/Requests/Users/UpdateUserRequest.php](file:///c:/dev/getnow-auth2/app/Http/Requests/Users/UpdateUserRequest.php) | Form request: `authorize` executes `$actor->can('update', $user)`. Rules define `sometimes` for `name`, `email`, `password`, `role`. Normalizes email, outputs `UserData`. |
| [app/Providers/TokenServiceProvider.php](file:///c:/dev/getnow-auth2/app/Providers/TokenServiceProvider.php) | Binds `TokenIssuer::class` to `PassportTokenIssuer::class` in application container. |
| [database/seeders/SuperadminSeeder.php](file:///c:/dev/getnow-auth2/database/seeders/SuperadminSeeder.php) | Updates superadmin via direct Eloquent `$user->update(...)`, bypassing `UserService`. |
| [tests/Feature/Accounts/UserServiceTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Accounts/UserServiceTest.php) | Resolves service exclusively via container (`app(UserService::class)`). |
| [tests/Feature/Accounts/UserControllerTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Accounts/UserControllerTest.php) | Verifies that customers and retailers are forbidden (403) from accessing or modifying user records. |
| [tests/Feature/Accounts/AuthEndpointsTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Accounts/AuthEndpointsTest.php) | Pattern for binding throwing anonymous `TokenIssuer` implementation into container. |
| [tests/Feature/Tokens/TokenEndpointsTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Tokens/TokenEndpointsTest.php) | Demonstrates testing Passport Bearer token authentication and token rotation. |
| [tests/Support/InteractsWithPassportKeys.php](file:///c:/dev/getnow-auth2/tests/Support/InteractsWithPassportKeys.php) | Trait providing test RSA keys and cache flushing for Passport. |
| [tests/Arch/AccountsArchTest.php](file:///c:/dev/getnow-auth2/tests/Arch/AccountsArchTest.php) | Enforces architecture rules: `App\Domain` does not use `App\Http`; `Laravel\Passport` isolated to Tokens stream and `User`; controllers do not use `DB`. |

---

### 3. Verified current state

1. `App\Domain\Accounts\Services\UserService` ([UserService.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Services/UserService.php)):
   Current constructor and verbatim bodies of `update` and `destroy`:
   ```php
   public function __construct(
       private readonly UserRepositoryInterface $users,
   ) {}

   public function update(User $user, UserData $data): User
   {
       if ($user->role === UserRole::Superadmin) {
           if (! $data->role instanceof Optional) {
               throw ProtectedUserException::cannotChangeSuperadminRole();
           }
       } else {
           if (! $data->role instanceof Optional && $data->role === UserRole::Superadmin) {
               throw ProtectedUserException::cannotPromoteToSuperadmin();
           }
       }

       $email = ! $data->email instanceof Optional
           ? strtolower(trim($data->email))
           : new Optional;

       $normalizedData = new UserData(
           name: $data->name,
           email: $email,
           password: $data->password,
           role: $data->role,
       );

       return $this->users->update($user, $normalizedData);
   }

   public function destroy(User $user): void
   {
       if ($user->role === UserRole::Superadmin) {
           throw ProtectedUserException::cannotDeleteSuperadmin();
       }

       $this->users->destroy($user);
   }
   ```
2. `PassportTokenIssuer::revokeAll` verification ([PassportTokenIssuer.php:121-143](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Tokens/PassportTokenIssuer.php#L121-L143)):
   ```php
   public function revokeAll(User $user): void
   {
       $userId = $user->getAuthIdentifier();

       /** @var array<int, string> $tokenIds */
       $tokenIds = Passport::token()->newQuery()
           ->where('user_id', $userId)
           ->where('revoked', false)
           ->pluck('id')
           ->all();

       Passport::token()->newQuery()
           ->where('user_id', $userId)
           ->where('revoked', false)
           ->update(['revoked' => true]);

       if (! empty($tokenIds)) {
           Passport::refreshToken()->newQuery()
               ->whereIn('access_token_id', $tokenIds)
               ->where('revoked', false)
               ->update(['revoked' => true]);
       }
   }
   ```
   - **Conclusion**: Revokes all active access tokens and all associated refresh tokens.
   - **User isolation**: Scoped strictly to `$userId`; other users' tokens in `oauth_access_tokens` and `oauth_refresh_tokens` are untouched.
   - **Zero tokens case**: If the user holds zero tokens, `$tokenIds` is an empty array `[]`, the access token query executes an update touching 0 rows, the `if (! empty($tokenIds))` branch is bypassed, and the method completes cleanly without exception.
3. Database transactions and nested transactions ([tests/Pest.php:23-24](file:///c:/dev/getnow-auth2/tests/Pest.php#L23-L24)):
   The test environment uses `Illuminate\Foundation\Testing\RefreshDatabase` (registered in `tests/Pest.php:23-24`), wrapping each feature test in a database transaction. When application code executes `DB::transaction(...)`, Laravel creates a PostgreSQL savepoint (`SAVEPOINT trans1`). Any rollback inside `DB::transaction(...)` rolls back to the savepoint without corrupting outer test fixtures.
4. Soft-delete probe and mutation sensitivity ([PassportTokenIssuer.php:48-98](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Tokens/PassportTokenIssuer.php#L48-L98)):
   Verbatim lines 48-98 of `PassportTokenIssuer::refresh`:
   ```php
       return DB::transaction(function () use ($payload): TokenPairData {
           /** @var RefreshToken|null $consumedRefreshToken */
           $consumedRefreshToken = Passport::refreshToken()->newQuery()
               ->whereKey($payload['refresh_token_id'])
               ->lockForUpdate()
               ->first();

           if ($consumedRefreshToken === null || $consumedRefreshToken->revoked) {
               throw new AuthenticationException('Unauthenticated.');
           }

           /** @var Token|null $priorAccessToken */
           $priorAccessToken = Passport::token()->newQuery()
               ->whereKey($payload['access_token_id'])
               ->lockForUpdate()
               ->first();

           if ($priorAccessToken === null || $priorAccessToken->revoked) {
               throw new AuthenticationException('Unauthenticated.');
           }

           /** @var Client|null $client */
           $client = Passport::client()->newQuery()
               ->whereKey($payload['client_id'])
               ->first();

           if ($client === null || $client->revoked) {
               throw new AuthenticationException('Unauthenticated.');
           }

           $now = time();
           $payloadExpired = $payload['expire_time'] < $now;
           $refreshExpired = $consumedRefreshToken->expires_at !== null && $consumedRefreshToken->expires_at->isPast();
           $accessExpired = $priorAccessToken->expires_at !== null && $priorAccessToken->expires_at->isPast();

           if ($payloadExpired || $refreshExpired || $accessExpired) {
               throw new AuthenticationException('Unauthenticated.');
           }

           if (
               $priorAccessToken->user_id === null ||
               (string) $priorAccessToken->user_id !== (string) $payload['user_id']
           ) {
               throw new AuthenticationException('Unauthenticated.');
           }

           $consumedRefreshToken->revoke();
           $priorAccessToken->revoke();

           return $this->mintTokenPair($priorAccessToken->user_id, $client);
       });
   ```
   - **Conclusion**: `refresh()` inspects only `oauth_refresh_tokens`, `oauth_access_tokens`, and `oauth_clients`. It never queries the `users` table. If `revokeAll` is omitted on user deletion (Mutation 1), `consumedRefreshToken->revoked` and `priorAccessToken->revoked` remain false, allowing `refresh()` to mint new tokens. Asserting that `POST /api/v1/auth/refresh` returns HTTP 401 for a soft-deleted user strictly requires `revokeAll` to have run, directly discriminating Mutation 1.
5. `App\Domain\Accounts\Contracts\TokenIssuer` ([TokenIssuer.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Contracts/TokenIssuer.php)):
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
6. `App\Domain\Accounts\Contracts\UserRepositoryInterface` ([UserRepositoryInterface.php](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Contracts/UserRepositoryInterface.php)):
   `public function update(User $user, UserData $data): User;`
   `public function destroy(User $user): void;`
7. `App\Domain\Accounts\Policies\UserPolicy` ([UserPolicy.php:27-35](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Policies/UserPolicy.php#L27-L35)):
   `update` and `delete` return `$actor->role === UserRole::Superadmin;`. Both operations are restricted to `Superadmin` actors only. Customers and Retailers receive `403 Forbidden` from `UpdateUserRequest::authorize()`.
8. `App\Http\Controllers\Api\V1\Users\UserController` ([UserController.php:76-92](file:///c:/dev/getnow-auth2/app/Http/Controllers/Api/V1/Users/UserController.php#L76-L92)):
   `update` calls `$this->userService->update($user, $request->toDto());` and returns HTTP 200 with `UserResponseData`.
   `destroy` authorizes `delete`, calls `$this->userService->destroy($user);`, and returns HTTP 204.
9. `App\Providers\TokenServiceProvider` ([TokenServiceProvider.php:29](file:///c:/dev/getnow-auth2/app/Providers/TokenServiceProvider.php#L29)):
   Binds `TokenIssuer::class` to `PassportTokenIssuer::class`.
10. Call sites and test resolution:
    Application code: [UserController.php](file:///c:/dev/getnow-auth2/app/Http/Controllers/Api/V1/Users/UserController.php) lines 78 and 89.
    Tests: [UserServiceTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Accounts/UserServiceTest.php) resolves `UserService` exclusively via `app(UserService::class)`. Zero files in the repository execute `new UserService(...)`.

---

### 4. File manifest

| Path | New or Modify | Layer | Responsibility | Rule IDs |
|---|---|---|---|---|
| `app/Domain/Accounts/Services/UserService.php` | Modify | Domain (Service) | Injects `TokenIssuer`, wraps update and destroy in `DB::transaction`, triggers `revokeAll` on password update, role change, and user destruction | STM1, STM3, LAY2, LAY3, ACT8, STD1, STD2 |
| `tests/Feature/Accounts/UserTokenRevocationTest.php` | New | Testing | Feature tests verifying token revocation across all AC-1 through AC-8 scenarios using real Bearer tokens and fakes | TST1, TST2, TST3, TST4, ACT8, TOK5 |
| `docs/adr/0004-token-revocation-on-account-changes.md` | New | Documentation (ADR) | Records revocation triggers, fail-closed database transaction semantics, empty scopes policy evaluation, and session termination | DNT7, ACT8, TOK2 |
| `ARCHITECTURE.md` | Modify | Documentation | Documents revocation behavior in Sections 7 and 11, and links ADR 0004 | DNT7, ACT8 |

File count guard: Exactly 4 files (2 modified, 2 new), satisfying the 5-file ceiling.

---

### 5. Interface specs

#### 5.1 `App\Domain\Accounts\Services\UserService`
- Path: `app/Domain/Accounts/Services/UserService.php`
- Strict types: `declare(strict_types=1);`
- Class declaration: `final class UserService`
- Constructor:
  ```php
  public function __construct(
      private readonly UserRepositoryInterface $users,
      private readonly TokenIssuer $tokens,
  ) {}
  ```
- Method `update(User $user, UserData $data): User`:
  Order of execution:
  1. `ProtectedUserException` guard checks on superadmin role transitions (unchanged).
  2. Calculation of `$shouldRevoke` checking presence of password or differing role:
     `$shouldRevoke = (! $data->password instanceof Optional) || (! $data->role instanceof Optional && $data->role !== $user->role);`
  3. Email normalization and creation of `$normalizedData` sits immediately prior to the transaction (outside `DB::transaction`).
  4. `DB::transaction` wraps `$this->users->update($user, $normalizedData)` and conditional `$this->tokens->revokeAll($user)`.
  Complete final method body:
  ```php
  public function update(User $user, UserData $data): User
  {
      if ($user->role === UserRole::Superadmin) {
          if (! $data->role instanceof Optional) {
              throw ProtectedUserException::cannotChangeSuperadminRole();
          }
      } else {
          if (! $data->role instanceof Optional && $data->role === UserRole::Superadmin) {
              throw ProtectedUserException::cannotPromoteToSuperadmin();
          }
      }

      $shouldRevoke = (! $data->password instanceof Optional)
          || (! $data->role instanceof Optional && $data->role !== $user->role);

      $email = ! $data->email instanceof Optional
          ? strtolower(trim($data->email))
          : new Optional;

      $normalizedData = new UserData(
          name: $data->name,
          email: $email,
          password: $data->password,
          role: $data->role,
      );

      return DB::transaction(function () use ($user, $normalizedData, $shouldRevoke): User {
          $updatedUser = $this->users->update($user, $normalizedData);

          if ($shouldRevoke) {
              $this->tokens->revokeAll($user);
          }

          return $updatedUser;
      });
  }
  ```
- Method `destroy(User $user): void`:
  Order of execution:
  1. `ProtectedUserException::cannotDeleteSuperadmin()` guard check (unchanged).
  2. `DB::transaction` wraps `$this->users->destroy($user)` and `$this->tokens->revokeAll($user)`.
  Complete final method body:
  ```php
  public function destroy(User $user): void
  {
      if ($user->role === UserRole::Superadmin) {
          throw ProtectedUserException::cannotDeleteSuperadmin();
      }

      DB::transaction(function () use ($user): void {
          $this->users->destroy($user);
          $this->tokens->revokeAll($user);
      });
  }
  ```
- Preserved unmodified: `index`, `show`, `store`.

#### 5.2 `docs/adr/0004-token-revocation-on-account-changes.md`
- Status: Accepted
- Title: Token Revocation on Account Changes
- Content:
  - Context: Describes the security window where active JWT access tokens and refresh tokens remained valid after account deletion, credential updates, or role transitions.
  - Decision 1 (Triggers): Revocation executed on: (1) user soft delete (`destroy`), (2) presence of `password` in `UserData` update, and (3) presence of `role` in `UserData` update differing from the current persisted role. Name-only, email-only, and identical-role updates do not revoke.
  - Decision 2 (Atomicity & Fail-Closed): Revocation runs within the same database transaction as the account update or delete. If `revokeAll` throws, the database changes roll back and the request terminates with HTTP 500.
  - Decision 3 (Self-Update Session Termination): Under current authorization policies, only a `Superadmin` can update their own record via `PATCH /api/v1/users/{self}`. When a superadmin changes their own password, all existing tokens are revoked. The current request completes with HTTP 200, but subsequent requests with the prior token receive HTTP 401 Unauthenticated. Customer and Retailer self-updates over HTTP remain forbidden by `UserPolicy` until a future step introduces self-service endpoints.
  - Decision 4 (Empty Scopes & Authorization): OAuth2 access tokens contain empty default scopes per ADR 0001. User role is read from the database on every request and evaluated server-side via `UserPolicy`. A role change takes effect immediately on database commit. Revocation on role change invalidates active access tokens and refresh tokens, ending existing sessions and forcing re-authentication across all client devices, but is not the mechanism that enforces the new role.

---

### 6. Data and migrations

None. Zero migrations, zero schema alterations, and zero column additions (`DBS2`). Revocation alters existing rows in Passport tables `oauth_access_tokens` and `oauth_refresh_tokens` via `PassportTokenIssuer::revokeAll`.

---

### 7. Behavior and flows

#### Flow 1: Superadmin Deletes User (`DELETE /api/v1/users/{public_id}`)
- Happy path:
  1. Superadmin submits request with valid Bearer token.
  2. `UserController::destroy` authorizes action via `UserPolicy::delete`.
  3. `UserService::destroy` verifies target user is not `Superadmin`.
  4. Inside `DB::transaction`:
     - Calls `$this->users->destroy($user)`, which executes `$user->delete()` (sets `deleted_at`).
     - Calls `$this->tokens->revokeAll($user)`, which sets `revoked = true` on all active access and refresh tokens for that user ID.
  5. Transaction commits. Controller returns HTTP 204 No Content.
  6. Subsequent API requests with the target's access token fail with HTTP 401 Unauthenticated.
  7. Subsequent refresh requests with the target's refresh token fail with HTTP 401 Unauthenticated.
  8. Bystander users' access and refresh tokens continue operating without interruption.
- Failure paths:
  - Target user is `Superadmin`: `UserService::destroy` throws `ProtectedUserException::cannotDeleteSuperadmin()`. Exception handler returns HTTP 403. No database update or revocation occurs.
  - Actor is `Customer` or `Retailer`: `UserController::destroy` authorization fails via `UserPolicy::delete`. Returns HTTP 403 Forbidden.
  - `TokenIssuer::revokeAll` throws exception: database transaction rolls back. Target user is not soft-deleted. Endpoint returns HTTP 500.
- Activity log: Accounts module does not implement an activity log service or database table. No log entry is written.
- Concurrency note: User deletion utilizes database transaction wrapping. If an incoming request authenticates against the target user concurrently, Passport authenticates against the committed database state. Once committed, the user is soft-deleted and all tokens are revoked.

#### Flow 2: Superadmin Updates User Password (`PATCH /api/v1/users/{public_id}`)
- Happy path:
  1. Superadmin submits request containing a new valid `password`.
  2. `UpdateUserRequest` validates payload and generates `UserData(password: 'new-pass')`.
  3. `UserService::update` detects `$data->password` is not `Optional`. Sets `$shouldRevoke = true`.
  4. Inside `DB::transaction`:
     - Calls `$this->users->update($user, $normalizedData)`.
     - Calls `$this->tokens->revokeAll($user)`.
  5. Transaction commits. Controller returns HTTP 200 with `UserResponseData`.
  6. Superadmin's own Bearer token remains valid and functional.
  7. All prior access tokens and refresh tokens for the target user now fail with HTTP 401.
  8. Client can immediately log in via `POST /api/v1/auth/login` using the new password to receive a new working token pair.
- Failure paths:
  - Validation failure: returns HTTP 422. Tokens remain valid.
  - Target is `Superadmin` and payload attempts role modification: returns HTTP 403. Tokens remain valid.
  - `TokenIssuer::revokeAll` throws: database transaction rolls back. Password hash in database remains unchanged. Returns HTTP 500.
- Activity log: None.
- Concurrency note: If a concurrent login request executes `Hash::check` against the old password immediately before this transaction commits, the login mints a token that revocation does not catch. Per brief trap 10, row-level locking during authentication is out of scope, and this window is accepted as a known operational risk.

#### Flow 3: Superadmin Self-Password Update (`PATCH /api/v1/users/{superadmin_public_id}`)
- Happy path (AC-3a):
  1. Superadmin updates their own password via `PATCH /api/v1/users/{self}`.
  2. `UserService::update` updates password and calls `$this->tokens->revokeAll($superadmin)`.
  3. The current request finishes and returns HTTP 200 with `UserResponseData`.
  4. On the subsequent request, the Bearer token used for the update is rejected with HTTP 401 Unauthenticated.
  5. Superadmin must log in again via `/api/v1/auth/login` with the new password.
- Activity log: None.
- Concurrency note: Handled inside database transaction. Revocation invalidates all tokens associated with the superadmin user ID.

#### Flow 4: Superadmin Updates User Role (`PATCH /api/v1/users/{public_id}`)
- Happy path (AC-4):
  1. Superadmin changes a `Customer` to `Retailer` (or `Retailer` to `Customer`).
  2. `UserService::update` detects `$data->role` is not `Optional` and `$data->role !== $user->role`. Sets `$shouldRevoke = true`.
  3. Inside `DB::transaction`, repository updates role and `revokeAll($user)` invalidates all target tokens.
  4. Returns HTTP 200. Target user's tokens fail with HTTP 401. Superadmin's token remains valid.
- Failure path:
  - Attempting to promote a user to `Superadmin`: throws `ProtectedUserException::cannotPromoteToSuperadmin()`. Returns HTTP 403. Tokens remain valid.
  - Attempting to change a `Superadmin` role: throws `ProtectedUserException::cannotChangeSuperadminRole()`. Returns HTTP 403. Tokens remain valid.
- Activity log: None.
- Concurrency note: Role is read from database on each request. The role change takes effect immediately on database commit. Revocation ensures existing client sessions must re-authenticate.

#### Flow 5: Non-Revoking Update (Name only, Email only, Identical Role)
- Happy path (AC-5):
  1. Superadmin updates name, email, or provides `role` identical to the target's existing role.
  2. `UserService::update` detects `$data->password instanceof Optional` AND (`$data->role instanceof Optional || $data->role === $user->role`).
  3. Evaluates `$shouldRevoke = false`.
  4. Calls repository update without calling `revokeAll`.
  5. Returns HTTP 200. Target user's access tokens and refresh tokens continue working without interruption.
- Activity log: None.
- Concurrency note: Standard database update. No token mutations or concurrency interactions occur.

#### Flow 6: Fail-Closed Atomicity Rollback
- Path (AC-7):
  1. `TokenIssuer::revokeAll` throws an unexpected runtime exception during password update, role change, or user deletion.
  2. `DB::transaction` catches the unhandled exception, executes a database rollback to the savepoint, and re-throws.
  3. Framework exception handler converts the failure into HTTP 500 Internal Server Error.
  4. Verified outcome: user is not soft-deleted, password hash remains unchanged, role remains unchanged.
- Activity log: None.
- Concurrency note: Rollback restores database state to the pre-transaction savepoint.

---

### 8. Rule compliance matrix

| Rule ID | Applies | How satisfied, or why N/A |
|---|---|---|
| STM1 | Y | Edits only Users stream paths (`app/Domain/Accounts/Services/UserService.php`, docs, tests). No files in Tokens stream or Contract seam modified. |
| STM2 | Y | `TokenIssuer` contract interface is imported without alteration. `UserService` imports `TokenIssuer` only and does not import `TokenPairData`. |
| STM3 | Y | `UserService` imports `App\Domain\Accounts\Contracts\TokenIssuer`. It never imports `Laravel\Passport` or `PassportTokenIssuer`. |
| STM4 | Y | Imported token code in `app/Domain/Accounts/Tokens/` remains untouched. |
| LAY1 | Y | Flow follows `FormRequest` -> `Controller` -> `Service` -> `RepositoryInterface` -> `Repository`. |
| LAY2 | Y | `UserService` imports no `Illuminate\Http` or `App\Http` classes. Exception handling translates through Laravel exception handler. |
| LAY3 | Y | `UserService` depends on `UserRepositoryInterface` and `TokenIssuer`. Contains business rules and transactions only. |
| LAY4 | Y | `UserRepository` executes database queries. It contains no revocation calls or HTTP exceptions. |
| LAY5 | Y | `UserService` retains its 5 canonical methods: `index`, `store`, `show`, `update`, `destroy`. |
| LAY6 | Y | `App\Domain` does not import `App\Http`. Verified by architecture test. |
| LAY7 | Y | All files reside in paths specified by `ARCHITECTURE.md` Section 5. |
| LAY8 | Y | Controller returns `UserResponseData` or empty 204. Never returns Eloquent model. |
| STD1 | Y | `declare(strict_types=1);` present in all new and modified PHP files. |
| STD2 | Y | Strict parameter, property, and return types across all methods. Classes declared `final`. |
| STD3 | Y | Uses `UserRole` enum cases for role checks. No string literals. |
| STD4 | Y | Single `UserData` DTO handles both creation and partial update via `Optional` properties. |
| STD5 | Y | `update` modifies only provided non-`Optional` fields. Untouched fields remain intact. Existing email normalization preserved. |
| STD6 | Y | Code passes `composer lint` (Pint) and `composer stan` (PHPStan Level 8) cleanly without baseline entries. |
| STD7 | Y | Explanatory architectural comments; zero commented-out code blocks. |
| ACT1 | N | Public registration handled in `AuthService`, untouched in this step. |
| ACT2 | N | Retailer creation handled via `POST /users`, untouched in this step. |
| ACT3 | Y | User endpoints remain protected server-side by `UserPolicy`. |
| ACT4 | Y | Superadmin protections in `UserService` prevent creation, promotion, demotion, or deletion of superadmins. |
| ACT5 | N | Login endpoint handled in `AuthService`, untouched in this step. |
| ACT6 | Y | Passwords hashed exclusively through `hashed` model cast. No `Hash::make` calls in application code. |
| ACT7 | N | Login credential verification error shapes untouched. |
| ACT8 | Y | Deleting a user, changing a password, or changing a role calls `TokenIssuer::revokeAll`. Core deliverable of this step. |
| ACT9 | N | Token issuance handled in `AuthService`. |
| ACT10 | Y | User responses serialize `UserResponseData`. Raw internal database ID is never exposed. |
| ACT11 | Y | Passwords and tokens are never logged. |
| TOK1 | Y | `TokenIssuer` implemented by `PassportTokenIssuer` in Tokens stream, bound in `TokenServiceProvider`. |
| TOK2 | Y | Lifetimes and scopes conform to ADR 0001 and ADR 0004. |
| TOK3 | Y | Passport dependencies and configuration untouched. |
| TOK4 | Y | Lifetimes configured via `config/passport.php`. |
| TOK5 | Y | Refresh token rotation and post-revocation failure verified via integration tests. |
| TOK6 | Y | Refresh and logout endpoints remain throttled. |
| DBS1 | Y | PostgreSQL database utilized exclusively. |
| DBS2 | Y | Zero migrations created or edited. |
| DBS3 | N | No schema drops or renames in this step. |
| DBS4 | N | No new database queries or schema constraints introduced in this step. |
| DBS5 | Y | Route model binding and URLs address users by ULID `public_id`. |
| DBS6 | Y | Seeders remain idempotent. |
| SCR1 | Y | No secrets, private keys, or `.env` files committed. |
| SCR2 | Y | Authorization and validation rules active across all paths. |
| SCR3 | Y | Server-side authorization enforced via `UserPolicy` and form requests. |
| SCR4 | Y | Rate limiters remain active on authentication routes. |
| TST1 | Y | Pest feature tests in `tests/Feature/Accounts/UserTokenRevocationTest.php`. |
| TST2 | Y | Covers failure and authorization cases: non-revoking updates, rejected operations, fail-closed rollbacks, token rejection. |
| TST3 | Y | Tests run against PostgreSQL with real Passport tokens via `InteractsWithPassportKeys`. |
| TST4 | Y | Test fixtures created via `UserFactory`. |
| TST5 | Y | Zero skipped, todo, or incomplete tests. |
| GIT1 | Y | Feature branch named `feat/users-token-revocation`. |
| GIT2 | Y | Conventional Commits with stream scope `feat(users):`, `test(users):`, `docs(users):`. |
| GIT3 | Y | No direct pushes to `main`. |
| GIT4 | N | Step belongs to Users stream; no contract PR required. |
| GIT5 | Y | Pull request description details changes, tests, and security considerations. |
| GIT6 | Y | Merge order adheres to `ARCHITECTURE.md` Section 10 (`ACC-U4` -> `ACC-U5`). |
| ENV1 | Y | Windows PowerShell commands executed individually without bash syntax or `&&`. |
| ENV2 | Y | Native PHP and PostgreSQL environment without Docker or Sail containers. |
| ENV3 | Y | Commands run with non-interactive flags. Zero background servers left active. |
| ENV4 | Y | `composer check` must pass cleanly and entire output reviewed. |
| DNT1 | Y | Zero new composer or npm packages added. |
| DNT2 | Y | Infrastructure and CI configurations untouched. |
| DNT3 | Y | Out-of-scope features excluded. |
| DNT4 | Y | Revocation orchestration encapsulated in `UserService`, not in controllers or models. |
| DNT5 | Y | No destructive database commands executed outside local test runner. |
| DNT6 | Y | No external keys or `.env` files imported. |
| DNT7 | Y | Updates `ARCHITECTURE.md` and records ADR 0004 in `docs/adr/`. |
| WFL1 | Y | Work proceeds strictly from approved plan. |
| WFL2 | Y | Deviations or ambiguities resolved via documented findings or change requests. |
| WFL3 | Y | Step concludes with implementation report containing raw terminal output. |
| WFL4 | Y | Done state determined solely by reviewer acceptance. |
| WFL5 | Y | Formal plan submitted and approved prior to code modifications. |
| WFL6 | Y | Scope restricted strictly to token revocation on account modifications. |
| WFL7 | Y | Open questions documented in Section 13. |

---

### 9. AC traceability

| AC ID | Requirement | Files | Exact Test Name in `tests/Feature/Accounts/UserTokenRevocationTest.php` | How Verified |
|---|---|---|---|---|
| **AC-1** | `DELETE /api/v1/users/{user}` as `Superadmin` revokes all target tokens: access token rejected with 401, refresh token rejected by `/auth/refresh`. Other user's tokens unaffected. | `UserService.php`, `UserTokenRevocationTest.php` | `it('revokes access and refresh tokens when user is soft deleted by superadmin while leaving bystander tokens intact')` | Issue tokens for Target and Bystander; superadmin deletes Target via `DELETE /api/v1/users/{public_id}`; assert 204; assert Target access and refresh tokens revoked via `assertUserTokenRevoked`; assert Bystander tokens work via `assertUserTokenWorks` (asserting 403 for Customer role). |
| **AC-2** | `PATCH /api/v1/users/{user}` with `password` revokes all target tokens. Old password login returns 401; new password login returns 200 with working tokens. Superadmin token remains valid. | `UserService.php`, `UserTokenRevocationTest.php` | `it('revokes tokens on password update and allows login only with new password while leaving superadmin token intact')` | Issue tokens for Target and Superadmin; superadmin updates Target password via `PATCH /api/v1/users/{public_id}`; assert 200; assert superadmin token works via `assertUserTokenWorks`; assert Target access and refresh tokens revoked via `assertUserTokenRevoked`; assert old password login fails with 401; assert new password login returns 200. |
| **AC-3a** | Superadmin self password change over HTTP: returns 200, caller token rejected with 401 on next request. | `UserService.php`, `UserTokenRevocationTest.php` | `it('revokes caller token upon superadmin self password update over http')` | Superadmin updates own password via `PATCH /api/v1/users/{admin_id}`; assert 200; assert same superadmin Bearer token rejected on next request via `assertUserTokenRevoked`. |
| **AC-3b** | Superadmin changing another user's password revokes target tokens; superadmin token works. | `UserService.php`, `UserTokenRevocationTest.php` | `it('revokes tokens on password update and allows login only with new password while leaving superadmin token intact')` | Covered in Test 2: verifies target tokens revoked and superadmin token works via `assertUserTokenWorks`. |
| **AC-3c** | `UserService::update` with a password for Customer revokes target access and refresh tokens. | `UserService.php`, `UserTokenRevocationTest.php` | `it('revokes customer access and refresh tokens upon domain service password update')` | Issue tokens for Customer; call `UserService::update` with new password; assert customer access and refresh tokens revoked via `assertUserTokenRevoked`. |
| **AC-4** | Role change between `Customer` and `Retailer` in both directions revokes target's tokens. Superadmin and bystanders unaffected. | `UserService.php`, `UserTokenRevocationTest.php` | `it('revokes tokens on role change between customer and retailer in both directions')` | Sub-case 1: Customer to Retailer; Sub-case 2: Retailer to Customer. For each: assert 200; assert Target tokens revoked via `assertUserTokenRevoked`; assert Bystander tokens work via `assertUserTokenWorks`; assert Superadmin token works. |
| **AC-5** | No-op updates do not revoke: name-only, email-only, and role equal to current role leave access and refresh tokens working. Isolated token pairs per case. | `UserService.php`, `UserTokenRevocationTest.php` | `it('preserves active tokens across name only email only and identical role updates with isolated token pairs')` | Sub-cases A (name), B (email), C (identical role) each issue a fresh token pair; update executed; access token verified working first via `assertUserTokenWorks`; refresh token verified rotating last via `/api/v1/auth/refresh`. |
| **AC-6** | Rejected operations do not revoke: deleting superadmin, promoting to superadmin, demoting superadmin, customer/retailer actor 403s, 404, or validation errors leave tokens working and database unchanged with zero revoke calls. | `UserService.php`, `UserTokenRevocationTest.php` | `it('preserves tokens and leaves database untouched when operations are rejected with zero revoke calls')` | Bind recording spy fake after issuing tokens; test delete superadmin (403), promote customer (403), demote superadmin (403), customer actor PATCH/DELETE (403), retailer actor PATCH/DELETE (403), unknown public_id (404), invalid email (422); assert spy call count is 0; assert database rows unchanged; assert all issued tokens work via `assertUserTokenWorks`. |
| **AC-7** | Fail closed: throwing `revokeAll` fake causes password update, role update, and delete to roll back leaving database unchanged. HTTP PATCH returns 500. | `UserService.php`, `UserTokenRevocationTest.php` | `it('rolls back database modifications atomically when revoke all throws exception over http and service calls')` | Bind throwing fake after issuing superadmin token; HTTP case: Superadmin calls `PATCH` on customer with password -> assert status 500, assert stored password hash unchanged; Service cases: role update and delete on fresh users throw `RuntimeException`, assert role unchanged, assert `User::withTrashed()` shows `deleted_at === null`. |
| **AC-8** | Revocation calls contract exactly once per qualifying call: delete, combined password + role update, and zero for non-qualifying updates. Tested with isolated fresh spies and throwing `revoke()`. | `UserService.php`, `UserTokenRevocationTest.php` | `it('invokes revoke all exactly once on qualifying operations and zero times on non qualifying operations with isolated spies')` | Spy implements `TokenIssuer` with throwing `revoke()`. For each operation: bind fresh spy; call name-only update -> assert 0 calls; call combined password + role update -> assert 1 call; call destroy -> assert 1 call. |
| **AC-9** | Layering and gates: `UserService` imports contract only; HTTP layer and policy never mention `TokenIssuer`; arch and existing tests pass. | `UserService.php` | N/A (Verified via Layer A commands and existing suites) | Verified via Layer A commands 15a through 15j; test suites `tests/Arch`, `tests/Feature/Tokens`, and `tests/Feature/Accounts` (including `UserServiceTest`, `UserControllerTest`, and `AuthEndpointsTest`). |
| **AC-10** | ADR 0004 recorded in `docs/adr/`, linked from `ARCHITECTURE.md`; `composer check` passes cleanly. | `ARCHITECTURE.md`, `0004-token-revocation-on-account-changes.md` | N/A (Verified via documentation checks and quality gate) | Verified by file existence of `docs/adr/0004-token-revocation-on-account-changes.md`, `git diff` on `ARCHITECTURE.md`, and clean `composer check` run. |

---

### 10. Test plan

#### File: `tests/Feature/Accounts/UserTokenRevocationTest.php`
- Uses trait: `Tests\Support\InteractsWithPassportKeys`.
- Setup helper: `beforeEach` runs `$this->ensurePassportKeys(); Cache::flush();`.

#### Test Helpers Defined in File:
```php
use App\Domain\Accounts\Enums\UserRole;
use Tests\TestCase;

function assertUserTokenWorks(TestCase $test, string $accessToken, UserRole $role, ?string $probePublicId = null): void
{
    $probeUrl = $probePublicId !== null
        ? "/api/v1/users/{$probePublicId}"
        : '/api/v1/users';

    $response = $test->withHeader('Authorization', 'Bearer '.$accessToken)->getJson($probeUrl);

    if ($role === UserRole::Superadmin) {
        $response->assertOk();
    } else {
        // Customer and Retailer roles are forbidden from /users endpoints by UserPolicy.
        // Because auth:api middleware executes prior to UserPolicy, a working token produces 403,
        // whereas a revoked or invalid token produces 401 Unauthenticated.
        $response->assertForbidden();
    }
}

function assertUserTokenRevoked(TestCase $test, string $accessToken, string $refreshToken): void
{
    // Probe /api/v1/users directly to test authentication without route model binding parameters.
    $test->withHeader('Authorization', 'Bearer '.$accessToken)
        ->getJson('/api/v1/users')
        ->assertUnauthorized();

    $test->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken])
        ->assertUnauthorized();
}
```

#### Throttle Analysis and Call Volume:
The `token-refresh` rate limiter is configured in `app/Providers/TokenServiceProvider.php:43-45` as `Limit::perMinute(10)->by($request->ip())`.
In `beforeEach()`, `Cache::flush()` clears rate limiting buckets before each test execution.
Worst-case refresh calls per test:
- Test 1: 2 calls (1 revoked assertion + 1 bystander refresh) <= 10.
- Test 2: 1 call (1 revoked assertion) <= 10.
- Test 3: 1 call (1 revoked assertion) <= 10.
- Test 4: 1 call (1 revoked assertion) <= 10.
- Test 5: 2 calls (2 revoked assertions across role directions) <= 10.
- Test 6: 3 calls (1 refresh call per sub-case across 3 sub-cases) <= 10.
All tests operate strictly below the 10 requests per minute ceiling with zero risk of HTTP 429.

#### Test Cases:

1. `it('revokes access and refresh tokens when user is soft deleted by superadmin while leaving bystander tokens intact')`:
   - Arrange: Create `superadmin`, `target` (Customer), and `bystander` (Customer). Issue token pairs for `target` and `bystander` via `TokenIssuer::issue()`.
   - Act: Superadmin deletes `target` via `DELETE /api/v1/users/{target->public_id}`.
   - Assert:
     - Response status 204.
     - `assertUserTokenRevoked($this, $targetTokens->accessToken, $targetTokens->refreshToken);`
     - `assertUserTokenWorks($this, $bystanderTokens->accessToken, UserRole::Customer, $bystander->public_id);`
     - Calling `POST /api/v1/auth/refresh` with `$bystanderTokens->refreshToken` returns 200.

2. `it('revokes tokens on password update and allows login only with new password while leaving superadmin token intact')`:
   - Arrange: Create `superadmin` and `target` customer with password `'initial-pass'`. Issue token pair for `target` and `superadmin`.
   - Act: Superadmin calls `PATCH /api/v1/users/{target->public_id}` with `['password' => 'new-secret-1234']`.
   - Assert:
     - Response status 200.
     - `assertUserTokenWorks($this, $superadminTokens->accessToken, UserRole::Superadmin, $target->public_id);`
     - `assertUserTokenRevoked($this, $targetTokens->accessToken, $targetTokens->refreshToken);`
     - Calling `POST /api/v1/auth/login` with `'initial-pass'` returns 401.
     - Calling `POST /api/v1/auth/login` with `'new-secret-1234'` returns 200 with new tokens.

3. `it('revokes caller token upon superadmin self password update over http')`:
   - Arrange: Create `superadmin` with password `'admin-pass'`. Issue token pair via `TokenIssuer::issue()`.
   - Act: Call `PATCH /api/v1/users/{superadmin->public_id}` with header `'Authorization: Bearer '.$superadminTokens->accessToken` and payload `['password' => 'new-admin-pass']`.
   - Assert:
     - Response status 200.
     - Subsequent request using `$superadminTokens->accessToken` returns 401 Unauthenticated via `assertUserTokenRevoked($this, $superadminTokens->accessToken, $superadminTokens->refreshToken);`.

4. `it('revokes customer access and refresh tokens upon domain service password update')`:
   - Arrange: Create `customer` with password `'customer-pass'`. Issue token pair via `TokenIssuer::issue()`.
   - Act: Call `app(UserService::class)->update($customer, new UserData(password: 'brand-new-pass'))`.
   - Assert:
     - `assertUserTokenRevoked($this, $customerTokens->accessToken, $customerTokens->refreshToken);`
     - Fresh login with `'brand-new-pass'` returns 200.

5. `it('revokes tokens on role change between customer and retailer in both directions')`:
   - Arrange: Create `superadmin`, `bystander` (Customer), `target1` (Customer), and `target2` (Retailer). Issue tokens for all.
   - Act & Assert:
     - Customer to Retailer: Superadmin calls `PATCH /api/v1/users/{target1->public_id}` with `['role' => 'retailer']`. Assert 200. `assertUserTokenRevoked($this, $target1Tokens->accessToken, $target1Tokens->refreshToken);`. `assertUserTokenWorks($this, $superadminTokens->accessToken, UserRole::Superadmin, $target1->public_id);`. `assertUserTokenWorks($this, $bystanderTokens->accessToken, UserRole::Customer, $bystander->public_id);`.
     - Retailer to Customer: Superadmin calls `PATCH /api/v1/users/{target2->public_id}` with `['role' => 'customer']`. Assert 200. `assertUserTokenRevoked($this, $target2Tokens->accessToken, $target2Tokens->refreshToken);`. `assertUserTokenWorks($this, $superadminTokens->accessToken, UserRole::Superadmin, $target2->public_id);`.

6. `it('preserves active tokens across name only email only and identical role updates with isolated token pairs')`:
   - Arrange: Create `superadmin` and `target` customer.
   - Act & Assert:
     - Sub-case A (name-only): Issue fresh pair A for `target`. Superadmin calls `PATCH` with `['name' => 'Brand New Name']`. Assert 200. `assertUserTokenWorks($this, $pairA->accessToken, UserRole::Customer, $target->public_id);`. `POST /api/v1/auth/refresh` with `$pairA->refreshToken` returns 200.
     - Sub-case B (email-only): Issue fresh pair B for `target`. Superadmin calls `PATCH` with `['email' => 'newemail@example.com']`. Assert 200. `assertUserTokenWorks($this, $pairB->accessToken, UserRole::Customer, $target->public_id);`. `POST /api/v1/auth/refresh` with `$pairB->refreshToken` returns 200.
     - Sub-case C (identical role): Issue fresh pair C for `target`. Superadmin calls `PATCH` with `['role' => 'customer']`. Assert 200. `assertUserTokenWorks($this, $pairC->accessToken, UserRole::Customer, $target->public_id);`. `POST /api/v1/auth/refresh` with `$pairC->refreshToken` returns 200.

7. `it('preserves tokens and leaves database untouched when operations are rejected with zero revoke calls')`:
   - Arrange:
     1. Create `superadmin`, `customer`, `retailer`, and `otherCustomer` via `User::factory()`.
     2. Capture initial database values:
        `$initialOtherCustomerName = $otherCustomer->name;`
        `$initialRetailerName = $retailer->name;`
     3. Issue real Bearer tokens for `superadmin`, `customer`, and `retailer` BEFORE binding the spy:
        `$superadminTokens = app(TokenIssuer::class)->issue($superadmin);`
        `$customerTokens = app(TokenIssuer::class)->issue($customer);`
        `$retailerTokens = app(TokenIssuer::class)->issue($retailer);`
     4. Bind recording spy fake into container:
        ```php
        $spy = new class implements TokenIssuer {
            public int $revokeAllCalls = 0;
            public function issue(User $user): TokenPairData { throw new RuntimeException('Unused'); }
            public function refresh(string $refreshToken): TokenPairData { throw new RuntimeException('Unused'); }
            public function revoke(User $user): void { throw new RuntimeException('Unexpected revoke() call'); }
            public function revokeAll(User $user): void { $this->revokeAllCalls++; }
        };
        $this->app->instance(TokenIssuer::class, $spy);
        ```
   - Act & Assert:
     - Superadmin attempts to delete superadmin: `DELETE /api/v1/users/{superadmin->public_id}` as superadmin -> assert 403.
     - Superadmin attempts to promote customer to superadmin: `PATCH /api/v1/users/{customer->public_id}` with `['role' => 'superadmin']` -> assert 403.
     - Superadmin attempts to demote superadmin: `PATCH /api/v1/users/{superadmin->public_id}` with `['role' => 'customer']` -> assert 403.
     - Customer actor attempts PATCH on another user: `PATCH /api/v1/users/{otherCustomer->public_id}` with `['name' => 'Hacked']` as customer -> assert 403.
     - Customer actor attempts DELETE on another user: `DELETE /api/v1/users/{otherCustomer->public_id}` as customer -> assert 403.
     - Retailer actor attempts PATCH on another user: `PATCH /api/v1/users/{otherCustomer->public_id}` with `['name' => 'Hacked']` as retailer -> assert 403.
     - Retailer actor attempts DELETE on another user: `DELETE /api/v1/users/{otherCustomer->public_id}` as retailer -> assert 403.
     - Superadmin attempts PATCH with unknown public_id: `PATCH /api/v1/users/01JNONEXISTENT000000000000` -> assert 404.
     - Superadmin attempts DELETE with unknown public_id: `DELETE /api/v1/users/01JNONEXISTENT000000000000` -> assert 404.
     - Superadmin attempts update with invalid email: `PATCH /api/v1/users/{customer->public_id}` with `['email' => 'invalid-email']` -> assert 422.
     - Spy verification: expect `$spy->revokeAllCalls` to be 0.
     - Database unchanged:
       - Expect `$superadmin->fresh()->role` to be `UserRole::Superadmin`.
       - Expect `$customer->fresh()->role` to be `UserRole::Customer`.
       - Expect `$customer->fresh()->deleted_at` to be null.
       - Expect `$otherCustomer->fresh()->name` to be `$initialOtherCustomerName`.
       - Expect `$otherCustomer->fresh()->deleted_at` to be null.
       - Expect `$retailer->fresh()->name` to be `$initialRetailerName`.
       - Expect `$retailer->fresh()->deleted_at` to be null.
     - Tokens still work:
       - `assertUserTokenWorks($this, $superadminTokens->accessToken, UserRole::Superadmin, $superadmin->public_id);`
       - `assertUserTokenWorks($this, $customerTokens->accessToken, UserRole::Customer, $customer->public_id);`
       - `assertUserTokenWorks($this, $retailerTokens->accessToken, UserRole::Retailer, $retailer->public_id);`

8. `it('rolls back database modifications atomically when revoke all throws exception over http and service calls')`:
   - Arrange:
     1. Create `superadmin` via `User::factory()->superadmin()->create()`.
     2. Create `user1` (Customer) via `User::factory()->customer()->create(['password' => 'secret-user-1'])`. Capture `$originalHash1 = $user1->password;`.
     3. Create `user2` (Customer) via `User::factory()->customer()->create()`.
     4. Create `user3` (Customer) via `User::factory()->customer()->create()`.
     5. Issue the superadmin Bearer token using the real `TokenIssuer` BEFORE binding the throwing fake:
        `$superadminTokens = app(TokenIssuer::class)->issue($superadmin);`
     6. Bind the throwing fake into container:
        ```php
        $throwingIssuer = new class implements TokenIssuer {
            public function issue(User $user): TokenPairData { throw new RuntimeException('Unused'); }
            public function refresh(string $refreshToken): TokenPairData { throw new RuntimeException('Unused'); }
            public function revoke(User $user): void { throw new RuntimeException('Unexpected revoke() call'); }
            public function revokeAll(User $user): void { throw new RuntimeException('Revocation failed.'); }
        };
        $this->app->instance(TokenIssuer::class, $throwingIssuer);
        ```
   - Act & Assert:
     - HTTP case: Superadmin calls `PATCH /api/v1/users/{user1->public_id}` with header `'Authorization: Bearer '.$superadminTokens->accessToken` and payload `['password' => 'new-secret-123']`. Assert status 500. Assert `$user1->fresh()->password === $originalHash1`.
     - Service role update case: `expect(fn () => app(UserService::class)->update($user2, new UserData(role: UserRole::Retailer)))->toThrow(RuntimeException::class, 'Revocation failed.');`. Assert `$user2->fresh()->role === UserRole::Customer`.
     - Service delete case: `expect(fn () => app(UserService::class)->destroy($user3))->toThrow(RuntimeException::class, 'Revocation failed.');`. Assert `User::withTrashed()->where('id', $user3->id)->first()->deleted_at === null`.

9. `it('invokes revoke all exactly once on qualifying operations and zero times on non qualifying operations with isolated spies')`:
   - Arrange:
     Create fresh spy per operation where `revoke()` throws `RuntimeException('Unexpected revoke() call')` and `revokeAll()` increments counter.
   - Act & Assert:
     - Non-qualifying update: call `$service->update($user, new UserData(name: 'Updated'))` with fresh Spy A. Assert `$spyA->revokeAllCalls === 0`.
     - Qualifying update with password and role: call `$service->update($user, new UserData(password: 'newpass123', role: UserRole::Retailer))` with fresh Spy B. Assert `$spyB->revokeAllCalls === 1`.
     - Qualifying destroy: call `$service->destroy($user)` with fresh Spy C. Assert `$spyC->revokeAllCalls === 1`.

10. `it('updates user password successfully when target user holds zero active tokens')`:
    - Arrange: Create `superadmin` and `target` user via `User::factory()`. Do not issue any tokens for `target`.
    - Act: Superadmin calls `PATCH /api/v1/users/{target->public_id}` with `['password' => 'new-password-zero-tokens']`.
    - Assert: Response status 200. Password hash updated in database (`Hash::check('new-password-zero-tokens', $target->fresh()->password)` is true).

---

### 11. Docs and contract updates

#### 1. Create `docs/adr/0004-token-revocation-on-account-changes.md`
- Next sequential number after `0003-user-soft-deletes-and-schema-cleanup.md`.
- Records context, decisions for revocation triggers, transactional atomicity, self-update session invalidation, and empty scopes design.

#### 2. Update `ARCHITECTURE.md`
- **Section 7 (Endpoints)**: Under `PUT/PATCH /users/{user}` and `DELETE /users/{user}`, append:
  ```markdown
  Successful user soft deletion, password updates, and role modifications trigger immediate token revocation via `TokenIssuer::revokeAll`. Name-only, email-only, and identical-role updates do not revoke tokens (see ADR 0004).
  ```
- **Section 11 (Security, testing and decisions)**: Under the Open decisions table, exact verbatim context lines 323-327:
  ```markdown
  | OQ4 | Scopes per role or policies only? | Resolved: ADR 0001 (empty scopes, authorization in `UserPolicy`). |
  | OQ5 | How does a retailer receive its first password: set by the superadmin, or an emailed set-password link? | Senior |
  | OQ6 | Hard delete or soft delete for `destroy`. | Resolved: ADR 0003 (soft delete via `deleted_at` and `SoftDeletes` trait). Adopted in `ACC-U1`. |
  | OQ7 | Self-service profile endpoints (`/me`) for retailers and customers. | Senior |
  ```
  Append new row using free identifier `OQ8`:
  ```markdown
  | OQ8 | When are tokens revoked on account changes? | Resolved: ADR 0004 (revocation on delete, password update, and role change inside DB::transaction). |
  ```

---

### 12. Implementation order

#### Commit 1: `feat(users): add token revocation to user service on delete, password and role changes`
- Files:
  - `app/Domain/Accounts/Services/UserService.php`
- State:
  - `UserService` constructor injects `TokenIssuer`.
  - `destroy` wraps deletion and `revokeAll` in `DB::transaction`.
  - `update` calculates revocation criteria and wraps update and `revokeAll` in `DB::transaction`.
  - `composer check` passes cleanly.

#### Commit 2: `test(users): add feature tests for token revocation across account change scenarios`
- Files:
  - `tests/Feature/Accounts/UserTokenRevocationTest.php`
- State:
  - Full suite of 10 feature tests covering AC-1 through AC-8 and edge cases.
  - All tests pass on PostgreSQL with real Passport tokens.
  - `composer check` passes cleanly.

#### Commit 3: `docs(users): record adr 0004 and update architecture specification for token revocation`
- Files:
  - `docs/adr/0004-token-revocation-on-account-changes.md`
  - `ARCHITECTURE.md`
- State:
  - ADR 0004 added to repository.
  - `ARCHITECTURE.md` Sections 7 and 11 updated. (Checkbox in Section 10 remains unchecked until G4 closeout).
  - Layer A checks 15a through 15j pass cleanly.

---

### 13. Risks, assumptions, conflicts

1. **Resolution of Brief Conflict (AC-3 / OD-3 and Current `UserPolicy`) via Amendment A1**:
   - *Conflict*: The brief initially assumed in OD-3 and AC-3 that "UserPolicy lets a Customer or Retailer update their own record." However, verified code in [UserPolicy.php:27-30](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Policies/UserPolicy.php#L27-L30) and [UpdateUserRequest.php:16-31](file:///c:/dev/getnow-auth2/app/Http/Requests/Users/UpdateUserRequest.php#L16-L31) strictly enforces that `update` is permitted only for `UserRole::Superadmin`. Furthermore, [UserControllerTest.php:67-94](file:///c:/dev/getnow-auth2/tests/Feature/Accounts/UserControllerTest.php#L67-L94) explicitly asserts that customers and retailers receive HTTP 403 Forbidden when attempting to update their own record. Modifying `UserPolicy`, `UserController`, or `UpdateUserRequest` is prohibited under the brief's "Out of scope" rules.
   - *Resolution under Amendment A1*:
     (a) A Superadmin changes their own password over HTTP: returns 200, and the same Bearer token returns 401 on the next request.
     (b) A Superadmin changes another user's password: the target's tokens are revoked and the superadmin's own token keeps working.
     (c) At the domain layer, calling `UserService::update` with a password for a Customer model revokes that Customer's access and refresh tokens. Customer and Retailer self-updates over HTTP remain forbidden by `UserPolicy` until a future step.
2. **Open Decisions (OD-1 to OD-6) Awaiting Developer Answers**:
   These items remain open decisions awaiting Achintha's explicit answers before freezing the plan:
   - `OD-1 (Branch Base)`:
     Raw git outputs verified from repository:
     ```text
     git branch --show-current
     feat/users-auth-endpoints

     git log --oneline -3 main
     d6fa3e0 Merge pull request #7 from AchinthaDe/feat/users-domain-layer
     77d89dc Merge pull request #6 from AchinthaDe/feat/users-http-layer
     a22e56c test(users): supply in-memory passport test keys for CI runner via shared feature trait

     git log --oneline main..feat/users-auth-endpoints
     1ecde77 docs(users): record ACC-U4 closeout and tick architecture delivery checkbox
     f44e3f2 test(users): expand customer role enforcement test with retailer and superadmin dataset
     6cce3f1 docs(users): update ACC-U4 report with r1 verify findings, AC mapping, and raw outputs
     b143cad fix(users): update dummy hash cost to 12 and add auth service customer role test
     0deb80e docs(users): add ACC-U4 blueprint brief, plan, and report
     2c05068 test(users): add comprehensive authentication endpoint feature tests
     677ce9b feat(users): add auth form requests, controller, routes, and rate limiters
     3ab7a7c feat(users): implement auth service with atomic registration and credential verification
     25dbc3b feat(users): add authentication data transfer objects, domain exception, and repository method
     ```
     `ACC-U4` has not yet been merged into `main`. Awaiting Achintha's confirmation whether `ACC-U4` will be merged to `main` before `feat/users-token-revocation` is branched (fixing `<BASE>` as `main`), or branched from `feat/users-auth-endpoints` (fixing `<BASE>` as `feat/users-auth-endpoints`).
   - `OD-2 (Revocation Triggers on Update)`: Awaiting Achintha's answer between: (a) password present or role changed, or (b) revoke on any update.
   - `OD-3 (Self-Password Change)`: Awaiting Achintha's confirmation of Amendment A1.
   - `OD-4 (Failure Semantics)`: Awaiting Achintha's answer between: (a) fail-closed via database transaction rollback (returning 500), or (b) revoke after commit and log failures.
   - `OD-5 (Policy Documentation)`: Awaiting Achintha's answer between: (a) ADR 0004 plus `ARCHITECTURE.md` updates, or (b) `ARCHITECTURE.md` only.
   - `OD-6 (Single-Session on Login)`: Awaiting Achintha's answer between: (a) multiple concurrent tokens allowed, or (b) single-session login.
3. **Probe Status Distinction (B1)**:
   - Every endpoint under `routes/api/users.php` is restricted to `superadmin` by `UserPolicy`. For non-superadmin tokens (Customer and Retailer), a valid working token produces HTTP 403 Forbidden on `GET /api/v1/users/{public_id}`, whereas a revoked token produces HTTP 401 Unauthorized. This distinction is strictly reliable because Passport's `auth:api` guard executes prior to policy authorization.
4. **Concurrency Window (trap 10)**:
   - A concurrent login request verifying an old password immediately before a password update transaction commits can mint a token after `revokeAll` executes. Per brief trap 10, row-level locking on the users table is out of scope, and this window is accepted as an operational risk.
5. **Pre-Production Token Backfill**:
   - Assumes pre-production state where orphan tokens from previously deleted test accounts do not require retrospective database cleanup scripts.
6. **Rate Limiting in Feature Tests**:
   - The `auth-login` rate limiter permits 5 requests per minute per IP and email. To prevent test flakiness, feature tests will mint initial test tokens directly using `TokenIssuer::issue($user)` rather than making repeated HTTP login calls.
7. **Soft-Delete Revocation Assertion (M2, M4)**:
   - On user soft delete, access token 401 alone could be produced by Passport's user provider query excluding soft-deleted records. Asserting that `POST /api/v1/auth/refresh` returns 401 proves that `revokeAll` revoked the refresh and access token records in the database because `PassportTokenIssuer::refresh` queries only `oauth_*` tables and never queries `users`. This assertion trips Mutation 1.

---

### 14. Dependencies

None. No new composer packages, npm dependencies, or external libraries are introduced in this step.
