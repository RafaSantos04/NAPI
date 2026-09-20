<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Profile;
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

        $permissions = Menu::create([
            'label' => 'Permissions',
            'route_name' => 'permissions.index',
            'icon' => 'lock',
            'order' => 1,
            'parent_id' => $profiles->id,
        ]);

        $this->grantPermissions($users, $profiles, $menus, $audit, $permissions);
    }

    /**
     * Grant default menu permissions per profile: admin gets full CRUD on
     * every menu; dev only gets view access to the Users menu (needed for
     * self-service endpoints); viewer gets nothing (blocked by default).
     */
    private function grantPermissions(Menu ...$menus): void
    {
        $admin = Profile::where('slug', 'admin')->firstOrFail();
        $dev = Profile::where('slug', 'dev')->firstOrFail();

        $fullAccess = ['can_view' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => true];

        foreach ($menus as $menu) {
            $menu->profiles()->attach($admin->id, $fullAccess);
        }

        $usersMenu = $menus[0];
        $usersMenu->profiles()->attach($dev->id, ['can_view' => true]);
    }
}
