<?php

declare(strict_types=1);

use App\Domain\Accounts\Contracts\TokenIssuer;
use App\Domain\Accounts\Data\TokenPairData;
use App\Domain\Accounts\Data\UserData;
use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Services\UserService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\Support\InteractsWithPassportKeys;
use Tests\TestCase;

uses(InteractsWithPassportKeys::class);

beforeEach(function (): void {
    $this->ensurePassportKeys();
    Cache::flush();
});

function assertUserTokenWorks(TestCase $test, string $accessToken, UserRole $role, ?string $probePublicId = null): void
{
    Auth::forgetGuards();

    $probeUrl = $probePublicId !== null
        ? "/api/v1/users/{$probePublicId}"
        : '/api/v1/users';

    $response = $test->withHeader('Authorization', 'Bearer '.$accessToken)->getJson($probeUrl);

    if ($role === UserRole::Superadmin) {
        $response->assertOk();
    } else {
        $response->assertForbidden();
    }
}

function assertUserTokenRevoked(TestCase $test, string $accessToken, string $refreshToken): void
{
    Auth::forgetGuards();

    $test->withHeader('Authorization', 'Bearer '.$accessToken)
        ->getJson('/api/v1/users')
        ->assertUnauthorized();

    $test->postJson('/api/v1/auth/refresh', ['refresh_token' => $refreshToken])
        ->assertUnauthorized();
}

it('revokes access and refresh tokens when user is soft deleted by superadmin while leaving bystander tokens intact', function (): void {
    $superadmin = User::factory()->superadmin()->create();
    $target = User::factory()->customer()->create();
    $bystander = User::factory()->customer()->create();

    $issuer = app(TokenIssuer::class);
    $targetTokens = $issuer->issue($target);
    $bystanderTokens = $issuer->issue($bystander);
    $superadminTokens = $issuer->issue($superadmin);

    Auth::forgetGuards();
    $response = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->deleteJson("/api/v1/users/{$target->public_id}");

    $response->assertNoContent();

    assertUserTokenRevoked($this, $targetTokens->accessToken, $targetTokens->refreshToken);
    assertUserTokenWorks($this, $bystanderTokens->accessToken, UserRole::Customer, $bystander->public_id);

    $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $bystanderTokens->refreshToken,
    ]);
    $refreshResponse->assertOk();
});

it('revokes tokens on password update and allows login only with new password while leaving superadmin token intact', function (): void {
    $superadmin = User::factory()->superadmin()->create();
    $target = User::factory()->customer()->create(['password' => 'initial-pass']);

    $issuer = app(TokenIssuer::class);
    $targetTokens = $issuer->issue($target);
    $superadminTokens = $issuer->issue($superadmin);

    Auth::forgetGuards();
    $response = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson("/api/v1/users/{$target->public_id}", [
            'password' => 'new-secret-1234',
        ]);

    $response->assertOk();

    assertUserTokenWorks($this, $superadminTokens->accessToken, UserRole::Superadmin, $target->public_id);
    assertUserTokenRevoked($this, $targetTokens->accessToken, $targetTokens->refreshToken);

    $oldLogin = $this->postJson('/api/v1/auth/login', [
        'email' => $target->email,
        'password' => 'initial-pass',
    ]);
    $oldLogin->assertUnauthorized();

    $newLogin = $this->postJson('/api/v1/auth/login', [
        'email' => $target->email,
        'password' => 'new-secret-1234',
    ]);
    $newLogin->assertOk();
});

it('revokes caller token upon superadmin self password update over http', function (): void {
    $superadmin = User::factory()->superadmin()->create(['password' => 'admin-pass']);

    $issuer = app(TokenIssuer::class);
    $superadminTokens = $issuer->issue($superadmin);

    Auth::forgetGuards();
    $response = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson("/api/v1/users/{$superadmin->public_id}", [
            'password' => 'new-admin-pass',
        ]);

    $response->assertOk();

    assertUserTokenRevoked($this, $superadminTokens->accessToken, $superadminTokens->refreshToken);
});

it('revokes customer access and refresh tokens upon domain service password update', function (): void {
    $customer = User::factory()->customer()->create(['password' => 'customer-pass']);

    $issuer = app(TokenIssuer::class);
    $customerTokens = $issuer->issue($customer);

    app(UserService::class)->update($customer, new UserData(password: 'brand-new-pass'));

    assertUserTokenRevoked($this, $customerTokens->accessToken, $customerTokens->refreshToken);

    $newLogin = $this->postJson('/api/v1/auth/login', [
        'email' => $customer->email,
        'password' => 'brand-new-pass',
    ]);
    $newLogin->assertOk();
});

