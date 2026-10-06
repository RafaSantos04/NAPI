<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserProfileController;
use App\Http\Controllers\Api\V1\UserStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth routes (com rate limit)
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware(['throttle:login'])
        ->name('auth.login');

    // Camadas (ADR-0008): autenticação (token válido + usuário ativo) →
    // ability do token pelo método HTTP → permissão funcional
    // (`permission:{key}.{action}`) → Policy → invariantes de domínio.
    Route::middleware(['auth:sanctum', 'token.ability'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])
            ->name('auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])
            ->name('auth.me');

        // Token management (só os tokens do próprio usuário)
        Route::apiResource('tokens', TokenController::class)
            ->only(['index', 'store', 'destroy'])
            ->whereNumber('token');

        // Users
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:users.view')
            ->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])
            ->middleware('permission:users.view')
            ->name('users.show');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:users.create')
            ->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.update')
            ->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete')
            ->name('users.destroy');
        Route::put('/users/{user}/profiles', [UserProfileController::class, 'update'])
            ->middleware('permission:users.update')
            ->name('users.profiles.update');
        Route::post('/users/{user}/deactivate', [UserStatusController::class, 'deactivate'])
            ->middleware('permission:users.update')
            ->name('users.deactivate');
        Route::post('/users/{user}/activate', [UserStatusController::class, 'activate'])
            ->middleware('permission:users.update')
            ->name('users.activate');

        // Profiles
        Route::get('/profiles', [ProfileController::class, 'index'])
            ->middleware('permission:profiles.view')
            ->name('profiles.index');
        Route::post('/profiles', [ProfileController::class, 'store'])
            ->middleware('permission:profiles.create')
            ->name('profiles.store');
        Route::get('/profiles/{profile}', [ProfileController::class, 'show'])
            ->middleware('permission:profiles.view')
            ->name('profiles.show');
        Route::put('/profiles/{profile}', [ProfileController::class, 'update'])
            ->middleware('permission:profiles.update')
            ->name('profiles.update');
        Route::delete('/profiles/{profile}', [ProfileController::class, 'destroy'])
            ->middleware('permission:profiles.delete')
            ->name('profiles.destroy');
        Route::put('/profiles/{profile}/menus', [ProfileController::class, 'syncMenus'])
            ->middleware('permission:permissions.update')
            ->name('profiles.menus.update');

        // Menus. A árvore de navegação só projeta as permissões do próprio
        // ator, então qualquer usuário autenticado pode consultá-la.
        Route::get('/menus/tree', [MenuController::class, 'tree'])
            ->name('menus.tree');
        Route::get('/menus', [MenuController::class, 'index'])
            ->middleware('permission:menus.view')
            ->name('menus.index');
        Route::post('/menus', [MenuController::class, 'store'])
            ->middleware('permission:menus.create')
            ->name('menus.store');
        Route::get('/menus/{menu}', [MenuController::class, 'show'])
            ->middleware('permission:menus.view')
            ->name('menus.show');
        Route::put('/menus/{menu}', [MenuController::class, 'update'])
            ->middleware('permission:menus.update')
            ->name('menus.update');
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])
            ->middleware('permission:menus.delete')
            ->name('menus.destroy');
    });
});
