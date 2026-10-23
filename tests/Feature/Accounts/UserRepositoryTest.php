<?php

declare(strict_types=1);

use App\Domain\Accounts\Contracts\UserRepositoryInterface;
use App\Domain\Accounts\Data\UserData;
use App\Domain\Accounts\Data\UserFilterData;
use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Repositories\UserRepository;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('resolves user repository interface to user repository from container', function (): void {
    $repository = app(UserRepositoryInterface::class);

    expect($repository)->toBeInstanceOf(UserRepository::class);
});

it('paginates users and filters by role in repository', function (): void {
    User::factory()->count(2)->customer()->create();
    $retailer = User::factory()->retailer()->create();

    $repository = app(UserRepositoryInterface::class);
    $paginator = $repository->index(new UserFilterData(role: UserRole::Retailer));

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]->id)->toBe($retailer->id)
        ->and($paginator->items()[0]->role)->toBe(UserRole::Retailer);
});

it('excludes soft deleted users by default in repository index', function (): void {
    User::factory()->create();
    $deletedUser = User::factory()->create();
    $deletedUser->delete();

    $repository = app(UserRepositoryInterface::class);
    $paginator = $repository->index(new UserFilterData);

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]->id)->not->toBe($deletedUser->id);
});

it('includes soft deleted users when includeTrashed is true in repository index', function (): void {
    User::factory()->create();
    $deletedUser = User::factory()->create();
    $deletedUser->delete();

    $repository = app(UserRepositoryInterface::class);
    $paginator = $repository->index(new UserFilterData(includeTrashed: true));

    expect($paginator->total())->toBe(2);
});

it('escapes percent wildcard matching literal percent character only in repository index', function (): void {
    $matched = User::factory()->create(['name' => 'Special%User']);
    User::factory()->create(['name' => 'SpecialOtherUser']);

    $repository = app(UserRepositoryInterface::class);
    $paginator = $repository->index(new UserFilterData(search: 'Special%'));

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]->id)->toBe($matched->id)
        ->and($paginator->items()[0]->name)->toBe('Special%User');
});

it('escapes underscore wildcard matching literal underscore character only in repository index', function (): void {
    $matched = User::factory()->create(['name' => 'a_b']);
    User::factory()->create(['name' => 'axb']);

    $repository = app(UserRepositoryInterface::class);
    $paginator = $repository->index(new UserFilterData(search: 'a_b'));

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]->id)->toBe($matched->id)
        ->and($paginator->items()[0]->name)->toBe('a_b');
});

it('escapes backslash wildcard matching literal backslash character only in repository index', function (): void {
    $matched = User::factory()->create(['name' => 'a\\b']);
    User::factory()->create(['name' => 'axb']);

    $repository = app(UserRepositoryInterface::class);
    $paginator = $repository->index(new UserFilterData(search: 'a\\b'));

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]->id)->toBe($matched->id)
        ->and($paginator->items()[0]->name)->toBe('a\\b');
});

it('matches user email in repository index search', function (): void {
    $matched = User::factory()->create(['email' => 'target.account@getnow.test']);
    User::factory()->create(['email' => 'other.account@getnow.test']);

    $repository = app(UserRepositoryInterface::class);
    $paginator = $repository->index(new UserFilterData(search: 'target.account'));

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]->id)->toBe($matched->id)
        ->and($paginator->items()[0]->email)->toBe('target.account@getnow.test');
});

it('searches user name case insensitively in repository index', function (): void {
    $matched = User::factory()->create(['name' => 'Alice Wonder']);

    $repository = app(UserRepositoryInterface::class);
    $paginator = $repository->index(new UserFilterData(search: 'alice'));

    expect($paginator->total())->toBe(1)
        ->and($paginator->items()[0]->id)->toBe($matched->id)
        ->and($paginator->items()[0]->name)->toBe('Alice Wonder');
});

it('finds user by public id excluding soft deleted users in repository show', function (): void {
    $user = User::factory()->create();
    $user->delete();

    $repository = app(UserRepositoryInterface::class);
    $found = $repository->show($user->public_id);

    expect($found)->toBeNull();
});

it('stores user record with generated ulid public id and hashed password in repository', function (): void {
    $repository = app(UserRepositoryInterface::class);

    $user = $repository->store(new UserData(
        name: 'Repo User',
        email: 'repo@getnow.test',
        password: 'secret-pass',
        role: UserRole::Customer,
    ));

    expect($user->public_id)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and(Hash::check('secret-pass', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('secret-pass');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'public_id' => $user->public_id,
        'email' => 'repo@getnow.test',
    ]);
});

it('updates only present fields leaving absent optional fields unmodified in repository', function (): void {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'stay@getnow.test',
        'password' => 'secret-initial',
        'role' => UserRole::Customer,
    ]);

    $originalHash = $user->password;
    $repository = app(UserRepositoryInterface::class);

    $updated = $repository->update($user, new UserData(
        name: 'Updated Name',
    ));

    expect($updated->name)->toBe('Updated Name')
        ->and($updated->email)->toBe('stay@getnow.test')
        ->and($updated->role)->toBe(UserRole::Customer)
        ->and($updated->password)->toBe($originalHash);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'email' => 'stay@getnow.test',
    ]);
});

it('destroys user via soft delete setting deleted_at in repository', function (): void {
    $user = User::factory()->create();

    $repository = app(UserRepositoryInterface::class);
    $repository->destroy($user);

    expect($user->trashed())->toBeTrue();

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
    ]);
    $this->assertNotNull(User::withTrashed()->find($user->id)?->deleted_at);
});
