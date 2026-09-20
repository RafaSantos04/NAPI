<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Seed the application's default menus.
     */
    public function run(): void
    {
        $users = Menu::create([
            'label' => 'Users',
            'route_name' => 'users.index',
            'icon' => 'users',
            'order' => 1,
        ]);

        $profiles = Menu::create([
            'label' => 'Profiles',
            'route_name' => 'profiles.index',
            'icon' => 'shield',
            'order' => 2,
        ]);

        $menus = Menu::create([
            'label' => 'Menus',
            'route_name' => 'menus.index',
            'icon' => 'menu',
            'order' => 3,
        ]);

        $audit = Menu::create([
            'label' => 'Audit Logs',
            'route_name' => 'audit-logs.index',
            'icon' => 'activity',
            'order' => 4,
        ]);

        Menu::create([
            'label' => 'Permissions',
            'route_name' => 'permissions.index',
            'icon' => 'lock',
            'order' => 1,
            'parent_id' => $profiles->id,
        ]);
    }
}
