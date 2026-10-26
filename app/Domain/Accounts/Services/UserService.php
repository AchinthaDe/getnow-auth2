<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Services;

use App\Domain\Accounts\Contracts\UserRepositoryInterface;
use App\Domain\Accounts\Data\UserData;
use App\Domain\Accounts\Data\UserFilterData;
use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Exceptions\ProtectedUserException;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Spatie\LaravelData\Optional;

final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function index(UserFilterData $filter): LengthAwarePaginator
    {
        $perPage = min(max($filter->perPage, 1), 100);
        $search = null;

        if ($filter->search !== null) {
            $trimmed = trim($filter->search);
            $search = $trimmed === '' ? null : $trimmed;
        }

        $normalizedFilter = new UserFilterData(
            search: $search,
            role: $filter->role,
            includeTrashed: $filter->includeTrashed,
            perPage: $perPage,
        );

        return $this->users->index($normalizedFilter);
    }

    public function show(string $publicId): User
    {
        $user = $this->users->show($publicId);

        if ($user === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$publicId]);
        }

        return $user;
    }

    public function store(UserData $data): User
    {
        if ($data->name instanceof Optional || $data->email instanceof Optional || $data->password instanceof Optional || $data->role instanceof Optional) {
            $missing = [];

            if ($data->name instanceof Optional) {
                $missing[] = 'name';
            }

            if ($data->email instanceof Optional) {
                $missing[] = 'email';
            }

            if ($data->password instanceof Optional) {
                $missing[] = 'password';
            }

            if ($data->role instanceof Optional) {
                $missing[] = 'role';
            }

            throw new InvalidArgumentException(sprintf('Missing required user fields: %s.', implode(', ', $missing)));
        }

        if ($data->role === UserRole::Superadmin) {
            throw ProtectedUserException::cannotCreateSuperadmin();
        }

        $normalizedEmail = strtolower(trim($data->email));
        $normalizedData = new UserData(
            name: $data->name,
            email: $normalizedEmail,
            password: $data->password,
            role: $data->role,
        );

        return $this->users->store($normalizedData);
    }

    public function update(User $user, UserData $data): User
    {
        if ($user->role === UserRole::Superadmin) {
            if (! $data->role instanceof Optional) {
                throw ProtectedUserException::cannotChangeSuperadminRole();
            }
        } else {
            if (! $data->role instanceof Optional && $data->role === UserRole::Superadmin) {
                throw ProtectedUserException::cannotPromoteToSuperadmin();
            }
        }

        $email = ! $data->email instanceof Optional
            ? strtolower(trim($data->email))
            : new Optional;

        $normalizedData = new UserData(
            name: $data->name,
            email: $email,
            password: $data->password,
            role: $data->role,
        );

        return $this->users->update($user, $normalizedData);
    }

    public function destroy(User $user): void
    {
        if ($user->role === UserRole::Superadmin) {
            throw ProtectedUserException::cannotDeleteSuperadmin();
        }

        $this->users->destroy($user);
    }
}
