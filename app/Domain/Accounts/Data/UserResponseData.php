<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Data;

use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class UserResponseData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public UserRole $role,
        public ?CarbonInterface $createdAt = null,
        public ?CarbonInterface $updatedAt = null,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->public_id,
            name: $user->name,
            email: $user->email,
            role: $user->role,
            createdAt: $user->created_at,
            updatedAt: $user->updated_at,
        );
    }
}
