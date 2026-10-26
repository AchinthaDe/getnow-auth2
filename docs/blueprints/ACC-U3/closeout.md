# ACC-U3 Closeout Note

- **Shipped**: UserResponseData (SnakeCaseMapper, public_id mapped to id, CarbonInterface timestamps), UserPolicy (strict superadmin-only per ARCHITECTURE.md §3 and §7, registered in AppServiceProvider), StoreUserRequest and UpdateUserRequest (with pre-validation email normalisation, max:72 password limit, and Optional partial update mapping), UserController (index, store, show, update, destroy), /api/v1/users routes bound by {user:public_id}, ProtectedUserException -> 403 rendering in bootstrap/app.php, and 19 feature tests in UserControllerTest.
- **Deviations accepted**:
  - **CR-1 (Route Seam)**: Added `require __DIR__.'/api/users.php';` to `routes/api.php` to mount the users route group, correcting the brief's assumption that it was already required.
  - **Policy Alignment (Fix 1)**: `UserPolicy` enforced as strictly Superadmin-only for all 5 actions (`viewAny`, `view`, `create`, `update`, `delete`) in accordance with `ARCHITECTURE.md` §3 and §7, overriding the brief's OD-1 self-access recommendation. Self-service profile editing is deferred to a future `/me` endpoint.
  - **CI Key Infrastructure Fix**: Added shared `InteractsWithPassportKeys` trait in `tests/Support/InteractsWithPassportKeys.php` registered globally for Feature tests in `tests/Pest.php` to supply in-memory test RSA keys to Passport in CI runners where gitignored `storage/oauth-*.key` files are absent.
- **Debt and follow-ups**:
  - Token revocation (`TokenIssuer::revokeAll`) on user soft-delete, password change, and role change is intentionally deferred to `ACC-U5` per `ARCHITECTURE.md` §10.
- **What the next step depends on**: `ACC-U4` (`AuthService`, `AuthController`, `LoginRequest`, `RegisterRequest`, `LoginData`, `AuthResultData`, `UserRepositoryInterface::findByEmail`, `/api/v1/auth/register` and `/api/v1/auth/login`) builds directly on `UserService`, `UserRepository`, `UserResponseData`, and `TokenIssuer::issue`.
- **ADRs added**: None (soft-delete ADR `0003-user-soft-deletes-and-schema-cleanup.md` was already recorded in `ACC-U1`).
