<?php

use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;

function adminProfile(): Profile
{
    return Profile::where('slug', 'admin')->firstOrFail();
}

/**
 * @return array<string, array{can_view: bool, can_create: bool, can_update: bool, can_delete: bool}>
 */
function matrixRow(string $key, bool $view = false, bool $create = false, bool $update = false, bool $delete = false): array
{
    return [Menu::where('key', $key)->firstOrFail()->id => [
        'can_view' => $view, 'can_create' => $create, 'can_update' => $update, 'can_delete' => $delete,
    ]];
}

describe('Profile assignment escalation', function () {
    it('does not let a user without users.update assign profiles', function () {
        $target = User::factory()->create();

        asToken(seededUser('viewer'), 'PUT', "/api/v1/users/{$target->id}/profiles", [
            'profile_ids' => [adminProfile()->id],
        ])->assertNotFound();

        expect($target->profiles()->count())->toBe(0);
    });

    it('does not let a user assign a privileged profile to themselves', function () {
        $manager = userWithPermissions(['users' => ['view', 'update']]);

        asToken($manager, 'PUT', "/api/v1/users/{$manager->id}/profiles", [
            'profile_ids' => [...$manager->profiles->pluck('id'), adminProfile()->id],
        ])->assertForbidden();

        expect($manager->fresh()->isAdministrator())->toBeFalse();
    });

    it('does not let a non-admin grant the admin profile to someone else', function () {
        $manager = userWithPermissions(['users' => ['view', 'update']]);
        $accomplice = User::factory()->create();

        asToken($manager, 'PUT', "/api/v1/users/{$accomplice->id}/profiles", [
            'profile_ids' => [adminProfile()->id],
        ])->assertForbidden();

        expect($accomplice->fresh()->isAdministrator())->toBeFalse();
    });

    it('does not let a non-admin grant a profile with permissions they lack', function () {
        $manager = userWithPermissions(['users' => ['view', 'update']]);
        $powerful = profileWithPermissions(['profiles' => ['view', 'create', 'update', 'delete']]);
        $target = User::factory()->create();

        asToken($manager, 'PUT', "/api/v1/users/{$target->id}/profiles", [
            'profile_ids' => [$powerful->id],
        ])->assertForbidden();
    });

    it('lets a non-admin grant a profile within their own permissions', function () {
        $manager = userWithPermissions(['users' => ['view', 'update']]);
        $reader = profileWithPermissions(['users' => ['view']]);
        $target = User::factory()->create();

        asToken($manager, 'PUT', "/api/v1/users/{$target->id}/profiles", [
            'profile_ids' => [$reader->id],
        ])->assertOk();

        expect($target->profiles()->pluck('profiles.id')->all())->toBe([$reader->id]);
    });

    it('does not let a non-admin demote an administrator', function () {
        $manager = userWithPermissions(['users' => ['view', 'update']]);
        $secondAdmin = User::factory()->create();
        $secondAdmin->profiles()->attach(adminProfile()->id);

        asToken($manager, 'PUT', "/api/v1/users/{$secondAdmin->id}/profiles", [
            'profile_ids' => [],
        ])->assertForbidden();

        expect($secondAdmin->fresh()->isAdministrator())->toBeTrue();
    });

    it('does not let a non-admin edit or delete an administrator account', function () {
        $manager = userWithPermissions(['users' => ['view', 'update', 'delete']]);
        $admin = seededUser('admin');

        asToken($manager, 'PUT', "/api/v1/users/{$admin->id}", ['email' => 'owned@napi.dev'])
            ->assertForbidden();
        asToken($manager, 'DELETE', "/api/v1/users/{$admin->id}")->assertForbidden();

        expect($admin->fresh()->email)->toBe('admin@napi.dev');
    });
});

describe('Permission matrix escalation', function () {
    it('does not let a user without permissions.update change the matrix', function () {
        $profile = Profile::factory()->create();

        asToken(seededUser('viewer'), 'PUT', "/api/v1/profiles/{$profile->id}/menus", [
            'permissions' => matrixRow('users', view: true),
        ])->assertNotFound();

        expect($profile->menus()->count())->toBe(0);
    });

    it('does not let a token ability bypass the permission check', function () {
        $profile = Profile::factory()->create();

        asToken(seededUser('dev'), 'PUT', "/api/v1/profiles/{$profile->id}/menus", [
            'permissions' => matrixRow('users', view: true),
        ], ['read', 'write', 'delete'])->assertNotFound();
    });

    it('does not let a non-admin change the matrix of a profile they hold', function () {
        $delegate = User::factory()->create();
        $own = profileWithPermissions(['permissions' => ['update']], $delegate);

        asToken($delegate, 'PUT', "/api/v1/profiles/{$own->id}/menus", [
            'permissions' => matrixRow('permissions', update: true) + matrixRow('users', view: true, delete: true),
        ])->assertForbidden();

        expect($delegate->fresh()->hasPermission('users.delete'))->toBeFalse();
    });

    it('does not let a non-admin grant permissions they do not hold', function () {
        $delegate = userWithPermissions(['permissions' => ['update'], 'users' => ['view']]);
        $other = Profile::factory()->create();

        asToken($delegate, 'PUT', "/api/v1/profiles/{$other->id}/menus", [
            'permissions' => matrixRow('users', view: true, delete: true),
        ])->assertForbidden();
    });

    it('lets a non-admin grant permissions they hold to another profile', function () {
        $delegate = userWithPermissions(['permissions' => ['update'], 'users' => ['view']]);
        $other = Profile::factory()->create();

        asToken($delegate, 'PUT', "/api/v1/profiles/{$other->id}/menus", [
            'permissions' => matrixRow('users', view: true),
        ])->assertOk();
    });

    it('keeps system profiles immutable, including for admin', function () {
        asToken(seededUser('admin'), 'PUT', '/api/v1/profiles/'.adminProfile()->id.'/menus', [
            'permissions' => matrixRow('users'),
        ])->assertForbidden();
    });

    it('rejects unknown menu ids with 422 instead of 500', function () {
        $profile = Profile::factory()->create();

        asToken(seededUser('admin'), 'PUT', "/api/v1/profiles/{$profile->id}/menus", [
            'permissions' => ['01JZZZZZZZZZZZZZZZZZZZZZZZ' => [
                'can_view' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false,
            ]],
        ])->assertUnprocessable();
    });

    it('allows revoking every permission of a profile', function () {
        $profile = profileWithPermissions(['users' => ['view']]);

        asToken(seededUser('admin'), 'PUT', "/api/v1/profiles/{$profile->id}/menus", [
            'permissions' => [],
        ])->assertOk();

        expect($profile->menus()->count())->toBe(0);
    });
});
