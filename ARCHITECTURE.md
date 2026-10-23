# Architecture: Accounts and Authentication

Standalone design for the GetNow authentication module. It is built in its own project for now and merged into the GetNow main repository later (section 12). One developer (Achintha) builds it with AI agents, in two work streams: users and tokens. This document defines the layers, the stream boundaries and the one contract between the streams.

The tokens stream was first built in an earlier project and is imported into this one (section 10). This project is the source of truth from now on.

If this file and `AGENTS.md` disagree, stop and ask.

## 1. Scope

In scope:
- User CRUD (index, store, show, update, destroy), restricted to the superadmin.
- Customer self-registration.
- Login for superadmin, retailer and customer.
- Access and refresh token system on Laravel Passport (issue, refresh, revoke).

Out of scope (do not build unless asked): Filament UI, retailer panel, email verification, password reset, 2FA, permission packages (Spatie permission/Shield), business accounts, social login.

## 2. Streams

| Stream | Scope | Status |
|---|---|---|
| Users (`U`) | User CRUD, register, login credential check, roles, user DTO, controller, service, repository, repository interface, `UserPolicy` | To build |
| Tokens (`T`) | Passport install and config, token issue, refresh, revoke, token lifetimes, auth guard, `PassportTokenIssuer` | Built in the earlier project, to import (`ACC-T0`) |
| Contract (`C`) | `TokenIssuer` interface and `TokenPairData` (section 6) | To fix against the imported code |

Streams keep the code separate and make ownership easy to hand out again: if a second developer joins, give them one stream. The streams meet only at the `TokenIssuer` contract.

## 3. Roles and rules

| Role | Created by | Can log in | Can call `/users` CRUD |
|---|---|---|---|
| `superadmin` | Seeder or artisan command only, never the API | Yes | Yes |
| `retailer` | Superadmin, via `POST /users` | Yes | No |
| `customer` | Self-register, or superadmin via `POST /users` | Yes | No |

Rules:
1. Public registration creates a **customer only**. The role is set inside `AuthService`. The register request never carries a role and any `role` input is ignored.
2. Retailers cannot register. Only the superadmin creates them.
3. Only the superadmin can use the `/users` endpoints.
4. The API never creates or promotes a `superadmin`: `store` and `update` reject `role = superadmin`. *(Proposed, confirm with the senior.)*
5. The superadmin logs in through the same login endpoint, otherwise it cannot call `/users`. *(Assumption, confirm.)*
6. Deleting a user, changing a user's password or changing a user's role revokes all of that user's tokens (the service calls `TokenIssuer::revokeAll`).

## 4. Layering

```
Request ─► FormRequest ─► Controller ─► Service ─► UserRepositoryInterface ◄─ UserRepository ─► DB
           (validate)     (HTTP only)   (rules)     (contract)                (Eloquent)
```

| Layer | Does | Must not |
|---|---|---|
| FormRequest | Validates input, builds the DTO | Contain business rules |
| Controller | Authorizes (Policy), calls the Service, returns a response | Touch Eloquent, repositories or business rules |
| Service | Business rules, role rules, orchestration, calls `TokenIssuer` when needed | Use `Request`/`Response`, query Eloquent directly |
| Repository interface | Contract the Service depends on | |
| Repository | All Eloquent and query code | Contain business rules, throw HTTP exceptions |
| DTO | Carries typed data between layers | Contain logic or validation |

