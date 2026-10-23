# ACC-U2 Closeout Note

- **Shipped**: UserData, UserFilterData, ProtectedUserException, UserRepositoryInterface/UserRepository, UserService, and AccountsServiceProvider (registered in bootstrap/providers.php), plus 34 feature tests.
- **Deviations accepted**: None against plan v3. Brief amendments A1 to A5 changed method names to LAY5 parity, moved the binding to AccountsServiceProvider, and moved UserResponseData to ACC-U3.
- **Debt**:
  - Token revocation is deferred to ACC-U5.
  - A duplicate email surfaces as UniqueConstraintViolationException until the ACC-U3 form requests.
  - ULID casing must be settled in ACC-U3.
  - Pre-existing files lacking strict_types are outside this step: AppServiceProvider, Controller.php, bootstrap/app.php, routes/console.php, tests/Pest.php, tests/Unit/ExampleTest.php.
- **Next step depends on**: UserService, UserData, UserFilterData, ProtectedUserException, and the repository binding.
- **ADRs added**: None.
