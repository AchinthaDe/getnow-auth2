<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Repositories;

use App\Domain\Accounts\Contracts\UserRepositoryInterface;
use App\Domain\Accounts\Data\UserData;
use App\Domain\Accounts\Data\UserFilterData;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\LaravelData\Optional;

final class UserRepository implements UserRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function index(UserFilterData $filter): LengthAwarePaginator
    {
        $query = User::query();

        if ($filter->includeTrashed) {
            $query->withTrashed();
        }

        if ($filter->role !== null) {
            $query->where('role', $filter->role->value);
        }

        if ($filter->search !== null) {
            $escaped = addcslashes($filter->search, '\\%_');
            $pattern = '%'.$escaped.'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder->where('name', 'ILIKE', $pattern)
                    ->orWhere('email', 'ILIKE', $pattern);
            });
        }

        $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        return $query->paginate(perPage: $filter->perPage);
    }

    public function show(string $publicId): ?User
    {
        return User::query()->where('public_id', $publicId)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function store(UserData $data): User
    {
        $attributes = [
            'public_id' => (string) Str::ulid(),
        ];

        if (! $data->name instanceof Optional) {
            $attributes['name'] = $data->name;
        }

        if (! $data->email instanceof Optional) {
            $attributes['email'] = $data->email;
        }

        if (! $data->password instanceof Optional) {
            $attributes['password'] = $data->password;
        }

        if (! $data->role instanceof Optional) {
            $attributes['role'] = $data->role;
        }

        return User::create($attributes);
    }

    public function update(User $user, UserData $data): User
    {
        $attributes = [];

        if (! $data->name instanceof Optional) {
            $attributes['name'] = $data->name;
        }

        if (! $data->email instanceof Optional) {
            $attributes['email'] = $data->email;
        }

        if (! $data->password instanceof Optional) {
            $attributes['password'] = $data->password;
        }

        if (! $data->role instanceof Optional) {
            $attributes['role'] = $data->role;
        }

        if ($attributes !== []) {
            $user->update($attributes);
        }

        return $user;
    }

    public function destroy(User $user): void
    {
        $user->delete();
    }
}
