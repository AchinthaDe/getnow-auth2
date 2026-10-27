# [ACC-U5 | IMPL-REPORT | Gemini]

## 1. Summary of Changes

Step `ACC-U5` ("Revocation on delete, password change and role change; full authorization tests") has been implemented strictly per approved Plan `ACC-U5 v3` on branch `feat/users-token-revocation` cut from `<BASE>` (`feat/users-auth-endpoints`).

1. **Commit 1 (`79babe3`)**: Injected `App\Domain\Accounts\Contracts\TokenIssuer` into `App\Domain\Accounts\Services\UserService`. Inside `update()`, evaluated `$shouldRevoke = (! $data->password instanceof Optional) || (! $data->role instanceof Optional && $data->role !== $user->role);` and wrapped the repository update and `$this->tokens->revokeAll($user)` call inside an atomic `DB::transaction`. Inside `destroy()`, wrapped `$this->users->destroy($user)` and `$this->tokens->revokeAll($user)` in `DB::transaction`.
2. **Commit 2 (`171598f`)**: Created `tests/Feature/Accounts/UserTokenRevocationTest.php` with 10 feature tests covering AC-1 through AC-8, self-update session invalidation, non-qualifying updates, zero-token updates, and atomicity rollbacks. Adhered to all three execution guards (no role string literals, zero `actingAs`, and `Auth::forgetGuards()` cache reset).
3. **Commit 3 (`e14783a`)**: Created `docs/adr/0004-token-revocation-on-account-changes.md` and updated `ARCHITECTURE.md` Section 7 (endpoint notes) and Section 11 (adding resolved row `OQ8`).
4. **Mutations**: Executed all four required mutation spot-checks, demonstrating explicit test failures for each mutation and verifying clean git restorations.
5. **Quality Gates**: `composer check`, all standard Layer A commands (1–14), and all step-specific Layer A commands (15a–15j) pass cleanly.

---

## 2. Commit Log

```text
git log --oneline feat/users-auth-endpoints..HEAD
e14783a docs(users): record adr 0004 and update architecture specification for token revocation
171598f test(users): add feature tests for token revocation across account change scenarios
79babe3 feat(users): add token revocation to user service on delete, password and role changes
```

---

## 3. Mutation Spot-Check Verification

### Mutation 1: Remove `revokeAll` from `destroy` (Trips AC-1)
- **Edit**: Removed `$this->tokens->revokeAll($user);` from `UserService::destroy`.
- **Command**:
  ```powershell
  php artisan test tests/Feature/Accounts/UserTokenRevocationTest.php --filter="revokes access and refresh tokens when user is soft deleted"
  ```
- **Raw Output**:
  ```text
  {"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":4,"duration_ms":1983,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Accounts\\UserTokenRevocationTest::__pest_evaluable_it_revokes_access_and_refresh_tokens_when_user_is_soft_deleted_by_superadmin_while_leaving_bystander_tokens_intact","file":"\\dev\\getnow-auth2\\vendor\\laravel\\framework\\src\\Illuminate\\Testing\\TestResponseAssert.php","line":46,"message":"Expected response status code [401] but received 200.\nFailed asserting that 200 is identical to 401.","trace":["C:\\dev\\getnow-auth2\\vendor\\laravel\\framework\\src\\Illuminate\\Testing\\TestResponseAssert.php:46","C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserTokenRevocationTest.php:50","C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserTokenRevocationTest.php:69"]}]}
  ```
- **Restore & Clean Diff**:
  ```powershell
  git restore app/Domain/Accounts/Services/UserService.php
  git status --short
  ?? docs/blueprints/ACC-U5/
  ```

---

### Mutation 2: Revoke on every `update` unconditionally (Trips AC-5)
- **Edit**: Set `$shouldRevoke = true;` unconditionally in `UserService::update`.
- **Command**:
  ```powershell
  php artisan test tests/Feature/Accounts/UserTokenRevocationTest.php --filter="preserves active tokens across name only email only and identical role updates"
  ```
- **Raw Output**:
  ```text
  The resource owner or authorization server denied the request.
  Expected response status code [403] but received 401.
  Failed asserting that 401 is identical to 403.
  ```
- **Restore & Clean Diff**:
  ```powershell
  git restore app/Domain/Accounts/Services/UserService.php
  git status --short
  ?? docs/blueprints/ACC-U5/
  ```

---

### Mutation 3: Move `revokeAll` before the `ProtectedUserException` guard (Trips AC-6)
- **Edit**: Placed `$this->tokens->revokeAll($user);` prior to `ProtectedUserException::cannotDeleteSuperadmin()` guard in `UserService::destroy`.
- **Command**:
  ```powershell
  php artisan test tests/Feature/Accounts/UserTokenRevocationTest.php --filter="preserves tokens and leaves database untouched when operations are rejected"
  ```
- **Raw Output**:
  ```text
  {"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":11,"duration_ms":2118,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Accounts\\UserTokenRevocationTest::__pest_evaluable_it_preserves_tokens_and_leaves_database_untouched_when_operations_are_rejected_with_zero_revoke_calls","file":"\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserTokenRevocationTest.php","line":330,"message":"Failed asserting that 1 is identical to 0.","trace":["C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserTokenRevocationTest.php:330"]}]}
  ```
