<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->middleware('auth:api')->group(function (): void {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('{user:public_id}', [UserController::class, 'show']);
    Route::match(['put', 'patch'], '{user:public_id}', [UserController::class, 'update']);
    Route::delete('{user:public_id}', [UserController::class, 'destroy']);
});
