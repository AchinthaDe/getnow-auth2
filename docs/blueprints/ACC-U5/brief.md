# [ACC-U5 | BRIEF | Claude]

## Goal

Close the token-revocation window left open by `ACC-U3` and `ACC-U4`. Today a deleted user, a user whose password was changed, and a user whose role was changed all keep every access and refresh token they already hold. This step makes `UserService` revoke all of that user's tokens, through the `TokenIssuer::revokeAll` contract method, when a user is soft-deleted, when a password is supplied on update, and when a role actually changes. The revocation is atomic with the change (OD-4), so a failure leaves the user and their tokens untouched. HTTP statuses and response shapes do not change. This is a domain-layer step with one production file, one test file and docs.

---

## Depends on

- **ACC-U1 to ACC-U4** (accepted per Achintha; `ACC-U4` was accepted in VERIFY r2 with one pre-PR check, see OD-1). Claude has not seen the repository directly, so Gemini must read the actual files in plan Section 3 and not rely on this brief for current state.
- From **ACC-U2 / ACC-U3**: `App\Domain\Accounts\Services\UserService` (constructor, `update`, `delete`; the create method is `store` in the code even though earlier briefs called it `create`, so read it), `UserData` (`<type>|Optional` fields), `ProtectedUserException`, `UserController` and `UserPolicy` (read only, they must not change), `UserFactory`. 
- From **ACC-U4**: `AuthService` as the precedent for injecting the `TokenIssuer` contract and wrapping work in `DB::transaction`, `AuthEndpointsTest` as the precedent for binding a throwing `TokenIssuer` fake, and the `auth-login` limiter (5/min per email + IP) that can throttle tests which log in repeatedly.
- From **ACC-T0**: `App\Domain\Accounts\Contracts\TokenIssuer` (`issue`, `refresh`, `revoke`, `revokeAll`), `PassportTokenIssuer` and `TokenController` (read only, to confirm what `revokeAll` really does), `routes/api/tokens.php` (`/api/v1/auth/refresh`, `/api/v1/auth/logout`), ADR 0001 (empty scopes, refresh token in the JSON body), `Tests\Support\InteractsWithPassportKeys`.
- From **ACC-C0**: `tests/Arch/AccountsArchTest.php`, which must stay green and untouched.

---

## In scope

1. `app/Domain/Accounts/Services/UserService.php`: inject the `TokenIssuer` contract; call `revokeAll($user)` in `delete` and in `update` when the rules in OD-2 say so, inside the same database transaction as the write, after the `ProtectedUserException` guards.
2. `tests/Feature/Accounts/UserTokenRevocationTest.php`: feature tests against PostgreSQL using real Bearer tokens, covering every AC below.
3. `docs/adr/<next number>-token-revocation-on-account-changes.md` (OD-5; verify the next free ADR number in `docs/adr/`): records the revocation triggers, fail-closed behaviour, the self password change logging the user out, and the fact that tokens carry empty scopes so role is read from the database on every request.
4. `ARCHITECTURE.md`: document the revocation behaviour next to the user and token endpoints (verify the exact sections) and link the ADR.

---

## Out of scope

- Single-session or revoke-on-login behaviour (OD-6), a logout-all endpoint, per-device session management, revoke on email or name change.
- Restore, force delete, bulk operations (still permanently out of scope for Users).
- Any change to `TokenIssuer`, `TokenPairData`, `PassportTokenIssuer`, `TokenController`, `TokenServiceProvider`, `config/passport.php`, `routes/api.php`, `routes/api/tokens.php`. If `revokeAll` turns out to be deficient (see trap 6), submit a Change Request; do not patch the Tokens stream.
- Any change to `UserController`, `UserPolicy`, `StoreUserRequest`, `UpdateUserRequest`, `UserResponseData`, `AuthService`, the auth requests and controller, or `routes/api/users.php` and `routes/api/auth.php`.
- Any migration, factory or seeder change (`DBS2`). The `SuperadminSeeder` email-normalisation fix stays a separate Change Request. Trusted-proxy configuration is a deployment task.
- Pruning or cleaning up revoked token rows, access-token deny-lists, caches, notifications or emails.
- Email verification, password reset, 2FA, Filament UI (permanently out of scope, `ARCHITECTURE.md` section 1).

