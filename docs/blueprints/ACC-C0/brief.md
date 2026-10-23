[ACC-C0 | BRIEF | Claude]

Goal:
Add architecture tests (Pest arch) that enforce the layer and stream boundaries from
ARCHITECTURE.md for the code that exists today, so later steps (ACC-U1 onward) cannot
break them silently. The toolchain part of ACC-C0 (composer check) was already completed
inside ACC-T0 and the TokenIssuer contract was confirmed by the import. This step adds
tests only. No application code changes.

Depends on:
ACC-T0 (accepted). Uses: App\Domain\Accounts\Contracts\TokenIssuer,
App\Domain\Accounts\Data\TokenPairData, App\Domain\Accounts\Tokens\PassportTokenIssuer,
App\Http\Controllers\Api\V1\Auth\TokenController,
App\Http\Requests\Auth\RefreshTokenRequest, App\Providers\TokenServiceProvider,
phpunit.xml, tests/Pest.php.

In scope:
- New file tests/Arch/AccountsArchTest.php.
- phpunit.xml: add an Arch testsuite ONLY if tests/Arch is not discovered otherwise.
- Rules for code that exists now (see Acceptance criteria).

Out of scope:
- Any change under app/, config/, routes/, database/, composer.json.
- Rules about Services, Repositories, UserData, UserPolicy (those classes do not exist yet).
- Porting the old project's rules for App\Domain\User, App\Domain\DTO or Api\UserController.
- Editing starter-kit files (Fortify, Livewire) to satisfy a rule.
- Any user-stream work (ACC-U steps).

Read first:
- ARCHITECTURE.md sections 4, 5, 10, 11 (Testing).
- AGENTS.md: "First thing every session", "Importing the tokens stream", "Testing".
- C:\dev\getnow-auth\tests\Arch\AccountsArchTest.php (read-only reference; adapt, do not copy blindly).
- phpunit.xml, tests/Pest.php.

Decisions made (do not re-decide):
- File is tests/Arch/AccountsArchTest.php, tagged [C].
- Imported code is frozen. If a rule fails against it, stop and raise a CR. Do not edit it.
- TokenController imports App\Models\User for an instanceof check. This is accepted. Scope
  the controller rule to repositories and the DB facade, not to App\Models.
- Rules cover only our namespaces. Starter-kit code is out of scope.

Open decisions:
None blocking. (Separate from this step: whether to keep or remove the starter kit,
Fortify and Livewire, must be answered before the ACC-U1 brief.)

Acceptance criteria:
AC-1: tests/Arch/AccountsArchTest.php exists and runs under composer check. The total
      test count rises by the number of arch tests added (state the before and after counts).
AC-2: App\Domain does not use App\Http.
AC-3: Laravel\Passport is used only by App\Domain\Accounts\Tokens,
      App\Providers\TokenServiceProvider and App\Models\User. If Pest counts test
      namespaces as users, add them to the allow list and state that in the report.
AC-4: Everything in App\Domain\Accounts\Contracts is an interface. Everything in
      App\Domain\Accounts\Data extends Spatie\LaravelData\Data.
AC-5: declare(strict_types=1) is enforced for App\Domain, App\Http\Controllers\Api,
      App\Http\Requests\Auth and App\Providers\TokenServiceProvider.
AC-6: App\Http\Controllers\Api does not use App\Domain\Accounts\Repositories or
      Illuminate\Support\Facades\DB. If Pest errors on the missing Repositories namespace,
      drop that half and carry it to ACC-U3.
AC-7: Mutation check: for two different rules, temporarily introduce a violation, paste the
      failing output, revert it, and paste the clean run. git status shows no leftover mutation.
AC-8: composer check passes. No file under app/ changed. phpstan.neon has no new
      baseline or ignore entries.

Rules most at risk:
- AGENTS.md "Do not": never change imported token code during this step.
- ARCHITECTURE.md section 4: Controllers must not touch Eloquent. The accepted exception is
  the User instanceof check in TokenController. Do not widen it.
- AGENTS.md "Testing": do not weaken, skip or delete checks to get green.

Known traps:
1. Discovery: phpunit.xml may only list the Unit and Feature suites. An arch test that is
   never executed proves nothing. AC-1 and AC-7 catch this.
2. Empty namespaces: check how the installed Pest arch plugin behaves for namespaces that
   do not exist yet. Do not guess.
3. Starter-kit files in app/ may lack strict_types. Keep AC-5 scoped as written.
4. The old arch test targets the old structure. Do not port its rules for App\Domain\User,
   App\Domain\DTO or Api\UserController.
5. RefreshDatabase is bound to the Feature suite only. Arch tests need no database.

Deferred rules (add in the step that creates the layer, list them in the CLOSEOUT):
- Services do not use Illuminate\Http.
- Services depend on UserRepositoryInterface, never on UserRepository.
- Controllers do not use Repositories.
- Users-stream code (Services, Repositories, Policies, user controllers) does not use Laravel\Passport.
- App\Domain\Accounts\Tokens does not use Services or Repositories.

Size guard:
1 new file, plus phpunit.xml only if discovery needs it. Split trigger: any change under
app/ or to composer.json.

Step-specific checks (Layer A, one at a time, raw output):
  php artisan test tests/Arch
  composer check
  plus the two mutation runs from AC-7.