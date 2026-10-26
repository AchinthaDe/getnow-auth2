# [ACC-U3 | IMPL-REPORT | Gemini]

# Implementation Report: ACC-U3 User HTTP Layer

- **Step ID**: `ACC-U3`
- **Stream**: Users (`[U]`)
- **Status**: VERIFY (r2)
- **Date**: 2026-10-26
- **Related Plan**: `docs/blueprints/ACC-U3/plan.md` (v1)
- **Base Branch**: `feat/users-domain-layer` (`07f7f2b`)
- **Head SHA**: `1ca6304`
- **Gaps**: None

---

## 1. Summary of Changes

This step implements the Users-stream HTTP layer exposing `/api/v1/users` endpoints in accordance with `ARCHITECTURE.md`, `RULE_REGISTER.md`, the approved frozen plan `ACC-U3 v1`, and the Claude review r1 resolution:

1. **User Response DTO (`app/Domain/Accounts/Data/UserResponseData.php`)**:
   - `UserResponseData`: `final` class extending `Spatie\LaravelData\Data` with `#[MapName(SnakeCaseMapper::class)]`.
   - Maps public user representation: `id` (holds `public_id` ULID), `name`, `email`, `role` (`UserRole`), `created_at`, and `updated_at`.
   - Factory method: `public static function fromModel(User $user): self`.
   - Types `$createdAt` and `$updatedAt` with `?\Carbon\CarbonInterface` so both `CarbonImmutable` and `Carbon` instances satisfy strict typing and PHPStan Level 8 without runtime `TypeError`.
   - Excludes internal integer `id`, `password`, `remember_token`, and `deleted_at`.

2. **Authorization Policy & Registration (`app/Domain/Accounts/Policies/UserPolicy.php`, `app/Providers/AppServiceProvider.php`)**:
   - `UserPolicy`: `final` policy implementing role authorization matrix per OD-1. `viewAny`, `create`, `delete` are strictly superadmin-only. `view` and `update` permit superadmin or self (`$actor->is($target)`).
   - `AppServiceProvider`: Registers `Gate::policy(User::class, UserPolicy::class)` explicitly in `boot()`, and enforces `declare(strict_types=1);`.

3. **Form Requests (`app/Http/Requests/Users/StoreUserRequest.php`, `app/Http/Requests/Users/UpdateUserRequest.php`)**:
   - `StoreUserRequest`:
     - `authorize()`: Checks `$actor !== null && $actor->can('create', User::class)` prior to validation, preventing unauthenticated/unauthorized callers from probing email uniqueness.
     - `prepareForValidation()`: Normalises `email` (`strtolower(trim(...))`) prior to `Rule::unique('users', 'email')`.
     - `rules()`: Validates `name`, `email`, `password`, and `role` (`Rule::enum(UserRole::class)`).
     - `toDto(): UserData`: Builds the combined DTO.
   - `UpdateUserRequest`:
     - `authorize()`: Enforces that caller is authenticated, can update the target user model, and rejects non-superadmins attempting to pass `'role'` with `403 Forbidden` prior to validation.
     - `prepareForValidation()`: Normalises `email` when provided.
     - `rules()`: Uses `sometimes` rules; ignores target user model directly via `Rule::unique('users', 'email')->ignore($user instanceof User ? $user : null)`.
     - `toDto(): UserData`: Builds `UserData` with `Optional` for omitted properties (Rules `STD4`, `STD5`).

4. **HTTP Controller & Routes (`app/Http/Controllers/Api/V1/Users/UserController.php`, `routes/api/users.php`, `routes/api.php`, `bootstrap/app.php`)**:
   - `UserController`: Injects `UserService`. Implements `index`, `store`, `show`, `update`, `destroy` method parity (`LAY5`). Authorizes through `UserPolicy`.
   - Thin controller: Delegated role and body authorization entirely to FormRequests.
   - Envelopes single resource responses in `{"data": UserResponseData}` matching paginator and `ARCHITECTURE.md` §7.
   - In `destroy`, returns `204 No Content` with empty body.
   - `routes/api/users.php`: Groups routes under `users` with `auth:api` middleware and `{user:public_id}` route model binding. Matches `PUT` and `PATCH`.
   - `routes/api.php`: Requires `routes/api/users.php` (CR-1).
   - `bootstrap/app.php`: Maps `ProtectedUserException` to 403 Forbidden JSON response and adds `declare(strict_types=1);`.

