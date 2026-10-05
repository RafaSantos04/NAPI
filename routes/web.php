<?php

use App\Http\Controllers\Web\Admin\Auth\LoginController;
use App\Http\Controllers\Web\Admin\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Área administrativa (Fase 4.1): Blade + guard `web` (sessão), sem consumir
// a API REST. Páginas internas futuras entram no grupo `auth`.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('guest')
        ->name('login');

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'destroy'])
            ->name('logout');
    });
});
