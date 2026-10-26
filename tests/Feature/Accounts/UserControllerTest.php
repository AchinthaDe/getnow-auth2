<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\UserResponseData;
use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('serializes user model to user response data without internal id or password', function (): void {
    $user = User::factory()->create();

    $data = UserResponseData::fromModel($user)->toArray();

    expect($data)->toHaveKeys(['id', 'name', 'email', 'role', 'created_at', 'updated_at'])
        ->and($data['id'])->toBe($user->public_id)
        ->and($data['id'])->not->toBe((string) $user->id)
        ->and($data)->not->toHaveKey('password')
        ->and($data)->not->toHaveKey('remember_token')
        ->and($data)->not->toHaveKey('deleted_at');
});

it('returns 401 unauthorized on unauthenticated requests across all user endpoints', function (): void {
    $target = User::factory()->create();

    $this->get('/api/v1/users')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    $this->post('/api/v1/users', [])->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    $this->get("/api/v1/users/{$target->public_id}")->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    $this->patch("/api/v1/users/{$target->public_id}", [])->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
    $this->delete("/api/v1/users/{$target->public_id}")->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
});

it('enforces authorization matrix forbidding customers and retailers from admin actions', function (): void {
    $retailer = User::factory()->retailer()->create();
    $customer = User::factory()->customer()->create();
    $other = User::factory()->customer()->create();

    // Customer forbidden from viewAny, create, viewing another, updating another, deleting another
    $this->actingAs($customer, 'api')->getJson('/api/v1/users')->assertForbidden();
    $this->actingAs($customer, 'api')->postJson('/api/v1/users', [
        'name' => 'Forbidden User',
        'email' => 'forbidden@test.com',
        'password' => 'secret123',
        'role' => 'customer',
    ])->assertForbidden();
    $this->actingAs($customer, 'api')->getJson("/api/v1/users/{$other->public_id}")->assertForbidden();
    $this->actingAs($customer, 'api')->patchJson("/api/v1/users/{$other->public_id}", [
        'name' => 'Hacked Name',
    ])->assertForbidden();
    $this->actingAs($customer, 'api')->deleteJson("/api/v1/users/{$other->public_id}")->assertForbidden();

    // Retailer forbidden from viewAny, create, viewing another, updating another, deleting another
    $this->actingAs($retailer, 'api')->getJson('/api/v1/users')->assertForbidden();
    $this->actingAs($retailer, 'api')->postJson('/api/v1/users', [
        'name' => 'Forbidden Retailer',
        'email' => 'retailer.forbidden@test.com',
        'password' => 'secret123',
        'role' => 'retailer',
    ])->assertForbidden();
    $this->actingAs($retailer, 'api')->getJson("/api/v1/users/{$other->public_id}")->assertForbidden();
    $this->actingAs($retailer, 'api')->patchJson("/api/v1/users/{$other->public_id}", [
        'name' => 'Hacked Retailer',
    ])->assertForbidden();
    $this->actingAs($retailer, 'api')->deleteJson("/api/v1/users/{$other->public_id}")->assertForbidden();
});

