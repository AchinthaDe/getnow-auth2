# 0003. User soft deletes and schema cleanup

- **Status**: Accepted
- **Date**: 2026-10-23
- **Step**: `ACC-U1`

## Context

`ARCHITECTURE.md` §9 specifies `deleted_at` for soft deletes so that future domain entities (such as orders and content blocks) can maintain referential integrity with historical user accounts. In `ARCHITECTURE.md` §11, Open Question OQ6 asked whether `destroy` should perform a hard delete or a soft delete.

Additionally, step `ACC-C1` removed all application usage of Laravel Fortify, Livewire, and session-based authentication. However, starter-kit columns (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, and `remember_token`) were retained in `ACC-C1` to adhere to rule DBS3 ("no destructive change in the same release that stops using it"). Because `ACC-C1` has been accepted and merged, `ACC-U1` represents a subsequent release where these unused columns can safely be pruned.

## Decision

1. Adopt soft deletes for user records:
   - Add a nullable `deleted_at` timestamp column to `users` via `softDeletes()` in migration `2026_10_23_000000_add_accounts_columns_to_users_table`.
   - Add the `Illuminate\Database\Eloquent\SoftDeletes` trait to `App\Models\User`.
2. Purge unused starter-kit columns from `users`:
   - Drop `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, and `remember_token` in the new additive migration.
   - Support clean bidirectional rollback by restoring these columns in the migration's `down()` method.
3. Mark Open Question OQ6 as resolved in `ARCHITECTURE.md`.

## Consequences

1. User records are soft-deleted when `delete()` is called, setting `deleted_at` and excluding them from standard queries while preserving foreign key relationships.
2. Unused two-factor and session remember-token columns are removed from the database schema, keeping the database aligned with the API-only architecture.
3. Rollback operations remain fully backward-compatible and bidirectional.
