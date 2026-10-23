<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('refresh', [TokenController::class, 'refresh'])
        ->middleware('throttle:token-refresh');

    Route::post('logout', [TokenController::class, 'logout'])
        ->middleware(['auth:api', 'throttle:token-logout']);
});
