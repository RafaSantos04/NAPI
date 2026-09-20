<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's demo users.
     */
    public function run(): void
    {
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