- **Restore & Clean Diff**:
  ```powershell
  git restore app/Domain/Accounts/Services/UserService.php
  git status --short
  ?? docs/blueprints/ACC-U5/
  ```

---

### Mutation 4: Move `revokeAll` outside `DB::transaction` (Trips AC-7)
- **Edit**: Moved `$this->tokens->revokeAll($user);` after `DB::transaction` block in `UserService::update`.
- **Command**:
  ```powershell
  php artisan test tests/Feature/Accounts/UserTokenRevocationTest.php --filter="rolls back database modifications atomically when revoke all throws exception"
  ```
- **Raw Output**:
  ```text
  {"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":2,"duration_ms":2136,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Accounts\\UserTokenRevocationTest::__pest_evaluable_it_rolls_back_database_modifications_atomically_when_revoke_all_throws_exception_over_http_and_service_calls","file":"\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserTokenRevocationTest.php","line":384,"message":"Failed asserting that two strings are identical.\n--- Expected\n+++ Actual\n@@ @@\n-'$2y$04$Unalxz0wdx7ss7wEm2GcF.nhHxCILEGDWPzCFUrf9QqWeiLWPzvhW'\n+'$2y$04$Z0c4mRkTSyop4mZjVwIrpuWHqoZw79jc1mm2ro0SLHB/pNv3c5/Qa'","trace":["C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserTokenRevocationTest.php:384"]}]}
  ```
- **Restore & Clean Diff**:
  ```powershell
  git restore app/Domain/Accounts/Services/UserService.php
  git status --short
  ?? docs/blueprints/ACC-U5/
  ```

---

## 4. Layer A Quality Verification Commands

### Standard Layer A Commands (1 to 14 from `RULE_REGISTER.md`)

#### 1. `git status --short`
```text
?? docs/blueprints/ACC-U5/
```

#### 2. `git log --oneline feat/users-auth-endpoints..HEAD`
```text
e14783a docs(users): record adr 0004 and update architecture specification for token revocation
171598f test(users): add feature tests for token revocation across account change scenarios
79babe3 feat(users): add token revocation to user service on delete, password and role changes
```

#### 3. `git diff --name-status feat/users-auth-endpoints...HEAD`
```text
M	ARCHITECTURE.md
M	app/Domain/Accounts/Services/UserService.php
A	docs/adr/0004-token-revocation-on-account-changes.md
A	tests/Feature/Accounts/UserTokenRevocationTest.php
```

#### 4. `git diff feat/users-auth-endpoints...HEAD -- composer.json composer.lock package.json`
*(empty - 0 changes)*

#### 5. `git diff feat/users-auth-endpoints...HEAD --stat -- '*baseline*' phpstan.neon`
*(empty - 0 changes)*

#### 6. `Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Http'`
*(empty - 0 matches)*

#### 7. `Get-ChildItem app/Http/Controllers -Recurse -Filter *.php | Select-String -SimpleMatch 'Repositories','Facades\DB'`
*(empty - 0 matches)*

#### 8. `Get-ChildItem app -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport' | Select-Object -ExpandProperty Path -Unique`
```text
C:\dev\getnow-auth2\app\Domain\Accounts\Tokens\PassportTokenIssuer.php
C:\dev\getnow-auth2\app\Models\User.php
C:\dev\getnow-auth2\app\Providers\TokenServiceProvider.php
```

#### 9. Strict types check
```powershell
Get-ChildItem app,bootstrap,routes,tests -Recurse -Filter *.php | Where-Object { -not (Select-String -Path $_.FullName -SimpleMatch 'declare(strict_types=1);' -Quiet) } | Select-Object -ExpandProperty FullName
```
```text
C:\dev\getnow-auth2\app\Http\Controllers\Controller.php
C:\dev\getnow-auth2\bootstrap\cache\packages.php
C:\dev\getnow-auth2\bootstrap\cache\services.php
C:\dev\getnow-auth2\routes\console.php
C:\dev\getnow-auth2\tests\Unit\ExampleTest.php
```
*(Exact baseline list from ACC-C1; zero new files without strict types)*

#### 10. `Get-ChildItem app -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'`
*(empty - 0 matches)*

#### 11. `Get-ChildItem tests -Recurse -Filter *.php | Select-String -SimpleMatch '->skip(','->todo(','markTestIncomplete','markTestSkipped'`
*(empty - 0 matches)*

#### 12. `git ls-files | Select-String -Pattern '(docker|compose|sail)'`
```text
composer.json
composer.lock
```
*(Only Composer lock and manifest filenames match substring 'compose'; no container files)*

#### 13. `git ls-files .env` and `git ls-files storage`
`git ls-files .env`: *(empty)*
`git ls-files storage`:
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

#### 14. `composer check`
```text
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pest","result":"passed","tests":118,"passed":118,"assertions":530,"duration_ms":20823}
```

---

### Step-Specific Layer A Commands (15a to 15j from `brief.md`)

#### 15a. `git diff --name-only feat/users-auth-endpoints...HEAD -- database`
*(empty - 0 changes)*

#### 15b. `git diff --name-only feat/users-auth-endpoints...HEAD | Select-String -SimpleMatch 'PassportTokenIssuer','TokenController','TokenServiceProvider','config/passport.php','routes/api/tokens.php','TokenIssuer.php','TokenPairData.php','routes/api.php'`
*(empty - 0 changes)*

