<?php

use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

describe('Profile relations', function () {
    it('has many users', function () {
        $profile = Profile::factory()->create(['slug' => 'admin']);
        $users = User::factory(3)->create();

        foreach ($users as $user) {
            $user->profiles()->attach($profile->id);
        }

        expect($profile->users()->count())->toBe(3);
    });

    it('has many menus with permissions', function () {
        $profile = Profile::factory()->create(['slug' => 'admin']);
        $menu = Menu::factory()->create();

        $profile->menus()->attach($menu->id, [
            'can_view' => true,
            'can_create' => true,
            'can_update' => true,
            'can_delete' => true,
        ]);

        $permission = $profile->menus()->first()->pivot;
        expect($permission->can_view)->toBeTrue();
        expect($permission->can_delete)->toBeTrue();
    });

    it('force deleting a profile cascades to user_profiles and menu_profiles', function () {
        $profile = Profile::factory()->create();
        $user = User::factory()->create();
        $menu = Menu::factory()->create();

        $user->profiles()->attach($profile->id);
        $profile->menus()->attach($menu->id, ['can_view' => true]);

        $profile->forceDelete();

        expect(UserProfile::count())->toBe(0);
        expect(DB::table('menu_profiles')->count())->toBe(0);
    });
});

describe('Profile validation', function () {
    it('slug is unique', function () {
        Profile::factory()->create(['slug' => 'admin']);

        expect(function () {
            Profile::create(['name' => 'Admin 2', 'slug' => 'admin']);
        })->toThrow(QueryException::class);
    });

    it('name is unique', function () {
        Profile::factory()->create(['name' => 'Administrator']);

        expect(function () {
            Profile::create(['name' => 'Administrator', 'slug' => 'admin-2']);
        })->toThrow(QueryException::class);
    });
});
