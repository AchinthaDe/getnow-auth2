# GetNow CMS: Step Workflow

Claude, Achintha and Gemini. Applies from UI Step 3 onward. Built on AGENTS.md and ARCHITECTURE.md. If this document conflicts with either, they win and this one gets fixed.

## 1. Roles

| Who | Does | Does not |
| --- | --- | --- |
| Achintha | Relays every artifact between Claude and Gemini, decides product questions, runs the verification commands, runs the manual UI script, says APPROVED or ACCEPTED | Judge code quality unaided |
| Claude | Writes the Step Brief (scope, acceptance criteria, traps), reviews plans, reviews diffs and evidence, issues verdicts and numbered findings | See the repo or run code. Claude reviews only what is pasted and never accepts a claim without a diff or raw output |
| Gemini | Writes the plan, implements the frozen plan, produces the Implementation Report and fix reports | Change scope, add dependencies, or deviate silently |

Evidence rule: a claim counts only when backed by a git diff, a file, or raw unedited command output. 'All tests pass' with no output means not done. Gemini's own summaries and self-audits are leads, not proof.

## 2. Phases to steps

A phase is the checklist in ARCHITECTURE.md section 1. A step is one vertical slice that:

- has one concern (AGENTS.md: one concern per change)
- ends mergeable with `composer check` green
- has 3 to 10 testable acceptance criteria (ACs)
- crosses one layer boundary at a time: domain, or admin UI, or HTTP, not all three

Split a step when the plan manifest passes about 10 files, mixes domain and UI work, or needs more than one ADR.

ID format: `P<phase>-<AREA><n>`, with a letter for sub-steps, for example `P1-UI3a`.

Proposed remainder of Phase 1:

| Step | Scope | Depends on |
| --- | --- | --- |
| P1-UI3a | BlockFormRegistry, AnnouncementBar form schema in app/Filament/Blocks, arch and unit tests. No pages | UI2 |
| P1-UI3b | Create and Edit pages wired to Actions, validation, link rules, smoke tests | UI3a |
| P1-UI4 | View page, header and row actions Publish, Archive, Enable, Disable through Actions, activity log, policy abilities | UI3b |
| P1-SET | StorefrontSettings page saving through an Action. This is in Phase 1 scope but has no line on the progress list: confirm whether it exists | none |
| P1-MENU1 | Menu and MenuItem models, migrations, Actions (depth 2 enforced), policy, tests | UI4 |
| P1-MENU2 | Menus admin UI with nested items | MENU1 |
| P1-API1 | GetLayoutContent Action, Data classes, visibility, D8 tie-break, content-api.md and fixture | MENU1, SET |
| P1-API2 | Controller, route, rate limit, ETag, contract test | API1 |
| P1-SEED | Idempotent ContentSeeder mirroring app.blade.php | API1 |
| P1-CLOSE | Phase exit review (section 11) | all |

Order can change. Dependencies cannot.

## 3. Step lifecycle

```text
BRIEF > PLAN v1 > PLAN-REVIEW > (REVISE > PLAN v2 ...) > APPROVED (plan frozen)
  > IMPLEMENT > IMPL-REPORT > VERIFY > (FINDINGS > FIX-REPORT > VERIFY r2 ...) > ACCEPTED > CLOSEOUT
```

Every message carries a tag so nothing gets mixed up across chats: `[P1-UI3a | PLAN v2 | Gemini]`. Types: BRIEF, PLAN vN, PLAN-REVIEW vN, APPROVED, CR (change request), IMPL-REPORT, VERIFY rN, FIX-REPORT rN, ACCEPTED.

| Gate | Entry | Exit |
| --- | --- | --- |
| G0 Ready | Previous step ACCEPTED, brief's open decisions answered by Achintha | Brief issued |
| G1 Plan | Brief | Claude verdict APPROVED, plan version frozen. Cap of 3 review rounds, then Claude lists what is still blocking and Achintha decides |
| G2 Build | Frozen plan | IMPL-REPORT delivered. Any deviation stops work and raises a CR |
| G3 Verify | IMPL-REPORT, raw outputs, diff | No open Blocker or Major findings. Cap of 3 rounds, then re-plan the failing part |
| G4 Close | Verified | Closeout note written, ARCHITECTURE.md checkbox ticked, PR opened |

