<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Data;

use App\Domain\Accounts\Enums\UserRole;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class UserData extends Data
{
    public function __construct(
        public string|Optional $name = new Optional,
        public string|Optional $email = new Optional,
        public string|Optional $password = new Optional,
        public UserRole|Optional $role = new Optional,
    ) {}
}
