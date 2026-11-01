<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Where accounts with a published password may exist. Anywhere else,
     * one `db:seed --force` would create an administrator anyone can log in
     * as (FIND-038).
     *
     * @var list<string>
     */
    public const ENVIRONMENTS = ['local', 'testing'];

    /**
     * Seed the application's demo users.
     */
    public function run(): void
    {
        if (! app()->environment(self::ENVIRONMENTS)) {
            throw new RuntimeException('Demo users have a published password and are only seeded in local and testing.');
        }

        $admin = Profile::where('slug', 'admin')->firstOrFail();
        $dev = Profile::where('slug', 'dev')->firstOrFail();
        $viewer = Profile::where('slug', 'viewer')->firstOrFail();

        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@napi.dev',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $adminUser->profiles()->attach($admin->id, ['assigned_by' => $adminUser->id]);

        $devUser = User::create([
            'name' => 'Dev User',
            'email' => 'dev@napi.dev',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $devUser->profiles()->attach($dev->id, ['assigned_by' => $adminUser->id]);

        $viewerUser = User::create([
            'name' => 'Viewer User',
            'email' => 'viewer@napi.dev',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $viewerUser->profiles()->attach($viewer->id, ['assigned_by' => $adminUser->id]);
    }
}
