<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Policies;

use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === UserRole::Superadmin;
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Superadmin;
    }

    public function create(User $actor): bool
    {
        return $actor->role === UserRole::Superadmin;
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Superadmin;
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->role === UserRole::Superadmin;
    }
}
