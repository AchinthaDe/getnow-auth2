# Implementation Report: ACC-C0 Architecture Tests & Seam Verification

- **Step ID**: `ACC-C0`
- **Stream**: Contract (`[C]`)
- **Status**: Ready for review
- **Date**: 2026-10-23
- **Related Plan**: `docs/blueprints/ACC-C0/plan.md` (v1)

---

## 1. Summary of Changes

This step adds Pest architecture tests in `tests/Arch/AccountsArchTest.php` enforcing the architectural layer and stream boundaries from `ARCHITECTURE.md` section 4 & 5 for code present in the repository, fulfilling all criteria of the `ACC-C0` brief:

1. **Test Discovery (`phpunit.xml`)**:
   - `phpunit.xml` previously only configured `Unit` and `Feature` suites; tests under `tests/Arch` were not discovered.
   - Added `<testsuite name="Arch"><directory>tests/Arch</directory></testsuite>` to `phpunit.xml` under `<testsuites>`.
   - Test count increased from **58** to **64** tests (+6 tests, +10 assertions).

2. **Architecture Test Suite (`tests/Arch/AccountsArchTest.php`)**:
   - **AC-2**: `App\Domain` does not use `App\Http`.
   - **AC-3**: `Laravel\Passport` is used only by `App\Domain\Accounts\Tokens`, `App\Providers\TokenServiceProvider`, and `App\Models\User`. (Test namespaces do not need to be in the allow list).
   - **AC-4**: All classes in `App\Domain\Accounts\Contracts` are interfaces. All classes in `App\Domain\Accounts\Data` extend `Spatie\LaravelData\Data`.
   - **AC-5**: `declare(strict_types=1)` enforced on `App\Domain`, `App\Http\Controllers\Api`, `App\Http\Requests\Auth`, and `App\Providers\TokenServiceProvider`.
   - **AC-6**: `App\Http\Controllers\Api` does not use `App\Domain\Accounts\Repositories` or `Illuminate\Support\Facades\DB` (DB half enforced and proven via M3; Repositories half is dormant per M4).

3. **No Application Code Changes**:
   - Zero permanent changes made under `app/`, `config/`, `routes/`, `database/`, or `composer.json`.

---

## 2. Step A Baseline & T0 Commit Evidence (F1)

### 2.1 Step A4: Staging Check on `feat/tokens-import` (No `.env` or `storage/*.key`)
```powershell
git status --short
```
```
M  .env.example
A  app/Domain/Accounts/Contracts/TokenIssuer.php
A  app/Domain/Accounts/Data/TokenPairData.php
A  app/Domain/Accounts/Tokens/PassportTokenIssuer.php
A  app/Http/Controllers/Api/V1/Auth/TokenController.php
A  app/Http/Requests/Auth/RefreshTokenRequest.php
M  app/Models/User.php
A  app/Providers/TokenServiceProvider.php
M  bootstrap/app.php
M  bootstrap/providers.php
M  composer.json
M  composer.lock
M  config/auth.php
A  config/passport.php
A  database/migrations/2026_10_22_081521_create_oauth_auth_codes_table.php
A  database/migrations/2026_10_22_081522_create_oauth_access_tokens_table.php
A  database/migrations/2026_10_22_081523_create_oauth_refresh_tokens_table.php
A  database/migrations/2026_10_22_081524_create_oauth_clients_table.php
A  database/migrations/2026_10_22_081525_create_oauth_device_codes_table.php
A  docs/adr/0001-passport-token-issuance-and-refresh-rotation.md
A  docs/blueprints/ACC-T0/plan.md
A  docs/blueprints/ACC-T0/report.md
A  "docs/blueprints/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md"
M  phpunit.xml
A  routes/api.php
A  routes/api/tokens.php
M  tests/Feature/Auth/AuthenticationTest.php
M  tests/Feature/Auth/EmailVerificationTest.php
M  tests/Feature/Auth/PasswordConfirmationTest.php
M  tests/Feature/Auth/PasswordResetTest.php
M  tests/Feature/Auth/RegistrationTest.php
M  tests/Feature/Auth/TwoFactorChallengeTest.php
M  tests/Feature/DashboardTest.php
M  tests/Feature/ExampleTest.php
M  tests/Feature/Settings/ProfileUpdateTest.php
M  tests/Feature/Settings/SecurityTest.php
A  tests/Feature/Tokens/PassportSetupTest.php
A  tests/Feature/Tokens/PassportTokenIssuerTest.php
A  tests/Feature/Tokens/TokenEndpointsTest.php
M  tests/Pest.php
A  tests/Unit/Domain/Accounts/TokenPairDataTest.php
M  tests/Unit/ExampleTest.php
?? docs/blueprints/ACC-C0/
?? tests/Arch/
```

