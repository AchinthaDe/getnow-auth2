<?php

declare(strict_types=1);

use App\Domain\Accounts\Contracts\TokenIssuer;
use App\Domain\Accounts\Contracts\UserRepositoryInterface;
use App\Domain\Accounts\Data\TokenPairData;
use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithPassportKeys;

uses(RefreshDatabase::class, InteractsWithPassportKeys::class);

beforeEach(function (): void {
    $this->ensurePassportKeys();
    Cache::flush();
});

it('registers a new customer with normalized email and returns 201 with auth result data', function (): void {
    $payload = [
        'name' => 'John Doe',
        'email' => '  JOHN.DOE@EXAMPLE.COM  ',
        'password' => 'secret1234',
    ];

    $response = $this->postJson('/api/v1/auth/register', $payload);

    $response->assertCreated();
    $response->assertJsonPath('data.user.name', 'John Doe');
    $response->assertJsonPath('data.user.email', 'john.doe@example.com');
    $response->assertJsonPath('data.user.role', 'customer');
    $response->assertJsonPath('data.tokens.token_type', 'Bearer');
    $response->assertJsonPath('data.tokens.expires_in', 900);

    expect($response->json('data.tokens.access_token'))->toBeString()
        ->and($response->json('data.tokens.refresh_token'))->toBeString();

    $persisted = User::where('email', 'john.doe@example.com')->first();
    expect($persisted)->not->toBeNull()
        ->and($persisted->role)->toBe(UserRole::Customer)
        ->and(app('hash')->check('secret1234', $persisted->password))->toBeTrue();
});

it('rejects register request with 422 for validation failures duplicate emails and prohibited role leaving database unchanged', function (): void {
    User::factory()->customer()->create(['email' => 'existing@example.com']);
    $deleted = User::factory()->customer()->create(['email' => 'deleted@example.com']);
    $deleted->delete();

    $initialCount = User::withTrashed()->count();

    $this->postJson('/api/v1/auth/register', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Invalid Email',
        'email' => 'not-an-email',
        'password' => 'secret1234',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Short Pass',
        'email' => 'short@example.com',
        'password' => 'short',
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Long Pass',
        'email' => 'long@example.com',
        'password' => str_repeat('a', 73),
    ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Existing User',
        'email' => 'EXISTING@example.com',
        'password' => 'secret1234',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Deleted User',
        'email' => 'deleted@example.com',
        'password' => 'secret1234',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Retailer Role',
        'email' => 'role1@example.com',
        'password' => 'secret1234',
        'role' => 'retailer',
    ])->assertUnprocessable()->assertJsonValidationErrors(['role']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Superadmin Role',
        'email' => 'role2@example.com',
        'password' => 'secret1234',
        'role' => 'superadmin',
    ])->assertUnprocessable()->assertJsonValidationErrors(['role']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Customer Role',
        'email' => 'role3@example.com',
        'password' => 'secret1234',
        'role' => 'customer',
    ])->assertUnprocessable()->assertJsonValidationErrors(['role']);

    expect(User::withTrashed()->count())->toBe($initialCount);
});

it('rolls back customer creation atomically when token issuance fails', function (): void {
    $throwingIssuer = new class implements TokenIssuer
    {
        public function issue(User $user): TokenPairData
        {
            throw new RuntimeException('Token minting failed.');
        }

        public function refresh(string $refreshToken): TokenPairData
        {
            throw new RuntimeException('Unused');
        }

        public function revoke(User $user): void {}

        public function revokeAll(User $user): void {}
    };

    $this->app->instance(TokenIssuer::class, $throwingIssuer);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Fail User',
        'email' => 'fail@example.com',
        'password' => 'secret1234',
    ]);

    $response->assertStatus(500);
    expect(User::where('email', 'fail@example.com')->exists())->toBeFalse();
});

it('authenticates customer retailer and superadmin returning 200 with auth result data and email casing tolerance', function (): void {
    $customer = User::factory()->customer()->create(['email' => 'cust@example.com']);
    $retailer = User::factory()->retailer()->create(['email' => 'ret@example.com']);
    $admin = User::factory()->superadmin()->create(['email' => 'admin@example.com']);

    $resCust = $this->postJson('/api/v1/auth/login', [
        'email' => $customer->email,
        'password' => 'password',
    ])->assertOk();

    expect($resCust->json('data.user.role'))->toBe('customer')
        ->and($resCust->json('data.tokens.token_type'))->toBe('Bearer')
        ->and($resCust->json('data.tokens.expires_in'))->toBe(900)
        ->and($resCust->json('data.tokens.access_token'))->toBeString()
        ->and($resCust->json('data.tokens.refresh_token'))->toBeString();

    $resRet = $this->postJson('/api/v1/auth/login', [
        'email' => strtoupper($retailer->email),
        'password' => 'password',
    ])->assertOk();

    expect($resRet->json('data.user.role'))->toBe('retailer');

    $resAdmin = $this->postJson('/api/v1/auth/login', [
        'email' => '  '.$admin->email.'  ',
        'password' => 'password',
    ])->assertOk();

    expect($resAdmin->json('data.user.role'))->toBe('superadmin');
});