---

## Read first

- `ARCHITECTURE.md`: sections on layering, the user endpoints, the token endpoints, security and passwords, and open questions (verify numbering).
- `AGENTS.md` and `docs/blueprints/RULE_REGISTER.md`: layering, Account rules, Security, Stream rules, Coding standards.
- `docs/adr/` (all files, to pick the next number) and ADR 0001 in particular.
- `app/Domain/Accounts/Services/UserService.php`, `UserData.php`, `ProtectedUserException.php`, `UserRepositoryInterface.php`.
- `app/Domain/Accounts/Services/AuthService.php`: the precedent for contract injection and transactions.
- `app/Domain/Accounts/Contracts/TokenIssuer.php`, `app/Domain/Accounts/Tokens/PassportTokenIssuer.php` (`revokeAll` and `refresh`), `app/Http/Controllers/Api/V1/Auth/TokenController.php`.
- `app/Http/Controllers/Api/V1/Users/UserController.php` and `app/Domain/Accounts/Policies/UserPolicy.php` (what the self-update and role-change rules are).
- Every caller of `UserService::update` and `UserService::delete` (`SuperadminSeeder`, controllers, tests): any caller gets the new behaviour.
- `tests/Feature/Accounts/UserServiceTest.php`, `UserControllerTest.php`, `AuthEndpointsTest.php`, `tests/Feature/Tokens/TokenEndpointsTest.php`, `tests/Support/InteractsWithPassportKeys.php`, `tests/Arch/AccountsArchTest.php`.

---

## Decisions made

Gemini must not re-decide these.

| Decision | Source |
|---|---|
| Revocation uses only `TokenIssuer::revokeAll(User $user)`. No direct access to Passport models, the `oauth_*` tables or `$user->tokens()` anywhere in `app/` | `STM1`, `LAY2` |
| Revocation lives in `UserService`, not in the controller, a listener or a request. `UserService` may import the `TokenIssuer` contract; the `ACC-U3` ban on that import is lifted for `UserService` only. `UserController`, `UserPolicy` and the Form Requests still must not import `TokenIssuer`, and nothing in `app/Http` or the Accounts domain may import `Laravel\Passport` or `PassportTokenIssuer` | `ACC-U3` scope, `LAY2` |
| `delete` always revokes all of the target user's tokens | `ACC-U3` trap 10 |
| Revocation happens only after the `ProtectedUserException` guards pass and only for a write that is actually persisted. A rejected operation (protected user, `403`, `404`, `422`) revokes nothing | Data integrity |
| At most one `revokeAll` call per `update` or `delete` call, even when password and role change together | Efficiency |
| HTTP behaviour is unchanged: `update` still returns `200` with `UserResponseData`, `delete` still returns `204`. No new endpoints, no new response fields | `ACC-U3` contracts |
| Tokens carry empty scopes and role is read from the database on each request (ADR 0001), so a role change already takes effect immediately. Revocation on role change forces a re-login and ends existing sessions; it is not what enforces the new role. The ADR must say this plainly | ADR 0001 |
| Tests use real tokens sent as `Authorization: Bearer` headers. `actingAs` does not exercise tokens and cannot prove revocation | `TST2` |

---

## Open decisions

Achintha must answer these before the plan is frozen (G1 gate). My recommendation is marked for each. If `ARCHITECTURE.md` already states a rule, `ARCHITECTURE.md` takes precedence and Gemini cites the section.