Method parity (the senior's rule: the same five names in every layer):

| Controller | Service | Repository (and interface) |
|---|---|---|
| `index(IndexUserRequest)` | `index(UserFilterData)` | `index(UserFilterData)` |
| `store(StoreUserRequest)` | `store(UserData)` | `store(UserData)` |
| `show(User)` | `show(User)` | `show(string $publicId)` |
| `update(UpdateUserRequest, User)` | `update(User, UserData)` | `update(User, UserData)` |
| `destroy(User)` | `destroy(User)` | `destroy(User)` |

The interface is bound to the Eloquent implementation in `AccountsServiceProvider`. Services type-hint the interface only.

## 5. Structure and ownership

Paths mirror the GetNow main repo (`app/Domain/<Context>`, `app/Http/Controllers/Api/V1/...`) so merging is a file move, not a rename. Tags show the stream: `[U]` users, `[T]` tokens, `[C]` contract.

```
app/
├── Models/User.php                              [U] (the token import adds `HasApiTokens` and `OAuthenticatable`)
├── Domain/Accounts/
│   ├── Contracts/
│   │   ├── UserRepositoryInterface.php          [U]
│   │   └── TokenIssuer.php                      [C]
│   ├── Data/
│   │   ├── UserData.php                         [U] single DTO for store and update
│   │   ├── UserFilterData.php                   [U]
│   │   ├── UserResponseData.php                 [U] API output, never the model
│   │   ├── LoginData.php                        [U]
│   │   └── TokenPairData.php                    [C]
│   ├── Enums/UserRole.php                       [U]
│   ├── Repositories/UserRepository.php          [U]
│   ├── Services/
│   │   ├── UserService.php                      [U]
│   │   └── AuthService.php                      [U] register, login
│   ├── Policies/UserPolicy.php                  [U]
│   └── Tokens/PassportTokenIssuer.php           [T] implements TokenIssuer
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── Users/UserController.php             [U]
│   │   └── Auth/
│   │       ├── AuthController.php               [U] register, login
│   │       └── TokenController.php              [T] refresh, logout
│   └── Requests/
│       ├── Users/{Store,Update,Index}UserRequest.php   [U]
│       └── Auth/
│           ├── {Register,Login}Request.php             [U]
│           └── RefreshTokenRequest.php                 [T]
└── Providers/
    ├── AccountsServiceProvider.php              [U] binds UserRepositoryInterface
    └── TokenServiceProvider.php                 [T] binds TokenIssuer, Passport config
config/
└── passport.php                                 [T] token lifetimes, key env vars
routes/
├── api.php                                      [shared, one include line per stream]
└── api/{users.php [U], tokens.php [T]}
database/
├── migrations/                                  each stream adds its own files, never edits the other's
├── factories/UserFactory.php                    [U]
└── seeders/SuperadminSeeder.php                 [U]
tests/
├── Feature/Accounts/                            [U]
├── Feature/Contract/                            [C]
├── Feature/Tokens/                              [T]
├── Support/FakeTokenIssuer.php                  [U] used by users-stream tests
└── Arch/                                        [C]
docs/{adr,contracts}/
```

Touch points between the streams (files both streams edit, so changes there are kept small and deliberate):

| File | Rule |
|---|---|
| `composer.json` / `composer.lock` | Passport arrives in the token import PR. Never hand-merge `composer.lock`, regenerate it. |
| `app/Models/User.php` | Users stream owns it. The token import only adds `HasApiTokens` and `implements OAuthenticatable` (required by Passport 13). |
| `config/auth.php` | Tokens stream owns it (guard and provider for `api`). |
| `routes/api.php` | One `require` line per stream. Each stream owns its own file under `routes/api/`. |
| `bootstrap/providers.php` | One line per provider. |
| `.env.example` | Append only, new variables grouped under a comment with the stream name. |

## 6. The seam: `TokenIssuer`

This is the only thing the two streams share. `AuthService` and `UserService` (users stream) depend on it. `PassportTokenIssuer` (tokens stream) implements it with Passport.

```php
interface TokenIssuer
{
    /** Called after AuthService has verified credentials. */
    public function issue(User $user): TokenPairData;

    public function refresh(string $refreshToken): TokenPairData;

    /** Logout: revoke the token pair behind the current request. */
    public function revoke(User $user): void;

    /** Delete, password change, role change: revoke every token of the user. */
    public function revokeAll(User $user): void;
}

final class TokenPairData extends Data
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public string $tokenType,   // "Bearer"
        public int $expiresIn,      // seconds
    ) {}
}
```

Rules:
- The users stream codes against the interface and tests with `FakeTokenIssuer`. A missing production binding fails loudly instead of silently issuing nothing.
- The tokens stream never touches credentials, roles or the users table. `AuthService` has already decided the user may log in before `issue()` is called.
- The imported `PassportTokenIssuer` already implements all four methods with these signatures, so the interface stays as written. If a future change needs a different shape, do it in a contract step.
- `AuthService` calls `issue()` exactly once per login or register.
- Changing the interface or `TokenPairData` is a contract change (section 10).

## 7. Endpoints

Base path `/api/v1`. Output is always `UserResponseData`, never the model. Users are addressed by `public_id` (ULID).

| Method and path | Auth | Role | Track |
|---|---|---|---|
| `POST /auth/register` | none, throttled | customer only | U |
| `POST /auth/login` | none, throttled | superadmin, retailer, customer | U |
| `POST /auth/refresh` | refresh token, throttled | any | T |
| `POST /auth/logout` | access token | any | T |
| `GET /users` | access token | superadmin | U |
| `POST /users` | access token | superadmin | U |
| `GET /users/{user}` | access token | superadmin | U |
| `PUT/PATCH /users/{user}` | access token | superadmin | U |
| `DELETE /users/{user}` | access token | superadmin | U |

Login and register success response:

```json
{ "data": { "user": { "id": "01J...", "name": "", "email": "", "role": "customer" },
            "tokens": { "access_token": "", "refresh_token": "", "token_type": "Bearer", "expires_in": 900 } } }
```

`GET /users` supports `role`, `search` (name or email), `page` and `per_page` (max 100).

## 8. One DTO for insert and update

**What "combining" means.** The idea is one `UserData` class used by both `store` and `update`, instead of `CreateUserData` and `UpdateUserData` with the same fields copied into both. The two classes drift apart over time (a field added to one and forgotten in the other), and every layer needs two code paths.

**The catch.** Create needs every field. Update usually sends only some. A DTO whose fields are all `null` cannot tell "field not sent" from "set it to null", so a partial update can wipe data by accident.

**Chosen approach.** One `UserData` (spatie/laravel-data, already in the GetNow stack) whose properties are `Optional` when absent:

```php
final class UserData extends Data
{
    public function __construct(
        public string|Optional $name,
        public string|Optional $email,
        public string|Optional $password,
        public UserRole|Optional $role,
    ) {}
}
```

- **Rules live in the FormRequests, not the DTO.** `StoreUserRequest` marks name, email, password and role as required. `UpdateUserRequest` marks them `sometimes`. The DTO only carries what was validated: `UserData::from($request->validated())`.
- **Update only writes what was sent.** `Optional` fields are left out of `toArray()`, so the repository calls `$user->update($data->toArray())` and untouched fields stay untouched.
- **Passwords are hashed once**, by the `hashed` cast on the `User` model. No layer calls `Hash::make`.
- **Register** builds `UserData` inside `AuthService` with `role = UserRole::Customer`. The request has no role field.
- Confirm with the senior that "combine" meant this. If he meant something else, record the answer as an ADR.

Rejected: two separate DTOs (duplication and drift); one DTO with plain nullable fields (cannot distinguish absent from null).

## 9. Data model

`users` additions (PostgreSQL, same conventions as the main repo):
- `public_id` ULID, unique. `bigint` primary key stays internal and is never exposed.
- `role` varchar(16), `CHECK (role IN ('superadmin','retailer','customer'))`, indexed.
- `email` unique, `password` hashed, `deleted_at` (soft delete, proposed so later orders and content can keep their foreign keys).

Passport's own tables come from its published migrations and belong to Achintha.

## 10. Delivery order and the token import

Status boxes are ticked by the reviewer, not by agents.

- [x] `ACC-C0` Bootstrap checks and contract: `composer check` green, `tests/Arch` rules, `TokenIssuer` and `TokenPairData` fixed against the imported code.
- [x] `ACC-T0` Import the tokens stream from the earlier project (below).
- [ ] `ACC-C1` Remove starter kit: strip Fortify, Livewire, Flux UI, passkeys, web routes and views; add unauthenticated JSON contract test.
- [ ] `ACC-U1` Users foundation: `UserRole`, users migration additions, `User` model, factory, `SuperadminSeeder`.
- [ ] `ACC-U2` Repository interface and repository, `UserData`, `UserFilterData`, `UserResponseData`.
- [ ] `ACC-U3` `UserService`, `UserPolicy`, `UserController`, form requests, `/users` routes.
- [ ] `ACC-U4` `AuthService`, `AuthController`: register and login, wired to `TokenIssuer`.
- [ ] `ACC-U5` Revocation on delete, password change and role change; full authorization tests.

Token import (`ACC-T0`). Source: the earlier project `getnow-auth` (Laravel 13, Passport `^13.10`, spatie/laravel-data `^4.23`). The code is accepted work: the import PR moves it and wires it up, nothing else.

Copy as-is (paths already match section 5):
- `app/Domain/Accounts/Contracts/TokenIssuer.php`
- `app/Domain/Accounts/Data/TokenPairData.php`
- `app/Domain/Accounts/Tokens/PassportTokenIssuer.php`
- `app/Http/Controllers/Api/V1/Auth/TokenController.php`
- `app/Http/Requests/Auth/RefreshTokenRequest.php`
- `app/Providers/TokenServiceProvider.php`
- `config/passport.php`
- `routes/api/tokens.php`
- `docs/adr/0001-passport-token-issuance-and-refresh-rotation.md` (answers OQ1 to OQ4)
- `tests/Feature/Tokens/{PassportSetupTest,PassportTokenIssuerTest,TokenEndpointsTest}.php` and `tests/Unit/Domain/Accounts/TokenPairDataTest.php`

Hand-merge (small edits to files that already exist here):
- `app/Models/User.php`: add `implements OAuthenticatable` and `use HasApiTokens`.
- `config/auth.php`: add guard `api` with driver `passport` and provider `users`.
- `bootstrap/app.php`: register `api: routes/api.php`, set `apiPrefix: 'api/v1'`, and render JSON for `api/*` requests.
- `bootstrap/providers.php`: register `TokenServiceProvider`.
- `routes/api.php`: new file with one line requiring `routes/api/tokens.php`.
- `.env.example`: add `PASSPORT_ACCESS_TOKEN_TTL_SECONDS`, `PASSPORT_REFRESH_TOKEN_TTL_DAYS`, `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY` (names only, no values).

Do not copy:
- Secrets and generated files: `.env`, `storage/oauth-*.key`, `vendor/`, `composer.lock`, the old database.
- The earlier Passport migrations. Publish them fresh from the installed version.
- Anything Fortify, Livewire, starter-kit UI, or Sail related.
- Earlier users-stream code: `AuthService`, `AuthResultData`, `AuthController`, `UserController`, user DTOs, user repository and service, and their tests. These are rebuilt in `ACC-U` steps. `AuthResultData` in particular exposes the internal `id`, so the new login response uses `UserResponseData`.

Setup in this project (the same way the earlier project did it):
1. Enable `extension=sodium` in `php.ini`.
2. `composer require laravel/passport:"^13.10"`. Do not run `install:api` and do not run `passport:client`: the issuer creates its first-party client (`GetNow First-Party Auth Client`, no secret, `refresh_token` grant) on first use.
3. Publish the Passport migrations (`vendor:publish --tag=passport-migrations`), then `php artisan migrate`. Passport's `user_id` columns are bigint, so the users primary key stays bigint.
4. Keys: for local work run `php artisan passport:keys` (the issuer falls back to `storage/oauth-private.key`), or set `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`. Never commit keys. `.gitignore` must contain `/storage/*.key` (a ported test checks this).
5. `phpunit.xml` stays on PostgreSQL. Do not copy the earlier project's SQLite override.

Known issues carried over from the earlier project:
- Two token test files hard-code Windows `openssl.cnf` paths for key generation. Tests fail on another OS or on CI until this is made portable. Fix in a separate `chore(tokens)` PR after the import, not inside it.
- The earlier users code never called `revokeAll` on delete, password change or role change. This is built in `ACC-U5`.
- The issuer relies on Passport internals (bridge classes, `Passport::tokenEncryptionKey`). Re-run the token tests on every Passport update.
- When `ACC-U1` adds a `role` column, the user factory must set a default role or the token tests break.

Import rules:
- The imported tests must pass here before the PR is opened.
- Anything the earlier code assumed about the user model (class, ID type, guard names) is checked against this project's `User` and `config/auth.php`.

Working rules:
- A contract change (section 6) is its own PR, labelled `contract`.
- Keep PRs small, one concern each. Rebase on `main` at least daily.
- Record significant decisions as an ADR in `docs/adr/`.

## 11. Security, testing and decisions

Security:
- Throttle `register`, `login` and `refresh`.
- Login returns the same error for an unknown email and a wrong password.
- Never accept `role` from the public register request. Never mass-assign `role` from any request outside `store` and `update` with the rules in section 3.
- Never return, log or cache passwords or raw tokens.
- Tokens are revoked on delete, password change and role change.
- Authorization is server-side through `UserPolicy`.

Testing (Pest, PostgreSQL, factories, no SQLite):
- Feature tests: every endpoint, including authorization failures (customer and retailer calling `/users`), register ignoring a sent `role`, login for all three roles, partial update leaving other fields untouched, destroy revoking tokens (via `FakeTokenIssuer`).
- Architecture tests in `tests/Arch`: controllers do not use repositories or models directly, services do not use `Illuminate\Http`, services depend on `UserRepositoryInterface` not `UserRepository`, `Domain` does not import `Http`, every file has `strict_types`.

Open decisions (record as ADRs):

| ID | Question | Owner |
|---|---|---|
| OQ1 | Which Passport grant issues the access and refresh pair? | Resolved: ADR 0001 (direct minting via Passport and League entities, no password grant). Imported in `ACC-T0`. |
| OQ2 | Refresh token delivery. | Resolved: ADR 0001 (JSON body, no cookie). |
| OQ3 | Token lifetimes. | Resolved: ADR 0001 (access 900 s, refresh 14 days, from `config/passport.php`). |
| OQ4 | Scopes per role or policies only? | Resolved: ADR 0001 (empty scopes, authorization in `UserPolicy`). |
| OQ5 | How does a retailer receive its first password: set by the superadmin, or an emailed set-password link? | Senior |
| OQ6 | Hard delete or soft delete for `destroy`. | Senior |
| OQ7 | Self-service profile endpoints (`/me`) for retailers and customers. | Senior |

## 12. Merge plan into the GetNow main repository

The main repo currently treats Accounts as a later phase and has rules this module changes. Handle these in the merge PR:
1. Fold this document into the main `ARCHITECTURE.md` as an Accounts section, move Accounts from "Later" into the phase table, and update the "do not build accounts" line in the main `AGENTS.md`.
2. The main repo puts business logic in Actions and keeps controllers thin. This module uses Controller, Service, Repository per the senior's instruction. Record an ADR that the Accounts context uses Services and Repositories, and adjust the arch tests so the rule applies per context.
3. The main repo's `users` table and `is_platform_admin` flag already exist and gate Filament `canAccessPanel`. Reconcile with `role = superadmin` in an additive migration (no drops in the same release). Until an ADR says otherwise, keep `canAccessPanel` on `is_platform_admin`.
4. Keep `App\Models\User` where it is. Main-repo tables reference `users` through foreign keys.
5. Add the Passport and spatie/laravel-data dependencies to the main stack table with justification.
6. Paths already match the main repo layout, so no renames are needed.
