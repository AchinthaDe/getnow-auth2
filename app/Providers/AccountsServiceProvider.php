<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Accounts\Contracts\UserRepositoryInterface;
use App\Domain\Accounts\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

final class AccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class,
        );
    }
}