it('revokes tokens on role change between customer and retailer in both directions', function (): void {
    $superadmin = User::factory()->superadmin()->create();
    $bystander = User::factory()->customer()->create();
    $target1 = User::factory()->customer()->create();
    $target2 = User::factory()->retailer()->create();

    $issuer = app(TokenIssuer::class);
    $superadminTokens = $issuer->issue($superadmin);
    $bystanderTokens = $issuer->issue($bystander);
    $target1Tokens = $issuer->issue($target1);
    $target2Tokens = $issuer->issue($target2);

    Auth::forgetGuards();
    $response1 = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson("/api/v1/users/{$target1->public_id}", [
            'role' => UserRole::Retailer->value,
        ]);
    $response1->assertOk();

    assertUserTokenRevoked($this, $target1Tokens->accessToken, $target1Tokens->refreshToken);
    assertUserTokenWorks($this, $superadminTokens->accessToken, UserRole::Superadmin, $target1->public_id);
    assertUserTokenWorks($this, $bystanderTokens->accessToken, UserRole::Customer, $bystander->public_id);

    Auth::forgetGuards();
    $response2 = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson("/api/v1/users/{$target2->public_id}", [
            'role' => UserRole::Customer->value,
        ]);
    $response2->assertOk();

    assertUserTokenRevoked($this, $target2Tokens->accessToken, $target2Tokens->refreshToken);
    assertUserTokenWorks($this, $superadminTokens->accessToken, UserRole::Superadmin, $target2->public_id);
});

it('preserves active tokens across name only email only and identical role updates with isolated token pairs', function (): void {
    $superadmin = User::factory()->superadmin()->create();
    $target = User::factory()->customer()->create();

    $issuer = app(TokenIssuer::class);
    $superadminTokens = $issuer->issue($superadmin);

    // Sub-case A: name only
    $pairA = $issuer->issue($target);
    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson("/api/v1/users/{$target->public_id}", [
            'name' => 'Brand New Name',
        ])
        ->assertOk();

    assertUserTokenWorks($this, $pairA->accessToken, UserRole::Customer, $target->public_id);
    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $pairA->refreshToken,
    ])->assertOk();

    // Sub-case B: email only
    $pairB = $issuer->issue($target);
    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson("/api/v1/users/{$target->public_id}", [
            'email' => 'newemail@example.com',
        ])
        ->assertOk();

    assertUserTokenWorks($this, $pairB->accessToken, UserRole::Customer, $target->public_id);
    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $pairB->refreshToken,
    ])->assertOk();

    // Sub-case C: identical role
    $pairC = $issuer->issue($target);
    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson("/api/v1/users/{$target->public_id}", [
            'role' => UserRole::Customer->value,
        ])
        ->assertOk();

    assertUserTokenWorks($this, $pairC->accessToken, UserRole::Customer, $target->public_id);
    $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $pairC->refreshToken,
    ])->assertOk();
});

it('preserves tokens and leaves database untouched when operations are rejected with zero revoke calls', function (): void {
    $superadmin = User::factory()->superadmin()->create();
    $customer = User::factory()->customer()->create();
    $retailer = User::factory()->retailer()->create();
    $otherCustomer = User::factory()->customer()->create();

    $initialOtherCustomerName = $otherCustomer->name;
    $initialRetailerName = $retailer->name;

    $issuer = app(TokenIssuer::class);
    $superadminTokens = $issuer->issue($superadmin);
    $customerTokens = $issuer->issue($customer);
    $retailerTokens = $issuer->issue($retailer);

    $spy = new class implements TokenIssuer
    {
        public int $revokeAllCalls = 0;

        public function issue(User $user): TokenPairData
        {
            throw new RuntimeException('Unused');
        }

        public function refresh(string $refreshToken): TokenPairData
        {
            throw new RuntimeException('Unused');
        }

        public function revoke(User $user): void
        {
            throw new RuntimeException('Unexpected revoke() call');
        }

        public function revokeAll(User $user): void
        {
            $this->revokeAllCalls++;
        }
    };
    $this->app->instance(TokenIssuer::class, $spy);

    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->deleteJson("/api/v1/users/{$superadmin->public_id}")
        ->assertForbidden();

    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson("/api/v1/users/{$customer->public_id}", [
            'role' => UserRole::Superadmin->value,
        ])
        ->assertForbidden();

    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson("/api/v1/users/{$superadmin->public_id}", [
            'role' => UserRole::Customer->value,
        ])
        ->assertForbidden();

    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$customerTokens->accessToken)
        ->patchJson("/api/v1/users/{$otherCustomer->public_id}", [
            'name' => 'Hacked',
        ])
        ->assertForbidden();

    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$customerTokens->accessToken)
        ->deleteJson("/api/v1/users/{$otherCustomer->public_id}")
        ->assertForbidden();

    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$retailerTokens->accessToken)
        ->patchJson("/api/v1/users/{$otherCustomer->public_id}", [
            'name' => 'Hacked',
        ])
        ->assertForbidden();

    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$retailerTokens->accessToken)
        ->deleteJson("/api/v1/users/{$otherCustomer->public_id}")
        ->assertForbidden();

    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson('/api/v1/users/01JNONEXISTENT000000000000', [
            'name' => 'New',
        ])
        ->assertNotFound();

    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->deleteJson('/api/v1/users/01JNONEXISTENT000000000000')
        ->assertNotFound();

    Auth::forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson("/api/v1/users/{$customer->public_id}", [
            'email' => 'invalid-email',
        ])
        ->assertUnprocessable();

    expect($spy->revokeAllCalls)->toBe(0);

    expect($superadmin->fresh()->role)->toBe(UserRole::Superadmin);
    expect($customer->fresh()->role)->toBe(UserRole::Customer);
    expect($customer->fresh()->deleted_at)->toBeNull();
    expect($otherCustomer->fresh()->name)->toBe($initialOtherCustomerName);
    expect($otherCustomer->fresh()->deleted_at)->toBeNull();
    expect($retailer->fresh()->name)->toBe($initialRetailerName);
    expect($retailer->fresh()->deleted_at)->toBeNull();

    assertUserTokenWorks($this, $superadminTokens->accessToken, UserRole::Superadmin, $superadmin->public_id);
    assertUserTokenWorks($this, $customerTokens->accessToken, UserRole::Customer, $customer->public_id);
    assertUserTokenWorks($this, $retailerTokens->accessToken, UserRole::Retailer, $retailer->public_id);
});