#### 15c. `git diff --name-only feat/users-auth-endpoints...HEAD -- app routes bootstrap config`
```text
app/Domain/Accounts/Services/UserService.php
```

#### 15d. Contracts and boundaries in UserService
```powershell
Select-String -Path app/Domain/Accounts/Services/UserService.php -SimpleMatch 'Contracts\TokenIssuer'
```
```text
app\Domain\Accounts\Services\UserService.php:7:use App\Domain\Accounts\Contracts\TokenIssuer;
```
```powershell
Select-String -Path app/Domain/Accounts/Services/UserService.php -SimpleMatch 'Laravel\Passport','PassportTokenIssuer','Illuminate\Http','App\Http'
```
*(empty - 0 matches)*

#### 15e. HTTP layer and policy never mention token revocation
```powershell
Get-ChildItem app/Http,app/Domain/Accounts/Policies -Recurse -Filter *.php | Select-String -SimpleMatch 'TokenIssuer','Laravel\Passport','revokeAll'
```
```text
app\Http\Controllers\Api\V1\Auth\TokenController.php:7:use App\Domain\Accounts\Contracts\TokenIssuer;
app\Http\Controllers\Api\V1\Auth\TokenController.php:19:        private readonly TokenIssuer $tokenIssuer,
app\Http\Controllers\Api\V1\Auth\TokenController.php:25:            $tokenPair = $this->tokenIssuer->refresh((string) $request->validated('refresh_token'));
app\Http\Controllers\Api\V1\Auth\TokenController.php:47:        $this->tokenIssuer->revoke($user);
```
*(TokenController is the Token-stream controller for /refresh and /logout; UserController and UserPolicy have 0 matches; revokeAll has 0 matches)*

#### 15f. Passport imports in Domain/Accounts
```powershell
Get-ChildItem app/Domain/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport' | Select-Object -ExpandProperty Path -Unique
```
```text
C:\dev\getnow-auth2\app\Domain\Accounts\Tokens\PassportTokenIssuer.php
```

#### 15g. Token table access in app or Accounts tests
```powershell
Get-ChildItem app,tests/Feature/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'oauth_access_tokens','oauth_refresh_tokens','->tokens()'
```
*(empty - 0 matches)*

#### 15h. Role string literals in UserTokenRevocationTest.php
```powershell
Select-String -Path tests/Feature/Accounts/UserTokenRevocationTest.php -SimpleMatch 'Hash::make',"'superadmin'","'retailer'","'customer'"
```
*(empty - 0 matches)*

#### 15i. Zero `actingAs` matches
```powershell
Select-String -Path tests/Feature/Accounts/UserTokenRevocationTest.php -SimpleMatch 'actingAs'
```
*(empty - 0 matches)*

#### 15j. Compact test suite run
```powershell
php artisan test --compact tests/Feature/Tokens tests/Arch tests/Feature/Accounts
```
```text
{"tool":"pest","result":"passed","tests":114,"passed":114,"assertions":515,"duration_ms":23149}
```

---

## 5. Acceptance Criteria Mapping

| AC ID | Requirement | Test / Verification Method | Status |
|---|---|---|---|
| **AC-1** | Soft-delete revokes target tokens; bystander tokens intact. Refresh on target fails 401. | `UserTokenRevocationTest > it('revokes access and refresh tokens when user is soft deleted by superadmin while leaving bystander tokens intact')` | PASS |
| **AC-2** | Password update revokes target tokens; old login 401; new login 200; superadmin token intact. | `UserTokenRevocationTest > it('revokes tokens on password update and allows login only with new password while leaving superadmin token intact')` | PASS |
| **AC-3a** | Superadmin self-password update over HTTP returns 200, caller token rejected with 401 on next request. | `UserTokenRevocationTest > it('revokes caller token upon superadmin self password update over http')` | PASS |
| **AC-3b** | Superadmin changing other user's password revokes target tokens; superadmin token works. | `UserTokenRevocationTest > it('revokes tokens on password update and allows login only with new password while leaving superadmin token intact')` | PASS |
| **AC-3c** | Customer password update via domain service revokes customer tokens. | `UserTokenRevocationTest > it('revokes customer access and refresh tokens upon domain service password update')` | PASS |
| **AC-4** | Role change between Customer and Retailer in both directions revokes target tokens. | `UserTokenRevocationTest > it('revokes tokens on role change between customer and retailer in both directions')` | PASS |
| **AC-5** | Non-revoking updates (name, email, identical role) preserve active tokens with isolated token pairs. | `UserTokenRevocationTest > it('preserves active tokens across name only email only and identical role updates with isolated token pairs')` | PASS |
| **AC-6** | Rejected operations (superadmin delete/demote/promote, forbidden actors, 404, 422) preserve tokens, leave DB untouched, and execute 0 revoke calls. | `UserTokenRevocationTest > it('preserves tokens and leaves database untouched when operations are rejected with zero revoke calls')` | PASS |
| **AC-7** | Fail-closed: throwing revoke fake rolls back DB changes atomically (HTTP 500, service throws, DB unchanged). | `UserTokenRevocationTest > it('rolls back database modifications atomically when revoke all throws exception over http and service calls')` | PASS |
| **AC-8** | `revokeAll` invoked exactly once per qualifying operation and 0 times on non-qualifying operations. | `UserTokenRevocationTest > it('invokes revoke all exactly once on qualifying operations and zero times on non qualifying operations with isolated spies')` | PASS |
| **AC-9** | Layering & gates: `UserService` imports contract only; HTTP layer and policy never mention `TokenIssuer`; arch and existing tests pass. | Verified via Layer A commands 6, 7, 8, 15d, 15e, 15f, 15j. | PASS |
| **AC-10** | ADR 0004 recorded, linked from `ARCHITECTURE.md`; `composer check` passes. | Verified via existence of `0004-token-revocation-on-account-changes.md`, `git diff ARCHITECTURE.md`, and `composer check`. | PASS |

