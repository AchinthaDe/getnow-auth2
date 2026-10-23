<?php

declare(strict_types=1);
use Spatie\LaravelData\Data;

arch('domain does not use http')
    ->expect('App\Domain')
    ->not->toUse('App\Http');

// User model carries HasApiTokens and implements OAuthenticatable for Passport 13 integration
arch('passport is used only by token stream and user model')
    ->expect('Laravel\Passport')
    ->toOnlyBeUsedIn([
        'App\Domain\Accounts\Tokens',
        'App\Providers\TokenServiceProvider',
        'App\Models\User',
    ]);

arch('contracts are interfaces')
    ->expect('App\Domain\Accounts\Contracts')
    ->toBeInterfaces();

arch('data objects extend spatie laravel data')
    ->expect('App\Domain\Accounts\Data')
    ->toExtend(Data::class);

arch('strict types are enforced')
    ->expect([
        'App\Domain',
        'App\Http\Controllers\Api',
        'App\Http\Requests\Auth',
        'App\Models',
        'App\Providers\TokenServiceProvider',
        'Database\Factories',
        'Database\Seeders',
    ])
    ->toUseStrictTypes();

// Excludes App\Models to allow the accepted TokenController User instanceof check
arch('api controllers do not use repositories or db facade')
    ->expect('App\Http\Controllers\Api')
    ->not->toUse([
        'App\Domain\Accounts\Repositories',
        'Illuminate\Support\Facades\DB',
    ]);