| ID | Question |
|---|---|
| OD-1 | **Branch base.** Is `ACC-U4` merged into `main` (and was the `b143cad..6cce3f1` docs-only check pasted)? Recommended: merge `ACC-U4` first, then branch `feat/users-token-revocation` from `main`, so every `main...HEAD` check works unchanged. Alternative: branch from the `ACC-U4` branch and replace `<BASE>` in the commands below. |
| OD-2 | **What triggers revocation on update.** Recommended: (a) the `password` field is present in the update (not `Optional`), regardless of whether the value is the same, because comparing a plaintext against a hash is pointless work; (b) the `role` field is present and differs from the persisted role; (c) nothing else. Name-only and email-only updates, and a `role` equal to the current role, do not revoke. Alternative: revoke on any update (simpler, but logs users out when they edit their name). |
| OD-3 | **A user changes their own password.** `UserPolicy` lets a Customer or Retailer update their own record. Recommended: `revokeAll` including the token used for the request. The request still returns `200`, and the client must log in again with the new password. Alternative: keep the current token and revoke the others, which needs a new contract method (Tokens stream and Contract seam, so a Change Request) and is not recommended. |
| OD-4 | **Failure semantics.** Recommended: fail closed. The write and `revokeAll` share one `DB::transaction`, so if `revokeAll` throws, the password, role or soft delete is rolled back and the request returns `500`. Alternative: revoke after commit and log failures, which leaves a window where a changed or deleted account still has live tokens. |
| OD-5 | **Documentation of the policy.** Recommended: one short ADR plus the `ARCHITECTURE.md` update (this is a security decision someone will question later). Alternative: `ARCHITECTURE.md` text only, which drops one file from the manifest. |
| OD-6 | **Single-session on login.** Should logging in revoke the user's older tokens? Recommended: no. Multiple devices stay allowed and this step stays about account changes. If you want it, it is a separate step (`ACC-U6`) because it changes `AuthService` and product behaviour. |

---

## Acceptance criteria

| ID | Criterion |
|---|---|
| AC-1 | `DELETE /api/v1/users/{user}` as `Superadmin` revokes all of the deleted user's tokens: their access token is rejected with `401` on a protected endpoint and their refresh token is rejected by `/api/v1/auth/refresh`. Another user's tokens still work. |
| AC-2 | `PATCH /api/v1/users/{user}` with a `password` revokes all of the target's tokens (access and refresh). Logging in with the old password then returns `401`, and logging in with the new password returns `200` with tokens that work. |
| AC-3 | Self password change (OD-3): a Customer changing their own password gets `200`, and the token used for that request is rejected on the next request. A `Superadmin` changing another user's password revokes that user's tokens, and the superadmin's own token keeps working. |
| AC-4 | A role change by a `Superadmin` between `Customer` and `Retailer` revokes the target's tokens. Other users' tokens and the superadmin's token are unaffected. |
| AC-5 | No-op updates do not revoke (OD-2): a name-only update, an email-only update, and a `role` equal to the current role each leave the target's access and refresh tokens working. |
| AC-6 | Rejected operations do not revoke: attempting to delete a `Superadmin`, promote someone to `Superadmin`, or demote a `Superadmin` fails with the `ACC-U3` status, changes nothing in the database, and leaves every token working. A `403`, `404` or `422` on `PATCH` or `DELETE` also leaves tokens working. |
| AC-7 | Fail closed (OD-4): with a `TokenIssuer` fake whose `revokeAll` throws, a password update leaves the stored password hash unchanged, a role update leaves the role unchanged, and a delete leaves the user not soft-deleted. |
| AC-8 | Revocation goes through the contract exactly once per qualifying call: a recording `TokenIssuer` fake sees one `revokeAll` for a delete, one for a combined password and role update, and none for a non-qualifying update. No other `TokenIssuer` method is called by `UserService`. |
| AC-9 | Layering and gates: `UserService` imports the `TokenIssuer` contract and nothing from Passport or Http; `UserController`, `UserPolicy` and the requests do not mention `TokenIssuer`; `tests/Arch/`, `tests/Feature/Tokens/` and the `ACC-U2`, `ACC-U3`, `ACC-U4` tests pass unchanged. |
| AC-10 | The ADR (if OD-5 accepts it) exists in `docs/adr/` and is linked from `ARCHITECTURE.md`; `ARCHITECTURE.md` describes the revocation behaviour; `composer check` (Pint + Larastan + Pest) passes with zero errors. |