---

## 6. Full Raw Git Diff

```diff
diff --git a/ARCHITECTURE.md b/ARCHITECTURE.md
index c235cc0..f66f2ff 100644
--- a/ARCHITECTURE.md
+++ b/ARCHITECTURE.md
@@ -197,7 +197,7 @@ ## 7. Endpoints
             "tokens": { "access_token": "", "refresh_token": "", "token_type": "Bearer", "expires_in": 900 } } }
 ```
 
-`GET /users` supports `role`, `search` (name or email), `include_trashed` (boolean), `page` and `per_page` (max 100), returning a paginated JSON response with items wrapped in `{"data": [...]}`. Single user endpoints (`POST /users`, `GET /users/{user}`, `PUT/PATCH /users/{user}`) return `{"data": UserResponseData}`.
+`GET /users` supports `role`, `search` (name or email), `include_trashed` (boolean), `page` and `per_page` (max 100), returning a paginated JSON response with items wrapped in `{"data": [...]}`. Single user endpoints (`POST /users`, `GET /users/{user}`, `PUT/PATCH /users/{user}`) return `{"data": UserResponseData}`. Successful user soft deletion, password updates, and role modifications trigger immediate token revocation via `TokenIssuer::revokeAll`. Name-only, email-only, and identical-role updates do not revoke tokens (see ADR 0004).
 
 ## 8. One DTO for insert and update
 
@@ -324,6 +324,7 @@ ## 11. Security, testing and decisions
 | OQ5 | How does a retailer receive its first password: set by the superadmin, or an emailed set-password link? | Senior |
 | OQ6 | Hard delete or soft delete for `destroy`. | Resolved: ADR 0003 (soft delete via `deleted_at` and `SoftDeletes` trait). Adopted in `ACC-U1`. |
 | OQ7 | Self-service profile endpoints (`/me`) for retailers and customers. | Senior |
+| OQ8 | When are tokens revoked on account changes? | Resolved: ADR 0004 (revocation on delete, password update, and role change inside DB::transaction). |
 
 ## 12. Merge plan into the GetNow main repository
 
diff --git a/app/Domain/Accounts/Services/UserService.php b/app/Domain/Accounts/Services/UserService.php
index 1752067..ace6f06 100644
--- a/app/Domain/Accounts/Services/UserService.php
+++ b/app/Domain/Accounts/Services/UserService.php
@@ -4,6 +4,7 @@
 
 namespace App\Domain\Accounts\Services;
 
+use App\Domain\Accounts\Contracts\TokenIssuer;
 use App\Domain\Accounts\Contracts\UserRepositoryInterface;
 use App\Domain\Accounts\Data\UserData;
 use App\Domain\Accounts\Data\UserFilterData;
@@ -12,6 +13,7 @@
 use App\Models\User;
 use Illuminate\Database\Eloquent\ModelNotFoundException;
 use Illuminate\Pagination\LengthAwarePaginator;
+use Illuminate\Support\Facades\DB;
 use InvalidArgumentException;
 use Spatie\LaravelData\Optional;
 