### 2.2 Step A5: Commit ACC-T0 to `feat/tokens-import`
```powershell
git commit -m "feat(tokens): import Passport token stream (ACC-T0)"
```
```
[feat/tokens-import 63393ea] feat(tokens): import Passport token stream (ACC-T0)
 42 files changed, 2902 insertions(+), 39 deletions(-)
 create mode 100644 app/Domain/Accounts/Contracts/TokenIssuer.php
 create mode 100644 app/Domain/Accounts/Data/TokenPairData.php
 create mode 100644 app/Domain/Accounts/Tokens/PassportTokenIssuer.php
 create mode 100644 app/Http/Controllers/Api/V1/Auth/TokenController.php
 create mode 100644 app/Http/Requests/Auth/RefreshTokenRequest.php
 create mode 100644 app/Providers/TokenServiceProvider.php
 create mode 100644 config/passport.php
 create mode 100644 database/migrations/2026_10_22_081521_create_oauth_auth_codes_table.php
 create mode 100644 database/migrations/2026_10_22_081522_create_oauth_access_tokens_table.php
 create mode 100644 database/migrations/2026_10_22_081523_create_oauth_refresh_tokens_table.php
 create mode 100644 database/migrations/2026_10_22_081524_create_oauth_clients_table.php
 create mode 100644 database/migrations/2026_10_22_081525_create_oauth_device_codes_table.php
 create mode 100644 docs/adr/0001-passport-token-issuance-and-refresh-rotation.md
 create mode 100644 docs/blueprints/ACC-T0/plan.md
 create mode 100644 docs/blueprints/ACC-T0/report.md
 create mode 100644 docs/blueprints/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md
 create mode 100644 routes/api.php
 create mode 100644 routes/api/tokens.php
 create mode 100644 tests/Feature/Tokens/PassportSetupTest.php
 create mode 100644 tests/Feature/Tokens/PassportTokenIssuerTest.php
 create mode 100644 tests/Feature/Tokens/TokenEndpointsTest.php
 create mode 100644 tests/Unit/Domain/Accounts/TokenPairDataTest.php
```

### 2.3 Step A6: Baseline `composer check` (AC-1 Before Count)
```powershell
composer check
```
```
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pest","result":"passed","tests":58,"passed":58,"assertions":181,"duration_ms":78087}
```
*Confirmed baseline: **58 tests, 181 assertions**.*

### 2.4 Step A9: `git status --short` on `feat/contract-arch-tests`
```powershell
git status --short
```
```
 M phpunit.xml
?? docs/blueprints/ACC-C0/
?? tests/Arch/
```

### 2.5 `git diff phpunit.xml` (F2)
```powershell
git diff phpunit.xml
```
```diff
diff --git a/phpunit.xml b/phpunit.xml
index 6a875d6..a44d2c6 100644
--- a/phpunit.xml
+++ b/phpunit.xml
@@ -11,6 +11,9 @@
         <testsuite name="Feature">
             <directory>tests/Feature</directory>
         </testsuite>
+        <testsuite name="Arch">
+            <directory>tests/Arch</directory>
+        </testsuite>
     </testsuites>
     <source>
         <include>
```

---

## 3. Full Contents of `tests/Arch/AccountsArchTest.php` (F4)

```php
<?php

declare(strict_types=1);
use Spatie\LaravelData\Data;

arch('domain does not use http')
    ->expect('App\Domain')
    ->not->toUse('App\Http');

// User model carries HasApiTokens and implements OAuthenticatable for Passport 13 integration
arch('passport is used only by token stream and user model')
    ->expect('Laravel\Passport')
    ->toOnlyBeUsedIn([
        'App\Domain\Accounts\Tokens',
        'App\Providers\TokenServiceProvider',
        'App\Models\User',
    ]);

arch('contracts are interfaces')
    ->expect('App\Domain\Accounts\Contracts')
    ->toBeInterfaces();

arch('data objects extend spatie laravel data')
    ->expect('App\Domain\Accounts\Data')
    ->toExtend(Data::class);

arch('strict types are enforced')
    ->expect([
        'App\Domain',
        'App\Http\Controllers\Api',
        'App\Http\Requests\Auth',
        'App\Providers\TokenServiceProvider',
    ])
    ->toUseStrictTypes();

// Excludes App\Models to allow the accepted TokenController User instanceof check
arch('api controllers do not use repositories or db facade')
    ->expect('App\Http\Controllers\Api')
    ->not->toUse([
        'App\Domain\Accounts\Repositories',
        'Illuminate\Support\Facades\DB',
    ]);
```

