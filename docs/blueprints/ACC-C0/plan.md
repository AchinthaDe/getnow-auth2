# Implementation Plan: ACC-C0 Architecture Tests & Seam Verification

- **Step ID**: `ACC-C0`
- **Stream**: Contract (`[C]`)
- **Version**: `v1`
- **Author**: Gemini
- **Status**: Proposed / Awaiting Approval

---

## 1. Goal

Add Pest architecture tests in `tests/Arch/AccountsArchTest.php` enforcing the layer and stream boundaries from `ARCHITECTURE.md` section 4 & 5 for existing code, ensuring later steps (`ACC-U1` onward) cannot silently break architectural constraints. No changes to application code under `app/`.

---

## 2. Test Discovery & Suite Registration

1. **Discovery Finding**:
   - `phpunit.xml` currently defines only `Unit` and `Feature` test suites:
     ```xml
     <testsuites>
         <testsuite name="Unit">
             <directory>tests/Unit</directory>
         </testsuite>
         <testsuite name="Feature">
             <directory>tests/Feature</directory>
         </testsuite>
     </testsuites>
     ```
   - Running `php artisan test` or `composer check` without an `Arch` testsuite ignores `tests/Arch`.
2. **Configuration Change**:
   - In accordance with the brief ("add an Arch testsuite ONLY if tests/Arch is not discovered otherwise"), add `<testsuite name="Arch"><directory>tests/Arch</directory></testsuite>` to `phpunit.xml`.
   - Baseline test count: **58 tests, 181 assertions**.
   - With 6 arch tests added: **64 tests, 191 assertions** (+6 tests, +10 assertions).

---

## 3. Architecture Rules (`tests/Arch/AccountsArchTest.php`)

All tests will be contained in `tests/Arch/AccountsArchTest.php` (tagged `[C]`):

1. **AC-2: Domain Layer Isolation**
   ```php
   arch('domain does not use http')
       ->expect('App\Domain')
       ->not->toUse('App\Http');
   ```
2. **AC-3: Passport Encapsulation**
   ```php
   arch('passport is used only by token stream and user model')
       ->expect('Laravel\Passport')
       ->toOnlyBeUsedIn([
           'App\Domain\Accounts\Tokens',
           'App\Providers\TokenServiceProvider',
           'App\Models\User',
       ]);
   ```
   *(Pest arch scans application classes; tests do not need to be in the allow list).*
3. **AC-4: Contracts and Data Objects Structural Typing**
   ```php
   arch('contracts are interfaces')
       ->expect('App\Domain\Accounts\Contracts')
       ->toBeInterfaces();

   arch('data objects extend spatie laravel data')
       ->expect('App\Domain\Accounts\Data')
       ->toExtend(Data::class);
   ```
4. **AC-5: Strict Types Enforcement**
   ```php
   arch('strict types are enforced')
       ->expect([
           'App\Domain',
           'App\Http\Controllers\Api',
           'App\Http\Requests\Auth',
           'App\Providers\TokenServiceProvider',
       ])
       ->toUseStrictTypes();
   ```
5. **AC-6: Controller Boundaries (No Repositories or DB Facade)**
   ```php
   arch('api controllers do not use repositories or db facade')
       ->expect('App\Http\Controllers\Api')
       ->not->toUse([
           'App\Domain\Accounts\Repositories',
           'Illuminate\Support\Facades\DB',
       ]);
   ```
   *(Verified that Pest does not error on the non-existent `Repositories` namespace; both constraints are safely active).*

---

## 4. Verification and Mutation Testing (AC-7)

Two independent mutation tests to prove the arch rules catch violations:
1. **Mutation 1 (AC-2 violation)**:
   - Temporarily add `use App\Http\Requests\Auth\RefreshTokenRequest;` to `app/Domain/Accounts/Tokens/PassportTokenIssuer.php`.
   - Execute `php artisan test tests/Arch`.
   - Record failure message (`Expecting 'App\Domain' not to use 'App\Http'`).
   - Revert file back to exact baseline byte-match.
   - Run `php artisan test tests/Arch` to verify clean pass.
2. **Mutation 2 (AC-5 violation)**:
   - Temporarily remove `declare(strict_types=1);` from `app/Providers/TokenServiceProvider.php`.
   - Execute `php artisan test tests/Arch`.
   - Record failure message (`Expecting 'app\Providers\TokenServiceProvider.php' to use strict types`).
   - Revert file back to exact baseline byte-match.
   - Run `php artisan test tests/Arch` to verify clean pass.
3. **Integrity Check**:
   - Run `git status` to ensure zero residual mutations.
   - Verify byte hashes of all 13 baseline files against `C:\dev\getnow-auth`.

---

## 5. Quality Gate Verification (AC-8)

Execute Layer A verification commands:
```powershell
php artisan test tests/Arch
composer check
```
Verify:
- Pint passes without formatting diffs.
- PHPStan level 7 passes with 0 errors (no new baseline or ignores).
- Pest executes 64 tests with 64 passing.

---

## 6. Deferred Rules (To be tracked for subsequent steps)

The following rules cannot be tested yet because the target classes do not exist:
1. `App\Domain\Accounts\Services` does not use `Illuminate\Http` (Deferred to `ACC-U3`).
2. `UserService` / `AuthService` depend on `UserRepositoryInterface`, never on `UserRepository` (Deferred to `ACC-U3`).
3. Controllers do not use `UserRepository` (Deferred to `ACC-U3`).
4. Users-stream code does not use `Laravel\Passport` (Deferred to `ACC-U1` - `ACC-U5`).
5. `App\Domain\Accounts\Tokens` does not use Services or Repositories (Deferred to `ACC-U3`).