Keep step artifacts in docs/blueprints/ (already used for step\_2b.md): `P1-UI3a/brief.md`, `plan.v3.md`, `report.md`, `verify.md`.

## 4. Rule register

Plans and reviews cite these IDs. All come from AGENTS.md.

| ID | Rule |
| --- | --- |
| S1 | Business logic only in app/Domain/Content/Actions, one invokable per use case, reads included |
| S2 | Controllers, Filament resources and pages are thin: validate, authorize, call an Action, return |
| S3 | App\\Domain never imports App\\Filament or App\\Http |
| S4 | Filament never calls Model::create, update or delete directly |
| S5 | Block definitions in Domain/Content/Blocks/Name, form schemas in app/Filament/Blocks |
| S6 | Panel code under app/Filament/Admin only, nothing in default Filament paths |
| S7 | No new context or top-level folder without asking |
| C1 | declare(strict\_types=1) in every PHP file |
| C2 | Typed parameters, returns, properties. Prefer final classes |
| C3 | Enums for fixed sets, spatie/laravel-data for structured input and output between layers |
| C4 | Status and is\_enabled change only through Actions (Publish, Archive, Enable, Disable), never a form field |
| C5 | Every write to persisted state, Spatie settings included, goes through an Action |
| C6 | Form Request or Data validation for input, Policies for authorization on every exposed model |
| C7 | Pint and Larastan clean, no baseline entries without approval |
| C8 | Comments explain why, no commented-out code |
| K1 | Block type keys are permanent |
| K2 | schema\_version on every payload, shape change means bump plus upgrader, old payloads still load |
| K3 | No raw HTML, CSS or JS fields. Short text with max lengths, enums, validated links, booleans |
| K4 | Links are a relative path starting with / or an https:// URL, every other scheme rejected |
| K5 | Placements, menu keys, block types defined in code, admins never create them |
| K6 | Block registration is an explicit list in ContentServiceProvider |
| K7 | Visibility decided server-side, API never returns draft, archived, disabled or out-of-window content |
| K8 | API v1 additive only, content-api.md and fixture updated in the same change |
| K9 | New block type follows the ARCHITECTURE.md section 6 checklist |
| DB1 | PostgreSQL only, tests never on SQLite |
| DB2 | Never edit a merged migration, add a new one |
| DB3 | No destructive change in the same release that stops using the thing |
| DB4 | Foreign keys, indexes for query paths, CHECK constraints |
| DB5 | public\_id (ULID) external, never raw IDs |
| DB6 | Seeders idempotent, destructive commands only on phone\_store and phone\_store\_test |
| SEC1 | No secrets or .env committed, new variables in .env.example |
| SEC2 | Never disable authorization, policies, strict types or static analysis rules to pass |
| SEC3 | No sensitive data in logs |
| SEC4 | Admin access checked server-side in canAccessPanel |
| SEC5 | Publish, disable, menu and settings changes written to the activity log |
| T1 | Pest. Feature tests for Actions, API, Filament resources. Unit tests for pure logic. Arch tests in tests/Arch |
| T2 | Every Filament resource has a smoke test for list, create and edit |
| T3 | Every block type tests payload validation, API output, visibility |
| T4 | Factories, not hand-built rows. Tests independent and deterministic |
| T5 | No lowered coverage, skipped or incomplete tests |
| G1 | Branch feat/, fix/ or chore/ from main |
| G2 | Conventional Commits |
| G3 | Never push to main or bypass hooks |
| X1 | No dependency outside ARCHITECTURE.md section 3 without approval |
| X2 | No Docker, Sail or container config |
| X3 | No deployment pipeline or infrastructure change as a side effect |
| X4 | Nothing for later phases |
| X5 | Storefront repo untouched |
| X6 | composer check passes and its output was read |
| X7 | ARCHITECTURE.md, content-api.md, .env.example updated where relevant |
| E1 | PowerShell, one command at a time, no && or bash syntax, no servers left running |