it('allows customers and retailers to view and update their own record without role changes', function (): void {
    $customer = User::factory()->customer()->create();
    $retailer = User::factory()->retailer()->create();

    $this->actingAs($customer, 'api')
        ->getJson("/api/v1/users/{$customer->public_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $customer->public_id);

    $this->actingAs($customer, 'api')
        ->patchJson("/api/v1/users/{$customer->public_id}", [
            'name' => 'Updated Customer Self',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Customer Self');

    expect($customer->fresh()->name)->toBe('Updated Customer Self');

    $this->actingAs($retailer, 'api')
        ->getJson("/api/v1/users/{$retailer->public_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $retailer->public_id);

    $this->actingAs($retailer, 'api')
        ->patchJson("/api/v1/users/{$retailer->public_id}", [
            'name' => 'Updated Retailer Self',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Retailer Self');

    expect($retailer->fresh()->name)->toBe('Updated Retailer Self');
});

it('forbids customers and retailers from changing role on update', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer, 'api')
        ->patchJson("/api/v1/users/{$customer->public_id}", [
            'role' => 'retailer',
        ])
        ->assertForbidden();

    expect($customer->fresh()->role)->toBe(UserRole::Customer);
});

it('returns paginated users list ordered by created_at desc with filtering for superadmin', function (): void {
    $admin = User::factory()->superadmin()->create(['created_at' => now()->subMinutes(10)]);
    User::factory()->create(['name' => 'Alpha User', 'email' => 'alpha@test.com', 'created_at' => now()->subMinutes(5)]);
    User::factory()->create(['name' => 'Beta User', 'email' => 'beta@test.com', 'created_at' => now()->subMinutes(4)]);
    User::factory()->create(['name' => 'Gamma User', 'email' => 'gamma@test.com', 'created_at' => now()->subMinutes(3)]);

    $response = $this->actingAs($admin, 'api')->getJson('/api/v1/users?search=alpha&per_page=10');

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'current_page',
            'per_page',
            'total',
        ]);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('Alpha User');
});

it('returns 422 on invalid query parameters on index', function (): void {
    $admin = User::factory()->superadmin()->create();

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/users?role=invalid_role&include_trashed=not_bool&per_page=999')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['role', 'include_trashed', 'per_page']);
});

it('creates a customer or retailer user via post users endpoint', function (): void {
    $admin = User::factory()->superadmin()->create();

    $response = $this->actingAs($admin, 'api')->postJson('/api/v1/users', [
        'name' => 'Alice Retailer',
        'email' => 'alice@example.com',
        'password' => 'secret1234',
        'role' => 'retailer',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Alice Retailer')
        ->assertJsonPath('data.email', 'alice@example.com')
        ->assertJsonPath('data.role', 'retailer');

    /** @var string $publicId */
    $publicId = $response->json('data.id');
    expect($publicId)->toBeString()
        ->and(strlen($publicId))->toBe(26);

    $created = User::where('public_id', $publicId)->firstOrFail();
    expect(Hash::check('secret1234', $created->password))->toBeTrue();
});

it('validates store user input rejecting missing fields, malformed emails, and invalid roles', function (): void {
    $admin = User::factory()->superadmin()->create();

    $this->actingAs($admin, 'api')->postJson('/api/v1/users', [
        'name' => '',
        'email' => 'bad-email',
        'password' => 'short',
        'role' => 'unknown_role',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
});

it('returns 422 when storing user with duplicate email including case and whitespace variants', function (): void {
    User::factory()->create(['email' => 'existing@example.com']);
    $admin = User::factory()->superadmin()->create();

    $this->actingAs($admin, 'api')->postJson('/api/v1/users', [
        'name' => 'Duplicate Attempt',
        'email' => '  EXISTING@EXAMPLE.COM  ',
        'password' => 'password123',
        'role' => 'customer',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns 422 when storing user with email belonging to soft-deleted user', function (): void {
    $deleted = User::factory()->create(['email' => 'deleted@example.com']);
    $deleted->delete();

    $admin = User::factory()->superadmin()->create();

    $this->actingAs($admin, 'api')->postJson('/api/v1/users', [
        'name' => 'Reborn Attempt',
        'email' => 'deleted@example.com',
        'password' => 'password123',
        'role' => 'customer',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns user by public_id and returns 404 on integer id, unknown ulid, or soft-deleted user', function (): void {
    $admin = User::factory()->superadmin()->create();
    $target = User::factory()->create();
    $deleted = User::factory()->create();
    $deleted->delete();

    $this->actingAs($admin, 'api')
        ->getJson("/api/v1/users/{$target->public_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->public_id);

    $this->actingAs($admin, 'api')
        ->getJson("/api/v1/users/{$target->id}")
        ->assertNotFound();

    $this->actingAs($admin, 'api')
        ->getJson('/api/v1/users/01JNONEXISTENTULID000000000')
        ->assertNotFound();

    $this->actingAs($admin, 'api')
        ->getJson("/api/v1/users/{$deleted->public_id}")
        ->assertNotFound();
});

it('partially updates user fields preserving untouched attributes', function (): void {
    $admin = User::factory()->superadmin()->create();
    $target = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    $response = $this->actingAs($admin, 'api')->patchJson("/api/v1/users/{$target->public_id}", [
        'name' => 'Modified Name',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Modified Name')
        ->assertJsonPath('data.email', 'original@example.com');

    expect($target->fresh()->email)->toBe('original@example.com');
});

it('allows updating user while retaining own email including casing variants', function (): void {
    $admin = User::factory()->superadmin()->create();
    $target = User::factory()->create([
        'email' => 'myemail@example.com',
    ]);

    $this->actingAs($admin, 'api')->patchJson("/api/v1/users/{$target->public_id}", [
        'name' => 'Updated With Own Email',
        'email' => '  MYEMAIL@EXAMPLE.COM  ',
    ])
        ->assertOk()
        ->assertJsonPath('data.email', 'myemail@example.com');
});

it('returns 422 when updating email to an existing email of another user', function (): void {
    $admin = User::factory()->superadmin()->create();
    User::factory()->create(['email' => 'other@example.com']);
    $target = User::factory()->create(['email' => 'target@example.com']);

    $this->actingAs($admin, 'api')->patchJson("/api/v1/users/{$target->public_id}", [
        'email' => 'other@example.com',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('soft deletes user and returns 204 no content', function (): void {
    $admin = User::factory()->superadmin()->create();
    $target = User::factory()->create();

    $response = $this->actingAs($admin, 'api')->deleteJson("/api/v1/users/{$target->public_id}");

    $response->assertNoContent();
    expect($response->getContent())->toBe('')
        ->and($target->fresh()->trashed())->toBeTrue();

    $this->actingAs($admin, 'api')
        ->getJson("/api/v1/users/{$target->public_id}")
        ->assertNotFound();
});

it('rejects superadmin creation, promotion, demotion, and deletion with 403 forbidden', function (): void {
    $admin = User::factory()->superadmin()->create();
    $target = User::factory()->create();

    // 1. Cannot create superadmin
    $this->actingAs($admin, 'api')->postJson('/api/v1/users', [
        'name' => 'Superadmin Attempt',
        'email' => 'super@example.com',
        'password' => 'password123',
        'role' => 'superadmin',
    ])
        ->assertForbidden()
        ->assertJson(['message' => 'Cannot create a user with the superadmin role.']);

    // 2. Cannot promote to superadmin
    $this->actingAs($admin, 'api')->patchJson("/api/v1/users/{$target->public_id}", [
        'role' => 'superadmin',
    ])
        ->assertForbidden()
        ->assertJson(['message' => 'Cannot promote a user to the superadmin role.']);

    // 3. Cannot change superadmin role
    $this->actingAs($admin, 'api')->patchJson("/api/v1/users/{$admin->public_id}", [
        'role' => 'customer',
    ])
        ->assertForbidden()
        ->assertJson(['message' => 'Cannot change the role of a user with the superadmin role.']);

    // 4. Cannot delete superadmin
    $this->actingAs($admin, 'api')->deleteJson("/api/v1/users/{$admin->public_id}")
        ->assertForbidden()
        ->assertJson(['message' => 'Cannot delete a user with the superadmin role.']);
});

it('allows updating superadmin name and email without role change', function (): void {
    $admin = User::factory()->superadmin()->create();

    $this->actingAs($admin, 'api')->patchJson("/api/v1/users/{$admin->public_id}", [
        'name' => 'Chief Admin',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Chief Admin');

    expect($admin->fresh()->name)->toBe('Chief Admin')
        ->and($admin->fresh()->role)->toBe(UserRole::Superadmin);
});