it('returns identical 401 response for wrong password unknown email and soft-deleted user without leaking password', function (): void {
    $active = User::factory()->customer()->create(['email' => 'active@example.com']);
    $deleted = User::factory()->customer()->create(['email' => 'deleted@example.com']);
    $deleted->delete();

    $res1 = $this->postJson('/api/v1/auth/login', [
        'email' => 'unknown@example.com',
        'password' => 'mypassword',
    ]);

    $res2 = $this->postJson('/api/v1/auth/login', [
        'email' => $active->email,
        'password' => 'wrongpassword',
    ]);

    $res3 = $this->postJson('/api/v1/auth/login', [
        'email' => $deleted->email,
        'password' => 'password',
    ]);

    $res1->assertStatus(401);
    $res2->assertStatus(401);
    $res3->assertStatus(401);

    expect($res1->getContent())->toBe($res2->getContent())
        ->and($res2->getContent())->toBe($res3->getContent())
        ->and($res1->json('message'))->toBe('Invalid credentials.')
        ->and($res1->getContent())->not->toContain('mypassword');

    $this->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);

    $resLeak = $this->postJson('/api/v1/auth/login', [
        'email' => 'invalid-email',
        'password' => 'leaked-check-pass',
    ])->assertUnprocessable();

    expect($resLeak->getContent())->not->toContain('leaked-check-pass');
});

it('serializes login and register responses strictly matching architecture specifications without sensitive fields', function (): void {
    $user = User::factory()->customer()->create(['email' => 'spec-user@example.com']);

    $loginRes = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $regRes = $this->postJson('/api/v1/auth/register', [
        'name' => 'Spec Register',
        'email' => 'spec-reg@example.com',
        'password' => 'password123',
    ])->assertCreated();

    foreach ([$loginRes, $regRes] as $res) {
        /** @var array<string, mixed> $userData */
        $userData = $res->json('data.user');
        /** @var array<string, mixed> $tokensData */
        $tokensData = $res->json('data.tokens');

        expect(array_keys($userData))->toBe(['id', 'name', 'email', 'role', 'created_at', 'updated_at'])
            ->and(array_keys($tokensData))->toBe(['access_token', 'refresh_token', 'token_type', 'expires_in'])
            ->and(strlen((string) $userData['id']))->toBe(26)
            ->and($res->getContent())->not->toContain('remember_token')
            ->and(isset($userData['password']))->toBeFalse();
    }
});

it('issues tokens that successfully authenticate user endpoint and rotate via refresh endpoint', function (): void {
    $admin = User::factory()->superadmin()->create(['email' => 'admin-e2e@example.com']);

    $loginRes = $this->postJson('/api/v1/auth/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertOk();

    /** @var string $accessToken */
    $accessToken = $loginRes->json('data.tokens.access_token');
    /** @var string $refreshToken */
    $refreshToken = $loginRes->json('data.tokens.refresh_token');

    $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->getJson('/api/v1/users/'.$admin->public_id)
        ->assertOk()
        ->assertJsonPath('data.id', $admin->public_id);

    $refreshRes = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $refreshToken,
    ])->assertOk();

    expect($refreshRes->json('data.tokens.access_token'))->toBeString()
        ->and($refreshRes->json('data.tokens.refresh_token'))->toBeString()
        ->and($refreshRes->json('data.tokens.refresh_token'))->not->toBe($refreshToken);
});

it('throttles excessive login and register attempts returning 429', function (): void {
    Cache::flush();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'throttle@example.com',
            'password' => 'wrong',
        ])->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'throttle@example.com',
        'password' => 'wrong',
    ])->assertStatus(429);

    Cache::flush();

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/auth/register', [])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/register', [])->assertStatus(429);

    Cache::flush();
});

it('finds user by exact email excluding soft-deleted records', function (): void {
    $active = User::factory()->customer()->create(['email' => 'target@example.com']);
    $deleted = User::factory()->customer()->create(['email' => 'deleted-repo@example.com']);
    $deleted->delete();

    $repo = app(UserRepositoryInterface::class);

    expect($repo->findByEmail('target@example.com')?->id)->toBe($active->id)
        ->and($repo->findByEmail('TARGET@EXAMPLE.COM'))->toBeNull()
        ->and($repo->findByEmail('deleted-repo@example.com'))->toBeNull()
        ->and($repo->findByEmail('nonexistent@example.com'))->toBeNull();
});

it('registers auth routes without collision against token stream routes', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => [
            'uri' => $route->uri(),
            'methods' => $route->methods(),
            'action' => $route->getActionName(),
        ]);

    $registerRoute = $routes->firstWhere('uri', 'api/v1/auth/register');
    $loginRoute = $routes->firstWhere('uri', 'api/v1/auth/login');
    $refreshRoute = $routes->firstWhere('uri', 'api/v1/auth/refresh');
    $logoutRoute = $routes->firstWhere('uri', 'api/v1/auth/logout');

    expect($registerRoute)->not->toBeNull()
        ->and($registerRoute['action'])->toContain('AuthController@register')
        ->and($loginRoute)->not->toBeNull()
        ->and($loginRoute['action'])->toContain('AuthController@login')
        ->and($refreshRoute)->not->toBeNull()
        ->and($refreshRoute['action'])->toContain('TokenController@refresh')
        ->and($logoutRoute)->not->toBeNull()
        ->and($logoutRoute['action'])->toContain('TokenController@logout');
});
