<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class AuthResultData extends Data
{
    public function __construct(
        public UserResponseData $user,
        public TokenPairData $tokens,
    ) {}
}