## 5. Step Brief (Claude writes, at the start of every step)

```text
[<STEP ID> | BRIEF | Claude]
Goal:                 one paragraph
Depends on:           accepted steps, and the exact classes/files from them this step uses
In scope:             explicit list
Out of scope:         explicit list, including later-phase things Gemini might add
Read first:           ARCHITECTURE.md sections, AGENTS.md sections, existing files (paths)
Decisions made:       Gemini must not re-decide these
Open decisions:       Achintha answers before G1
Acceptance criteria:  AC-1 .. AC-n (observable, testable, one behavior each)
Rules most at risk:   register IDs and why
Known traps:          specific to this step
Size guard:           expected file count, split trigger
Manual UI script:     numbered click path with expected result (UI steps)
Step-specific checks: extra commands for Layer A (migrations, routes, etc.)
```

## 6. Plan template (Gemini fills, all 14 sections, in order)

```text
[<STEP ID> | PLAN v<N> | Gemini]
Change log (v2 onward): finding ID > section changed

1. Understanding
   Goal in own words (max 5 lines). Out of scope restated, plus anything deliberately not built.

2. Sources read
   Table: file or doc section | what in it constrains this step.
   Includes every existing file this step will call, extend or copy a pattern from.
   Files are read, not recalled.

3. Verified current state
   Existing classes, methods, enums the step relies on: path and signature as they exist now.
   Anything not verified by reading goes to section 13 as an assumption.

4. File manifest
   Table: path | new or modify | layer | one-line responsibility | rule IDs it must satisfy.
   Total file count. Placement checked against AGENTS.md 'Where code goes'.

5. Interface specs
   Every new or changed class: namespace, final, constructor dependencies, public method
   signatures with full types, thrown exceptions, callers.
   Data classes: every field, type, nullability, max length, validation rule, schema_version.
   Enums: all cases and backing values.
   Filament classes: components with field names, labels, validation, and which Action
   each submit or row action calls.

6. Data and migrations
   Tables, columns, types, defaults, FKs, indexes, CHECKs, rollback.
   Backward-compatibility statement. Or: None.

7. Behavior and flows
   Per use case: happy path, each failure path, authorization outcome,
   activity log entry (event name and properties), concurrency note (D9).

8. Rule compliance matrix
   One row per ID in the rule register: Applies Y or N | how satisfied, or why N/A.
   No row skipped.

9. AC traceability
   Table: AC | files | test names | how verified.
   Every AC has a test. Every file maps to at least one AC.

10. Test plan
   Per test file: path, Pest it('...') names, arrange / act / assert in one line each.
   Includes failure, authorization and boundary cases. Factories and states used.
   Arch tests added or changed.

11. Docs and contract updates
   Exact edits: ARCHITECTURE.md (section and new text), content-api.md, fixture,
   .env.example, ADRs. Or: None, with reason.

12. Implementation order
   Numbered commits. Each: Conventional Commit message, files, and the state after it
   (composer check green).

13. Risks, assumptions, conflicts
   Unverified assumptions. Conflicts between AGENTS.md, ARCHITECTURE.md and the brief.
   Open questions. Write 'None' if empty. Never resolve a conflict silently.

14. Dependencies
   New packages: none, or name, justification and ARCHITECTURE.md section 3 status.
```

Specificity bar: a different developer could implement the plan without making a single design choice. Banned in plans: etc., and so on, as needed, appropriate, similar to, standard, handle errors, TBD.

## 7. Plan review (Claude)

Checklist applied every round:

