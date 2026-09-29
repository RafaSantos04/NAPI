<?php

use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;

// FIND-004, FIND-005, FIND-007, FIND-009: the profile x menu matrix is the
// single source of functional permissions; Policies only add contextual rules.
describe('Functional permissions (User::hasPermission)', function () {
    it('grants a custom profile exactly the flags set in the matrix', function () {
        $user = userWithPermissions(['users' => ['view', 'update']]);

        expect($user->hasPermission('users.view'))->toBeTrue()
            ->and($user->hasPermission('users.update'))->toBeTrue()
            ->and($user->hasPermission('users.delete'))->toBeFalse()
            ->and($user->hasPermission('profiles.view'))->toBeFalse();
    });

    it('fails closed for users without profiles', function () {
        expect(User::factory()->create()->hasPermission('users.view'))->toBeFalse();
    });

    it('fails closed for unknown keys and actions', function (string $permission) {
        expect(userWithPermissions(['users' => ['view']])->hasPermission($permission))->toBeFalse();
    })->with(['nope.view', 'users.approve', 'users', 'users.view.extra', '']);

    it('grants every functional permission to the admin profile', function () {
        $admin = seededUser('admin');
        Menu::factory()->create(['key' => 'reports']);

        expect($admin->hasPermission('reports.delete'))->toBeTrue();
    });

    it('ignores the navigation state of a menu', function () {
        Menu::where('key', 'users')->update(['is_active' => false]);

        expect(userWithPermissions(['users' => ['view']])->hasPermission('users.view'))->toBeTrue();
    });
});

describe('Authorization matrix over HTTP', function () {
    it('lets a custom profile with the permission use the endpoint', function () {
        $editor = userWithPermissions(['profiles' => ['view', 'create', 'update', 'delete']]);

        asToken($editor, 'GET', '/api/v1/profiles')->assertOk();
        asToken($editor, 'POST', '/api/v1/profiles', ['name' => 'Support', 'slug' => 'support'])
            ->assertCreated();
    });

    it('denies with 404 when the permission is missing', function () {
        $editor = userWithPermissions(['profiles' => ['view']]);

        asToken($editor, 'GET', '/api/v1/users')->assertNotFound();
        asToken($editor, 'POST', '/api/v1/profiles', ['name' => 'Support', 'slug' => 'support'])
            ->assertNotFound();
    });

    it('does not turn a view permission into a write permission', function () {
        $reader = userWithPermissions(['users' => ['view']]);

        asToken($reader, 'POST', '/api/v1/users', [
            'name' => 'X', 'email' => 'x@napi.dev', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertNotFound();
    });

    it('keeps list and detail consistent for the same permission', function () {
        $reader = userWithPermissions(['users' => ['view']]);
        $other = seededUser('viewer');

        asToken($reader, 'GET', '/api/v1/users')->assertOk();
        asToken($reader, 'GET', "/api/v1/users/{$other->id}")->assertOk();
    });

    it('limits dev to self-service', function () {
        $dev = seededUser('dev');
        $other = seededUser('viewer');

        asToken($dev, 'GET', '/api/v1/users')->assertNotFound();
        asToken($dev, 'GET', "/api/v1/users/{$other->id}")->assertNotFound();
        asToken($dev, 'GET', '/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $dev->id);
    });
});

describe('Stable permission keys', function () {
    it('keeps authorization when a menu route_name or label is renamed', function () {
        $admin = seededUser('admin');
        $reader = userWithPermissions(['menus' => ['view']]);
        $menusMenu = Menu::where('key', 'menus')->firstOrFail();

        asToken($admin, 'PUT', "/api/v1/menus/{$menusMenu->id}", [
            'route_name' => 'navigation.index',
            'label' => 'Navigation',
        ])->assertOk();

        asToken($admin, 'GET', '/api/v1/menus')->assertOk();
        asToken($reader, 'GET', '/api/v1/menus')->assertOk();
    });

    it('does not allow changing a menu key', function () {
        $menu = Menu::where('key', 'users')->firstOrFail();

        asToken(seededUser('admin'), 'PUT', "/api/v1/menus/{$menu->id}", ['key' => 'people'])
            ->assertUnprocessable()->assertJsonValidationErrors('key');

        expect($menu->fresh()->key)->toBe('users');
    });

    it('does not allow deleting a system menu', function () {
        $menu = Menu::where('key', 'audit-logs')->firstOrFail();

        asToken(seededUser('admin'), 'DELETE', "/api/v1/menus/{$menu->id}")
            ->assertForbidden();

        expect($menu->fresh())->not->toBeNull();
    });

    it('does not accept is_system from the client', function () {
        asToken(seededUser('admin'), 'POST', '/api/v1/menus', [
            'label' => 'Reports',
            'key' => 'reports',
            'route_name' => 'reports.index',
            'is_system' => true,
        ])->assertCreated();

        expect(Menu::where('key', 'reports')->first()->is_system)->toBeFalse();
    });

    it('requires a well-formed key when creating a menu', function (mixed $key) {
        asToken(seededUser('admin'), 'POST', '/api/v1/menus', [
            'label' => 'Reports',
            'key' => $key,
            'route_name' => 'reports.index',
        ])->assertUnprocessable()->assertJsonValidationErrors('key');
    })->with([null, 'Reports', 'reports.view', 'users']);

    it('still lets admin delete an unused custom profile', function () {
        $profile = Profile::factory()->create();

        asToken(seededUser('admin'), 'DELETE', "/api/v1/profiles/{$profile->id}")->assertOk();
    });
});
