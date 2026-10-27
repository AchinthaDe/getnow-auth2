# ACC-U4 Closeout Note

- **Shipped**: register and login endpoints (`POST /api/v1/auth/register`, `POST /api/v1/auth/login`), `AuthService`, `LoginData`, `AuthResultData`, `InvalidCredentialsException`, `UserRepositoryInterface::findByEmail`, `UserRepository::findByEmail`, `RegisterRequest`, `LoginRequest`, `AuthController`, named rate limiters (`auth-login` 5/min per email+ip, `auth-register` 10/min per ip), 401 exception rendering in `bootstrap/app.php`, and 11 feature tests covering AC-1 through AC-10 plus dataset role enforcement in `AuthEndpointsTest`.
- **Deviations accepted**:
  - **CR-1 (Route Seam)**: Added `require __DIR__.'/api/auth.php';` to `routes/api.php` to mount the auth endpoints route group.
  - **File Count**: Final file count is 14 code/config files (9 new, 5 modified) against the 13-file ceiling, due to the CR-1 seam inclusion.
  - **Domain Customer Role Re-construction**: `AuthService::register` explicitly rebuilds `UserData` with `UserRole::Customer` to guarantee customer-role enforcement even if callers bypass HTTP form requests.
  - **Production Dummy Hash Cost**: `DUMMY_HASH` cost set to 12 (`$2y$12$...`) matching `.env.example` `BCRYPT_ROUNDS=12` to eliminate the email-existence timing oracle.
- **Debt and follow-ups**:
  - `SuperadminSeeder` doesn't normalise `SUPERADMIN_EMAIL`; a mixed-case value would prevent superadmin login. Fix with a separate CR.
  - `$request->ip()` in `auth-register` needs trusted-proxy configuration before production deployment behind a reverse proxy.
  - If `BCRYPT_ROUNDS` changes in configuration, `DUMMY_HASH` must be updated to track it. A drift test comparing its cost to `.env.example` should be added in a future chore.
  - Token revocation window remains open: tokens are never revoked and a user can hold multiple active tokens until `ACC-U5`.
- **What the next step depends on**: `ACC-U5` (`TokenIssuer::revokeAll` invocation on delete, password change, and role change, plus full authorization matrix tests) depends on `UserService::destroy`, `UserService::update`, and `TokenIssuer::revokeAll`.
- **ADRs added**: None.
