# 1. Passport Token Issuance and Refresh Rotation

Date: 2026-10-20
Status: Accepted
Owner: Tokens Track (Achintha)

## Context

The GetNow backend module requires a token-based authentication system backed by Laravel Passport v13.10 and PostgreSQL. The system must meet strict domain boundary and security requirements:

1. **Separation of Concerns and Track Boundaries (`OWN4`)**: `AuthService` (Users track) handles credential verification and role assignment. Once credentials are confirmed valid, it delegates token generation to `TokenIssuer::issue(User $user)`. The Tokens track must never inspect passwords, verify credentials, check roles, or query the `users` table.
2. **Passport Grant Compatibility (`OQ1`)**:
   - Passport's standard `PasswordGrant` requires passing plaintext user credentials (`username` and `password`) to League's OAuth2 server, which would break `OWN4` by forcing credential handling into the token domain.
   - Passport's `PersonalAccessGrant` (`$user->createToken()`) issues personal access tokens without refresh tokens.
   - Passport's `RefreshTokenGrant` is designed to rotate refresh tokens, but expects `client_id` and client secrets in the request body, whereas the GetNow first-party clients (SPA and mobile app) only provide the refresh token.
3. **Delivery Mechanism (`OQ2`)**: Decision needed between delivering refresh tokens in the JSON response body or inside an `httpOnly` cookie.
4. **Token Lifetimes (`OQ3`)**: Access tokens must be short-lived, while refresh tokens must persist across developer-defined windows without being hardcoded.
5. **Authorization Enforcement (`OQ4`)**: Decision needed whether authorization should be encoded as OAuth2 token scopes or evaluated server-side through Laravel policies.

## Decisions

### 1. Direct Minting & Rotation via Passport Entities (`OQ1`)
`PassportTokenIssuer` implements `TokenIssuer` directly using Passport v13 and League OAuth2 Server entities:
- **Issuance**: `issue(User $user)` resolves the internal first-party client (`GetNow First-Party Auth Client`) and calls a private helper `mintTokenPair($user->getAuthIdentifier(), $client)`. This helper builds:
  - An 80-character signed OAuth2 JWT access token using `Laravel\Passport\Bridge\AccessToken` and the RSA private key, persisted in `oauth_access_tokens`.
  - An 80-character refresh token using `Laravel\Passport\Bridge\RefreshToken`, persisted in `oauth_refresh_tokens`.
  - An encrypted refresh token payload string matching League's `BearerTokenResponse` format (`client_id`, `refresh_token_id`, `access_token_id`, `scopes`, `user_id`, `expire_time`), encrypted using `Defuse\Crypto\Crypto` with Passport's token encryption key (`config('app.key')`).
- **Rotation**: `refresh(string $refreshToken)` decrypts the refresh token payload without requiring client secrets. Inside a PostgreSQL transaction with pessimistic locking (`->lockForUpdate()`), it verifies that:
  - The encrypted payload has not expired (`expire_time >= time()`).
  - The database records in `oauth_refresh_tokens` and `oauth_access_tokens` exist, have not been revoked, and their `expires_at` timestamps are not in the past.
  - The user ID in the prior access token matches the decrypted payload (`$priorAccessToken->user_id === $payload['user_id']`).
  - It atomically revokes both the consumed refresh token and its prior access token (`TOK4`), and calls `mintTokenPair($priorAccessToken->user_id, $client)`. Zero queries are made to the `users` table (`OWN4`). Any replay, reuse, expiration, or revocation immediately throws `Illuminate\Auth\AuthenticationException` (HTTP 401).
- **Revocation**:
  - `revoke(User $user)` revokes the current request's access token and its paired refresh token in `oauth_refresh_tokens` (`TOK4`).
  - `revokeAll(User $user)` revokes all active access tokens and all associated refresh tokens for the given user ID across all sessions.

### 2. JSON Body Delivery (`OQ2`)
Refresh and access tokens are returned in the JSON response body under `{ "data": { "tokens": { ... } } }`. This enables seamless cross-platform consumption by both single-page web applications and native mobile clients without cookie domain restrictions.

### 3. Config-Driven Lifetimes (`OQ3`)
Lifetimes are loaded exclusively from `config/passport.php`:
- Access token: `config('passport.token_lifetimes.access_token_ttl_seconds', 900)` (15 minutes).
- Refresh token: `config('passport.token_lifetimes.refresh_token_ttl_days', 14)` (14 days).
Configured globally in `TokenServiceProvider` via `Passport::tokensExpireIn()` and `Passport::refreshTokensExpireIn()`.

### 4. Policy-Based Authorization (`OQ4`)
Tokens carry empty default scopes. Authorization is enforced entirely on the server by `UserPolicy` inspecting `User::$role`. This eliminates scope-role synchronization issues and ensures role changes take effect immediately on token refresh or revocation.

## Consequences

- **Positive**:
  - Zero coupling between token issuance and user credentials/roles (`OWN4`).
  - Strict one-time refresh token rotation enforced at the database level (`TOK4`).
  - Fast, fully deterministic feature tests against real Passport in PostgreSQL without requiring committed RSA key files (`SEC1`).
  - Standard Passport `auth:api` guard validates the issued JWTs with zero modifications.
- **Negative / Trade-offs**:
  - The token issuer requires access to Passport's encryption key and RSA private key to mint entities directly rather than proxying through HTTP routes.
