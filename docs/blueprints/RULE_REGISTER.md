# Accounts Rule Register and Layer A Commands

Save as docs/blueprints/RULE\_REGISTER.md. It replaces sections 4 and 10A of the CMS step workflow for ACC steps, and ARCHITECTURE.md section 10 replaces the CMS step table in section 2. The rest of the workflow (roles, lifecycle, brief, plan, review, report, fix loop) applies as written.

Plans cite these IDs in the rule compliance matrix (one row per ID, Applies Y or N, none skipped). Reviews cite them in findings. Source of truth is the Accounts AGENTS.md and ARCHITECTURE.md: if this register disagrees with them, they win and this gets fixed. Step IDs are ACC-C\<n>, ACC-T\<n>, ACC-U\<n>. IDs use three-letter families so they never collide with the CMS register (S, C, K, DB, SEC, T, G, X, E).

Retired: the ACC-T0 import rules (move only, copy and do-not-copy lists) ended when T0 was accepted. Imported token code stays frozen under STM4.

## Streams and boundaries

| ID | Rule |
| --- | --- |
| STM1 | A step edits only its own stream's paths. A change in another stream's files means stop and ask, except the ARCHITECTURE.md section 5 touch points, which the plan must name. |
| STM2 | TokenIssuer and TokenPairData are the only link between the streams. Changing either is a contract change: its own PR, labelled contract, never a side effect. |
| STM3 | Users-stream code never imports Laravel\\Passport (exception: User carries HasApiTokens and OAuthenticatable). Tokens-stream code never reads or writes credentials, roles or the users table beyond what TokenIssuer receives. |
| STM4 | Imported token code is frozen. Changes need a Change Request, and a new ADR if they touch the grant, refresh delivery, lifetimes or scopes. |

## Layering and placement

| ID | Rule |
| --- | --- |
| LAY1 | Request flow: FormRequest, Controller, Service, Repository interface, Eloquent repository. |
| LAY2 | Controllers authorize through the Policy, call the Service and return UserResponseData. No Eloquent, no repositories, no business rules. Accepted exception: the User instanceof check in TokenController (ACC-C0). |
| LAY3 | Services hold business rules only. They depend on UserRepositoryInterface and TokenIssuer, never on UserRepository or Eloquent builders, and use no Illuminate\\Http classes. |
| LAY4 | Repositories hold all Eloquent and query code. No business rules, no HTTP exceptions. |
| LAY5 | Controller, Service and Repository expose the same five method names: index, store, show, update, destroy. |
| LAY6 | App\\Domain never imports App\\Http. |
| LAY7 | Code goes where ARCHITECTURE.md sections 4 and 5 say. No new top-level folder without asking. |
| LAY8 | Never return an Eloquent model from a controller. Return UserResponseData. |

## Coding standards

| ID | Rule |
| --- | --- |
| STD1 | declare(strict\_types=1); in every PHP file. |
| STD2 | Type every parameter, return value and property. Prefer final classes. |
| STD3 | Enums for fixed sets (UserRole). spatie/laravel-data classes for structured data between layers. |
| STD4 | One UserData DTO serves store and update, with Optional properties. Create-versus-update requirements live in StoreUserRequest and UpdateUserRequest. No second DTO. |
| STD5 | Update writes only the fields that were sent. Never write null over a field that was not provided. |
| STD6 | Pint and Larastan clean. No baseline entries without approval. |
| STD7 | Comments explain why, not what. No commented-out code. |

## Account rules

| ID | Rule |
| --- | --- |
| ACT1 | Public registration creates a customer only. The role is set inside AuthService and any role input is ignored. |
| ACT2 | Retailers are created only by the superadmin through POST /users. They never self-register. |
| ACT3 | Only the superadmin uses the /users endpoints, enforced server-side by UserPolicy. |
| ACT4 | The API never creates or promotes a superadmin. Superadmins come from SuperadminSeeder or an artisan command. |
| ACT5 | Superadmin, retailer and customer all log in through the same endpoint. |
| ACT6 | Hash passwords only through the hashed cast on User. Never call Hash::make in a layer. |
| ACT7 | Login returns the same error for an unknown email and a wrong password. |
| ACT8 | Deleting a user, changing a password or changing a role calls TokenIssuer::revokeAll. |
| ACT9 | AuthService calls TokenIssuer::issue exactly once per login or register. |
| ACT10 | Login and register responses use UserResponseData plus the token pair. Never expose the internal incremental id. |
| ACT11 | Never log passwords, tokens or refresh tokens. |