---

## Rules most at risk

| Rule ID | Why it is at risk in this step |
|---|---|
| STM1 / STM3 | Reaching into Passport models or tables, or editing the Tokens stream or Contract seam, to make revocation work. |
| LAY2 | Importing `Laravel\Passport`, `PassportTokenIssuer` or `Illuminate\Http` into `UserService`. |
| LAY1 / LAY6 | Moving revocation into `UserController` or a listener, or changing controller responses. |
| ACT (Account rules on credential and role changes, Gemini cites exact IDs) | Skipping revocation on one of the three triggers, or revoking on a rejected operation. |
| ACT6 / SCR2 | Comparing or hashing passwords in the service to decide whether to revoke (use field presence), or logging credentials. |
| STD1 / STD3 | Missing `declare(strict_types=1);`, role string literals instead of `UserRole` cases. |
| TST2 / TST4 | Tests that use `actingAs` (proves nothing about tokens), no negative cases, rows not created through `UserFactory`. |
| WFL6 | Scope creep: single-session login, logout-all, pruning, editing `ACC-U3`/`ACC-U4` files or the Tokens stream. |

---

## Known traps

1. **`UserService` constructor change versus existing tests.** Adding the `TokenIssuer` dependency breaks any test that builds the service with `new UserService(...)`. Read `UserServiceTest` and the other tests first. If any construct it manually, submit a Change Request; do not edit `ACC-U2`/`ACC-U3` tests silently.
2. **Revoke only on a real change.** Compare the requested role with the persisted role before the write, and treat password as "present and not `Optional`". Handle `Optional` explicitly. A `PATCH` with `role` equal to the current role must not revoke.
3. **Guard order.** `ProtectedUserException` must be thrown before any write and before any `revokeAll`. Test with a recording fake that no `revokeAll` call happened on a rejected operation.
4. **Atomicity.** Read how `UserService` handles transactions today. `AuthService::register` is the precedent for `DB::transaction`; nested transactions are fine in Laravel but the plan must state what happens. For AC-7, bind a throwing fake in the container (as in `AuthEndpointsTest`); the fake must implement all four `TokenIssuer` methods.
5. **Self password change logs the caller out.** The `200` response is built before the next request, so it still succeeds, but the very next request with the same token is `401`. Do not "fix" this in the controller. Test it with a real Bearer token, never `actingAs`.
6. **What `revokeAll` really does.** Read `PassportTokenIssuer::revokeAll`. It must revoke access tokens and refresh tokens, only for that user, and not fail when the user has no tokens. If it revokes only access tokens, or errors on zero tokens, stop and submit a Change Request to the Tokens stream.
7. **Refresh path after delete.** A deleted user's refresh token must not mint new tokens. Do not rely on the Passport guard rejecting soft-deleted users: that covers access tokens only. Read `PassportTokenIssuer::refresh` for how it treats soft-deleted users, and assert in a test that the refresh endpoint rejects the refresh token after delete. If it does not, that is a finding or Change Request against the Tokens stream, not a patch.
8. **Every caller gets the new behaviour.** `SuperadminSeeder` or any other caller of `UserService::update` or `delete` will now call `revokeAll`. List all callers in plan Section 3 and confirm none breaks (for example a seeder running before the token tables exist).
9. **Login limiter in tests.** `auth-login` allows 5 attempts per minute per email + IP, so tests that log in repeatedly can get `429`. Create tokens with `TokenIssuer::issue($user)` where possible, use `Cache::flush()` in `beforeEach`, and call `ensurePassportKeys()`.
10. **Concurrency.** A login that checks the old password just before the password-change transaction commits can mint a token that the revocation misses. Row locking is out of scope. Record this as an accepted risk in plan Section 13 with the concurrency note the plan template asks for.
11. **No backfill.** Tokens issued before this step for already-deleted users are not cleaned up. State the pre-production assumption in plan Section 13 rather than writing a data migration.
12. **Superadmin self-update.** `UserPolicy` allows a `Superadmin` on any record including their own, so a superadmin changing their own password loses their own tokens. Cover it with one test case.