---

## 4. Mutation Checks M1–M5 (F3, m1, m2)

### M1: `PassportTokenIssuer.php` — Add top-level `use App\Http\Requests\Auth\RefreshTokenRequest;`

1. `git diff app/Domain/Accounts/Tokens/PassportTokenIssuer.php`
```diff
diff --git a/app/Domain/Accounts/Tokens/PassportTokenIssuer.php b/app/Domain/Accounts/Tokens/PassportTokenIssuer.php
index 91ec8cb..ee16df2 100644
--- a/app/Domain/Accounts/Tokens/PassportTokenIssuer.php
+++ b/app/Domain/Accounts/Tokens/PassportTokenIssuer.php
@@ -6,6 +6,7 @@
 
 use App\Domain\Accounts\Contracts\TokenIssuer;
 use App\Domain\Accounts\Data\TokenPairData;
+use App\Http\Requests\Auth\RefreshTokenRequest;
 use App\Models\User;
 use DateInterval;
 use DateTimeImmutable;
```

2. `php artisan test tests/Arch`
```json
{"tool":"pest","result":"failed","tests":6,"passed":5,"assertions":9,"duration_ms":14330,"failed":1,"failures":[{"test":"P\\Tests\\Arch\\AccountsArchTest::__pest_evaluable_domain_does_not_use_http","file":"\\dev\\getnow-auth2\\vendor\\pestphp\\pest-plugin-arch\\src\\SingleArchExpectation.php","line":176,"message":"Expecting 'App\\Domain' not to use 'App\\Http'.","trace":["C:\\dev\\getnow-auth2\\vendor\\pestphp\\pest-plugin-arch\\src\\SingleArchExpectation.php:176"]}]}
```

3. `git restore app/Domain/Accounts/Tokens/PassportTokenIssuer.php`
*(exited with code 0)*

4. `git status --short`
```
 M phpunit.xml
?? docs/blueprints/ACC-C0/
?? tests/Arch/
```

5. `php artisan test tests/Arch`
```json
{"tool":"pest","result":"passed","tests":6,"passed":6,"assertions":10,"duration_ms":956}
```

---

### M2: `TokenServiceProvider.php` — Delete `declare(strict_types=1);`

1. `git diff app/Providers/TokenServiceProvider.php`
```diff
diff --git a/app/Providers/TokenServiceProvider.php b/app/Providers/TokenServiceProvider.php
index 9795dc7..87deed5 100644
--- a/app/Providers/TokenServiceProvider.php
+++ b/app/Providers/TokenServiceProvider.php
@@ -1,7 +1,5 @@
 <?php
 
-declare(strict_types=1);
-
 namespace App\Providers;
 
 use App\Domain\Accounts\Contracts\TokenIssuer;
```

2. `php artisan test tests/Arch`
```json
{"tool":"pest","result":"failed","tests":6,"passed":5,"assertions":10,"duration_ms":1537,"failed":1,"failures":[{"test":"P\\Tests\\Arch\\AccountsArchTest::__pest_evaluable_strict_types_are_enforced","file":"app\\Providers\\TokenServiceProvider.php","line":1,"message":"Expecting 'app\\Providers\\TokenServiceProvider.php' to use strict types."}]}
```

3. `git restore app/Providers/TokenServiceProvider.php`
*(exited with code 0)*

4. `git status --short`
```
 M phpunit.xml
?? docs/blueprints/ACC-C0/
?? tests/Arch/
```

5. `php artisan test tests/Arch`
```json
{"tool":"pest","result":"passed","tests":6,"passed":6,"assertions":10,"duration_ms":1068}
```

---

### M3: `TokenController.php` — Add top-level `use Illuminate\Support\Facades\DB;`

