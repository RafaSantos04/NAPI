<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth routes (com rate limit)
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware(['throttle:login'])
        ->name('auth.login');

    // Protected routes (autenticado)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])
            ->name('auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])
            ->name('auth.me');

        // Token management
        Route::apiResource('tokens', TokenController::class)
            ->only(['index', 'store', 'destroy']);

        // Users (com Policies + middleware de permissão por menu)
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:users.index,view')
            ->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->middleware('permission:users.index,view')
            ->name('users.show');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:users.index,create')
            ->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.index,update')
            ->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.index,delete')
            ->name('users.destroy');
    });
});