it('rolls back database modifications atomically when revoke all throws exception over http and service calls', function (): void {
    $superadmin = User::factory()->superadmin()->create();
    $user1 = User::factory()->customer()->create(['password' => 'secret-user-1']);
    $originalHash1 = $user1->password;
    $user2 = User::factory()->customer()->create();
    $user3 = User::factory()->customer()->create();

    $superadminTokens = app(TokenIssuer::class)->issue($superadmin);

    $throwingIssuer = new class implements TokenIssuer
    {
        public function issue(User $user): TokenPairData
        {
            throw new RuntimeException('Unused');
        }

        public function refresh(string $refreshToken): TokenPairData
        {
            throw new RuntimeException('Unused');
        }

        public function revoke(User $user): void
        {
            throw new RuntimeException('Unexpected revoke() call');
        }

        public function revokeAll(User $user): void
        {
            throw new RuntimeException('Revocation failed.');
        }
    };
    $this->app->instance(TokenIssuer::class, $throwingIssuer);

    Auth::forgetGuards();
    $response = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson("/api/v1/users/{$user1->public_id}", [
            'password' => 'new-secret-123',
        ]);
    $response->assertInternalServerError();
    expect($user1->fresh()->password)->toBe($originalHash1);

    expect(fn () => app(UserService::class)->update($user2, new UserData(role: UserRole::Retailer)))
        ->toThrow(RuntimeException::class, 'Revocation failed.');
    expect($user2->fresh()->role)->toBe(UserRole::Customer);

    expect(fn () => app(UserService::class)->destroy($user3))
        ->toThrow(RuntimeException::class, 'Revocation failed.');
    expect(User::withTrashed()->where('id', $user3->id)->first()->deleted_at)->toBeNull();
});

it('invokes revoke all exactly once on qualifying operations and zero times on non qualifying operations with isolated spies', function (): void {
    $createSpy = function (): object {
        return new class implements TokenIssuer
        {
            public int $revokeAllCalls = 0;

            public function issue(User $user): TokenPairData
            {
                throw new RuntimeException('Unused');
            }

            public function refresh(string $refreshToken): TokenPairData
            {
                throw new RuntimeException('Unused');
            }

            public function revoke(User $user): void
            {
                throw new RuntimeException('Unexpected revoke() call');
            }

            public function revokeAll(User $user): void
            {
                $this->revokeAllCalls++;
            }
        };
    };

    // Non-qualifying update
    $spyA = $createSpy();
    $this->app->instance(TokenIssuer::class, $spyA);
    $userA = User::factory()->customer()->create();
    app(UserService::class)->update($userA, new UserData(name: 'Updated'));
    expect($spyA->revokeAllCalls)->toBe(0);

    // Qualifying update with password and role
    $spyB = $createSpy();
    $this->app->instance(TokenIssuer::class, $spyB);
    $userB = User::factory()->customer()->create();
    app(UserService::class)->update($userB, new UserData(password: 'newpass123', role: UserRole::Retailer));
    expect($spyB->revokeAllCalls)->toBe(1);

    // Qualifying destroy
    $spyC = $createSpy();
    $this->app->instance(TokenIssuer::class, $spyC);
    $userC = User::factory()->customer()->create();
    app(UserService::class)->destroy($userC);
    expect($spyC->revokeAllCalls)->toBe(1);
});

it('updates user password successfully when target user holds zero active tokens', function (): void {
    $superadmin = User::factory()->superadmin()->create();
    $target = User::factory()->customer()->create();

    $superadminTokens = app(TokenIssuer::class)->issue($superadmin);

    Auth::forgetGuards();
    $response = $this->withHeader('Authorization', 'Bearer '.$superadminTokens->accessToken)
        ->patchJson("/api/v1/users/{$target->public_id}", [
            'password' => 'new-password-zero-tokens',
        ]);

    $response->assertOk();
    expect(Hash::check('new-password-zero-tokens', $target->fresh()->password))->toBeTrue();
});