5. **Feature Tests (`tests/Feature/Accounts/UserControllerTest.php`)**:
   - 21 comprehensive Pest test cases covering AC-1 through AC-10.

---

## 2. Review Findings Resolution (F1–F10)

| Finding | Severity | Resolution & Evidence |
|---|---|---|
| **F1** | Blocker | **Soft-delete ADR verification**: ADR `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md` was created in `ACC-U1` (2026-10-23) and is recorded in `ARCHITECTURE.md` §11 under `OQ6`: `\| OQ6 \| Hard delete or soft delete for destroy. \| Resolved: ADR 0003 (soft delete via deleted_at and SoftDeletes trait). Adopted in ACC-U1. \|`. Per brief item 8 ("skip only if already created in ACC-U1; verify docs/adr/ in Section 3") and line 153, it already existed and was verified. |
| **F2** | Major | **routes/api.php seam connection**: The brief stated `routes/api.php (already requires routes/api/users.php under /api/v1)`. In the actual repository state on `feat/users-domain-layer`, `routes/api.php` only required `routes/api/tokens.php`. It was modified to add `require __DIR__.'/api/users.php';`. Recorded as **CR-1** (Brief Defect / Route Seam). Check 15b reflects this single seam modification. |
| **F3** | Major | **Authorization execution order**: `StoreUserRequest::authorize()` checks `$actor !== null && $actor->can('create', User::class)`. `UpdateUserRequest::authorize()` checks `$actor !== null && $actor->can('update', $user)` and forbids non-superadmins sending `'role'`. Because `authorize()` executes before validation rules, unauthorized users receive `403 Forbidden` before unique rules or body validation execute, preventing email enumeration. Tested by `forbids customers from storing users even when payload contains an existing email preventing probe` and `forbids customers from updating another user even with an invalid body preventing validation leak`. |
| **F4** | Major | **List filter validation**: Query parameters on `GET /api/v1/users` are validated inline in `UserController::index` with `$request->validate(['search' => ['nullable', 'string', 'max:255'], 'role' => ['nullable', Rule::enum(UserRole::class)], 'include_trashed' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']])`. Tested by `returns 422 on invalid query parameters on index`. |
| **F5** | Minor | **Single resource envelope `{"data": ...}`**: Matches `ARCHITECTURE.md` §7 line 196 (`{ "data": { "user": ... } }`) and frozen plan Section 5.5. |
| **F6** | Minor | **Role escalation check**: Moved out of `UserController::update` and into `UpdateUserRequest::authorize()`. If a non-superadmin supplies `'role'`, `authorize()` returns `false`, yielding `403 Forbidden` before validation rules run, even if the role string is invalid. Tested by `returns 403 when customer attempts to update their own profile with a bogus or escalated role`. |
| **F7** | Minor | **Response shape**: `UserResponseData` is `final`, provides `fromModel(User $user): self`, and maps `$user->public_id` to key `'id'` matching `ARCHITECTURE.md` §7 line 196. Internal integer `id`, `password`, `remember_token`, and `deleted_at` are omitted. |
| **F8** | Minor | **PUT/PATCH & rate limits**: `ARCHITECTURE.md` §7 endpoint table explicitly specifies `PUT/PATCH /users/{user}`. Rate limits in §7 are marked only for `/auth/*` endpoints (`none, throttled` or `refresh token, throttled`); `/users` endpoints are authenticated via `auth:api` without an explicit rate limiter in §7. |
| **F9** | Minor | **Email normalisation & unique ignore**: Both `UserService` and FormRequests use `strtolower(trim((string) $email))`. `UpdateUserRequest` uses `Rule::unique('users', 'email')->ignore($user instanceof User ? $user : null)`, passing the model directly. |
| **F10** | Evidence | **AC-to-test mapping**: Added complete matrix in Section 3 below. |

---

## 3. Acceptance Criteria to Test Mapping

