<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserProfileController;
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
        Route::put('/users/{user}/profiles', [UserProfileController::class, 'update'])
            ->middleware('permission:users.index,update')
            ->name('users.profiles.update');

        // Profiles
        Route::get('/profiles', [ProfileController::class, 'index'])
            ->middleware('permission:profiles.index,view')
            ->name('profiles.index');
        Route::post('/profiles', [ProfileController::class, 'store'])
            ->middleware('permission:profiles.index,create')
            ->name('profiles.store');
        Route::get('/profiles/{profile}', [ProfileController::class, 'show'])
            ->middleware('permission:profiles.index,view')
            ->name('profiles.show');
        Route::put('/profiles/{profile}', [ProfileController::class, 'update'])
            ->middleware('permission:profiles.index,update')
            ->name('profiles.update');
        Route::delete('/profiles/{profile}', [ProfileController::class, 'destroy'])
            ->middleware('permission:profiles.index,delete')
            ->name('profiles.destroy');
        Route::put('/profiles/{profile}/menus', [ProfileController::class, 'syncMenus'])
            ->middleware('permission:permissions.index,update')
            ->name('profiles.menus.update');

        // Menus
        Route::get('/menus', [MenuController::class, 'index'])
            ->middleware('permission:menus.index,view')
            ->name('menus.index');
        Route::get('/menus/tree', [MenuController::class, 'tree'])
            ->middleware('permission:menus.index,view')
            ->name('menus.tree');
        Route::post('/menus', [MenuController::class, 'store'])
            ->middleware('permission:menus.index,create')
            ->name('menus.store');
        Route::get('/menus/{menu}', [MenuController::class, 'show'])
            ->middleware('permission:menus.index,view')
            ->name('menus.show');
        Route::put('/menus/{menu}', [MenuController::class, 'update'])
            ->middleware('permission:menus.index,update')
            ->name('menus.update');
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])
            ->middleware('permission:menus.index,delete')
            ->name('menus.destroy');
    });
});
