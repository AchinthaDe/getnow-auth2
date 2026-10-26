# ACC-U1 Closeout Note

- **What shipped**: UserRole enum, additive users migration (public_id, role, deleted_at, CHECK constraint, legacy column drops), final User model with SoftDeletes and role cast, rewritten UserFactory with ULID and role states, idempotent SuperadminSeeder via config(), DatabaseSeeder cleanup, ADR 0003.
- **Deviations accepted**: None.
- **Debt and follow-ups**: None. config/auth.php superadmin credentials block is intentionally simple (no loud failure when env var unset — falls back to local default).
- **What ACC-U2 depends on**: UserRole enum at App\Domain\Accounts\Enums\UserRole, User model with public_id, role cast, SoftDeletes, and hashed cast; SuperadminSeeder for test database state.
- **ADRs added**: docs/adr/0003-user-soft-deletes-and-schema-cleanup.md (OQ6 resolved).
