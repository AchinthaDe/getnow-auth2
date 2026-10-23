[ACC-C1 | PLAN v2 | Gemini]
Change log (v2 onward):
- `PLAN-REVIEW v1 (Conflict Resolution 1)` > Sections 4, 13, 14: Approved Option 1 to declare `laravel/passport: ^13.10` in [composer.json](file:///c:/dev/getnow-auth2/composer.json) prior to running `composer remove`, reconciling requirement declaration with [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md) section 10 and maintaining [composer.lock](file:///c:/dev/getnow-auth2/composer.lock) stability.
- `PLAN-REVIEW v1 (Open Decision OD1)` > Section 13: Confirmed full removal of starter-kit views and routes (`home`, `dashboard`, `settings`).
- `PLAN-REVIEW v1 (Strict Types Guard)` > Sections 4, 5, 8: Ensured [routes/web.php](file:///c:/dev/getnow-auth2/routes/web.php) retains `declare(strict_types=1);` upon route removal.
- `CR-1 (Route [login] not defined)` > Sections 3, 4, 5, 7, 9, 10, 12, 13: Added configuration of `$middleware->redirectGuestsTo(fn () => null);` in [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php) to prevent unauthenticated non-JSON requests from evaluating non-existent `route('login')`, ensuring HTTP 401 JSON is returned.

---

### 1. Understanding
Remove the Laravel Livewire starter kit ([Laravel\Fortify](file:///c:/dev/getnow-auth2/vendor/laravel/fortify), [Livewire](file:///c:/dev/getnow-auth2/vendor/livewire/livewire), [Flux UI](file:///c:/dev/getnow-auth2/vendor/livewire/flux), passkeys) to establish the standalone API-only Accounts module specified in [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L9-L18). All web routes, Blade views, Fortify actions, Livewire components, and starter tests are removed, leaving [routes/web.php](file:///c:/dev/getnow-auth2/routes/web.php) route-free with strict types declared. One contract regression test is added to verify unauthenticated API requests receive HTTP 401 JSON. Out of scope: migrations, database tables, seeders, factories, frontend build configuration ([package.json](file:///c:/dev/getnow-auth2/package.json), [vite.config.js](file:///c:/dev/getnow-auth2/vite.config.js)), the token stream ([Domain/Accounts/Tokens](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Tokens)), and any UI replacement.

### 2. Sources read

| File or Document Section | What in it constrains this step |
|---|---|
| [AGENTS.md](file:///c:/dev/getnow-auth2/AGENTS.md) | Enforces stream boundaries ([STM1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L13)), zero Docker/container additions ([ENV2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L116)), strict types ([STD1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L35)), no migration edits ([DBS2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L75)), and Conventional Commits ([GIT2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L105)). |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md) § 1, 5, 10, 12 | Declares web UI, password reset, email verification, and 2FA out of scope; defines stream touch points ([composer.json](file:///c:/dev/getnow-auth2/composer.json), [app/Models/User.php](file:///c:/dev/getnow-auth2/app/Models/User.php), [bootstrap/providers.php](file:///c:/dev/getnow-auth2/bootstrap/providers.php), [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php)); sequences `ACC-C1` between `ACC-T0` and `ACC-U1`. |
| [docs/blueprints/RULE_REGISTER.md](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md) | Provides all 75 rule IDs evaluated in the Section 8 compliance matrix and dictates Layer A commands. |
| [docs/blueprints/ACC-C1/brief.md](file:///c:/dev/getnow-auth2/docs/blueprints/ACC-C1/brief.md) | Specifies scope, 8 Acceptance Criteria, removal set, test arithmetic (64 - 33 + 1 = 32), and the AC-5 unauthenticated contract test. |
| [composer.json](file:///c:/dev/getnow-auth2/composer.json) | Identifies root required packages to remove (`laravel/fortify`, `livewire/livewire`, `livewire/flux`, `livewire/blaze`); records approved addition of `laravel/passport: ^13.10`. |
| [bootstrap/providers.php](file:///c:/dev/getnow-auth2/bootstrap/providers.php) | Contains [FortifyServiceProvider](file:///c:/dev/getnow-auth2/app/Providers/FortifyServiceProvider.php) registration to be excised. |
| [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php) | Contains routing configuration, exception rendering, and guest redirect middleware settings. |
| [routes/web.php](file:///c:/dev/getnow-auth2/routes/web.php) | Defines starter web routes (`home`, `dashboard`, `settings.php` include) to be emptied. |
| [routes/settings.php](file:///c:/dev/getnow-auth2/routes/settings.php) | Defines Livewire settings routes and `.well-known/passkey-endpoints` to be deleted. |
| [routes/api.php](file:///c:/dev/getnow-auth2/routes/api.php) | Requires [routes/api/tokens.php](file:///c:/dev/getnow-auth2/routes/api/tokens.php). Must remain untouched. |
| [routes/api/tokens.php](file:///c:/dev/getnow-auth2/routes/api/tokens.php) | Defines `POST /api/v1/auth/refresh` and `POST /api/v1/auth/logout`. Target of the AC-5 regression test. |
| [app/Models/User.php](file:///c:/dev/getnow-auth2/app/Models/User.php) | Implements [PasskeyUser](file:///c:/dev/getnow-auth2/app/Models/User.php#L37) and uses [PasskeyAuthenticatable](file:///c:/dev/getnow-auth2/app/Models/User.php#L40) and [TwoFactorAuthenticatable](file:///c:/dev/getnow-auth2/app/Models/User.php#L40); these must be removed while preserving [HasApiTokens](file:///c:/dev/getnow-auth2/app/Models/User.php#L40) and [OAuthenticatable](file:///c:/dev/getnow-auth2/app/Models/User.php#L37). |
| [app/Providers/FortifyServiceProvider.php](file:///c:/dev/getnow-auth2/app/Providers/FortifyServiceProvider.php) | Wires Fortify actions, rate limits, and views. To be deleted. |
| [app/Providers/AppServiceProvider.php](file:///c:/dev/getnow-auth2/app/Providers/AppServiceProvider.php) | Configures date immutability, destructive command protection, and password defaults. Verified clean of starter imports. |
| [app/Providers/TokenServiceProvider.php](file:///c:/dev/getnow-auth2/app/Providers/TokenServiceProvider.php) | Configures Passport and token rate limiters. Read-only touch point. |
| [tests/Pest.php](file:///c:/dev/getnow-auth2/tests/Pest.php) | Binds [TestCase](file:///c:/dev/getnow-auth2/tests/TestCase.php) to Feature and Unit suites and [RefreshDatabase](file:///c:/dev/getnow-auth2/tests/Pest.php#L20) to Feature suite. Read-only. |
| [tests/Arch/AccountsArchTest.php](file:///c:/dev/getnow-auth2/tests/Arch/AccountsArchTest.php) | Enforces 6 architectural boundary tests. Must remain green without modification. |
| `tests/Feature/Auth/*` (6 files) | Contains 19 starter authentication tests to be deleted. |
| `tests/Feature/DashboardTest.php` | Contains 2 dashboard UI tests to be deleted. |
| `tests/Feature/Settings/*` (2 files) | Contains 11 settings UI tests to be deleted. |
| `tests/Feature/ExampleTest.php` | Asserts `route('home')`. Contains 1 test to be deleted. |

### 3. Verified current state

Existing declarations verified by direct inspection:

- [App\Models\User](file:///c:/dev/getnow-auth2/app/Models/User.php#L37):
  ```php
  class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable, PasskeyUser
  {
      use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;
  ```
- [bootstrap/providers.php](file:///c:/dev/getnow-auth2/bootstrap/providers.php#L9-L13):
  ```php
  return [
      AppServiceProvider::class,
      FortifyServiceProvider::class,
      TokenServiceProvider::class,
  ];
  ```
- [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php#L8-L23):
  ```php
  return Application::configure(basePath: dirname(__DIR__))
      ->withRouting(
          web: __DIR__.'/../routes/web.php',
          api: __DIR__.'/../routes/api.php',
          commands: __DIR__.'/../routes/console.php',
          health: '/up',
          apiPrefix: 'api/v1',
      )
      ->withMiddleware(function (Middleware $middleware): void {
          $middleware->redirectGuestsTo(fn () => null);
      })
      ->withExceptions(function (Exceptions $exceptions): void {
          $exceptions->shouldRenderJsonWhen(
              fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
          );
      })->create();
  ```
- [routes/web.php](file:///c:/dev/getnow-auth2/routes/web.php): Empty routing file containing `<?php` and `declare(strict_types=1);`.
- [routes/api/tokens.php](file:///c:/dev/getnow-auth2/routes/api/tokens.php#L8-L14):
  ```php
  Route::prefix('auth')->group(function (): void {
      Route::post('refresh', [TokenController::class, 'refresh'])
          ->middleware('throttle:token-refresh');
      Route::post('logout', [TokenController::class, 'logout'])
          ->middleware(['auth:api', 'throttle:token-logout']);
  });
  ```
- Direct composer requirements post-resolution:
  `php: ^8.3`, `laravel/chisel: ^0.1.0`, `laravel/framework: ^13.17`, `laravel/passport: ^13.10`, `laravel/tinker: ^3.0`, `spatie/laravel-data: ^4.23`.
- Active route count: 15 routes (10 Passport OAuth routes, 2 token routes, 1 health route, 2 local storage routes).
- Active test suite count: 32 tests (22 Tokens, 3 Unit, 6 Arch, 1 Contract).
- Active migrations: 10 migrations, all marked `Ran`.

### 4. File manifest

| Path | New, Modify, or Delete | Layer | One-Line Responsibility | Rule IDs Satisfied |
|---|---|---|---|---|
| [composer.json](file:///c:/dev/getnow-auth2/composer.json) | Modify | Root Config | Prunes starter packages and records `laravel/passport: ^13.10` requirement | [DNT1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L124), [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126), [STM1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L13) |
| [composer.lock](file:///c:/dev/getnow-auth2/composer.lock) | Modify | Root Lock | Lockfile regenerated by Composer package operations | [DNT1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L124), [STM1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L13) |
| [bootstrap/providers.php](file:///c:/dev/getnow-auth2/bootstrap/providers.php) | Modify | Bootstrap | Unregisters [FortifyServiceProvider](file:///c:/dev/getnow-auth2/app/Providers/FortifyServiceProvider.php) | [STM1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L13), [LAY7](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L28) |
| [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php) | Modify | Bootstrap | Disables guest redirect to non-existent route `'login'` via `$middleware->redirectGuestsTo(fn () => null)` (CR-1) | [STM1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L13), [LAY7](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L28) |
| [app/Models/User.php](file:///c:/dev/getnow-auth2/app/Models/User.php) | Modify | Models | Removes Fortify traits and interfaces; retains [HasApiTokens](file:///c:/dev/getnow-auth2/app/Models/User.php#L40) and [OAuthenticatable](file:///c:/dev/getnow-auth2/app/Models/User.php#L37) | [STM1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L13), [STM3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L15), [STD1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L35) |
| [routes/web.php](file:///c:/dev/getnow-auth2/routes/web.php) | Modify | Routing | Clears all starter web routes leaving an empty PHP routing file with `declare(strict_types=1);` | [STM1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L13), [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126), [STD1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L35) |
| [app/Livewire/Actions/Logout.php](file:///c:/dev/getnow-auth2/app/Livewire/Actions/Logout.php) | Delete | Livewire | Livewire session logout action | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| [app/Livewire/Settings/Appearance.php](file:///c:/dev/getnow-auth2/app/Livewire/Settings/Appearance.php) | Delete | Livewire | Livewire appearance settings component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| [app/Livewire/Settings/DeleteUserForm.php](file:///c:/dev/getnow-auth2/app/Livewire/Settings/DeleteUserForm.php) | Delete | Livewire | Livewire account deletion form component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| [app/Livewire/Settings/Profile.php](file:///c:/dev/getnow-auth2/app/Livewire/Settings/Profile.php) | Delete | Livewire | Livewire profile edit component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| [app/Livewire/Settings/Security.php](file:///c:/dev/getnow-auth2/app/Livewire/Settings/Security.php) | Delete | Livewire | Livewire password and 2FA security component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| [app/Livewire/Settings/TwoFactor/RecoveryCodes.php](file:///c:/dev/getnow-auth2/app/Livewire/Settings/TwoFactor/RecoveryCodes.php) | Delete | Livewire | Livewire 2FA recovery codes component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| [app/Actions/Fortify/CreateNewUser.php](file:///c:/dev/getnow-auth2/app/Actions/Fortify/CreateNewUser.php) | Delete | Fortify | Fortify web registration action | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| [app/Actions/Fortify/ResetUserPassword.php](file:///c:/dev/getnow-auth2/app/Actions/Fortify/ResetUserPassword.php) | Delete | Fortify | Fortify web password reset action | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| [app/Concerns/PasswordValidationRules.php](file:///c:/dev/getnow-auth2/app/Concerns/PasswordValidationRules.php) | Delete | Concerns | Validation helper trait with zero external callers remaining | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| [app/Concerns/ProfileValidationRules.php](file:///c:/dev/getnow-auth2/app/Concerns/ProfileValidationRules.php) | Delete | Concerns | Validation helper trait with zero external callers remaining | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| [app/Providers/FortifyServiceProvider.php](file:///c:/dev/getnow-auth2/app/Providers/FortifyServiceProvider.php) | Delete | Providers | Fortify service configuration provider | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| [config/fortify.php](file:///c:/dev/getnow-auth2/config/fortify.php) | Delete | Config | Fortify feature and guard configuration | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| [routes/settings.php](file:///c:/dev/getnow-auth2/routes/settings.php) | Delete | Routing | Livewire settings and passkey routes | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/dashboard.blade.php` | Delete | Views | Starter dashboard view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/welcome.blade.php` | Delete | Views | Starter welcome marketing view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/components/app-logo-icon.blade.php` | Delete | Views | Blade icon component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/components/app-logo.blade.php` | Delete | Views | Blade logo component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/components/auth-header.blade.php` | Delete | Views | Blade auth header component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/components/auth-session-status.blade.php` | Delete | Views | Blade session status component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/components/desktop-user-menu.blade.php` | Delete | Views | Blade desktop menu component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/components/passkey-registration.blade.php` | Delete | Views | Blade passkey registration UI | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/components/passkey-verify.blade.php` | Delete | Views | Blade passkey verification UI | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/components/placeholder-pattern.blade.php` | Delete | Views | Blade SVG pattern component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/components/settings/layout.blade.php` | Delete | Views | Blade settings layout wrapper | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/flux/icon/book-open-text.blade.php` | Delete | Views | Flux UI icon component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/flux/icon/chevrons-up-down.blade.php` | Delete | Views | Flux UI icon component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/flux/icon/folder-git-2.blade.php` | Delete | Views | Flux UI icon component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/flux/icon/layout-grid.blade.php` | Delete | Views | Flux UI icon component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/flux/navlist/group.blade.php` | Delete | Views | Flux UI navlist group component | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/layouts/app.blade.php` | Delete | Views | Application layout template | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/layouts/auth.blade.php` | Delete | Views | Auth layout base template | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/layouts/app/header.blade.php` | Delete | Views | App header partial | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/layouts/app/sidebar.blade.php` | Delete | Views | App sidebar partial | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/layouts/auth/card.blade.php` | Delete | Views | Auth card layout partial | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/layouts/auth/simple.blade.php` | Delete | Views | Auth simple layout partial | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/layouts/auth/split.blade.php` | Delete | Views | Auth split layout partial | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/livewire/auth/confirm-password.blade.php` | Delete | Views | Livewire password confirmation view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/livewire/auth/forgot-password.blade.php` | Delete | Views | Livewire password reset link view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/livewire/auth/login.blade.php` | Delete | Views | Livewire login form view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/livewire/auth/register.blade.php` | Delete | Views | Livewire registration form view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/livewire/auth/reset-password.blade.php` | Delete | Views | Livewire password update form view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/livewire/auth/two-factor-challenge.blade.php` | Delete | Views | Livewire 2FA challenge form view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/livewire/auth/verify-email.blade.php` | Delete | Views | Livewire email verification view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/livewire/settings/appearance.blade.php` | Delete | Views | Livewire appearance settings view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/livewire/settings/delete-user-form.blade.php` | Delete | Views | Livewire account deletion view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/livewire/settings/profile.blade.php` | Delete | Views | Livewire profile settings view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/livewire/settings/security.blade.php` | Delete | Views | Livewire security settings view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/livewire/settings/two-factor/recovery-codes.blade.php` | Delete | Views | Livewire recovery codes view | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/partials/head.blade.php` | Delete | Views | Blade head partial | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `resources/views/partials/settings-heading.blade.php` | Delete | Views | Blade settings header partial | [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) |
| `tests/Feature/Auth/AuthenticationTest.php` | Delete | Tests | Tests web session login/logout (5 tests) | [TST5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L98) |
| `tests/Feature/Auth/EmailVerificationTest.php` | Delete | Tests | Tests email verification routes (5 tests) | [TST5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L98) |
| `tests/Feature/Auth/PasswordConfirmationTest.php` | Delete | Tests | Tests password confirmation screen (1 test) | [TST5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L98) |
| `tests/Feature/Auth/PasswordResetTest.php` | Delete | Tests | Tests password reset workflows (4 tests) | [TST5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L98) |
| `tests/Feature/Auth/RegistrationTest.php` | Delete | Tests | Tests Fortify registration view/action (2 tests) | [TST5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L98) |
| `tests/Feature/Auth/TwoFactorChallengeTest.php` | Delete | Tests | Tests 2FA challenge screen (2 tests) | [TST5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L98) |
| `tests/Feature/DashboardTest.php` | Delete | Tests | Tests dashboard auth redirect (2 tests) | [TST5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L98) |
| `tests/Feature/Settings/ProfileUpdateTest.php` | Delete | Tests | Tests Livewire profile update (5 tests) | [TST5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L98) |
| `tests/Feature/Settings/SecurityTest.php` | Delete | Tests | Tests Livewire security/password update (6 tests) | [TST5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L98) |
| `tests/Feature/ExampleTest.php` | Delete | Tests | Asserts route('home') status 200 (1 test) | [TST5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L98) |
| [tests/Feature/Contract/ApiUnauthenticatedResponseTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Contract/ApiUnauthenticatedResponseTest.php) | New | Tests | Asserts unauthenticated API request without Accept header returns 401 JSON | [TST1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L94), [STD1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L35) |
| [docs/adr/0002-remove-starter-kit.md](file:///c:/dev/getnow-auth2/docs/adr/0002-remove-starter-kit.md) | New | Documentation | Records decision, rationale, and consequences of removing starter kit | [DNT7](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L130) |
| [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md) | Modify | Documentation | Records `ACC-C1` in section 10 and adds `Feature/Contract/ [C]` to section 5 | [DNT7](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L130) |
| [AGENTS.md](file:///c:/dev/getnow-auth2/AGENTS.md) | Modify | Documentation | Adds `ACC-C1` to Git merge order | [DNT7](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L130) |

Total file count: 70 files (8 modified, 60 deleted, 2 added). Placement strictly conforms to [AGENTS.md](file:///c:/dev/getnow-auth2/AGENTS.md) 'Where code goes'.

### 5. Interface specs

No new classes, domain actions, controllers, DTOs, or enums are created in this step.

Changed application configuration:
- [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php#L16-L18):
  ```php
  ->withMiddleware(function (Middleware $middleware): void {
      $middleware->redirectGuestsTo(fn () => null);
  })
  ```

Changed model:
- [App\Models\User](file:///c:/dev/getnow-auth2/app/Models/User.php#L37):
  - Namespace: `App\Models`
  - Inheritance: `class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable`
  - Traits: `use HasApiTokens, HasFactory, Notifiable;`
  - Removed interfaces: `Laravel\Fortify\Contracts\PasskeyUser`
  - Removed traits: `Laravel\Fortify\PasskeyAuthenticatable`, `Laravel\Fortify\TwoFactorAuthenticatable`
  - Public methods: `casts(): array`, `initials(): string` (both retain existing signatures and types)
  - Thrown exceptions: none

New test specification:
- File: [tests/Feature/Contract/ApiUnauthenticatedResponseTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Contract/ApiUnauthenticatedResponseTest.php)
  - Directives: `declare(strict_types=1);`
  - Suite: Pest Feature test
  - Callers: Pest test runner via `composer test` and `composer check`

### 6. Data and migrations

- Tables, columns, types, defaults, foreign keys, indexes, check constraints: None.
- Rollback: None.
- Backward-compatibility statement: Migrations are completely untouched. Starter tables (`sessions`, `password_reset_tokens`, `passkeys`) and two-factor columns on the `users` table are preserved intact per [DBS3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L76) and Decision 3. [php artisan migrate:status](file:///c:/dev/getnow-auth2/database/migrations) is verified identical before and after.

### 7. Behavior and flows

#### Use Case 1: Unauthenticated request to protected API endpoint without Accept header (AC-5, CR-1)
- **Actor:** External HTTP client (browser, script, or webhook caller omitting `Accept: application/json`).
- **Endpoint:** `POST /api/v1/auth/logout`.
- **Request:** Headers contain no `Authorization` token and no `Accept` header.
- **Execution Path:**
  1. Request matches route `POST api/v1/auth/logout` in [routes/api/tokens.php](file:///c:/dev/getnow-auth2/routes/api/tokens.php).
  2. Middleware `auth:api` ([Authenticate](file:///c:/dev/getnow-auth2/vendor/laravel/framework/src/Illuminate/Auth/Middleware/Authenticate.php)) executes and identifies missing token credentials.
  3. `Authenticate::unauthenticated()` evaluates `$this->redirectTo($request)`.
  4. With `$middleware->redirectGuestsTo(fn () => null)` configured in [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php), the callback returns `null` instead of calling `route('login')`.
  5. [AuthenticationException](file:///c:/dev/getnow-auth2/vendor/laravel/framework/src/Illuminate/Auth/AuthenticationException.php) is instantiated with `$redirectTo = null` and thrown.
  6. Exception handler checks `shouldRenderJsonWhen` in [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php#L20-L22): `$request->is('api/*')` evaluates to `true`.
  7. Handler converts exception into JSON response.
- **Response:** HTTP 401 Unauthorized with header `Content-Type: application/json` and payload:
  ```json
  {"message":"Unauthenticated."}
  ```
- **Failure Path:** If `redirectGuestsTo(fn () => null)` were missing, Laravel's default fallback would call `route('login')`, which throws `RouteNotFoundException` and produces an HTTP 500 response.
- **Activity Log Entry:** None.
- **Concurrency Note:** Read-only authentication guard check; stateless with zero database mutations.

#### Use Case 2: Package removal and lockfile synchronization (AC-1, AC-2)
- **Actor:** Developer executing step implementation.
- **Operation:** Prune Fortify, Livewire, Flux, and Blaze packages after explicitly declaring `laravel/passport: ^13.10`.
- **Execution Path:** Composer parses dependencies, purges packages and autoloader references, and writes updated [composer.lock](file:///c:/dev/getnow-auth2/composer.lock).
- **Outcome:** `composer show --direct` reflects only removal of starter kit entries; retained packages (`laravel/framework`, `laravel/passport`, `spatie/laravel-data`) retain exact locked versions.

### 8. Rule compliance matrix

| ID | Applies (Y/N) | Compliance Rationale |
|---|---|---|
| [STM1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L13) | Y | Edits only [User.php](file:///c:/dev/getnow-auth2/app/Models/User.php), [bootstrap/providers.php](file:///c:/dev/getnow-auth2/bootstrap/providers.php), [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php), [composer.json](file:///c:/dev/getnow-auth2/composer.json), [routes/web.php](file:///c:/dev/getnow-auth2/routes/web.php), and documentation. All touch points were explicitly authorized. |
| [STM2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L14) | Y | [TokenIssuer](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Contracts/TokenIssuer.php) and [TokenPairData](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Data/TokenPairData.php) are untouched. |
| [STM3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L15) | Y | Users stream code does not import [Laravel\Passport](file:///c:/dev/getnow-auth2/vendor/laravel/passport). [User](file:///c:/dev/getnow-auth2/app/Models/User.php) preserves [HasApiTokens](file:///c:/dev/getnow-auth2/app/Models/User.php#L40) and [OAuthenticatable](file:///c:/dev/getnow-auth2/app/Models/User.php#L37). |
| [STM4](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L16) | Y | Imported token code is untouched and frozen. |
| [LAY1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L22) | N | This contract step removes UI scaffolding; no new application request pipeline is implemented. |
| [LAY2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L23) | N | No controllers are created or modified in this step. |
| [LAY3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L24) | N | No domain services are created or modified in this step. |
| [LAY4](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L25) | N | No repositories are created or modified in this step. |
| [LAY5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L26) | N | No CRUD methods are exposed or altered in this step. |
| [LAY6](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L27) | N | `App\Domain` does not import `App\Http`; verified by existing arch test. |
| [LAY7](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L28) | Y | New test placed in [tests/Feature/Contract](file:///c:/dev/getnow-auth2/tests/Feature/Contract) and new ADR in [docs/adr](file:///c:/dev/getnow-auth2/docs/adr) matching [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L77-L126). |
| [LAY8](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L29) | N | No controllers exist that return Eloquent models. |
| [STD1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L35) | Y | `declare(strict_types=1);` is declared in [ApiUnauthenticatedResponseTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Contract/ApiUnauthenticatedResponseTest.php), [routes/web.php](file:///c:/dev/getnow-auth2/routes/web.php), and preserved in all modified PHP files. |
| [STD2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L36) | Y | All parameters, return values, and properties in touched files are fully typed. |
| [STD3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L37) | N | No enums or Spatie data objects are introduced or modified in this step. |
| [STD4](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L38) | N | `UserData` DTO is built in `ACC-U2` and not touched in this step. |
| [STD5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L39) | N | User update logic is not implemented in this step. |
| [STD6](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L40) | Y | Pint and Larastan will pass with zero errors, zero baseline entries, and no modifications to [phpstan.neon](file:///c:/dev/getnow-auth2/phpstan.neon). |
| [STD7](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L41) | Y | No commented-out code; comments explain architectural rationale only. |
| [ACT1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L47) | N | Public registration business logic belongs to `ACC-U4`. |
| [ACT2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L48) | N | Retailer creation belongs to `ACC-U3`. |
| [ACT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L49) | N | User CRUD authorization belongs to `ACC-U3`. |
| [ACT4](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L50) | N | Superadmin seeding and role promotion restrictions belong to `ACC-U1`. |
| [ACT5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L51) | N | Login endpoint belongs to `ACC-U4`. |
| [ACT6](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L52) | N | Password hashing cast is preserved on [User.php](file:///c:/dev/getnow-auth2/app/Models/User.php#L51); `Hash::make` is not called anywhere in application code. |
| [ACT7](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L53) | N | Login credential verification error parity belongs to `ACC-U4`. |
| [ACT8](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L54) | N | Token revocation on delete/password change belongs to `ACC-U5`. |
| [ACT9](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L55) | N | AuthService token issuance belongs to `ACC-U4`. |
| [ACT10](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L56) | N | Auth response shaping belongs to `ACC-U4`. |
| [ACT11](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L57) | Y | No passwords, tokens, or credentials are logged. |
| [TOK1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L63) | Y | [PassportTokenIssuer](file:///c:/dev/getnow-auth2/app/Domain/Accounts/Tokens/PassportTokenIssuer.php) and [TokenServiceProvider](file:///c:/dev/getnow-auth2/app/Providers/TokenServiceProvider.php) remain intact and untouched. |
| [TOK2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L64) | Y | ADR 0001 grant, delivery, and lifetime decisions remain unmodified. |
| [TOK3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L65) | Y | Laravel Passport remains installed and all 22 token tests continue passing. |
| [TOK4](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L66) | Y | Token lifetimes remain configured in [config/passport.php](file:///c:/dev/getnow-auth2/config/passport.php). |
| [TOK5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L67) | Y | Refresh token rotation logic remains untouched and fully tested. |
| [TOK6](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L68) | Y | Refresh and logout rate limiting remain configured and verified by tests. |
| [DBS1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L74) | Y | Tests run exclusively against PostgreSQL (`getnow_auth_test`); SQLite is not used. |
| [DBS2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L75) | Y | Zero migration files are modified. |
| [DBS3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L76) | Y | No tables or columns dropped. Starter tables and 2FA columns remain intact. |
| [DBS4](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L77) | N | No database schema changes are introduced in this step. |
| [DBS5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L78) | N | Route model binding and ULID exposure belong to `ACC-U1` and `ACC-U3`. |
| [DBS6](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L79) | Y | Destructive database commands (`migrate:fresh`) will not be executed. |
| [SCR1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L85) | Y | No secrets, `.env` files, or Passport keys are committed. |
| [SCR2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L86) | Y | Authorization, policies, strict types, and static analysis configurations are not disabled or weakened. |
| [SCR3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L87) | Y | API authentication checks remain strictly server-side. |
| [SCR4](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L88) | Y | Rate limiting for `token-refresh` and `token-logout` remains enforced in [TokenServiceProvider.php](file:///c:/dev/getnow-auth2/app/Providers/TokenServiceProvider.php). |
| [TST1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L94) | Y | Pest feature test added to [tests/Feature/Contract/ApiUnauthenticatedResponseTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Contract/ApiUnauthenticatedResponseTest.php). |
| [TST2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L95) | N | User CRUD authorization failure cases belong to `ACC-U3` and `ACC-U5`. |
| [TST3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L96) | Y | Token suite tests continue running against real Passport on PostgreSQL. |
| [TST4](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L97) | Y | Tests do not use hand-built database rows. |
| [TST5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L98) | Y | Tests deleted are strictly limited to the 10 starter-kit test files itemized in AC-4 corresponding to removed features. No tests are skipped or marked incomplete. |
| [GIT1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L104) | Y | Branch will be `chore/contract-remove-starter-kit` cut from `feat/contract-arch-tests`. |
| [GIT2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L105) | Y | Conventional Commit with stream scope: `chore(contract): remove starter kit (ACC-C1)`. |
| [GIT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L106) | Y | Direct pushes to `main` and bypassing git hooks are prohibited. |
| [GIT4](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L107) | Y | PR will be labelled `contract` and merged before `ACC-U1`. |
| [GIT5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L108) | Y | PR description will articulate changes, rationale, test evidence, and risk analysis. |
| [GIT6](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L109) | Y | Delivery sequences follow [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L240-L249) section 10. |
| [ENV1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L115) | Y | Commands executed strictly one at a time via Windows PowerShell without bash syntax or `&&`. |
| [ENV2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L116) | Y | Native PHP, Composer, and PostgreSQL used with zero Docker or container tooling. |
| [ENV3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L117) | Y | Non-interactive execution; no background development servers or file watchers left active. |
| [ENV4](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L118) | Y | Full unedited output of `composer check` read and verified. |
| [DNT1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L124) | Y | No new package dependencies added. |
| [DNT2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L125) | Y | No deployment or infrastructure pipeline changes. |
| [DNT3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L126) | Y | Eliminates out-of-scope starter features (Livewire UI, Fortify passkeys, 2FA, email verification) per [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md#L17) section 1. |
| [DNT4](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L127) | N | No controllers or models are modified to contain business logic. |
| [DNT5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L128) | Y | No destructive database operations executed. |
| [DNT6](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L129) | Y | No secrets, keys, or external databases copied. |
| [DNT7](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L130) | Y | [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md), [AGENTS.md](file:///c:/dev/getnow-auth2/AGENTS.md), and [docs/adr/0002-remove-starter-kit.md](file:///c:/dev/getnow-auth2/docs/adr/0002-remove-starter-kit.md) updated. |
| [WFL1](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L136) | Y | Plan submitted and frozen prior to code implementation. |
| [WFL2](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L137) | Y | Deviations require stopping work and raising a Change Request. |
| [WFL3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L138) | Y | Step concludes with an Implementation Report containing raw command output. |
| [WFL4](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L139) | Y | Step completion declared only by reviewer acceptance. |
| [WFL5](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L140) | Y | Plan produced for structural changes touching multiple files. |
| [WFL6](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L141) | Y | Single concern: removal of starter kit. |
| [WFL7](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L142) | Y | Ambiguities and conflicts recorded in Section 13 for reviewer resolution without guessing. |

### 9. AC traceability

| AC | Files | Test Names | How Verified |
|---|---|---|---|
| AC-1: Fortify, Livewire, Flux, passkeys removed | 60 deleted files, [bootstrap/providers.php](file:///c:/dev/getnow-auth2/bootstrap/providers.php), [routes/web.php](file:///c:/dev/getnow-auth2/routes/web.php), [app/Models/User.php](file:///c:/dev/getnow-auth2/app/Models/User.php) | `tests/Arch/AccountsArchTest.php` ('passport is used only by token stream and user model') | `git diff --name-status` reconciles to manifest; Select-String for `Laravel\Fortify`, `Livewire`, `Flux` across app, bootstrap, config, routes, database, tests returns zero hits. |
| AC-2: Composer dependencies pruned | [composer.json](file:///c:/dev/getnow-auth2/composer.json), [composer.lock](file:///c:/dev/getnow-auth2/composer.lock) | `composer validate`, `composer check` | `composer show --direct` diff shows only removed packages; `composer.json` scripts block is byte-identical; `git diff composer.json`. |
| AC-3: Routes stripped to API and Passport | [routes/web.php](file:///c:/dev/getnow-auth2/routes/web.php), [routes/settings.php](file:///c:/dev/getnow-auth2/routes/settings.php) | `tests/Feature/Tokens/TokenEndpointsTest.php` | Full `php artisan route:list` before and after comparison: zero starter routes remain; 10 Passport OAuth routes, 2 token routes, 1 health route, and 2 storage routes remain identical. |
| AC-4: Starter-kit tests deleted and reconciled | 10 deleted test files, [tests/Feature/Contract/ApiUnauthenticatedResponseTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Contract/ApiUnauthenticatedResponseTest.php) | All 32 remaining tests | Test count arithmetic: 64 baseline - 33 deleted + 1 new = 32 tests (22 Tokens, 3 Unit, 6 Arch, 1 Contract). |
| AC-5: Unauthenticated logout returns 401 JSON | [tests/Feature/Contract/ApiUnauthenticatedResponseTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Contract/ApiUnauthenticatedResponseTest.php), [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php) | `test('unauthenticated api post request without accept header returns 401 json response')` | `php artisan test tests/Feature/Contract/ApiUnauthenticatedResponseTest.php`; mutation proof by temporarily commenting out `redirectGuestsTo` and `shouldRenderJsonWhen` in [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php). |
| AC-6: User.php Fortify traits removed; migrations untouched | [app/Models/User.php](file:///c:/dev/getnow-auth2/app/Models/User.php), [bootstrap/providers.php](file:///c:/dev/getnow-auth2/bootstrap/providers.php) | `tests/Feature/Tokens/PassportTokenIssuerTest.php`, `tests/Arch/AccountsArchTest.php` | `git diff app/Models/User.php` shows removal of Fortify imports and traits only; `php artisan migrate:status` identical before and after. |
| AC-7: ADR and documentation updates | [docs/adr/0002-remove-starter-kit.md](file:///c:/dev/getnow-auth2/docs/adr/0002-remove-starter-kit.md), [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md), [AGENTS.md](file:///c:/dev/getnow-auth2/AGENTS.md) | Manual review of git diff | Diffs verified: ADR 0002 added, `ACC-C1` listed in section 10 and merge order, `Feature/Contract/ [C]` in tests tree. |
| AC-8: Quality gates clean | [phpstan.neon](file:///c:/dev/getnow-auth2/phpstan.neon), [tests/Arch/AccountsArchTest.php](file:///c:/dev/getnow-auth2/tests/Arch/AccountsArchTest.php) | Full suite via `composer check` | Pint clean, PHPStan level 7 clean with zero baseline/ignore entries, 32 tests passing. |

### 10. Test plan

#### New Test File: [tests/Feature/Contract/ApiUnauthenticatedResponseTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Contract/ApiUnauthenticatedResponseTest.php)
- **Suite:** Feature / Contract (`[C]`)
- **Directives:** `declare(strict_types=1);`
- **Pest Case 1:** `test('unauthenticated api post request without accept header returns 401 json response', function (): void)`
  - **Arrange:** Set up client request with zero Authorization headers and zero Accept headers (plain `$this->post()`, not `$this->postJson()`).
  - **Act:** Send POST request to `/api/v1/auth/logout`.
  - **Assert:**
    - Verify response status is 401 Unauthorized (`$response->assertStatus(401)`).
    - Verify response header `content-type` is `application/json` (`$response->assertHeader('content-type', 'application/json')`).
    - Verify JSON payload contains `['message' => 'Unauthenticated.']` (`$response->assertJson(['message' => 'Unauthenticated.'])`).
- **Mutation Proof Execution (AC-5):**
  1. Temporarily comment out lines 17 in [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php) (`$middleware->redirectGuestsTo(fn () => null);`).
  2. Run `php artisan test tests/Feature/Contract/ApiUnauthenticatedResponseTest.php`.
  3. Capture failure output (RouteNotFoundException / 500 error resulting from missing `route('login')`).
  4. Restore [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php) via `git restore bootstrap/app.php`.
  5. Run `git status --short` to confirm clean working tree.
  6. Run `php artisan test tests/Feature/Contract/ApiUnauthenticatedResponseTest.php` to confirm clean passing result.

#### Deleted Test Itemization (AC-4):
1. [tests/Feature/Auth/AuthenticationTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Auth/AuthenticationTest.php): 5 tests (login screen, authentication, invalid password, logout redirect). Feature removed.
2. [tests/Feature/Auth/EmailVerificationTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Auth/EmailVerificationTest.php): 5 tests (email verification screen, verification process, already verified). Feature removed.
3. [tests/Feature/Auth/PasswordConfirmationTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Auth/PasswordConfirmationTest.php): 1 test (password confirmation screen). Feature removed.
4. [tests/Feature/Auth/PasswordResetTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Auth/PasswordResetTest.php): 4 tests (reset link screen, reset link delivery, reset password screen, password reset). Feature removed.
5. [tests/Feature/Auth/RegistrationTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Auth/RegistrationTest.php): 2 tests (registration screen, user registration). Web registration feature removed.
6. [tests/Feature/Auth/TwoFactorChallengeTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Auth/TwoFactorChallengeTest.php): 2 tests (2FA challenge screen, recovery code challenge). Feature removed.
7. [tests/Feature/DashboardTest.php](file:///c:/dev/getnow-auth2/tests/Feature/DashboardTest.php): 2 tests (guests redirected, dashboard rendered). Feature removed.
8. [tests/Feature/Settings/ProfileUpdateTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Settings/ProfileUpdateTest.php): 5 tests (profile page rendered, profile information update, email verification status, user deletion, correct password for deletion). Feature removed.
9. [tests/Feature/Settings/SecurityTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Settings/SecurityTest.php): 6 tests (security page rendered, password update, current password validation, passkey options, 2FA management). Feature removed.
10. [tests/Feature/ExampleTest.php](file:///c:/dev/getnow-auth2/tests/Feature/ExampleTest.php): 1 test (asserts `route('home')` returns 200). Route removed.

### 11. Docs and contract updates

1. **[ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md)**:
   - Section 5 ("Structure and ownership"): under `tests/`, add:
     ```text
     ├── Feature/Contract/                            [C]
     ```
   - Section 10 ("Delivery order and the token import"): add `ACC-C1` checklist line between `ACC-T0` and `ACC-U1`:
     ```markdown
     - [ ] `ACC-C1` Remove starter kit: strip Fortify, Livewire, Flux UI, passkeys, web routes and views; add unauthenticated JSON contract test.
     ```
2. **[AGENTS.md](file:///c:/dev/getnow-auth2/AGENTS.md)**:
   - Section "Git": update merge order line to:
     ```markdown
     - Merge order follows `ARCHITECTURE.md` section 10: `ACC-C0`, then `ACC-T0`, then `ACC-C1`, then `ACC-U1` onward.
     ```
3. **[docs/adr/0002-remove-starter-kit.md](file:///c:/dev/getnow-auth2/docs/adr/0002-remove-starter-kit.md)**:
   - Create ADR 0002 documenting:
     - **Context:** Starter kit contained Fortify, Livewire, Flux UI, and passkey scaffolding contrary to the API-only scope of [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md) section 1.
     - **Decision:** Remove server-side starter kit code, routes, views, config, and packages.
     - **Consequences:** Eliminates unmanaged authentication routes and `route('login')`. Preserves database migrations and 2FA user columns for zero downtime ([DBS3](file:///c:/dev/getnow-auth2/docs/blueprints/RULE_REGISTER.md#L76)). Acknowledges frontend toolchain assets may fail `npm run build` until main repo merge.
4. **`.env.example`, `content-api.md`, fixtures:** None. Reason: no new environment variables, CMS content APIs, or fixtures are introduced.

### 12. Implementation order

Single Conventional Commit to maintain atomic quality gates:

1. **Commit:** `chore(contract): remove starter kit (ACC-C1)`
   - **Files:**
     - Modified: [composer.json](file:///c:/dev/getnow-auth2/composer.json), [composer.lock](file:///c:/dev/getnow-auth2/composer.lock), [bootstrap/providers.php](file:///c:/dev/getnow-auth2/bootstrap/providers.php), [bootstrap/app.php](file:///c:/dev/getnow-auth2/bootstrap/app.php), [app/Models/User.php](file:///c:/dev/getnow-auth2/app/Models/User.php), [routes/web.php](file:///c:/dev/getnow-auth2/routes/web.php), [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md), [AGENTS.md](file:///c:/dev/getnow-auth2/AGENTS.md).
     - Deleted: 6 Livewire components, 2 Fortify actions, 2 concerns helper traits, 1 Fortify service provider, 1 config file, 1 route file, 37 Blade views, 10 test files.
     - Added: [docs/adr/0002-remove-starter-kit.md](file:///c:/dev/getnow-auth2/docs/adr/0002-remove-starter-kit.md), [tests/Feature/Contract/ApiUnauthenticatedResponseTest.php](file:///c:/dev/getnow-auth2/tests/Feature/Contract/ApiUnauthenticatedResponseTest.php).
   - **State After Commit:** `composer check` green (32 tests passing, Pint clean, PHPStan level 7 clean).

---

### 13. Risks, assumptions, conflicts

#### Resolved Conflicts and Open Decisions:
- **Conflict 1 Resolved (Passport in composer.json):** Per Claude review verdict `APPROVED: PLAN-REVIEW v1`, Option 1 was executed: `"laravel/passport": "^13.10"` was declared in [composer.json](file:///c:/dev/getnow-auth2/composer.json) prior to package pruning, maintaining `laravel/passport: 13.10.0` in [composer.lock](file:///c:/dev/getnow-auth2/composer.lock).
- **Open Decision OD1 Resolved:** Confirmed by reviewer that no demo, screenshot, or review depends on starter kit views (`home`, `dashboard`, `settings`). All starter views and web routes are deleted.
- **CR-1 Resolved:** `bootstrap/app.php` configures `$middleware->redirectGuestsTo(fn () => null);` inside `withMiddleware()`. This explicitly prevents Laravel's authentication middleware from calling `route('login')`, which was removed with the starter kit.
- **Frontend Assets:** `resources/css/app.css` imports Livewire and Flux UI vendor styles. Removing the vendor packages means `npm run build` may fail until frontend assets are realigned. This is accepted and recorded in ADR 0002 per Brief Decision 2.

---

### 14. Dependencies

New packages: None. `"laravel/passport": "^13.10"` is declared in [composer.json](file:///c:/dev/getnow-auth2/composer.json) as approved in [ARCHITECTURE.md](file:///c:/dev/getnow-auth2/ARCHITECTURE.md) section 10 and PLAN-REVIEW v1.
