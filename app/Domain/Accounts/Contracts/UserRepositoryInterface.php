<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Contracts;

use App\Domain\Accounts\Data\UserData;
use App\Domain\Accounts\Data\UserFilterData;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function index(UserFilterData $filter): LengthAwarePaginator;

    public function show(string $publicId): ?User;

    public function store(UserData $data): User;

    public function update(User $user, UserData $data): User;

    public function destroy(User $user): void;
}
