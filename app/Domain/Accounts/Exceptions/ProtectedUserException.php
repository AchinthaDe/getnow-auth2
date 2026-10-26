<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Exceptions;

use RuntimeException;

final class ProtectedUserException extends RuntimeException
{
    public static function cannotCreateSuperadmin(): self
    {
        return new self('Cannot create a user with the superadmin role.');
    }

    public static function cannotPromoteToSuperadmin(): self
    {
        return new self('Cannot promote a user to the superadmin role.');
    }

    public static function cannotDeleteSuperadmin(): self
    {
        return new self('Cannot delete a user with the superadmin role.');
    }

    public static function cannotChangeSuperadminRole(): self
    {
        return new self('Cannot change the role of a user with the superadmin role.');
    }
}
