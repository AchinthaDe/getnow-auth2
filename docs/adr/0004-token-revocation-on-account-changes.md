# 0004. Token revocation on account changes

- **Status**: Accepted
- **Date**: 2026-10-27
- **Step**: `ACC-U5`

## Context

In the accounts and authentication module, users authenticate to receive short-lived OAuth2 access tokens and rotating refresh tokens (ADR 0001). Prior to step `ACC-U5`, active access tokens and refresh tokens remained usable following user deletion, password changes, or role changes until natural expiration.

`AGENTS.md` mandates that deleting a user, changing a password, or changing a role must call `TokenIssuer::revokeAll`. Open Question OQ8 (formulated from OD-2 through OD-5) addresses the exact triggers, transactional boundaries, and session semantics for token revocation.

## Decision

1. **Revocation Triggers**:
   - `UserService::destroy`: Always calls `TokenIssuer::revokeAll($user)` upon user soft deletion.
   - `UserService::update`: Calls `TokenIssuer::revokeAll($user)` when `password` is present in `UserData` (not `Optional`), or when `role` is present (not `Optional`) and differs from the current persisted `$user->role`.
   - Name-only, email-only, and identical-role updates do not revoke tokens, allowing active sessions to persist uninterrupted.
2. **Transactional Atomicity and Fail-Closed Semantics**:
   - Both the database modification (`$this->users->update` or `$this->users->destroy`) and the token revocation (`$this->tokens->revokeAll($user)`) execute inside a single atomic database transaction (`DB::transaction`).
   - If `TokenIssuer::revokeAll` throws an exception, the database changes roll back completely, preventing desynchronization between user state and active credentials. The request terminates with HTTP 500 Internal Server Error.
3. **Self-Update Session Termination**:
   - Under current authorization rules, only `Superadmin` can update their own user record over HTTP (`PATCH /api/v1/users/{public_id}`). When a superadmin changes their own password, `revokeAll` invalidates all tokens belonging to that superadmin, including the token used to authenticate the current request.
   - The current update request completes with HTTP 200 and returns `UserResponseData`. Subsequent requests with the prior Bearer token receive HTTP 401 Unauthenticated. The superadmin must re-authenticate via `POST /api/v1/auth/login`.
   - Customer and Retailer self-updates over HTTP remain forbidden by `UserPolicy` until dedicated self-service endpoints are introduced. At the domain layer, calling `UserService::update` with a password for a customer revokes their tokens.
4. **Empty Scopes and Role Evaluation**:
   - Per ADR 0001, access tokens carry empty default scopes; authorization is evaluated server-side via `UserPolicy` using `$user->role` loaded from the database.
   - A role change takes effect in the database immediately upon transaction commit. Revoking tokens on role change terminates all active sessions across client devices, forcing users to re-authenticate under their new role.
5. **Concurrent Login Window**:
   - In accordance with brief trap 10, a concurrent login verifying an old password immediately before a password update transaction commits may issue a token that revocation does not catch. Row-level locking on authentication is out of scope and this window is accepted as an operational risk.
6. **Mark Open Question OQ8 as Resolved**:
   - Record OQ8 in `ARCHITECTURE.md` §11 as resolved by ADR 0004.

## Consequences

1. Active access tokens and refresh tokens are invalidated immediately upon user soft delete, password changes, or role changes.
2. Fail-closed transactional rollback guarantees that no credential or role changes persist if token revocation fails.
3. Non-sensitive profile changes (such as name or email updates) do not invalidate existing user sessions.
4. Callers modifying their own credentials are logged out across all sessions, requiring immediate re-login.
