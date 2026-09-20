<?php

namespace Database\Seeders;

use App\Models\Profile;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    /**
     * Seed the application's default profiles.
     */
    public function run(): void
    {
        Profile::create([
            'name' => 'Administrator',
            'slug' => 'admin',
            'description' => 'Full access to all features',
            'is_system' => true,
        ]);

        Profile::create([
            'name' => 'Developer',
            'slug' => 'dev',
            'description' => 'Access to development features',
            'is_system' => true,
        ]);

        Profile::create([
            'name' => 'Viewer',
            'slug' => 'viewer',
            'description' => 'Read-only access',
            'is_system' => true,
        ]);
    }
}
