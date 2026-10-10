<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Seed the system menus. Their `key`s are the permission identifiers
     * referenced by the routes, so they are flagged `is_system` (not
     * deletable through the API) and the key itself is immutable.
     *
     * No matrix rows are seeded: the admin profile holds every functional
     * permission implicitly (ADR-0008), dev is limited to self-service
     * (/auth/me) and viewer is blocked by default.
     *
     * Safe to run again: a menu that already exists is left as it is, so a
     * new system menu reaches an existing database with
     * `php artisan db:seed --class=MenuSeeder`.
     */
    public function run(): void
    {
        $this->systemMenu('users', 'Users', 'users.index', 'users', 1);
        $profiles = $this->systemMenu('profiles', 'Profiles', 'profiles.index', 'shield', 2);
        $this->systemMenu('menus', 'Menus', 'menus.index', 'menu', 3);
        $this->systemMenu('audit-logs', 'Audit Logs', 'audit-logs.index', 'activity', 4);
        $this->systemMenu('permissions', 'Permissions', 'permissions.index', 'lock', 1, $profiles);

        // Security Lab (Phase 5.1): `view` opens the lab and its history,
        // `create` runs a test (a run is created).
        $this->systemMenu('security-lab', 'Security Lab', 'security-lab.index', 'flask', 5);
    }

    private function systemMenu(string $key, string $label, string $routeName, string $icon, int $order, ?Menu $parent = null): Menu
    {
        return Menu::where('key', $key)->first() ?? Menu::forceCreate([
            'key' => $key,
            'label' => $label,
            'route_name' => $routeName,
            'icon' => $icon,
            'order' => $order,
            'parent_id' => $parent?->id,
            'is_system' => true,
        ]);
    }
}