---

## Size guard

**Expected file count: 3 to 4** (excluding `docs/blueprints/ACC-U5/*`)

- Modified (2): `app/Domain/Accounts/Services/UserService.php`, `ARCHITECTURE.md`.
- New (1 to 2): `tests/Feature/Accounts/UserTokenRevocationTest.php`, and `docs/adr/<next number>-token-revocation-on-account-changes.md` (omit if OD-5 chooses `ARCHITECTURE.md` only).

Split trigger: if the plan manifest exceeds **5 files**, touches any migration, factory or seeder, modifies `UserController`, `UserPolicy`, a Form Request, `AuthService` or any Tokens-stream or Contract file, or needs more than one ADR, stop and submit a Change Request.

---

## Step-specific Layer A checks

Run these in addition to the standard Layer A commands from `docs/blueprints/RULE_REGISTER.md`. Replace `<BASE>` with the branch agreed in OD-1 (`main` if `ACC-U4` is merged).

```powershell
# 15a. No migration, factory or seeder touched (expect empty)
git diff --name-only <BASE>...HEAD -- database

# 15b. No Tokens-stream or Contract seam files touched (expect empty)
git diff --name-only <BASE>...HEAD | Select-String -SimpleMatch 'PassportTokenIssuer','TokenController','TokenServiceProvider','config/passport.php','routes/api/tokens.php','TokenIssuer.php','TokenPairData.php','routes/api.php'

# 15c. Only UserService changed under app, routes, bootstrap and config (expect exactly app/Domain/Accounts/Services/UserService.php)
git diff --name-only <BASE>...HEAD -- app routes bootstrap config

# 15d. UserService imports the contract and nothing from Passport or Http (expect no output from the second command)
Select-String -Path app/Domain/Accounts/Services/UserService.php -SimpleMatch 'Contracts\TokenIssuer'
Select-String -Path app/Domain/Accounts/Services/UserService.php -SimpleMatch 'Laravel\Passport','PassportTokenIssuer','Illuminate\Http','App\Http'

# 15e. HTTP layer and policy never mention tokens or revocation (expect none)
Get-ChildItem app/Http,app/Domain/Accounts/Policies -Recurse -Filter *.php | Select-String -SimpleMatch 'TokenIssuer','Laravel\Passport','revokeAll'

# 15f. Passport is imported in the Accounts domain only by the token implementation (expect only app\Domain\Accounts\Tokens\PassportTokenIssuer.php)
Get-ChildItem app/Domain/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport' | Select-Object -ExpandProperty Path -Unique

# 15g. No direct token table or relation access in app or Accounts tests (expect none)
Get-ChildItem app,tests/Feature/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'oauth_access_tokens','oauth_refresh_tokens','->tokens()'

# 15h. No Hash::make and no role string literals in services, HTTP layer or Accounts tests (expect none)
Get-ChildItem app/Domain/Accounts/Services,app/Http,tests/Feature/Accounts -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make',"'superadmin'","'retailer'","'customer'"

# 15i. Tests that prove revocation must not use actingAs (every hit needs an explanation)
Select-String -Path tests/Feature/Accounts/UserTokenRevocationTest.php -SimpleMatch 'actingAs'

# 15j. Token, arch and Accounts tests pass unchanged
php artisan test --compact tests/Feature/Tokens tests/Arch tests/Feature/Accounts
```

---

## Mutation spot-check (required, authorization and security step)

Claude names these for VERIFY. Gemini breaks each one temporarily, shows the specific test that fails, restores it and shows a clean `git diff`:

1. Remove the `revokeAll` call from `delete` (AC-1 must fail).
2. Revoke on every update instead of only on a real change (AC-5 must fail).
3. Move the `revokeAll` call before the `ProtectedUserException` guard (AC-6 must fail).
4. Move `revokeAll` outside the transaction or swallow its exception (AC-7 must fail).