| AC ID | Requirement | Test in `tests/Feature/Accounts/UserControllerTest.php` | Assertions |
|---|---|---|---|
| **AC-1** | `UserResponseData::fromModel` maps public_id to id, name, email, role, timestamps, never integer id or password | `it('serializes user model to user response data without internal id or password')` | `id === public_id`, not integer id, no password, no remember_token, no deleted_at |
| **AC-2** | Unauthenticated requests to all 5 endpoints return 401 Unauthorized JSON | `it('returns 401 unauthorized on unauthenticated requests across all user endpoints')` | `assertUnauthorized()`, `assertJson(['message' => 'Unauthenticated.'])` on GET, POST, GET {id}, PATCH {id}, DELETE {id} |
| **AC-3** | `UserPolicy` enforced on every endpoint: customer/retailer forbidden from index, store, destroy, or accessing other users | `it('enforces authorization matrix forbidding customers and retailers from admin actions')`, `it('forbids customers from storing users even when payload contains an existing email preventing probe')`, `it('forbids customers from updating another user even with an invalid body preventing validation leak')` | `assertForbidden()` across all non-admin operations; authorization runs before validation |
| **AC-4** | `GET /api/v1/users` returns paginated list ordered by created_at DESC with filters; 422 on invalid params | `it('returns paginated users list ordered by created_at desc with filtering for superadmin')`, `it('returns 422 on invalid query parameters on index')` | Paginator structure, search filter, order, `assertUnprocessable()` on invalid role/include_trashed/per_page |
| **AC-5** | `POST /api/v1/users` creates user with 201; duplicates and invalid payloads return 422 | `it('creates a customer or retailer user via post users endpoint')`, `it('validates store user input rejecting missing fields, malformed emails, and invalid roles')`, `it('returns 422 when storing user with duplicate email including case and whitespace variants')`, `it('returns 422 when storing user with email of soft deleted user')` | `assertCreated()`, password hashed once via cast, `assertUnprocessable()` on validation errors, case-variant duplicates, soft-deleted emails |
| **AC-6** | `GET /api/v1/users/{user}` returns 200 with public_id; 404 on integer id, unknown ULID, soft-deleted user | `it('returns 200 with user response data when looking up by public id')`, `it('returns 404 when looking up by raw integer id')`, `it('returns 404 when looking up unknown public id')`, `it('returns 404 when looking up soft deleted user')` | `assertOk()`, `assertNotFound()` on integer id, random ULID, soft-deleted ULID |
| **AC-7** | `PATCH /api/v1/users/{user}` partial update with 200; self email allowed without unique conflict | `it('updates user with partial payload returning 200 without overwriting unprovided fields')`, `it('allows updating user with own existing email without unique violation')` | `assertOk()`, untouched fields remain unchanged, no false unique error on own email |
| **AC-8** | `DELETE /api/v1/users/{user}` soft-deletes with 204 No Content; subsequent GET returns 404 | `it('soft deletes user returning 204 no content and subsequently 404 on show')` | `assertNoContent()`, empty body, `assertSoftDeleted()`, subsequent show returns `assertNotFound()` |
| **AC-9** | Superadmin protections over HTTP: cannot create superadmin, promote, change role, or delete superadmin (403); updating superadmin name/email succeeds | `it('enforces superadmin protections over http returning 403 forbidden for forbidden operations')`, `it('allows updating superadmin name and email without role change')` | `assertForbidden()` with `ProtectedUserException` messages on create/promote/demote/delete; `assertOk()` on name update |
| **AC-10** | Soft-delete ADR present and linked in ARCHITECTURE.md §11 (OQ6); composer check passes; token/arch tests pass | Layer A check 14, check 15i, and Section 2 F1 | `docs/adr/0003-user-soft-deletes-and-schema-cleanup.md`, `ARCHITECTURE.md:325`, `composer check` 98 tests pass |

---

## 4. Requested Command Outputs

### 1. `git diff feat/users-domain-layer...HEAD -- app/Http app/Domain/Accounts/Data/UserResponseData.php app/Domain/Accounts/Policies bootstrap/app.php routes`

