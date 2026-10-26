[ACC-U2 | IMPL-REPORT | Gemini]

# Implementation Report: ACC-U2 User Domain Layer

- **Step ID**: `ACC-U2`
- **Stream**: Users (`[U]`)
- **Status**: VERIFY
- **Date**: 2026-10-23
- **Related Plan**: `docs/blueprints/ACC-U2/plan.md` (v3)
- **Base Branch**: `feat/users-foundation` (`24af45852640e656714227e189873a3dee13c1df`)
- **Head SHA**: `67b9eeb5ed4ce867f8be6a133664bb52a47c1d7d`
- **Docs diffs**: None (deferred to G4 closeout)
- **Gaps**: None

---

## 1. Summary of Changes

This step implements the Accounts domain persistence and business logic layer in accordance with `ARCHITECTURE.md`, `RULE_REGISTER.md`, and approved plan `ACC-U2 v3`:

1. **User DTOs & Domain Exceptions (`app/Domain/Accounts/Data/*`, `app/Domain/Accounts/Exceptions/*`)**:
   - `UserData`: Single Spatie Data DTO serving both store and update operations with typed `Optional|string` and `Optional|UserRole` properties. No secondary update DTO (Rule `STD4`).
   - `UserFilterData`: Spatie Data DTO for query filtering and pagination with defaults (`perPage: 15`, `includeTrashed: false`).
   - `ProtectedUserException`: Custom domain exception with named constructors `cannotCreateSuperadmin()`, `cannotPromoteToSuperadmin()`, `cannotChangeSuperadminRole()`, and `cannotDeleteSuperadmin()`.