1. `git diff app/Http/Controllers/Api/V1/Auth/TokenController.php`
```diff
diff --git a/app/Http/Controllers/Api/V1/Auth/TokenController.php b/app/Http/Controllers/Api/V1/Auth/TokenController.php
index c885d76..5bf1414 100644
--- a/app/Http/Controllers/Api/V1/Auth/TokenController.php
+++ b/app/Http/Controllers/Api/V1/Auth/TokenController.php
@@ -12,6 +12,7 @@
 use Illuminate\Http\JsonResponse;
 use Illuminate\Http\Response;
 use Illuminate\Support\Facades\Auth;
+use Illuminate\Support\Facades\DB;
 
 final class TokenController extends Controller
 {
```

2. `php artisan test tests/Arch`
```json
{"tool":"pest","result":"failed","tests":6,"passed":5,"assertions":9,"duration_ms":624,"failed":1,"failures":[{"test":"P\\Tests\\Arch\\AccountsArchTest::__pest_evaluable_api_controllers_do_not_use_repositories_or_db_facade","file":"\\dev\\getnow-auth2\\vendor\\pestphp\\pest-plugin-arch\\src\\SingleArchExpectation.php","line":176,"message":"Expecting 'App\\Http\\Controllers\\Api' not to use 'Illuminate\\Support\\Facades\\DB'.","trace":["C:\\dev\\getnow-auth2\\vendor\\pestphp\\pest-plugin-arch\\src\\SingleArchExpectation.php:176"]}]}
```

3. `git restore app/Http/Controllers/Api/V1/Auth/TokenController.php`
*(exited with code 0)*

4. `git status --short`
```
 M phpunit.xml
?? docs/blueprints/ACC-C0/
?? tests/Arch/
```

5. `php artisan test tests/Arch`
```json
{"tool":"pest","result":"passed","tests":6,"passed":6,"assertions":10,"duration_ms":628}
```

---

### M4: `TokenController.php` — Add top-level `use App\Domain\Accounts\Repositories\UserRepository;`

1. `git diff app/Http/Controllers/Api/V1/Auth/TokenController.php`
```diff
diff --git a/app/Http/Controllers/Api/V1/Auth/TokenController.php b/app/Http/Controllers/Api/V1/Auth/TokenController.php
index c885d76..da00502 100644
--- a/app/Http/Controllers/Api/V1/Auth/TokenController.php
+++ b/app/Http/Controllers/Api/V1/Auth/TokenController.php
@@ -5,6 +5,7 @@
 namespace App\Http\Controllers\Api\V1\Auth;
 
 use App\Domain\Accounts\Contracts\TokenIssuer;
+use App\Domain\Accounts\Repositories\UserRepository;
 use App\Http\Controllers\Controller;
 use App\Http\Requests\Auth\RefreshTokenRequest;
 use App\Models\User;
```

2. `php artisan test tests/Arch`
```json
{"tool":"pest","result":"passed","tests":6,"passed":6,"assertions":10,"duration_ms":733}
```

> **CR / Technical Finding for M4**:
> Mutation M4 **passed** instead of failing.
> **Explanation**: The namespace `App\Domain\Accounts\Repositories` does not exist yet (no files or classes exist in that path). In Pest Arch, `LayerFactory::make()` constructs layers from classes discovered in the project via Composer autoload reflection. Because no classes exist under `App\Domain\Accounts\Repositories`, the dependency layer contains zero elements (`[]`), causing Pest's dependency assertion to find no usage. Per AC-6, Pest does not crash on the non-existent namespace, but enforcement of this rule remains dormant until `UserRepository` is created in `ACC-U2`/`ACC-U3`.

3. `git restore app/Http/Controllers/Api/V1/Auth/TokenController.php`
*(exited with code 0)*

4. `git status --short`
```
 M phpunit.xml
?? docs/blueprints/ACC-C0/
?? tests/Arch/
```

5. `php artisan test tests/Arch`
```json
{"tool":"pest","result":"passed","tests":6,"passed":6,"assertions":10,"duration_ms":652}
```

---

### M5: `TokenController.php` — Add top-level `use Laravel\Passport\Passport;`

1. `git diff app/Http/Controllers/Api/V1/Auth/TokenController.php`
```diff
diff --git a/app/Http/Controllers/Api/V1/Auth/TokenController.php b/app/Http/Controllers/Api/V1/Auth/TokenController.php
index c885d76..5765b40 100644
--- a/app/Http/Controllers/Api/V1/Auth/TokenController.php
+++ b/app/Http/Controllers/Api/V1/Auth/TokenController.php
@@ -6,6 +6,7 @@
 
 use App\Domain\Accounts\Contracts\TokenIssuer;
 use App\Http\Controllers\Controller;
+use Laravel\Passport\Passport;
 use App\Http\Requests\Auth\RefreshTokenRequest;
 use App\Models\User;
 use Illuminate\Auth\AuthenticationException;
```

