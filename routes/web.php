<?php

use App\Http\Controllers\Web\Admin\Auth\LoginController;
use App\Http\Controllers\Web\Admin\HomeController;
use App\Http\Controllers\Web\Admin\MenuController;
use App\Http\Controllers\Web\Admin\MenuPermissionController;
use App\Http\Controllers\Web\Admin\ProfileController;
use App\Http\Controllers\Web\Admin\ProfilePermissionController;
use App\Http\Controllers\Web\Admin\Security\IdorTestController;
use App\Http\Controllers\Web\Admin\Security\MassAssignmentTestController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Admin\UserProfileController;
use App\Http\Controllers\Web\Admin\UserStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Área administrativa (Fases 4.1 a 4.4, 5.1 e 5.2): Blade + guard `web` (sessão), sem
// consumir a API REST. Mesmas camadas da API, adaptadas à sessão (ADR-0008):
// sessão válida e usuário ativo → acesso à área (`admin.access`) →
// permissão funcional (`permission:{key}.{action}`, 404) → Policy (403) →
// invariantes de domínio (redirect com mensagem).
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', HomeController::class)
        ->middleware('admin.access')
        ->name('home');

    // Rate limit inside VerifyLoginCredentials, shared with the API login
    // (ADR-0013).
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('guest')
        ->name('login');

    // Mesmo limiter por usuário da API: uma cota por pessoa, não por adapter.
    Route::middleware(['auth', 'throttle:authenticated'])->group(function () {
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

        // Perfis e matriz de permissões (Fase 4.3). A matriz usa a mesma
        // permissão da API (`permissions.update`) e a mesma Policy
        // (ProfilePolicy::syncMenus, com a regra anti-escalação).
        Route::middleware('admin.access')->prefix('profiles')->name('profiles.')->group(function () {
            Route::get('/', [ProfileController::class, 'index'])
                ->middleware('permission:profiles.view')
                ->name('index');
            Route::get('/create', [ProfileController::class, 'create'])
                ->middleware('permission:profiles.create')
                ->name('create');
            Route::post('/', [ProfileController::class, 'store'])
                ->middleware('permission:profiles.create')
                ->name('store');
            Route::get('/{profile}', [ProfileController::class, 'show'])
                ->middleware('permission:profiles.view')
                ->name('show');
            Route::get('/{profile}/edit', [ProfileController::class, 'edit'])
                ->middleware('permission:profiles.update')
                ->name('edit');
            Route::put('/{profile}', [ProfileController::class, 'update'])
                ->middleware('permission:profiles.update')
                ->name('update');
            Route::delete('/{profile}', [ProfileController::class, 'destroy'])
                ->middleware('permission:profiles.delete')
                ->name('destroy');
            Route::put('/{profile}/permissions', [ProfilePermissionController::class, 'update'])
                ->middleware('permission:permissions.update')
                ->name('permissions.update');
        });

        // Menus (Fase 4.4). `key` é a identidade da permissão e não muda;
        // o resto é navegação.
        Route::middleware('admin.access')->prefix('menus')->name('menus.')->group(function () {
            Route::get('/', [MenuController::class, 'index'])
                ->middleware('permission:menus.view')
                ->name('index');
            Route::get('/create', [MenuController::class, 'create'])
                ->middleware('permission:menus.create')
                ->name('create');
            Route::post('/', [MenuController::class, 'store'])
                ->middleware('permission:menus.create')
                ->name('store');
            Route::get('/{menu}', [MenuController::class, 'show'])
                ->middleware('permission:menus.view')
                ->name('show');
            Route::get('/{menu}/edit', [MenuController::class, 'edit'])
                ->middleware('permission:menus.update')
                ->name('edit');
            Route::put('/{menu}', [MenuController::class, 'update'])
                ->middleware('permission:menus.update')
                ->name('update');
            Route::delete('/{menu}', [MenuController::class, 'destroy'])
                ->middleware('permission:menus.delete')
                ->name('destroy');
            // A matriz vista pelo menu: mesma permissão e mesma Policy da
            // matriz no perfil.
            Route::put('/{menu}/permissions', [MenuPermissionController::class, 'update'])
                ->middleware('permission:permissions.update')
                ->name('permissions.update');
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