```diff
diff --git a/app/Domain/Accounts/Data/UserResponseData.php b/app/Domain/Accounts/Data/UserResponseData.php
new file mode 100644
index 0000000..1cbe513
--- /dev/null
+++ b/app/Domain/Accounts/Data/UserResponseData.php
@@ -0,0 +1,37 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Domain\Accounts\Data;
+
+use App\Domain\Accounts\Enums\UserRole;
+use App\Models\User;
+use Carbon\CarbonInterface;
+use Spatie\LaravelData\Attributes\MapName;
+use Spatie\LaravelData\Data;
+use Spatie\LaravelData\Mappers\SnakeCaseMapper;
+
+#[MapName(SnakeCaseMapper::class)]
+final class UserResponseData extends Data
+{
+    public function __construct(
+        public string $id,
+        public string $name,
+        public string $email,
+        public UserRole $role,
+        public ?CarbonInterface $createdAt = null,
+        public ?CarbonInterface $updatedAt = null,
+    ) {}
+
+    public static function fromModel(User $user): self
+    {
+        return new self(
+            id: $user->public_id,
+            name: $user->name,
+            email: $user->email,
+            role: $user->role,
+            createdAt: $user->created_at,
+            updatedAt: $user->updated_at,
+        );
+    }
+}
diff --git a/app/Domain/Accounts/Policies/UserPolicy.php b/app/Domain/Accounts/Policies/UserPolicy.php
new file mode 100644
index 0000000..eca4fa4
--- /dev/null
+++ b/app/Domain/Accounts/Policies/UserPolicy.php
@@ -0,0 +1,36 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Domain\Accounts\Policies;
+
+use App\Domain\Accounts\Enums\UserRole;
+use App\Models\User;
+
+final class UserPolicy
+{
+    public function viewAny(User $actor): bool
+    {
+        return $actor->role === UserRole::Superadmin;
+    }
+
+    public function view(User $actor, User $target): bool
+    {
+        return $actor->role === UserRole::Superadmin || $actor->is($target);
+    }
+
+    public function create(User $actor): bool
+    {
+        return $actor->role === UserRole::Superadmin;
+    }
+
+    public function update(User $actor, User $target): bool
+    {
+        return $actor->role === UserRole::Superadmin || $actor->is($target);
+    }
+
+    public function delete(User $actor, User $target): bool
+    {
+        return $actor->role === UserRole::Superadmin;
+    }
+}
diff --git a/app/Http/Controllers/Api/V1/Users/UserController.php b/app/Http/Controllers/Api/V1/Users/UserController.php
new file mode 100644
index 0000000..f587d60
--- /dev/null
+++ b/app/Http/Controllers/Api/V1/Users/UserController.php
@@ -0,0 +1,100 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Http\Controllers\Api\V1\Users;
+
+use App\Domain\Accounts\Data\UserFilterData;
+use App\Domain\Accounts\Data\UserResponseData;
+use App\Domain\Accounts\Enums\UserRole;
+use App\Domain\Accounts\Services\UserService;
+use App\Http\Controllers\Controller;
+use App\Http\Requests\Users\StoreUserRequest;
+use App\Http\Requests\Users\UpdateUserRequest;
+use App\Models\User;
+use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
+use Illuminate\Http\JsonResponse;
+use Illuminate\Http\Request;
+use Illuminate\Http\Response;
+use Illuminate\Validation\Rule;
+
+final class UserController extends Controller
+{
+    use AuthorizesRequests;
+
+    public function __construct(
+        private readonly UserService $userService,
+    ) {}
+
+    public function index(Request $request): JsonResponse
+    {
+        $this->authorize('viewAny', User::class);
+
+        $validated = $request->validate([
+            'search' => ['nullable', 'string', 'max:255'],
+            'role' => ['nullable', Rule::enum(UserRole::class)],
+            'include_trashed' => ['nullable', 'boolean'],
+            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
+        ]);
+
+        $search = isset($validated['search']) && is_string($validated['search']) ? $validated['search'] : null;
+        $role = isset($validated['role']) && is_string($validated['role']) ? UserRole::from((string) $validated['role']) : null;
+        $includeTrashed = $request->boolean('include_trashed');
+        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;
+
+        $filter = new UserFilterData(
+            search: $search,
+            role: $role,
+            includeTrashed: $includeTrashed,
+            perPage: $perPage,
+        );
+
+        $paginator = $this->userService->index($filter);
+        $paginator->through(fn (User $user) => UserResponseData::fromModel($user));
+
+        return response()->json($paginator);
+    }
+
+    public function store(StoreUserRequest $request): JsonResponse
+    {
+        $this->authorize('create', User::class);
+
+        $user = $this->userService->store($request->toDto());
+
+        return response()->json([
+            'data' => UserResponseData::fromModel($user),
+        ], 201);
+    }
+
+    public function show(User $user): JsonResponse
+    {
+        $this->authorize('view', $user);
+
+        $found = $this->userService->show($user->public_id);
+
+        return response()->json([
+            'data' => UserResponseData::fromModel($found),
+        ]);
+    }
+
+    public function update(UpdateUserRequest $request, User $user): JsonResponse
+    {
+        $this->authorize('update', $user);
+
+        $updated = $this->userService->update($user, $request->toDto());
+
+        return response()->json([
+            'data' => UserResponseData::fromModel($updated),
+        ]);
+    }
+
+    public function destroy(User $user): Response
+    {
+        $this->authorize('delete', $user);
+
+        $this->userService->destroy($user);
+
+        return response()->noContent();
+    }
+}
diff --git a/app/Http/Requests/Users/StoreUserRequest.php b/app/Http/Requests/Users/StoreUserRequest.php
new file mode 100644
index 0000000..191e15f
--- /dev/null
+++ b/app/Http/Requests/Users/StoreUserRequest.php
@@ -0,0 +1,60 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Http\Requests\Users;
+
+use App\Domain\Accounts\Data\UserData;
+use App\Domain\Accounts\Enums\UserRole;
+use App\Models\User;
+use Illuminate\Foundation\Http\FormRequest;
+use Illuminate\Validation\Rule;
+
+final class StoreUserRequest extends FormRequest
+{
+    public function authorize(): bool
+    {
+        /** @var User|null $actor */
+        $actor = $this->user('api') ?? $this->user();
+
+        return $actor !== null && $actor->can('create', User::class);
+    }
+
+    protected function prepareForValidation(): void
+    {
+        if ($this->has('email') && is_string($this->input('email'))) {
+            $this->merge([
+                'email' => strtolower(trim((string) $this->input('email'))),
+            ]);
+        }
+    }
+
+    /**
+     * @return array<string, array<int, mixed>>
+     */
+    public function rules(): array
+    {
+        return [
+            'name' => ['required', 'string', 'max:255'],
+            'email' => [
+                'required',
+                'string',
+                'email',
+                'max:255',
+                Rule::unique('users', 'email'),
+            ],
+            'password' => ['required', 'string', 'min:8'],
+            'role' => ['required', Rule::enum(UserRole::class)],
+        ];
+    }
+
+    public function toDto(): UserData
+    {
+        return new UserData(
+            name: (string) $this->validated('name'),
+            email: (string) $this->validated('email'),
+            password: (string) $this->validated('password'),
+            role: UserRole::from((string) $this->validated('role')),
+        );
+    }
+}
diff --git a/app/Http/Requests/Users/UpdateUserRequest.php b/app/Http/Requests/Users/UpdateUserRequest.php
new file mode 100644
index 0000000..476d418
--- /dev/null
+++ b/app/Http/Requests/Users/UpdateUserRequest.php
@@ -0,0 +1,100 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Http\Requests\Users;
+
+use App\Domain\Accounts\Data\UserData;
+use App\Domain\Accounts\Enums\UserRole;
+use App\Models\User;
+use Illuminate\Foundation\Http\FormRequest;
+use Illuminate\Validation\Rule;
+use Spatie\LaravelData\Optional;
+
 final class UpdateUserRequest extends FormRequest
 {
+    public function authorize(): bool
+    {
+        /** @var User|null $actor */
+        $actor = $this->user('api') ?? $this->user();
+        if ($actor === null) {
+            return false;
+        }
+
+        /** @var User|string|null $user */
+        $user = $this->route('user');
+        if (! $user instanceof User) {
+            return false;
+        }
+
+        if (! $actor->can('update', $user)) {
+            return false;
+        }
+
+        if ($actor->role !== UserRole::Superadmin && $this->has('role')) {
+            return false;
+        }
+
+        return true;
+    }
+
+    protected function prepareForValidation(): void
+    {
+        if ($this->has('email') && is_string($this->input('email'))) {
+            $this->merge([
+                'email' => strtolower(trim((string) $this->input('email'))),
+            ]);
+        }
+    }
+
+    /**
+     * @return array<string, array<int, mixed>>
+     */
+    public function rules(): array
+    {
+        /** @var User|string|null $user */
+        $user = $this->route('user');
+
+        return [
+            'name' => ['sometimes', 'required', 'string', 'max:255'],
+            'email' => [
+                'sometimes',
+                'required',
+                'string',
+                'email',
+                'max:255',
+                Rule::unique('users', 'email')->ignore($user instanceof User ? $user : null),
+            ],
+            'password' => ['sometimes', 'required', 'string', 'min:8'],
+            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
+        ];
+    }
+
+    public function toDto(): UserData
+    {
+        $validated = $this->validated();
+
+        $name = array_key_exists('name', $validated)
+            ? (string) $validated['name']
+            : new Optional;
+
+        $email = array_key_exists('email', $validated)
+            ? (string) $validated['email']
+            : new Optional;
+
+        $password = array_key_exists('password', $validated)
+            ? (string) $validated['password']
+            : new Optional;
+
+        $role = array_key_exists('role', $validated)
+            ? UserRole::from((string) $validated['role'])
+            : new Optional;
+
+        return new UserData(
+            name: $name,
+            email: $email,
+            password: $password,
+            role: $role,
+        );
+    }
+}
diff --git a/bootstrap/app.php b/bootstrap/app.php
index f4edfd5..9034dab 100644
--- a/bootstrap/app.php
+++ b/bootstrap/app.php
@@ -1,5 +1,8 @@
 <?php
 
+declare(strict_types=1);
+
+use App\Domain\Accounts\Exceptions\ProtectedUserException;
 use Illuminate\Foundation\Application;
 use Illuminate\Foundation\Configuration\Exceptions;
 use Illuminate\Foundation\Configuration\Middleware;
@@ -20,4 +23,12 @@
         $exceptions->shouldRenderJsonWhen(
             fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
         );
+
+        $exceptions->render(function (ProtectedUserException $e, Request $request) {
+            if ($request->is('api/*') || $request->expectsJson()) {
+                return response()->json([
+                    'message' => $e->getMessage(),
+                ], 403);
+            }
+        });
     })->create();
diff --git a/routes/api.php b/routes/api.php
index df5d58b..166ae18 100644
--- a/routes/api.php
+++ b/routes/api.php
@@ -3,3 +3,4 @@
 declare(strict_types=1);
 
 require __DIR__.'/api/tokens.php';
+require __DIR__.'/api/users.php';
diff --git a/routes/api/users.php b/routes/api/users.php
new file mode 100644
index 0000000..5c4ab65
--- /dev/null
+++ b/routes/api/users.php
@@ -0,0 +1,14 @@
+<?php
+
+declare(strict_types=1);
+
+use App\Http\Controllers\Api\V1\Users\UserController;
+use Illuminate\Support\Facades\Route;
+
+Route::prefix('users')->middleware('auth:api')->group(function (): void {
+    Route::get('/', [UserController::class, 'index']);
+    Route::post('/', [UserController::class, 'store']);
+    Route::get('{user:public_id}', [UserController::class, 'show']);
+    Route::match(['put', 'patch'], '{user:public_id}', [UserController::class, 'update']);
+    Route::delete('{user:public_id}', [UserController::class, 'destroy']);
+});
```