1. Coverage: every AC and in-scope item is mapped, nothing out of scope is present
2. Specificity: banned words, any interface a developer would have to guess
3. Verified state: paths and signatures match the code Achintha pasted, invented ones flagged
4. Placement and layering (S1 to S7)
5. Rule matrix: spot-check Y and N rows, wrong N/A flagged
6. Failure paths: authorization, validation, concurrency, empty data, old payload versions
7. Tests: each AC has a test that would fail if the behavior broke, failure and authorization cases exist, factories used
8. Docs and contract updates listed
9. Commit order: each commit green on its own
10. Scope creep and later-phase leakage (X4)

Verdict format:

```text
[<STEP ID> | PLAN-REVIEW v<N> | Claude]
Verdict: APPROVED | REVISE
Blockers:  B1 | rule or AC | plan section | problem | required change
Majors:    M1 ...
Minors:    m1 ...
Questions for Achintha: decisions only you can make
For v<N+1>: exact sections Gemini must change
```

Blocker: would produce wrong behavior or break a rule. Major: ambiguity that forces a guess. Minor: polish. APPROVED only with zero Blockers and Majors. The approval line is `APPROVED: <STEP ID> plan v<N>` and it freezes that version. Gemini resends the full plan each round, with the change log on top and unflagged sections untouched.

## 8. Implementation protocol

1. Work only from the frozen plan, in its commit order.
2. One PowerShell command at a time.
3. No extras: no refactors, renames, added dependencies, 'improvements'.
4. Bug fixes start with a failing test.
5. Any deviation stops work. Gemini sends a CR and waits for `APPROVED: CR-n`.
6. Finish with `composer check`, read it, then the IMPL-REPORT. Gemini never declares the step done.

```text
[<STEP ID> | CR-<n> | Gemini]
Plan section:
Plan says:
Reality requires:
Evidence: error output or file excerpt
Options (max 2) with impact on ACs, rules, other files:
Recommendation:
```

## 9. Implementation Report (Gemini fills)

```text
[<STEP ID> | IMPL-REPORT | Gemini]
1. Plan version, base SHA, head SHA, raw output of: git log --oneline <base>..<head>
2. File reconciliation: raw git diff --name-status output, then a table:
   planned and done | planned and not done | unplanned (with reason)
3. AC results: AC | file:line | test name | result
4. Deviations: CR IDs and what changed. 'None' stated explicitly.
5. Raw unedited output: composer check (full), php artisan migrate (if any), git status
6. Rule self-audit: output of the section 10A commands
7. Docs diffs: ARCHITECTURE.md, content-api.md, fixture, .env.example, ADRs
8. Gaps: anything unfinished, skipped, flaky or suspicious. 'None' stated explicitly.
```

A report with summaries in place of raw output is rejected without review.

## 10. Verification

### 10A. Layer A, mechanical (Achintha runs, one at a time, pastes raw output)

1. `git status --short` (expect empty)
2. `git log --oneline main..HEAD` (commits match plan order, Conventional Commits)
3. `git diff --name-status main...HEAD` (compare with manifest, an M on a merged migration fails DB2)
4. `git diff main...HEAD -- composer.json composer.lock package.json` (no new dependency, X1)
5. `git diff main...HEAD --stat -- '*baseline*'` (any hit fails C7)
6. `Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '::create(','->update(','->delete(','->save(','->forceDelete('` (every hit needs an explanation, S4)
7. `Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Filament','App\Http'` (expect none, S3)
8. `Get-ChildItem app,tests,database -Recurse -Filter *.php | Where-Object { -not (Select-String -Path $_.FullName -SimpleMatch 'declare(strict_types=1);' -Quiet) } | Select-Object -ExpandProperty FullName` (expect none, C1)
9. `Get-ChildItem tests -Recurse -Filter *.php | Select-String -SimpleMatch '->skip(','->todo(','markTestIncomplete','markTestSkipped'` (expect none, T5)
10. `git ls-files | Select-String -SimpleMatch 'docker','compose','sail'` (expect none, X2)
11. `git ls-files .env` (expect empty, SEC1)
12. `composer check` (read all of it)
13. Any step-specific commands from the brief

### 10B. Layer B, diff review (Claude)

Achintha pastes `git diff main...HEAD`, or the changed files in manifest order. Claude checks:

