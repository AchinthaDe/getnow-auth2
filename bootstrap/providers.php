<?php

declare(strict_types=1);

use App\Providers\AccountsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\TokenServiceProvider;

return [
    AccountsServiceProvider::class,
    AppServiceProvider::class,
    TokenServiceProvider::class,
];
