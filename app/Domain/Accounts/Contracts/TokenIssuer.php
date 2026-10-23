<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Contracts;

use App\Domain\Accounts\Data\TokenPairData;
use App\Models\User;

interface TokenIssuer
{
    /**
     * Called after AuthService has verified credentials.
     */
    public function issue(User $user): TokenPairData;

    public function refresh(string $refreshToken): TokenPairData;

    /**
     * Logout: revoke the token pair behind the current request.
     */
    public function revoke(User $user): void;

    /**
     * Delete, password change, role change: revoke every token of the user.
     */
    public function revokeAll(User $user): void;
}