2. **Repository Interface & Eloquent Implementation (`app/Domain/Accounts/Contracts/*`, `app/Domain/Accounts/Repositories/*`)**:
   - `UserRepositoryInterface`: Defines contract with LAY5 CRUD method parity: `index()`, `show()`, `store()`, `update()`, `destroy()`.
   - `UserRepository`: Implements `UserRepositoryInterface` using Eloquent queries only (no business logic, no HTTP exceptions). Handles Postgres case-insensitive ILIKE search escaping (`%`, `_`, `\`), role filtering, trashed record inclusion/exclusion, ULID generation on store, and selective attribute updates avoiding `null` overwrite.

3. **Container Registration (`app/Providers/AccountsServiceProvider.php`, `bootstrap/providers.php`)**:
   - `AccountsServiceProvider`: Final service provider binding `UserRepositoryInterface` to `UserRepository`.
   - Registered in `bootstrap/providers.php` under `declare(strict_types=1);`. `AppServiceProvider` remains untouched.

4. **User Domain Service (`app/Domain/Accounts/Services/UserService.php`)**:
   - Implements `UserService` adhering to LAY5 method parity (`index`, `show`, `store`, `update`, `destroy`).
   - Depends strictly on `UserRepositoryInterface` (no Eloquent builder dependency, no `App\Http`).
   - Enforces business rules:
     - Required field validation on store, writing no database row if missing.
     - Email normalization (trimming + lowercasing) on store and update.
     - Search string sanitization (trimming and converting whitespace-only to null).
     - Pagination clamping between 1 and 100 on index.
     - Superadmin protections: refuses superadmin creation, promotion to superadmin, changing superadmin role, or deletion of superadmin.

5. **Comprehensive Feature Test Suites (`tests/Feature/Accounts/*`)**:
   - `UserRepositoryTest`: 13 feature tests covering container resolution, role filtering, soft delete filtering, literal wildcard escaping (`%`, `_`, `\`), case-insensitive search, ULID generation, partial updates, and soft deletion.
   - `UserServiceTest`: 21 feature tests covering auto-wiring, DTO defaults, store validations, email normalization, partial updates, superadmin protections, pagination clamping, whitespace handling, and model not found handling.

---

## 2. Commit History

### `git log --oneline feat/users-foundation..HEAD`
```
67b9eeb feat(users): add user domain service and business rules
6f95384 feat(users): add user repository interface, eloquent repository, and service provider
8df439a feat(users): add user data transfer objects and domain exceptions
```

---

## 3. File Reconciliation

### 3.1 Raw `git diff --name-status feat/users-foundation...HEAD`
```
A	app/Domain/Accounts/Contracts/UserRepositoryInterface.php
A	app/Domain/Accounts/Data/UserData.php
A	app/Domain/Accounts/Data/UserFilterData.php
A	app/Domain/Accounts/Exceptions/ProtectedUserException.php
A	app/Domain/Accounts/Repositories/UserRepository.php
A	app/Domain/Accounts/Services/UserService.php
A	app/Providers/AccountsServiceProvider.php
M	bootstrap/providers.php
A	tests/Feature/Accounts/UserRepositoryTest.php
A	tests/Feature/Accounts/UserServiceTest.php
```

### 3.2 Raw `git diff --stat feat/users-foundation...HEAD`
```
 .../Accounts/Contracts/UserRepositoryInterface.php |  26 ++
 app/Domain/Accounts/Data/UserData.php              |  19 ++
 app/Domain/Accounts/Data/UserFilterData.php        |  18 ++
 .../Accounts/Exceptions/ProtectedUserException.php |  30 +++
 .../Accounts/Repositories/UserRepository.php       | 108 ++++++++
 app/Domain/Accounts/Services/UserService.php       | 131 ++++++++++
 app/Providers/AccountsServiceProvider.php          |  20 ++
 bootstrap/providers.php                            |   2 +
 tests/Feature/Accounts/UserRepositoryTest.php      | 183 ++++++++++++++
 tests/Feature/Accounts/UserServiceTest.php         | 276 +++++++++++++++++++++
 10 files changed, 813 insertions(+)
```

### 3.3 Reconciliation Table

| File | Status | Layer | Notes |
|---|---|---|---|
| `app/Domain/Accounts/Data/UserData.php` | Planned and done | Domain Data | Spatie Data DTO with Optional properties for store/update |
| `app/Domain/Accounts/Data/UserFilterData.php` | Planned and done | Domain Data | Spatie Data DTO for index filtering and pagination |
| `app/Domain/Accounts/Exceptions/ProtectedUserException.php` | Planned and done | Domain Exception | Domain exception with 4 named constructors for superadmin protections |
| `app/Domain/Accounts/Contracts/UserRepositoryInterface.php` | Planned and done | Domain Contract | Repository interface with LAY5 CRUD method parity |
| `app/Domain/Accounts/Repositories/UserRepository.php` | Planned and done | Domain Repository | Eloquent repository with wildcard escaping and selective updates |
| `app/Domain/Accounts/Services/UserService.php` | Planned and done | Domain Service | Business logic service with validation, normalizations, and protections |
| `app/Providers/AccountsServiceProvider.php` | Planned and done | Provider | Final service provider binding `UserRepositoryInterface` to `UserRepository` |
| `bootstrap/providers.php` | Planned and done | Bootstrap | Registers `AccountsServiceProvider::class` under strict_types |
| `tests/Feature/Accounts/UserRepositoryTest.php` | Planned and done | Testing | 13 feature tests for repository operations and wildcard escapes |
| `tests/Feature/Accounts/UserServiceTest.php` | Planned and done | Testing | 21 feature tests for domain service behaviors and protections |

Planned and not done: None.  
Unplanned: None. (Total: exactly 10 files).

---

## 4. Acceptance Criteria Results

| AC | File:Line | Test Name | Result |
|---|---|---|---|
| AC-1: DTO structures | `app/Domain/Accounts/Data/UserData.php:11`, `app/Domain/Accounts/Data/UserFilterData.php:10` | `tests/Feature/Accounts/UserServiceTest.php:21` (`it initializes user filter data with default filter values`), `tests/Feature/Accounts/UserServiceTest.php:30` (`it preserves optional fields during create then update lifecycle`) | PASSED |
| AC-2: Container binding | `app/Providers/AccountsServiceProvider.php:15`, `bootstrap/providers.php:7` | `tests/Feature/Accounts/UserRepositoryTest.php:13` (`it resolves user repository interface to user repository from container`), `tests/Feature/Accounts/UserServiceTest.php:15` (`it resolves user service from container via auto wiring`) | PASSED |
| AC-3: Store method & rules | `app/Domain/Accounts/Services/UserService.php:23`, `app/Domain/Accounts/Repositories/UserRepository.php:67` | `tests/Feature/Accounts/UserServiceTest.php:50` (`it throws invalid argument exception and writes no row when required fields are missing on store`), `tests/Feature/Accounts/UserServiceTest.php:68` (`it stores user with lowercased trimmed email, ulid public id, and hashed password`), `tests/Feature/Accounts/UserRepositoryTest.php:123` (`it stores user record with generated ulid public id and hashed password in repository`) | PASSED |
| AC-4: Update method & partials | `app/Domain/Accounts/Services/UserService.php:56`, `app/Domain/Accounts/Repositories/UserRepository.php:83` | `tests/Feature/Accounts/UserServiceTest.php:103` (`it updates only name without altering email, role, or password hash`), `tests/Feature/Accounts/UserServiceTest.php:124` (`it updates password hash when password is provided on update`), `tests/Feature/Accounts/UserServiceTest.php:140` (`it lowercases and trims email when email is provided on update`), `tests/Feature/Accounts/UserRepositoryTest.php:144` (`it updates only present fields leaving absent optional fields unmodified in repository`) | PASSED |
| AC-5: Index method & filters | `app/Domain/Accounts/Services/UserService.php:102`, `app/Domain/Accounts/Repositories/UserRepository.php:20` | `tests/Feature/Accounts/UserRepositoryTest.php:19` (`it paginates users and filters by role in repository`), `tests/Feature/Accounts/UserRepositoryTest.php:31` (`it excludes soft deleted users by default in repository index`), `tests/Feature/Accounts/UserRepositoryTest.php:43` (`it includes soft deleted users when includeTrashed is true in repository index`), `tests/Feature/Accounts/UserRepositoryTest.php:54` (`it escapes percent wildcard matching literal percent character only in repository index`), `tests/Feature/Accounts/UserRepositoryTest.php:66` (`it escapes underscore wildcard matching literal underscore character only in repository index`), `tests/Feature/Accounts/UserRepositoryTest.php:78` (`it escapes backslash wildcard matching literal backslash character only in repository index`), `tests/Feature/Accounts/UserRepositoryTest.php:90` (`it matches user email in repository index search`), `tests/Feature/Accounts/UserRepositoryTest.php:102` (`it searches user name case insensitively in repository index`), `tests/Feature/Accounts/UserServiceTest.php:248` (`it clamps per page between 1 and 100 on index`), `tests/Feature/Accounts/UserServiceTest.php:259` (`it treats empty or whitespace only search string as null on index`), `tests/Feature/Accounts/UserServiceTest.php:268` (`it trims search string in service so padded search matches user on index`) | PASSED |
| AC-6: Destroy method & soft delete | `app/Domain/Accounts/Services/UserService.php:94`, `app/Domain/Accounts/Repositories/UserRepository.php:105` | `tests/Feature/Accounts/UserRepositoryTest.php:171` (`it destroys user via soft delete setting deleted_at in repository`), `tests/Feature/Accounts/UserServiceTest.php:224` (`it destroys user keeping row in database while excluding from show and default index`) | PASSED |
| AC-7: Show method & lookups | `app/Domain/Accounts/Services/UserService.php:84`, `app/Domain/Accounts/Repositories/UserRepository.php:60` | `tests/Feature/Accounts/UserRepositoryTest.php:113` (`it finds user by public id excluding soft deleted users in repository show`), `tests/Feature/Accounts/UserServiceTest.php:197` (`it shows user by public id`), `tests/Feature/Accounts/UserServiceTest.php:207` (`it throws model not found exception for unknown public id on show`), `tests/Feature/Accounts/UserServiceTest.php:214` (`it throws model not found exception for soft deleted user on show`) | PASSED |
| AC-8: Superadmin protections | `app/Domain/Accounts/Services/UserService.php:28,61,96`, `app/Domain/Accounts/Exceptions/ProtectedUserException.php:13` | `tests/Feature/Accounts/UserServiceTest.php:89` (`it throws protected user exception and writes no row when attempting to store superadmin`), `tests/Feature/Accounts/UserServiceTest.php:151` (`it throws protected user exception and does not change role when attempting to promote user to superadmin`), `tests/Feature/Accounts/UserServiceTest.php:162` (`it throws protected user exception and does not change role when attempting to change superadmin role`), `tests/Feature/Accounts/UserServiceTest.php:173` (`it throws protected user exception and does not change role when updating superadmin with superadmin role provided`), `tests/Feature/Accounts/UserServiceTest.php:185` (`it allows updating name or email of existing superadmin`), `tests/Feature/Accounts/UserServiceTest.php:238` (`it throws protected user exception and does not destroy user when attempting to destroy superadmin`) | PASSED |
| AC-9: Checks clean & no regressions | Entire test suite | `composer check` (Pint + Larastan + Pest: 77/77 tests passing, 22/22 token tests unchanged) | PASSED |
| AC-10: Strict types & coding standards | All 10 manifest files | Tests in `tests/Arch/AccountsArchTest.php` and Layer A commands 15c to 15f | PASSED |

---

## 5. Deviations / CRs

None. Implementation strictly executed all specifications of approved plan `ACC-U2 v3` with zero deviations, zero extra files, and zero new dependencies.

---

## 6. Base Branch Baselines vs Post-Step State

### 6.1 Test Suite Expansion
- **Baseline on `feat/users-foundation`**: 43 tests (22 token tests, 4 unit tests, 6 arch tests, 11 user foundation tests).
- **Post-Step on `feat/users-domain-layer`**: 77 tests (+13 in `UserRepositoryTest`, +21 in `UserServiceTest`).

### 6.2 Raw `php artisan test --compact tests/Feature/Accounts/UserRepositoryTest.php`
```
{"tool":"pest","result":"passed","tests":13,"passed":13,"assertions":35,"duration_ms":2398}
```

### 6.3 Raw `php artisan test --compact tests/Feature/Accounts/UserServiceTest.php`
```
{"tool":"pest","result":"passed","tests":21,"passed":21,"assertions":53,"duration_ms":3162}
```

### 6.4 Raw `php artisan test --compact tests/Feature/Accounts/UserRoleTest.php`
```
{"tool":"pest","result":"passed","tests":11,"passed":11,"assertions":28,"duration_ms":2173}
```

### 6.5 Raw `php artisan test --compact tests/Arch/AccountsArchTest.php`
```
{"tool":"pest","result":"passed","tests":6,"passed":6,"assertions":10,"duration_ms":712}
```

---

## 7. Standard Layer A Verification Evidence

### Check 1: `git status --short`
```
?? docs/blueprints/ACC-U1/
?? docs/blueprints/ACC-U2/
```
*(Decision for F4: `docs/blueprints/ACC-U1/` and `docs/blueprints/ACC-U2/` are workflow blueprints and reports; they will be committed at G4 closeout along with the architecture checklist update)*

### Check 2: `git log --oneline feat/users-foundation..HEAD`
```
67b9eeb feat(users): add user domain service and business rules
6f95384 feat(users): add user repository interface, eloquent repository, and service provider
8df439a feat(users): add user data transfer objects and domain exceptions
```

### Check 3: `git diff --name-status feat/users-foundation...HEAD`
```
A	app/Domain/Accounts/Contracts/UserRepositoryInterface.php
A	app/Domain/Accounts/Data/UserData.php
A	app/Domain/Accounts/Data/UserFilterData.php
A	app/Domain/Accounts/Exceptions/ProtectedUserException.php
A	app/Domain/Accounts/Repositories/UserRepository.php
A	app/Domain/Accounts/Services/UserService.php
A	app/Providers/AccountsServiceProvider.php
M	bootstrap/providers.php
A	tests/Feature/Accounts/UserRepositoryTest.php
A	tests/Feature/Accounts/UserServiceTest.php
```

### Check 4: `git diff feat/users-foundation...HEAD -- composer.json composer.lock package.json`
*(Zero matches — empty output, no dependency changes)*

### Check 5: `git diff feat/users-foundation...HEAD --stat -- '*baseline*' phpstan.neon`
*(Zero matches — empty output, no PHPStan configuration or baseline modifications)*

### Check 6: `Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Http'`
*(Zero matches — empty output, Rule LAY6 strictly satisfied)*

### Check 7: `Get-ChildItem app/Http/Controllers -Recurse -Filter *.php | Select-String -SimpleMatch 'Repositories','Facades\DB'`
*(Zero matches — empty output, Rule LAY2 satisfied)*

### Check 8: `Get-ChildItem app -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport' | Select-Object -ExpandProperty Path -Unique`
```
C:\dev\getnow-auth2\app\Domain\Accounts\Tokens\PassportTokenIssuer.php
C:\dev\getnow-auth2\app\Models\User.php
C:\dev\getnow-auth2\app\Providers\TokenServiceProvider.php
```
*(Only Tokens stream issuer, TokenServiceProvider, and User model per Rule STM3)*

### Check 9: `Get-ChildItem app,bootstrap,routes,tests -Recurse -Filter *.php | Where-Object { -not (Select-String -Path $_.FullName -SimpleMatch 'declare(strict_types=1);' -Quiet) } | Select-Object -ExpandProperty FullName`
```
C:\dev\getnow-auth2\app\Http\Controllers\Controller.php
C:\dev\getnow-auth2\app\Providers\AppServiceProvider.php
C:\dev\getnow-auth2\bootstrap\app.php
C:\dev\getnow-auth2\bootstrap\cache\packages.php
C:\dev\getnow-auth2\bootstrap\cache\services.php
C:\dev\getnow-auth2\routes\console.php
C:\dev\getnow-auth2\tests\Pest.php
C:\dev\getnow-auth2\tests\Unit\ExampleTest.php
```
*(Matches baseline list captured in ACC-C1 exactly; all new and modified files have strict_types)*

### Check 10: `Get-ChildItem app -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'`
*(Zero matches — empty output, Rule ACT6 satisfied)*

### Check 11: `Get-ChildItem tests -Recurse -Filter *.php | Select-String -SimpleMatch '->skip(','->todo(','markTestIncomplete','markTestSkipped'`
*(Zero matches — empty output, Rule TST5 satisfied)*

### Check 12: `git ls-files | Select-String -SimpleMatch 'docker','compose','sail'`
```
composer.json
composer.lock
```
*(Zero matches for docker/compose/sail infrastructure files; matches composer.json/lock keywords only)*

### Check 13a: `git ls-files .env`
*(Zero matches — empty output, no .env files tracked)*

### Check 13b: `git ls-files storage`
```
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
*(Only .gitignore files tracked; zero key files or credentials in storage)*

### Check 14: `composer check`
```
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pest","result":"passed","tests":77,"passed":77,"assertions":229,"duration_ms":12673}
```

---

## 8. Step-Specific Layer A Verification Evidence

### Check 15a: No Migration Touched or Added
Versus base (`feat/users-foundation`):
```powershell
git diff --name-only feat/users-foundation...HEAD -- database/migrations
```
*(Zero matches — empty output)*

Versus `main`:
```powershell
git diff --name-only main...HEAD -- database/migrations
```
```
database/migrations/2026_10_22_081521_create_oauth_auth_codes_table.php
database/migrations/2026_10_22_081522_create_oauth_access_tokens_table.php
database/migrations/2026_10_22_081523_create_oauth_refresh_tokens_table.php
database/migrations/2026_10_22_081524_create_oauth_clients_table.php
database/migrations/2026_10_22_081525_create_oauth_device_codes_table.php
database/migrations/2026_10_23_000000_add_accounts_columns_to_users_table.php
```
*(All listed migrations are prior unmerged steps ACC-T0 and ACC-U1; zero migrations added in ACC-U2)*

### Check 15b: No Tokens-Stream, Config, or Route Files Touched
Versus base (`feat/users-foundation`):
```powershell
git diff --name-only feat/users-foundation...HEAD | Select-String -SimpleMatch 'Token','passport','routes/'
```
*(Zero matches — empty output)*

Versus `main`:
```powershell
git diff --name-only main...HEAD | Select-String -SimpleMatch 'Token','passport','routes/'
```
```
app/Domain/Accounts/Contracts/TokenIssuer.php
app/Domain/Accounts/Data/TokenPairData.php
app/Domain/Accounts/Tokens/PassportTokenIssuer.php
app/Http/Controllers/Api/V1/Auth/TokenController.php
app/Http/Requests/Auth/RefreshTokenRequest.php
app/Providers/TokenServiceProvider.php
config/passport.php
database/migrations/2026_10_22_081522_create_oauth_access_tokens_table.php
database/migrations/2026_10_22_081523_create_oauth_refresh_tokens_table.php
docs/adr/0001-passport-token-issuance-and-refresh-rotation.md
routes/api.php
routes/api/tokens.php
routes/settings.php
routes/web.php
tests/Feature/Tokens/PassportSetupTest.php
tests/Feature/Tokens/PassportTokenIssuerTest.php
tests/Feature/Tokens/TokenEndpointsTest.php
tests/Unit/Domain/Accounts/TokenPairDataTest.php
```
*(Zero touches introduced in ACC-U2)*

### Check 15c: Accounts Domain Imports Nothing from Passport, Http, or Filament
```powershell
Get-ChildItem app/Domain/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport','App\Http','App\Filament'
```
```
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
*(Matches exclusively within imported Tokens stream `PassportTokenIssuer.php`. Users domain classes contain zero imports of Passport, Http, or Filament)*

Filtering to Users stream domain code only:
```powershell
Get-ChildItem app/Domain/Accounts -Recurse -Filter *.php | Where-Object { $_.FullName -notmatch '\\Tokens\\' } | Select-String -SimpleMatch 'Laravel\Passport','App\Http','App\Filament'
```
*(Zero matches — empty output)*

### Check 15d: No Hash::make in Accounts Domain or Accounts Tests
```powershell
Get-ChildItem app/Domain/Accounts,tests/Feature/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'
```
*(Zero matches — empty output)*

### Check 15e: No Role String Literals in Accounts Domain
```powershell
Get-ChildItem app/Domain/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch "'superadmin'","'retailer'","'customer'"
```
```
app\Domain\Accounts\Enums\UserRole.php:9:    case Superadmin = 'superadmin';
app\Domain\Accounts\Enums\UserRole.php:10:    case Retailer = 'retailer';
app\Domain\Accounts\Enums\UserRole.php:11:    case Customer = 'customer';
```
*(Only backing values within the UserRole enum definition itself; zero string literals in repository, service, DTOs, or exceptions)*

### Check 15f: Every New/Modified File Has declare(strict_types=1); (including bootstrap per Amendment A4)
```powershell
Get-ChildItem app/Domain/Accounts,app/Providers,bootstrap,tests/Feature/Accounts -Recurse -Filter *.php | Where-Object { -not (Select-String -Path $_.FullName -SimpleMatch 'declare(strict_types=1);' -Quiet) } | Select-Object -ExpandProperty FullName
```
```
C:\dev\getnow-auth2\app\Providers\AppServiceProvider.php
C:\dev\getnow-auth2\bootstrap\app.php
C:\dev\getnow-auth2\bootstrap\cache\packages.php
C:\dev\getnow-auth2\bootstrap\cache\services.php
```
*(All new files in `app/Domain/Accounts/`, `app/Providers/AccountsServiceProvider.php`, modified file `bootstrap/providers.php`, and test files have `declare(strict_types=1);`. Untouched framework files match pre-existing baseline)*

### Check 15g: No Routes Added for Users
```powershell
php artisan route:list --path=users
```
```
 ERROR Your application doesn't have any routes matching the given criteria. 
```

### Check 15h: Token Tests Still Pass Unchanged
```powershell
php artisan test --compact tests/Feature/Tokens
```
```
{"tool":"pest","result":"passed","tests":22,"passed":22,"assertions":88,"duration_ms":5789}
```

---

## 9. Quality Gate Suite (`composer check`)

```powershell
composer check
```
```
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pest","result":"passed","tests":77,"passed":77,"assertions":229,"duration_ms":12673}
```

---

## 10. Mutation Spot-Check Evidence (Finding F2)

### Mutation 1: Remove Superadmin Guard in `UserService::destroy`
- **Change**: Removed `if ($user->role === UserRole::Superadmin) { throw ProtectedUserException::cannotDeleteSuperadmin(); }` from `app/Domain/Accounts/Services/UserService.php`.
- **Command**:
  ```powershell
  php artisan test --compact tests/Feature/Accounts/UserServiceTest.php
  ```
- **Raw Failing Output**:
  ```
  {"tool":"pest","result":"failed","tests":21,"passed":20,"assertions":51,"duration_ms":4367,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Accounts\\UserServiceTest::__pest_evaluable_it_throws_protected_user_exception_and_does_not_destroy_user_when_attempting_to_destroy_superadmin","file":"\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserServiceTest.php","line":243,"message":"Exception [App\\Domain\\Accounts\\Exceptions\\ProtectedUserException] not thrown.","trace":["C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserServiceTest.php:243"]}]}
  ```
- **Test that failed**: `it throws protected user exception and does not destroy user when attempting to destroy superadmin`
- **Restoration**: `git restore app/Domain/Accounts/Services/UserService.php`

### Mutation 2: Remove Search Trimming in `UserService::index`
- **Change**: Replaced search trimming and empty-check with `$search = $filter->search;` in `app/Domain/Accounts/Services/UserService.php`.
- **Command**:
  ```powershell
  php artisan test --compact tests/Feature/Accounts/UserServiceTest.php
  ```
- **Raw Failing Output**:
  ```
  {"tool":"pest","result":"failed","tests":21,"passed":19,"assertions":52,"duration_ms":3564,"failed":2,"failures":[{"test":"P\\Tests\\Feature\\Accounts\\UserServiceTest::__pest_evaluable_it_treats_empty_or_whitespace_only_search_string_as_null_on_index","file":"\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserServiceTest.php","line":265,"message":"Failed asserting that 0 is identical to 3.","trace":["C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserServiceTest.php:265"]},{"test":"P\\Tests\\Feature\\Accounts\\UserServiceTest::__pest_evaluable_it_trims_search_string_in_service_so_padded_search_matches_user_on_index","file":"\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserServiceTest.php","line":274,"message":"Failed asserting that 0 is identical to 1.","trace":["C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserServiceTest.php:274"]}]}
  ```
- **Tests that failed**:
  1. `it treats empty or whitespace only search string as null on index`
  2. `it trims search string in service so padded search matches user on index`
- **Restoration**: `git restore app/Domain/Accounts/Services/UserService.php`

### Mutation 3: Remove `addcslashes` Escaping in `UserRepository::index`
- **Change**: Removed `addcslashes($filter->search, '\\%_')` in `app/Domain/Accounts/Repositories/UserRepository.php`.
- **Command**:
  ```powershell
  php artisan test --compact tests/Feature/Accounts/UserRepositoryTest.php
  ```
- **Raw Failing Output**:
  ```
  {"tool":"pest","result":"failed","tests":13,"passed":10,"assertions":29,"duration_ms":2793,"failed":3,"failures":[{"test":"P\\Tests\\Feature\\Accounts\\UserRepositoryTest::__pest_evaluable_it_escapes_percent_wildcard_matching_literal_percent_character_only_in_repository_index","file":"\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserRepositoryTest.php","line":61,"message":"Failed asserting that 2 is identical to 1.","trace":["C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserRepositoryTest.php:61"]},{"test":"P\\Tests\\Feature\\Accounts\\UserRepositoryTest::__pest_evaluable_it_escapes_underscore_wildcard_matching_literal_underscore_character_only_in_repository_index","file":"\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserRepositoryTest.php","line":73,"message":"Failed asserting that 2 is identical to 1.","trace":["C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserRepositoryTest.php:73"]},{"test":"P\\Tests\\Feature\\Accounts\\UserRepositoryTest::__pest_evaluable_it_escapes_backslash_wildcard_matching_literal_backslash_character_only_in_repository_index","file":"\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserRepositoryTest.php","line":85,"message":"Failed asserting that 0 is identical to 1.","trace":["C:\\dev\\getnow-auth2\\tests\\Feature\\Accounts\\UserRepositoryTest.php:85"]}]}
  ```
- **Tests that failed**:
  1. `it escapes percent wildcard matching literal percent character only in repository index`
  2. `it escapes underscore wildcard matching literal underscore character only in repository index`
  3. `it escapes backslash wildcard matching literal backslash character only in repository index`
- **Restoration**: `git restore app/Domain/Accounts/Repositories/UserRepository.php`