## Token rules

| ID | Rule |
| --- | --- |
| TOK1 | TokenIssuer is implemented by PassportTokenIssuer and bound in TokenServiceProvider. It is imported, not rewritten. |
| TOK2 | Grant, refresh delivery, lifetimes and scopes are decided in ADR 0001. Changing any of them needs a new ADR. |
| TOK3 | Check the installed Passport version's documentation before changing the issuer. Re-run the token tests on every Passport update. |
| TOK4 | Token lifetimes live in config, not in code. |
| TOK5 | Refresh tokens rotate on use. A used or revoked refresh token must fail. |
| TOK6 | refresh and logout are throttled and covered by tests, including expired, revoked and reused tokens. |

## Database

| ID | Rule |
| --- | --- |
| DBS1 | PostgreSQL only. Tests never run on SQLite. |
| DBS2 | Never edit a merged migration. Add a new one. Your own unmerged migration may be edited. |
| DBS3 | No destructive change (drop or rename a column or table) in the same release that stops using it. |
| DBS4 | Foreign keys, indexes for query paths, and CHECK constraints (for example role). |
| DBS5 | Expose public\_id (ULID) externally, never raw incremental ids. Route model binding uses public\_id. |
| DBS6 | Seeders are idempotent. Destructive database commands run only against the local dev and test databases. |

## Security

| ID | Rule |
| --- | --- |
| SCR1 | Never commit secrets, .env files, real keys (including Passport keys) or production data. New variables go in .env.example. .gitignore keeps /storage/\*.key. |
| SCR2 | Never disable authorization, policies, strict types or static analysis rules to make code pass. |
| SCR3 | Authorization is checked server-side only. |
| SCR4 | Throttle register, login and refresh. |

## Testing

| ID | Rule |
| --- | --- |
| TST1 | Pest. Feature tests for every endpoint, unit tests for pure logic, architecture tests in tests/Arch. |
| TST2 | Cover failure and authorization cases: customer and retailer calling /users, register ignoring a sent role, partial update leaving other fields untouched, destroy revoking tokens. |
| TST3 | Users-stream tests use tests/Support/FakeTokenIssuer. Tokens-stream tests use real Passport on the test database. |
| TST4 | Factories, not hand-built rows. Tests independent and deterministic. |
| TST5 | No lowered coverage, skipped or incomplete tests. Tests are deleted only with the feature they test, itemised in the brief. |

## Git

| ID | Rule |
| --- | --- |
| GIT1 | Branch from main with the stream in the name: feat, fix or chore, then users, tokens or contract, then a description. |
| GIT2 | Conventional Commits with a stream scope, for example feat(users): add update user endpoint. |
| GIT3 | Never push to main directly or bypass git hooks. Rebase on main at least daily. |
| GIT4 | Contract PRs are labelled contract and merge before anything depends on them. |
| GIT5 | PR description: what changed, why, how it was tested, and risks (migrations, shared files, contract, security). |
| GIT6 | Merge order follows ARCHITECTURE.md section 10. |

## Environment

| ID | Rule |
| --- | --- |
| ENV1 | Windows PowerShell. One command at a time. No bash syntax and no &&. |
| ENV2 | Native PHP, Composer, Node and PostgreSQL. No Docker, Sail or containers. |
| ENV3 | Use non-interactive flags. Leave no servers or watchers running. |
| ENV4 | composer check (Pint, Larastan, Pest) must pass and its output must be read. |

## Do not

