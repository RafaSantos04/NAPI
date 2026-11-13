<?php

use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// FIND-038: demo accounts exist only in local and testing, and their password
// must come from the environment. Not under the pre-seeded folders: each test
// seeds for itself.

describe('Demo accounts', function () {
    it('seeds the system profiles and menus but no demo account outside local and testing', function () {
        app()->detectEnvironment(fn () => 'production');

        // The flag a deploy script would pass.
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        expect(Profile::where('slug', Profile::ADMIN)->exists())->toBeTrue()
            ->and(Menu::where('key', 'users')->exists())->toBeTrue()
            ->and(User::count())->toBe(0);
    });

    it('refuses to run the demo user seeder directly outside local and testing', function () {
        app()->detectEnvironment(fn () => 'production');
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        expect(fn () => $this->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true])->run())
            ->toThrow(RuntimeException::class, 'Demo users are only seeded in local and testing');

        expect(User::count())->toBe(0);
    });

    it('refuses to seed demo accounts when the environment password is missing', function () {
        app()->detectEnvironment(fn () => 'local');
        config(['security.seed.user_password' => null]);

        expect(fn () => $this->artisan('db:seed', ['--force' => true])->run())
            ->toThrow(RuntimeException::class, 'NAPI_SEED_USER_PASSWORD must be set');

        expect(User::count())->toBe(0);
    });

    it('refuses to seed demo accounts with a password outside the safe length', function (string $password) {
        app()->detectEnvironment(fn () => 'local');
        config(['security.seed.user_password' => $password]);

        expect(fn () => $this->artisan('db:seed', ['--force' => true])->run())
            ->toThrow(RuntimeException::class, 'NAPI_SEED_USER_PASSWORD must contain at least 12 characters and at most 72 bytes');

        expect(User::count())->toBe(0);
    })->with([
        'too short' => fn () => Str::random(11),
        'too long for bcrypt' => fn () => Str::random(73),
    ]);

    it('accepts and hashes a configured password with 12 characters', function () {
        app()->detectEnvironment(fn () => 'local');
        $password = Str::password(12);
        config(['security.seed.user_password' => $password]);

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $users = User::whereIn('email', ['admin@napi.dev', 'dev@napi.dev', 'viewer@napi.dev'])->get();

        expect($users)->toHaveCount(3)
            ->and($users->every(fn (User $user) => $user->password !== $password))->toBeTrue()
            ->and($users->every(fn (User $user) => Hash::check($password, $user->password)))->toBeTrue();
    });

    it('rotates existing demo account passwords without creating duplicates', function () {
        app()->detectEnvironment(fn () => 'local');
        $oldPassword = Str::password();
        config(['security.seed.user_password' => $oldPassword]);
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $newPassword = Str::password();
        config(['security.seed.user_password' => $newPassword]);
        $this->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true])->assertSuccessful();

        $users = User::whereIn('email', ['admin@napi.dev', 'dev@napi.dev', 'viewer@napi.dev'])->get();
        expect($users)->toHaveCount(3)
            ->and($users->every(fn (User $user) => ! Hash::check($oldPassword, $user->password)))->toBeTrue()
            ->and($users->every(fn (User $user) => Hash::check($newPassword, $user->password)))->toBeTrue();
    });
});
