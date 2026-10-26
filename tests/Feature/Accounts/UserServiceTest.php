<?php

declare(strict_types=1);

use App\Domain\Accounts\Data\UserData;
use App\Domain\Accounts\Data\UserFilterData;
use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Exceptions\ProtectedUserException;
use App\Domain\Accounts\Services\UserService;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

it('resolves user service from container via auto wiring', function (): void {
    $service = app(UserService::class);

    expect($service)->toBeInstanceOf(UserService::class);
});

it('initializes user filter data with default filter values', function (): void {
    $filter = new UserFilterData;

    expect($filter->search)->toBeNull()
        ->and($filter->role)->toBeNull()
        ->and($filter->includeTrashed)->toBeFalse()
        ->and($filter->perPage)->toBe(15);
});

it('preserves optional fields during create then update lifecycle', function (): void {
    $service = app(UserService::class);

    $created = $service->store(new UserData(
        name: 'Original',
        email: 'initial@getnow.test',
        password: 'plain-pass',
        role: UserRole::Customer,
    ));

    $updated = $service->update($created, new UserData(
        name: 'Modified',
    ));

    expect($updated->name)->toBe('Modified')
        ->and($updated->email)->toBe('initial@getnow.test')
        ->and($updated->role)->toBe(UserRole::Customer)
        ->and(Hash::check('plain-pass', $updated->fresh()->password))->toBeTrue();
});

it('throws invalid argument exception and writes no row when required fields are missing on store', function (): void {
    $countBefore = User::count();
    $service = app(UserService::class);

    try {
        $service->store(new UserData(
            name: 'Incomplete User',
            email: 'incomplete@getnow.test',
        ));
        $this->fail('Expected InvalidArgumentException was not thrown.');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->toContain('password')
            ->and($e->getMessage())->toContain('role');
    }

    expect(User::count())->toBe($countBefore);
});

it('stores user with lowercased trimmed email, ulid public id, and hashed password', function (): void {
    $service = app(UserService::class);

    $user = $service->store(new UserData(
        name: 'Normal User',
        email: '  NEW.USER@EXAMPLE.COM  ',
        password: 'plain-password',
        role: UserRole::Customer,
    ));

    expect($user->email)->toBe('new.user@example.com')
        ->and($user->public_id)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and(Hash::check('plain-password', $user->password))->toBeTrue();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'new.user@example.com',
        'public_id' => $user->public_id,
    ]);
});

it('throws protected user exception and writes no row when attempting to store superadmin', function (): void {
    $countBefore = User::count();
    $service = app(UserService::class);

    expect(fn () => $service->store(new UserData(
        name: 'Admin Attempt',
        email: 'admin.attempt@getnow.test',
        password: 'secret-password',
        role: UserRole::Superadmin,
    )))->toThrow(ProtectedUserException::class, 'Cannot create a user with the superadmin role.');

    expect(User::count())->toBe($countBefore);
});

it('updates only name without altering email, role, or password hash', function (): void {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'preserve@getnow.test',
        'password' => 'secret-pw',
        'role' => UserRole::Customer,
    ]);

    $originalHash = $user->password;
    $service = app(UserService::class);

    $updated = $service->update($user, new UserData(
        name: 'New Name',
    ));

    expect($updated->name)->toBe('New Name')
        ->and($updated->email)->toBe('preserve@getnow.test')
        ->and($updated->role)->toBe(UserRole::Customer)
        ->and($updated->password)->toBe($originalHash);
});

it('updates password hash when password is provided on update', function (): void {
    $user = User::factory()->create([
        'password' => 'initial-pass',
    ]);

    $initialHash = $user->password;
    $service = app(UserService::class);

    $updated = $service->update($user, new UserData(
        password: 'brand-new-pass',
    ));

    expect($updated->password)->not->toBe($initialHash)
        ->and(Hash::check('brand-new-pass', $updated->fresh()->password))->toBeTrue();
});

it('lowercases and trims email when email is provided on update', function (): void {
    $user = User::factory()->create();
    $service = app(UserService::class);

    $updated = $service->update($user, new UserData(
        email: '  UPDATED@EXAMPLE.COM ',
    ));

    expect($updated->email)->toBe('updated@example.com');
});