- manifest against the diff: missing files, unplanned files, hidden edits to config, composer files, baseline
- signatures against plan section 5
- every rule in the register that applies
- test quality: assertions that prove behavior (not only a 200 status), failure, authorization and boundary cases, factories used
- docs and contract edits match plan section 11

Mutation spot-check, required for steps touching visibility, status transitions, authorization or the API: Claude names 2 or 3 behaviors, Gemini breaks each one temporarily, shows the specific test that fails, then restores it and shows a clean `git diff`.

### 10C. Layer C, behavior (Achintha)

Run the manual UI script from the brief. Mark pass or fail per line and paste the result. Attach a screenshot for any visual claim.

### Verify format

```text
[<STEP ID> | VERIFY r<N> | Claude]
Verdict: ACCEPTED | FIXES REQUIRED
Layer A: pass or fail per command
Layer B: what was reviewed, what was not pasted
Findings:
  F1 | Blocker, Major or Minor | AC or rule ID | file:line | problem | required fix | evidence required
Not verified: anything Claude could not check from what was pasted
```

### Fix loop

Gemini fixes only the listed findings, one commit per group, and sends a FIX-REPORT mapping finding ID to commit SHA to diff to test or output. Anything beyond the findings is a CR. ACCEPTED requires zero open Blockers and Majors, clean Layer A, all Layer C lines passing, and the AGENTS.md Definition of Done ticked.

## 11. Closeout and phase exit

Step closeout:

- tick the checkbox in ARCHITECTURE.md Phase 1 progress
- closeout note, 5 lines: what shipped, deviations accepted, debt and follow-ups, what the next step depends on, ADRs added
- PR description: what changed, why, how tested, risks (migrations, API contract, security)

Phase start: a Phase Brief with scope, step list, order, risks and exit criteria.

Phase exit review (P1-CLOSE):

- all steps ACCEPTED and `composer check` green on main
- content-api.md, fixture and the real API response agree
- `php artisan migrate:fresh --seed` on the local phone\_store database reproduces the storefront values
- every Filament resource has a smoke test, arch tests cover the dependency rules
- every sensitive change is in the activity log
- ARCHITECTURE.md phase status updated, open decisions recorded as ADRs
- no later-phase scaffolding anywhere (X4)

## 12. Prompts to paste to Gemini

Plan request:

```text
Read AGENTS.md and ARCHITECTURE.md, then the Step Brief below. Produce a plan using the Plan template exactly: all 14 sections, in order, none skipped. Read the files you list in section 2 before writing section 3. Do not recall signatures from memory. Do not write code. Do not use: etc., as needed, appropriate, similar to, TBD. If something is ambiguous or conflicts, put it in section 13 and stop there; do not choose. Start with the tag [<STEP ID> | PLAN v1 | Gemini].
```

Plan revision:

```text
Revise the plan per the review below. Resend the whole plan as v<N+1> with a change log at the top mapping each finding ID to the section you changed. Do not touch sections that were not flagged.
```

Implementation:

```text
Implement plan <STEP ID> v<N> exactly. Follow the section 12 commit order. One PowerShell command at a time, no &&. No refactors, extra files, dependencies or improvements. If the plan is wrong or incomplete, stop and send a CR; do not work around it. When finished, run composer check, then fill the IMPL-REPORT template with raw unedited outputs. A summary in place of raw output is a failed report.
```

Fixes:

```text
Fix only findings F1 to Fn from VERIFY r<N>. One commit per group. Send a FIX-REPORT: finding, commit SHA, diff, evidence. If a fix needs anything beyond the finding, send a CR instead.
```

## 13. Suggested AGENTS.md addition (your call)

```text
## Step protocol
- Work only from an approved plan identified by step ID and version. No approved plan, no code.
- If the plan is wrong or incomplete, stop and write a Change Request. Never deviate silently.
- A step ends with an IMPLEMENTATION REPORT containing raw, unedited command output. Summaries are not evidence.
- Do not mark a step done. Only the reviewer accepts it.
```
