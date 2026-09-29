<?php

use App\Models\Menu;

// FIND-010: the navigation tree is a projection of the functional permissions
// already decided by the backend, never a source of authorization.
function treeKeys(array $nodes): array
{
    $keys = [];

    foreach ($nodes as $node) {
        $keys[$node['key']] = treeKeys($node['children'] ?? []);
    }

    return $keys;
}

describe('Menu tree', function () {
    it('shows admin every active menu with its children', function () {
        $tree = asToken(seededUser('admin'), 'GET', '/api/v1/menus/tree')->assertOk()->json('data');

        expect(treeKeys($tree))->toBe([
            'users' => [],
            'profiles' => ['permissions' => []],
            'menus' => [],
            'audit-logs' => [],
        ]);
    });

    it('hides inactive roots and inactive children', function () {
        Menu::whereIn('key', ['audit-logs', 'permissions'])->update(['is_active' => false]);

        $tree = asToken(seededUser('admin'), 'GET', '/api/v1/menus/tree')->json('data');

        expect(treeKeys($tree))->toBe(['users' => [], 'profiles' => [], 'menus' => []]);
    });

    it('hides children the user cannot view', function () {
        $user = userWithPermissions(['profiles' => ['view']]);

        $tree = asToken($user, 'GET', '/api/v1/menus/tree')->assertOk()->json('data');

        expect(treeKeys($tree))->toBe(['profiles' => []]);
    });

    it('hides a child whose parent is not visible', function () {
        $user = userWithPermissions(['permissions' => ['view']]);

        expect(asToken($user, 'GET', '/api/v1/menus/tree')->json('data'))->toBe([]);
    });

    it('serves the tree to users without menu management permission', function () {
        asToken(seededUser('dev'), 'GET', '/api/v1/menus/tree')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    });

    it('supports more than one level of nesting', function () {
        $permissions = Menu::where('key', 'permissions')->firstOrFail();
        Menu::factory()->withParent($permissions)->create(['key' => 'permission-audit']);

        $tree = asToken(seededUser('admin'), 'GET', '/api/v1/menus/tree')->json('data');

        expect(treeKeys($tree)['profiles'])->toBe(['permissions' => ['permission-audit' => []]]);
    });

    it('does not grant access to what it shows', function () {
        $user = userWithPermissions(['profiles' => ['view']]);

        asToken($user, 'GET', '/api/v1/menus/tree')->assertOk();
        asToken($user, 'GET', '/api/v1/menus')->assertNotFound();
        asToken($user, 'POST', '/api/v1/profiles', ['name' => 'X', 'slug' => 'x'])->assertNotFound();
    });
});