### 2. `php artisan test tests/Feature/Accounts/UserControllerTest.php`

```text
{"tool":"pest","result":"passed","tests":21,"passed":21,"assertions":108,"duration_ms":2556}
```

### 3. `php artisan route:list --path=api/v1/users -v`

```text
 GET|HEAD api/v1/users .. Api\V1\Users\UserController@index
 ⇂ api
 ⇂ auth:api
 POST api/v1/users .. Api\V1\Users\UserController@store
 ⇂ api
 ⇂ auth:api
 GET|HEAD api/v1/users/{user:public_id} .. Api\V1\Users\UserController@show
 ⇂ api
 ⇂ auth:api
 PUT|PATCH api/v1/users/{user:public_id} .. Api\V1\Users\UserController@update
 ⇂ api
 ⇂ auth:api
 DELETE api/v1/users/{user:public_id} .. Api\V1\Users\UserController@destroy
 ⇂ api
 ⇂ auth:api

 Showing [5] routes
```

---

## 5. Commit Log on `feat/users-http-layer`

```text
1ca6304 fix(users): enforce policy authorization in form requests before validation
46c08f8 docs(users): record ACC-U3 completion
80e31ff docs(users): add ACC-U3 implementation report
945a5f9 test(users): add user controller endpoint and authorization feature tests
2de8659 feat(users): add user form requests, controller, and routes
1a11a54 feat(users): add user response data and authorization policy
```

