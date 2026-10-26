# ACC-U3 Closeout Note

- **Shipped**: UserResponseData (SnakeCaseMapper, public_id mapped to id, CarbonInterface timestamps), UserPolicy (registered in AppServiceProvider), StoreUserRequest and UpdateUserRequest (with pre-validation email normalisation and Optional partial update mapping), UserController (index, store, show, update, destroy), /api/v1/users routes bound by {user:public_id}, ProtectedUserException -> 403 rendering in bootstrap/app.php, and 18 feature tests in UserControllerTest.
- **Deviations accepted**: None.
- **Debt and follow-ups**:
  - Token revocation (TokenIssuer::revokeAll) on user soft-delete, password change, and role change is intentionally deferred to ACC-U5 per ARCHITECTURE.md §10.
- **What the next step depends on**: ACC-U4 (AuthService, AuthController, LoginRequest, RegisterRequest, LoginData, AuthResultData, UserRepositoryInterface::findByEmail, /api/v1/auth/register and /api/v1/auth/login) builds directly on UserService, UserRepository, UserResponseData, and TokenIssuer::issue.
- **ADRs added**: None (soft-delete ADR 0003-user-soft-deletes-and-schema-cleanup.md was already recorded in ACC-U1).