it('throws protected user exception and does not change role when attempting to promote user to superadmin', function (): void {
    $user = User::factory()->customer()->create();
    $service = app(UserService::class);

    expect(fn () => $service->update($user, new UserData(
        role: UserRole::Superadmin,
    )))->toThrow(ProtectedUserException::class, 'Cannot promote a user to the superadmin role.');

    expect($user->fresh()->role)->toBe(UserRole::Customer);
});

it('throws protected user exception and does not change role when attempting to change superadmin role', function (): void {
    $superadmin = User::factory()->superadmin()->create();
    $service = app(UserService::class);

    expect(fn () => $service->update($superadmin, new UserData(
        role: UserRole::Retailer,
    )))->toThrow(ProtectedUserException::class, 'Cannot change the role of a user with the superadmin role.');

    expect($superadmin->fresh()->role)->toBe(UserRole::Superadmin);
});

it('throws protected user exception and does not change role when updating superadmin with superadmin role provided', function (): void {
    $superadmin = User::factory()->superadmin()->create();
    $service = app(UserService::class);

    expect(fn () => $service->update($superadmin, new UserData(
        name: 'New Name',
        role: UserRole::Superadmin,
    )))->toThrow(ProtectedUserException::class, 'Cannot change the role of a user with the superadmin role.');

    expect($superadmin->fresh()->role)->toBe(UserRole::Superadmin);
});

it('allows updating name or email of existing superadmin', function (): void {
    $superadmin = User::factory()->superadmin()->create();
    $service = app(UserService::class);

    $updated = $service->update($superadmin, new UserData(
        name: 'Updated Superadmin',
    ));

    expect($updated->name)->toBe('Updated Superadmin')
        ->and($updated->role)->toBe(UserRole::Superadmin);
});

it('shows user by public id', function (): void {
    $user = User::factory()->create();
    $service = app(UserService::class);

    $found = $service->show($user->public_id);

    expect($found->id)->toBe($user->id)
        ->and($found->public_id)->toBe($user->public_id);
});

it('throws model not found exception for unknown public id on show', function (): void {
    $service = app(UserService::class);

    expect(fn () => $service->show((string) Str::ulid()))
        ->toThrow(ModelNotFoundException::class);
});

it('throws model not found exception for soft deleted user on show', function (): void {
    $user = User::factory()->create();
    $user->delete();

    $service = app(UserService::class);

    expect(fn () => $service->show($user->public_id))
        ->toThrow(ModelNotFoundException::class);
});

it('destroys user keeping row in database while excluding from show and default index', function (): void {
    $user = User::factory()->create();
    $service = app(UserService::class);

    $service->destroy($user);

    expect(fn () => $service->show($user->public_id))
        ->toThrow(ModelNotFoundException::class)
        ->and($service->index(new UserFilterData)->total())->toBe(0);

    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertNotNull(User::withTrashed()->find($user->id)?->deleted_at);
});

it('throws protected user exception and does not destroy user when attempting to destroy superadmin', function (): void {
    $superadmin = User::factory()->superadmin()->create();
    $service = app(UserService::class);

    expect(fn () => $service->destroy($superadmin))
        ->toThrow(ProtectedUserException::class, 'Cannot delete a user with the superadmin role.');

    expect($superadmin->fresh()->trashed())->toBeFalse();
});

it('clamps per page between 1 and 100 on index', function (): void {
    User::factory()->count(5)->create();
    $service = app(UserService::class);

    $highPaginator = $service->index(new UserFilterData(perPage: 200));
    $lowPaginator = $service->index(new UserFilterData(perPage: -5));

    expect($highPaginator->perPage())->toBe(100)
        ->and($lowPaginator->perPage())->toBe(1);
});

it('treats empty or whitespace only search string as null on index', function (): void {
    User::factory()->count(3)->create();
    $service = app(UserService::class);

    $paginator = $service->index(new UserFilterData(search: '   '));

    expect($paginator->total())->toBe(3);
});

it('trims search string in service so padded search matches user on index', function (): void {
    User::factory()->create(['name' => 'Alice']);
    $service = app(UserService::class);

    $paginator = $service->index(new UserFilterData(search: ' alice '));

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]->name)->toBe('Alice');
});