| ID | Rule |
| --- | --- |
| DNT1 | No new dependency without asking and justifying it. |
| DNT2 | No deployment pipeline or infrastructure change as a side effect of a feature. |
| DNT3 | Nothing out of scope per ARCHITECTURE.md section 1: Filament UI, retailer panel, email verification, password reset, 2FA, permission packages, business accounts, social login. |
| DNT4 | No business logic in controllers or models. |
| DNT5 | No destructive file or database operations outside migrations and the local reset command. |
| DNT6 | Never copy secrets, Passport keys, .env or the old database into this project. |
| DNT7 | Update ARCHITECTURE.md and .env.example when structure, flows, tables or endpoints change. ADR for significant decisions, including the section 11 open questions. |

## Step protocol

| ID | Rule |
| --- | --- |
| WFL1 | Work only from an approved plan identified by step ID and version. No approved plan, no code. |
| WFL2 | If the plan is wrong or incomplete, stop and send a Change Request. Never deviate silently. |
| WFL3 | A step ends with an implementation report containing raw, unedited command output. Summaries are not evidence. |
| WFL4 | Agents never mark a step done. Only the reviewer accepts it. |
| WFL5 | More than three files, a new dependency or a structural change: write a plan and wait for approval. |
| WFL6 | Keep changes small. One concern per change. No refactors mixed with features. |
| WFL7 | If a requirement is ambiguous or conflicts with ARCHITECTURE.md, ask. Do not invent requirements. |

## Layer A commands (replaces workflow 10A)

Achintha runs these one at a time and pastes raw output. `<base>` is the branch the step was cut from.

1. `git status --short` (expect empty)
2. `git log --oneline <base>..HEAD` (matches the plan's commit order, Conventional Commits with stream scope: GIT2)
3. `git diff --name-status <base>...HEAD` (compare with the manifest; an M on a merged migration fails DBS2)
4. `git diff <base>...HEAD -- composer.json composer.lock package.json` (no unplanned dependency: DNT1)
5. `git diff <base>...HEAD --stat -- '*baseline*' phpstan.neon` (any hit fails STD6)
6. `Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Http'` (expect none: LAY6)
7. `Get-ChildItem app/Http/Controllers -Recurse -Filter *.php | Select-String -SimpleMatch 'Repositories','Facades\DB'` (expect none: LAY2)
8. `Get-ChildItem app -Recurse -Filter *.php | Select-String -SimpleMatch 'Laravel\Passport' | Select-Object -ExpandProperty Path -Unique` (expect only Domain/Accounts/Tokens, TokenServiceProvider and Models/User: STM3)
9. `Get-ChildItem app,bootstrap,routes,tests -Recurse -Filter *.php | Where-Object { -not (Select-String -Path $_.FullName -SimpleMatch 'declare(strict_types=1);' -Quiet) } | Select-Object -ExpandProperty FullName` (compare with the baseline list captured in ACC-C1; any new name fails STD1)
10. `Get-ChildItem app -Recurse -Filter *.php | Select-String -SimpleMatch 'Hash::make'` (expect none: ACT6)
11. `Get-ChildItem tests -Recurse -Filter *.php | Select-String -SimpleMatch '->skip(','->todo(','markTestIncomplete','markTestSkipped'` (expect none: TST5)
12. `git ls-files | Select-String -SimpleMatch 'docker','compose','sail'` (expect none: ENV2)
13. `git ls-files .env` and `git ls-files storage` (expect no .env and no key files: SCR1)
14. `composer check` (read all of it: ENV4)
15. Any step-specific commands from the brief.

## Mutation spot-check (replaces the workflow 10B trigger)

Required for steps that touch authorization (ACT3), role handling (ACT1, ACT4), token revocation or rotation (ACT8, TOK5), or architecture rules. Claude names two or three behaviors, Gemini breaks each one temporarily, shows the diff, the specific test that fails, then restores with `git restore` and shows a clean `git status --short` and a clean run. A rule that targets a namespace with no classes yet is dormant (found in ACC-C0, M4): it needs a mutation proof in the step that creates the first class.
