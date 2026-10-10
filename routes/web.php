<?php

use App\Http\Controllers\Web\Admin\Auth\LoginController;
use App\Http\Controllers\Web\Admin\HomeController;
use App\Http\Controllers\Web\Admin\Security\IdorTestController;
use App\Http\Controllers\Web\Admin\Security\MassAssignmentTestController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Admin\UserProfileController;
use App\Http\Controllers\Web\Admin\UserStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Área administrativa (Fases 4.1, 4.2 e 5.1): Blade + guard `web` (sessão), sem
// consumir a API REST. Mesmas camadas da API, adaptadas à sessão (ADR-0008):
// sessão válida e usuário ativo → acesso à área (`admin.access`) →
// permissão funcional (`permission:{key}.{action}`, 404) → Policy (403) →
// invariantes de domínio (redirect com mensagem).
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', HomeController::class)
        ->middleware('admin.access')
        ->name('home');

    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('guest')
        ->name('login');

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'destroy'])
            ->name('logout');

        Route::middleware('admin.access')->prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])
                ->middleware('permission:users.view')
                ->name('index');
            Route::get('/create', [UserController::class, 'create'])
                ->middleware('permission:users.create')
                ->name('create');
            Route::post('/', [UserController::class, 'store'])
                ->middleware('permission:users.create')
                ->name('store');
            Route::get('/{user}', [UserController::class, 'show'])
                ->middleware('permission:users.view')
                ->name('show');
            Route::get('/{user}/edit', [UserController::class, 'edit'])
                ->middleware('permission:users.update')
                ->name('edit');
            Route::put('/{user}', [UserController::class, 'update'])
                ->middleware('permission:users.update')
                ->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])
                ->middleware('permission:users.delete')
                ->name('destroy');
            Route::post('/{user}/deactivate', [UserStatusController::class, 'deactivate'])
                ->middleware('permission:users.update')
                ->name('deactivate');
            Route::post('/{user}/activate', [UserStatusController::class, 'activate'])
                ->middleware('permission:users.update')
                ->name('activate');
            Route::put('/{user}/profiles', [UserProfileController::class, 'update'])
                ->middleware('permission:users.update')
                ->name('profiles.update');
        });

        // Security Lab (Fases 5.1 e 5.2, ADR-0010). Além das camadas do
        // admin, a feature flag `security.lab`: desligado, tudo aqui
        // responde 404. Nenhuma rota serve um recurso sem checar o dono nem
        // grava um payload num model: os cenários vulneráveis só rodam
        // dentro dos casos de uso RunIdorTest e RunMassAssignmentTest.
        Route::middleware(['admin.access', 'security.lab'])->prefix('security')->name('security.')->group(function () {
            Route::view('/', 'admin.security.index')
                ->middleware('permission:security-lab.view')
                ->name('index');
            Route::get('/idor', [IdorTestController::class, 'show'])
                ->middleware('permission:security-lab.view')
                ->name('idor.show');
            Route::post('/idor/run', [IdorTestController::class, 'run'])
                ->middleware('permission:security-lab.create')
                ->name('idor.run');
            Route::get('/mass-assignment', [MassAssignmentTestController::class, 'show'])
                ->middleware('permission:security-lab.view')
                ->name('mass-assignment.show');
            Route::post('/mass-assignment/run', [MassAssignmentTestController::class, 'run'])
                ->middleware('permission:security-lab.create')
                ->name('mass-assignment.run');
        });
    });
});