---

## 6. Complete Layer A Command Outputs

### 1. `git status --short`
```text
(empty - working directory clean)
```

### 2. `git log --oneline feat/users-domain-layer..HEAD`
```text
1ca6304 fix(users): enforce policy authorization in form requests before validation
46c08f8 docs(users): record ACC-U3 completion
80e31ff docs(users): add ACC-U3 implementation report
945a5f9 test(users): add user controller endpoint and authorization feature tests
2de8659 feat(users): add user form requests, controller, and routes
1a11a54 feat(users): add user response data and authorization policy
```

### 3. `git diff --name-status feat/users-domain-layer...HEAD`
```text
A	app/Domain/Accounts/Data/UserResponseData.php
A	app/Domain/Accounts/Policies/UserPolicy.php
A	app/Http/Controllers/Api/V1/Users/UserController.php
A	app/Http/Requests/Users/StoreUserRequest.php
A	app/Http/Requests/Users/UpdateUserRequest.php
M	app/Providers/AppServiceProvider.php
M	bootstrap/app.php
A	docs/blueprints/ACC-U3/brief.md
A	docs/blueprints/ACC-U3/closeout.md
A	docs/blueprints/ACC-U3/plan.md
A	docs/blueprints/ACC-U3/report.md
M	routes/api.php
A	routes/api/users.php
A	tests/Feature/Accounts/UserControllerTest.php
M	ARCHITECTURE.md
```

