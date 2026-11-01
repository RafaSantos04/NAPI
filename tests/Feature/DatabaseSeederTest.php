<?php

use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;
use Database\Seeders\UserSeeder;

// FIND-038: the demo accounts have a published password, so they exist only
// in local and testing. Not under the pre-seeded folders: each test seeds
// for itself.

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
            ->toThrow(RuntimeException::class, 'Demo users have a published password');

        expect(User::count())->toBe(0);
    });

    it('seeds the demo accounts in local', function () {
        app()->detectEnvironment(fn () => 'local');

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        expect(User::whereIn('email', ['admin@napi.dev', 'dev@napi.dev', 'viewer@napi.dev'])->count())->toBe(3);
    });
});