@@ -19,6 +21,7 @@ final class UserService
 {
     public function __construct(
         private readonly UserRepositoryInterface $users,
+        private readonly TokenIssuer $tokens,
     ) {}
 
     /**
@@ -106,6 +109,9 @@ public function update(User $user, UserData $data): User
             }
         }
 
+        $shouldRevoke = (! $data->password instanceof Optional)
+            || (! $data->role instanceof Optional && $data->role !== $user->role);
+
         $email = ! $data->email instanceof Optional
             ? strtolower(trim($data->email))
             : new Optional;
@@ -117,7 +123,15 @@ public function update(User $user, UserData $data): User
             role: $data->role,
         );
 
-        return $this->users->update($user, $normalizedData);
+        return DB::transaction(function () use ($user, $normalizedData, $shouldRevoke): User {
+            $updatedUser = $this->users->update($user, $normalizedData);
+
+            if ($shouldRevoke) {
+                $this->tokens->revokeAll($user);
+            }
+
+            return $updatedUser;
+        });
     }
 
     public function destroy(User $user): void
@@ -126,6 +140,9 @@ public function destroy(User $user): void
             throw ProtectedUserException::cannotDeleteSuperadmin();
         }
 
-        $this->users->destroy($user);
+        DB::transaction(function () use ($user): void {
+            $this->users->destroy($user);
+            $this->tokens->revokeAll($user);
+        });
     }
 }
diff --git a/docs/adr/0004-token-revocation-on-account-changes.md b/docs/adr/0004-token-revocation-on-account-changes.md
new file mode 100644
index 0000000..fe50868
--- /dev/null
+++ b/docs/adr/0004-token-revocation-on-account-changes.md
@@ -0,0 +1,38 @@
+# 0004. Token revocation on account changes
+
+- **Status**: Accepted
+- **Date**: 2026-10-27
+- **Step**: `ACC-U5`
+
+## Context
+
+In the accounts and authentication module, users authenticate to receive short-lived OAuth2 access tokens and rotating refresh tokens (ADR 0001). Prior to step `ACC-U5`, active access tokens and refresh tokens remained usable following user deletion, password changes, or role changes until natural expiration.
+
+`AGENTS.md` mandates that deleting a user, changing a password, or changing a role must call `TokenIssuer::revokeAll`. Open Question OQ8 (formulated from OD-2 through OD-5) addresses the exact triggers, transactional boundaries, and session semantics for token revocation.
+
+## Decision
+
+1. **Revocation Triggers**:
+   - `UserService::destroy`: Always calls `TokenIssuer::revokeAll($user)` upon user soft deletion.
+   - `UserService::update`: Calls `TokenIssuer::revokeAll($user)` when `password` is present in `UserData` (not `Optional`), or when `role` is present (not `Optional`) and differs from the current persisted `$user->role`.
+   - Name-only, email-only, and identical-role updates do not revoke tokens, allowing active sessions to persist uninterrupted.
+2. **Transactional Atomicity and Fail-Closed Semantics**:
+   - Both the database modification (`$this->users->update` or `$this->users->destroy`) and the token revocation (`$this->tokens->revokeAll($user)`) execute inside a single atomic database transaction (`DB::transaction`).
+   - If `TokenIssuer::revokeAll` throws an exception, the database changes roll back completely, preventing desynchronization between user state and active credentials. The request terminates with HTTP 500 Internal Server Error.
+3. **Self-Update Session Termination**:
+   - Under current authorization rules, only `Superadmin` can update their own user record over HTTP (`PATCH /api/v1/users/{public_id}`). When a superadmin changes their own password, `revokeAll` invalidates all tokens belonging to that superadmin, including the token used to authenticate the current request.
+   - The current update request completes with HTTP 200 and returns `UserResponseData`. Subsequent requests with the prior Bearer token receive HTTP 401 Unauthenticated. The superadmin must re-authenticate via `POST /api/v1/auth/login`.
+   - Customer and Retailer self-updates over HTTP remain forbidden by `UserPolicy` until dedicated self-service endpoints are introduced. At the domain layer, calling `UserService::update` with a password for a customer revokes their tokens.
+4. **Empty Scopes and Role Evaluation**:
+   - Per ADR 0001, access tokens carry empty default scopes; authorization is evaluated server-side via `UserPolicy` using `$user->role` loaded from the database.
+   - A role change takes effect in the database immediately upon transaction commit. Revoking tokens on role change terminates all active sessions across client devices, forcing users to re-authenticate under their new role.
+5. **Concurrent Login Window**:
+   - In accordance with brief trap 10, a concurrent login verifying an old password immediately before a password update transaction commits may issue a token that revocation does not catch. Row-level locking on authentication is out of scope and this window is accepted as an operational risk.
+6. **Mark Open Question OQ8 as Resolved**:
+   - Record OQ8 in `ARCHITECTURE.md` §11 as resolved by ADR 0004.
+
+## Consequences
+
+1. Active access tokens and refresh tokens are invalidated immediately upon user soft delete, password changes, or role changes.
+2. Fail-closed transactional rollback guarantees that no credential or role changes persist if token revocation fails.
+3. Non-sensitive profile changes (such as name or email updates) do not invalidate existing user sessions.
+4. Callers modifying their own credentials are logged out across all sessions, requiring immediate re-login.
diff --git a/tests/Feature/Accounts/UserTokenRevocationTest.php b/tests/Feature/Accounts/UserTokenRevocationTest.php
new file mode 100644
index 0000000..f6f69c9
--- /dev/null
+++ b/tests/Feature/Accounts/UserTokenRevocationTest.php
@@ -0,0 +1,417 @@
+<?php
+
+declare(strict_types=1);
+
+use App\Domain\Accounts\Contracts\TokenIssuer;
+use App\Domain\Accounts\Data\TokenPairData;
+use App\Domain\Accounts\Data\UserData;
+use App\Domain\Accounts\Enums\UserRole;
+use App\Domain\Accounts\Services\UserService;
+use App\Models\User;
+use Illuminate\Support\Facades\Auth;
+use Illuminate\Support\Facades\Cache;
+use Illuminate\Support\Facades\Hash;
+use Tests\Support\InteractsWithPassportKeys;
+use Tests\TestCase;
+
+uses(InteractsWithPassportKeys::class);
+
+beforeEach(function (): void {
+    $this->ensurePassportKeys();
+    Cache::flush();
+});
+
+function assertUserTokenWorks(TestCase $test, string $accessToken, UserRole $role, ?string $probePublicId = null): void
+{
+    Auth::forgetGuards();
+
+    $probeUrl = $probePublicId !== null
+        ? "/api/v1/users/{$probePublicId}"
+        : '/api/v1/users';
+
+    $response = $test->withHeader('Authorization', 'Bearer '.$accessToken)->getJson($probeUrl);
+
+    if ($role === UserRole::Superadmin) {
+        $response->assertOk();
+    } else {
+        $response->assertForbidden();
+    }
+}
+
+function assertUserTokenRevoked(TestCase $test, string $accessToken, string $refreshToken): void
+{
+    Auth::forgetGuards();
+
+    $test->withHeader('Authorization', 'Bearer '.$accessToken)
+        ->getJson('/api/v1/users')
+        ->assertUnauthorized();
+
+    $test->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken])
+        ->assertUnauthorized();
+}
+
+it('revokes access and refresh tokens when user is soft deleted by superadmin while leaving bystander tokens intact', function (): void {
+    $superadmin = User::factory()->superadmin()->create();
+    $target = User::factory()->customer()->create();
+    $bystander = User::factory()->customer()->create();
+
+    $issuer = app(TokenIssuer::class);
+    $targetTokens = $issuer->issue($target);
+    $bystanderTokens = $issuer->issue($bystander);
+    $superadminTokens = $issuer->issue($superadmin);
+
+    Auth::forgetGuards();
+    $response = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->deleteJson("/api/v1/users/{$target->public_id}");
+
+    $response->assertNoContent();
+
+    assertUserTokenRevoked($this, $targetTokens->accessToken, $targetTokens->refreshToken);
+    assertUserTokenWorks($this, $bystanderTokens->accessToken, UserRole::Customer, $bystander->public_id);
+
+    $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
+        'refresh_token' => $bystanderTokens->refreshToken,
+    ]);
+    $refreshResponse->assertOk();
+});
+
+it('revokes tokens on password update and allows login only with new password while leaving superadmin token intact', function (): void {
+    $superadmin = User::factory()->superadmin()->create();
+    $target = User::factory()->customer()->create(['password' => 'initial-pass']);
+
+    $issuer = app(TokenIssuer::class);
+    $targetTokens = $issuer->issue($target);
+    $superadminTokens = $issuer->issue($superadmin);
+
+    Auth::forgetGuards();
+    $response = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson("/api/v1/users/{$target->public_id}", [
+            'password' => 'new-secret-1234',
+        ]);
+
+    $response->assertOk();
+
+    assertUserTokenWorks($this, $superadminTokens->accessToken, UserRole::Superadmin, $target->public_id);
+    assertUserTokenRevoked($this, $targetTokens->accessToken, $targetTokens->refreshToken);
+
+    $oldLogin = $this->postJson('/api/v1/auth/login', [
+        'email' => $target->email,
+        'password' => 'initial-pass',
+    ]);
+    $oldLogin->assertUnauthorized();
+
+    $newLogin = $this->postJson('/api/v1/auth/login', [
+        'email' => $target->email,
+        'password' => 'new-secret-1234',
+    ]);
+    $newLogin->assertOk();
+});
+
+it('revokes caller token upon superadmin self password update over http', function (): void {
+    $superadmin = User::factory()->superadmin()->create(['password' => 'admin-pass']);
+
+    $issuer = app(TokenIssuer::class);
+    $superadminTokens = $issuer->issue($superadmin);
+
+    Auth::forgetGuards();
+    $response = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson("/api/v1/users/{$superadmin->public_id}", [
+            'password' => 'new-admin-pass',
+        ]);
+
+    $response->assertOk();
+
+    assertUserTokenRevoked($this, $superadminTokens->accessToken, $superadminTokens->refreshToken);
+});
+
+it('revokes customer access and refresh tokens upon domain service password update', function (): void {
+    $customer = User::factory()->customer()->create(['password' => 'customer-pass']);
+
+    $issuer = app(TokenIssuer::class);
+    $customerTokens = $issuer->issue($customer);
+
+    app(UserService::class)->update($customer, new UserData(password: 'brand-new-pass'));
+
+    assertUserTokenRevoked($this, $customerTokens->accessToken, $customerTokens->refreshToken);
+
+    $newLogin = $this->postJson('/api/v1/auth/login', [
+        'email' => $customer->email,
+        'password' => 'brand-new-pass',
+    ]);
+    $newLogin->assertOk();
+});
+
+it('revokes tokens on role change between customer and retailer in both directions', function (): void {
+    $superadmin = User::factory()->superadmin()->create();
+    $bystander = User::factory()->customer()->create();
+    $target1 = User::factory()->customer()->create();
+    $target2 = User::factory()->retailer()->create();
+
+    $issuer = app(TokenIssuer::class);
+    $superadminTokens = $issuer->issue($superadmin);
+    $bystanderTokens = $issuer->issue($bystander);
+    $target1Tokens = $issuer->issue($target1);
+    $target2Tokens = $issuer->issue($target2);
+
+    Auth::forgetGuards();
+    $response1 = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson("/api/v1/users/{$target1->public_id}", [
+            'role' => UserRole::Retailer->value,
+        ]);
+    $response1->assertOk();
+
+    assertUserTokenRevoked($this, $target1Tokens->accessToken, $target1Tokens->refreshToken);
+    assertUserTokenWorks($this, $superadminTokens->accessToken, UserRole::Superadmin, $target1->public_id);
+    assertUserTokenWorks($this, $bystanderTokens->accessToken, UserRole::Customer, $bystander->public_id);
+
+    Auth::forgetGuards();
+    $response2 = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson("/api/v1/users/{$target2->public_id}", [
+            'role' => UserRole::Customer->value,
+        ]);
+    $response2->assertOk();
+
+    assertUserTokenRevoked($this, $target2Tokens->accessToken, $target2Tokens->refreshToken);
+    assertUserTokenWorks($this, $superadminTokens->accessToken, UserRole::Superadmin, $target2->public_id);
+});
+
+it('preserves active tokens across name only email only and identical role updates with isolated token pairs', function (): void {
+    $superadmin = User::factory()->superadmin()->create();
+    $target = User::factory()->customer()->create();
+
+    $issuer = app(TokenIssuer::class);
+    $superadminTokens = $issuer->issue($superadmin);
+
+    // Sub-case A: name only
+    $pairA = $issuer->issue($target);
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson("/api/v1/users/{$target->public_id}", [
+            'name' => 'Brand New Name',
+        ])
+        ->assertOk();
+
+    assertUserTokenWorks($this, $pairA->accessToken, UserRole::Customer, $target->public_id);
+    $this->postJson('/api/v1/auth/refresh', [
+        'refresh_token' => $pairA->refreshToken,
+    ])->assertOk();
+
+    // Sub-case B: email only
+    $pairB = $issuer->issue($target);
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson("/api/v1/users/{$target->public_id}", [
+            'email' => 'newemail@example.com',
+        ])
+        ->assertOk();
+
+    assertUserTokenWorks($this, $pairB->accessToken, UserRole::Customer, $target->public_id);
+    $this->postJson('/api/v1/auth/refresh', [
+        'refresh_token' => $pairB->refreshToken,
+    ])->assertOk();
+
+    // Sub-case C: identical role
+    $pairC = $issuer->issue($target);
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson("/api/v1/users/{$target->public_id}", [
+            'role' => UserRole::Customer->value,
+        ])
+        ->assertOk();
+
+    assertUserTokenWorks($this, $pairC->accessToken, UserRole::Customer, $target->public_id);
+    $this->postJson('/api/v1/auth/refresh', [
+        'refresh_token' => $pairC->refreshToken,
+    ])->assertOk();
+});
+
+it('preserves tokens and leaves database untouched when operations are rejected with zero revoke calls', function (): void {
+    $superadmin = User::factory()->superadmin()->create();
+    $customer = User::factory()->customer()->create();
+    $retailer = User::factory()->retailer()->create();
+    $otherCustomer = User::factory()->customer()->create();
+
+    $initialOtherCustomerName = $otherCustomer->name;
+    $initialRetailerName = $retailer->name;
+
+    $issuer = app(TokenIssuer::class);
+    $superadminTokens = $issuer->issue($superadmin);
+    $customerTokens = $issuer->issue($customer);
+    $retailerTokens = $issuer->issue($retailer);
+
+    $spy = new class implements TokenIssuer
+    {
+        public int $revokeAllCalls = 0;
+
+        public function issue(User $user): TokenPairData
+        {
+            throw new RuntimeException('Unused');
+        }
+
+        public function refresh(string $refreshToken): TokenPairData
+        {
+            throw new RuntimeException('Unused');
+        }
+
+        public function revoke(User $user): void
+        {
+            throw new RuntimeException('Unexpected revoke() call');
+        }
+
+        public function revokeAll(User $user): void
+        {
+            $this->revokeAllCalls++;
+        }
+    };
+    $this->app->instance(TokenIssuer::class, $spy);
+
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->deleteJson("/api/v1/users/{$superadmin->public_id}")
+        ->assertForbidden();
+
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson("/api/v1/users/{$customer->public_id}", [
+            'role' => UserRole::Superadmin->value,
+        ])
+        ->assertForbidden();
+
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson("/api/v1/users/{$superadmin->public_id}", [
+            'role' => UserRole::Customer->value,
+        ])
+        ->assertForbidden();
+
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$customerTokens->accessToken)
+        ->patchJson("/api/v1/users/{$otherCustomer->public_id}", [
+            'name' => 'Hacked',
+        ])
+        ->assertForbidden();
+
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$customerTokens->accessToken)
+        ->deleteJson("/api/v1/users/{$otherCustomer->public_id}")
+        ->assertForbidden();
+
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$retailerTokens->accessToken)
+        ->patchJson("/api/v1/users/{$otherCustomer->public_id}", [
+            'name' => 'Hacked',
+        ])
+        ->assertForbidden();
+
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$retailerTokens->accessToken)
+        ->deleteJson("/api/v1/users/{$otherCustomer->public_id}")
+        ->assertForbidden();
+
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson('/api/v1/users/01JNONEXISTENT000000000000', [
+            'name' => 'New',
+        ])
+        ->assertNotFound();
+
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->deleteJson('/api/v1/users/01JNONEXISTENT000000000000')
+        ->assertNotFound();
+
+    Auth::forgetGuards();
+    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson("/api/v1/users/{$customer->public_id}", [
+            'email' => 'invalid-email',
+        ])
+        ->assertUnprocessable();
+
+    expect($spy->revokeAllCalls)->toBe(0);
+
+    expect($superadmin->fresh()->role)->toBe(UserRole::Superadmin);
+    expect($customer->fresh()->role)->toBe(UserRole::Customer);
+    expect($customer->fresh()->deleted_at)->toBeNull();
+    expect($otherCustomer->fresh()->name)->toBe($initialOtherCustomerName);
+    expect($otherCustomer->fresh()->deleted_at)->toBeNull();
+    expect($retailer->fresh()->name)->toBe($initialRetailerName);
+    expect($retailer->fresh()->deleted_at)->toBeNull();
+
+    assertUserTokenWorks($this, $superadminTokens->accessToken, UserRole::Superadmin, $superadmin->public_id);
+    assertUserTokenWorks($this, $customerTokens->accessToken, UserRole::Customer, $customer->public_id);
+    assertUserTokenWorks($this, $retailerTokens->accessToken, UserRole::Retailer, $retailer->public_id);
+});
+
+it('rolls back database modifications atomically when revoke all throws exception over http and service calls', function (): void {
+    $superadmin = User::factory()->superadmin()->create();
+    $user1 = User::factory()->customer()->create(['password' => 'secret-user-1']);
+    $originalHash1 = $user1->password;
+    $user2 = User::factory()->customer()->create();
+    $user3 = User::factory()->customer()->create();
+
+    $superadminTokens = app(TokenIssuer::class)->issue($superadmin);
+
+    $throwingIssuer = new class implements TokenIssuer
+    {
+        public function issue(User $user): TokenPairData
+        {
+            throw new RuntimeException('Unused');
+        }
+
+        public function refresh(string $refreshToken): TokenPairData
+        {
+            throw new RuntimeException('Unused');
+        }
+
+        public function revoke(User $user): void
+        {
+            throw new RuntimeException('Unexpected revoke() call');
+        }
+
+        public function revokeAll(User $user): void
+        {
+            throw new RuntimeException('Revocation failed.');
+        }
+    };
+    $this->app->instance(TokenIssuer::class, $throwingIssuer);
+
+    Auth::forgetGuards();
+    $response = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson("/api/v1/users/{$user1->public_id}", [
+            'password' => 'new-secret-123',
+        ]);
+    $response->assertInternalServerError();
+    expect($user1->fresh()->password)->toBe($originalHash1);
+
+    expect(fn () => app(UserService::class)->update($user2, new UserData(role: UserRole::Retailer)))
+        ->toThrow(RuntimeException::class, 'Revocation failed.');
+    expect($user2->fresh()->role)->toBe(UserRole::Customer);
+
+    expect(fn () => app(UserService::class)->destroy($user3))
+        ->toThrow(RuntimeException::class, 'Revocation failed.');
+    expect(User::withTrashed()->where('id', $user3->id)->first()->deleted_at)->toBeNull();
+});
+
+it('invokes revoke all exactly once on qualifying operations and zero times on non qualifying operations with isolated spies', function (): void {
+    $createSpy = function (): object {
+        return new class implements TokenIssuer
+        {
+            public int $revokeAllCalls = 0;
+
+            public function issue(User $user): TokenPairData
+            {
+                throw new RuntimeException('Unused');
+            }
+
+            public function refresh(string $refreshToken): TokenPairData
+            {
+                throw new RuntimeException('Unused');
+            }
+
+            public function revoke(User $user): void
+            {
+                throw new RuntimeException('Unexpected revoke() call');
+            }
+
+            public function revokeAll(User $user): void
+            {
+                $this->revokeAllCalls++;
+            }
+        };
+    };
+
+    // Non-qualifying update
+    $spyA = $createSpy();
+    $this->app->instance(TokenIssuer::class, $spyA);
+    $userA = User::factory()->customer()->create();
+    app(UserService::class)->update($userA, new UserData(name: 'Updated'));
+    expect($spyA->revokeAllCalls)->toBe(0);
+
+    // Qualifying update with password and role
+    $spyB = $createSpy();
+    $this->app->instance(TokenIssuer::class, $spyB);
+    $userB = User::factory()->customer()->create();
+    app(UserService::class)->update($userB, new UserData(password: 'newpass123', role: UserRole::Retailer));
+    expect($spyB->revokeAllCalls)->toBe(1);
+
+    // Qualifying destroy
+    $spyC = $createSpy();
+    $this->app->instance(TokenIssuer::class, $spyC);
+    $userC = User::factory()->customer()->create();
+    app(UserService::class)->destroy($userC);
+    expect($spyC->revokeAllCalls)->toBe(1);
+});
+
+it('updates user password successfully when target user holds zero active tokens', function (): void {
+    $superadmin = User::factory()->superadmin()->create();
+    $target = User::factory()->customer()->create();
+
+    $superadminTokens = app(TokenIssuer::class)->issue($superadmin);
+
+    Auth::forgetGuards();
+    $response = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
+        ->patchJson("/api/v1/users/{$target->public_id}", [
+            'password' => 'new-password-zero-tokens',
+        ]);
+
+    $response->assertOk();
+    expect(Hash::check('new-password-zero-tokens', $target->fresh()->password))->toBeTrue();
+});