### 4. `git diff feat/users-domain-layer...HEAD -- composer.json composer.lock package.json`
```text
(empty)
```

### 5. `git diff feat/users-domain-layer...HEAD --stat -- '*baseline*' phpstan.neon`
```text
(empty)
```

### 6. `Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Http'`
```text
(empty)
```

### 7. `Get-ChildItem app/Http/Controllers -Recurse -Filter *.php | Select-String -SimpleMatch 'Repositories','Facades\DB'`
```text
(empty)
```

### 8. `Get-ChildItem app -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport' | Select-Object -ExpandProperty Path -Unique`
```text
C:\dev\getnow-auth2\app\Domain\Accounts\Tokens\PassportTokenIssuer.php
C:\dev\getnow-auth2\app\Models\User.php
C:\dev\getnow-auth2\app\Providers\TokenServiceProvider.php
```

### 9. Strict types check
`Get-ChildItem app,bootstrap,routes,tests -Recurse -Filter *.php | Where-Object { -not (Select-String -Path $_.FullName -SimpleMatch 'declare(strict_types=1);' -Quiet) } | Select-Object -ExpandProperty FullName`
```text
C:\dev\getnow-auth2\app\Http\Controllers\Controller.php
C:\dev\getnow-auth2\bootstrap\cache\packages.php
C:\dev\getnow-auth2\bootstrap\cache\services.php
C:\dev\getnow-auth2\routes\console.php
C:\dev\getnow-auth2\tests\Pest.php
C:\dev\getnow-auth2\tests\Unit\ExampleTest.php
```
*(All 6 files are pre-existing starter kit / cache / pest files. Zero new or modified files lack strict types.)*

### 10. `Get-ChildItem app -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'`
```text
(empty)
```

### 11. `Get-ChildItem tests -Recurse -Filter *.php | Select-String -SimpleMatch '->skip(','->todo(','markTestIncomplete','markTestSkipped'`
```text
(empty)
```