2. `php artisan test tests/Arch`
```json
{"tool":"pest","result":"failed","tests":6,"passed":5,"assertions":13,"duration_ms":723,"failed":1,"failures":[{"test":"P\\Tests\\Arch\\AccountsArchTest::__pest_evaluable_passport_is_used_only_by_token_stream_and_user_model","file":"\\dev\\getnow-auth2/app\\Http\\Controllers\\Api\\V1\\Auth\\TokenController.php","line":9,"message":"Expecting 'Laravel\\Passport\\Passport' not to be used on 'App\\Http\\Controllers\\Api\\V1\\Auth\\TokenController'.","trace":["C:\\dev\\getnow-auth2/app\\Http\\Controllers\\Api\\V1\\Auth\\TokenController.php:9"]}]}
```

3. `git restore app/Http/Controllers/Api/V1/Auth/TokenController.php`
*(exited with code 0)*

4. `git status --short`
```
 M phpunit.xml
?? docs/blueprints/ACC-C0/
?? tests/Arch/
```

5. `php artisan test tests/Arch`
```json
{"tool":"pest","result":"passed","tests":6,"passed":6,"assertions":10,"duration_ms":632}
```

---

## 5. Full Quality Gate Suite (`composer check`)

```powershell
composer check
```
```
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pest","result":"passed","tests":64,"passed":64,"assertions":191,"duration_ms":12754}
```

- Before count (ACC-T0 on `feat/tokens-import`): **58 tests, 181 assertions**.
- After count (ACC-C0 on `feat/contract-arch-tests`): **64 tests, 191 assertions** (+6 tests, +10 assertions).

---

## 6. Clean Working Tree (`git status`)

```powershell
git status
```
```
On branch feat/contract-arch-tests
Changes not staged for commit:
  (use "git add <file>..." to update what will be committed)
  (use "git restore <file>..." to discard changes in working directory)
	modified:   phpunit.xml

Untracked files:
  (use "git add <file>..." to include in what will be committed)
	docs/blueprints/ACC-C0/
	tests/Arch/

no changes added to commit (use "git add" and/or "git commit -a")
```
*(No files under `app/`, `config/`, `routes/`, `database/`, or `composer.json` are modified or untracked).*

---

## 7. Deferred Rules (Closeout Checklist) (m4)

- Services do not use Illuminate\Http (U3)
- Services depend on UserRepositoryInterface, never UserRepository (U3, covers AuthService in U4)
- Controllers must not use Repositories (dormant until the namespace has a class) or UserRepositoryInterface (Contracts) (U3)
- Users-stream code does not use Laravel\Passport, added per layer: Repositories (U2), Services, Policies, UserController (U3), AuthService, AuthController (U4)
- Tokens does not use Repositories (U2) or Services (U3)
- U2 acceptance criteria must include mutation proof that the controller→Repositories and Tokens→Repositories rules fire

---

## 8. Definition of Done Checklist (m3)

- [ ] `tests/Arch/AccountsArchTest.php` exists and runs under `composer check` (AC-1)
- [ ] Test count rose from 58 to 64 (+6 tests, +10 assertions) (AC-1)
- [ ] `App\Domain` does not use `App\Http` (AC-2)
- [ ] `Laravel\Passport` restricted to `App\Domain\Accounts\Tokens`, `App\Providers\TokenServiceProvider`, and `App\Models\User` (AC-3)
- [ ] `App\Domain\Accounts\Contracts` contains only interfaces; `App\Domain\Accounts\Data` extends `Spatie\LaravelData\Data` (AC-4)
- [ ] `declare(strict_types=1)` enforced on all specified namespaces (AC-5)
- [ ] `App\Http\Controllers\Api` forbidden from using `Repositories` or `DB` facade (AC-6: DB half enforced and proven via M3; Repositories half is dormant per M4)
- [ ] Mutation check verified for AC-2 and AC-5 with failing and reverted passes (AC-7)
- [ ] Zero leftover mutations in git working tree (AC-7)
- [ ] `composer check` passes cleanly (Pint + PHPStan + Pest) with 0 errors (AC-8)
- [ ] No changes under `app/`, `config/`, `routes/`, `database/`, or `composer.json` (AC-8)
