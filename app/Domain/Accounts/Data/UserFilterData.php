<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Data;

use App\Domain\Accounts\Enums\UserRole;
use Spatie\LaravelData\Data;

final class UserFilterData extends Data
{
    public function __construct(
        public ?string $search = null,
        public ?UserRole $role = null,
        public bool $includeTrashed = false,
        public int $perPage = 15,
    ) {}
}