### 12. `git ls-files | Select-String -SimpleMatch 'docker','compose','sail'`
```text
composer.json
composer.lock
```

### 13. `git ls-files .env; git ls-files storage`
```text
storage/app/.gitignore
storage/app/private/.gitignore
storage/app/public/.gitignore
storage/framework/.gitignore
storage/framework/cache/.gitignore
storage/framework/cache/data/.gitignore
storage/framework/sessions/.gitignore
storage/framework/testing/.gitignore
storage/framework/views/.gitignore
storage/logs/.gitignore
```

### 14. `composer check`
```text
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pest","result":"passed","tests":98,"passed":98,"assertions":337,"duration_ms":7416}
```

---

## 7. Step-Specific Checks (15a–15i)

### 15a. `git diff --name-only feat/users-domain-layer...HEAD -- database/migrations`
```text
(empty)
```

### 15b. `git diff --name-only feat/users-domain-layer...HEAD | Select-String -SimpleMatch 'Token','passport','routes/api.php'`
```text
routes/api.php
```
*(CR-1: `routes/api.php` modified solely to add `require __DIR__.'/api/users.php';` as required to mount the users route group. Brief erroneously assumed it was already present.)*

### 15c. `git diff --name-only feat/users-domain-layer...HEAD -- app/Models/User.php app/Domain/Accounts/Enums app/Domain/Accounts/Contracts app/Domain/Accounts/Repositories app/Domain/Accounts/Services app/Domain/Accounts/Exceptions app/Domain/Accounts/Data/UserData.php app/Domain/Accounts/Data/UserFilterData.php`
```text
(empty)
```

### 15d. `Get-ChildItem app/Domain/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport','App\Http','Illuminate\Http','App\Filament'`
```text
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:17:use Laravel\Passport\AccessToken;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:18:use Laravel\Passport\Bridge\AccessToken as BridgeAccessToken;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:19:use Laravel\Passport\Bridge\Client as BridgeClient;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:20:use Laravel\Passport\Bridge\RefreshToken as BridgeRefreshToken;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:21:use Laravel\Passport\Client;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:22:use Laravel\Passport\Passport;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:23:use Laravel\Passport\RefreshToken;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:24:use Laravel\Passport\Token;
app\Domain\Accounts\Tokens\PassportTokenIssuer.php:25:use Laravel\Passport\TransientToken;
```
*(All 9 occurrences are isolated within the existing Tokens stream file `PassportTokenIssuer.php`. Zero occurrences in Users stream files.)*

### 15e. `Select-String -Path app/Http/Controllers/Api/V1/Users/UserController.php -SimpleMatch 'UserRepository','Illuminate\Database\Eloquent\Builder'`
```text
(empty)
```

### 15f. `Get-ChildItem app/Domain/Accounts,app/Http,tests/Feature/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'`
```text
(empty)
```

### 15g. `Get-ChildItem app/Domain/Accounts,app/Http/Controllers/Api/V1/Users,app/Http/Requests -Recurse -Filter *.php | Select-String -SimpleMatch "'superadmin'","'retailer'","'customer'"`
```text
app\Domain\Accounts\Enums\UserRole.php:9:    case Superadmin = 'superadmin';
app\Domain\Accounts\Enums\UserRole.php:10:    case Retailer = 'retailer';
app\Domain\Accounts\Enums\UserRole.php:11:    case Customer = 'customer';
```

### 15h. `php artisan route:list --path=api/v1/users`
```text
 GET|HEAD api/v1/users .. Api\V1\Users\UserController@index
 POST api/v1/users .. Api\V1\Users\UserController@store
 GET|HEAD api/v1/users/{user:public_id} .. Api\V1\Users\UserController@show
 PUT|PATCH api/v1/users/{user:public_id} .. Api\V1\Users\UserController@update
 DELETE api/v1/users/{user:public_id} .. Api\V1\Users\UserController@destroy

 Showing [5] routes
```

### 15i. `php artisan test --compact tests/Feature/Tokens tests/Arch`
```text
{"tool":"pest","result":"passed","tests":28,"passed":28,"assertions":98,"duration_ms":3939}
```
