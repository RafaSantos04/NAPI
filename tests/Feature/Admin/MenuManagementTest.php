<?php

use App\Domain\IAM\Actions\DeleteMenu;
use App\Domain\IAM\Actions\UpdateMenu;
use App\Domain\IAM\AuditContext;
use App\Domain\IAM\Exceptions\ResourceInUseException;
use App\DTOs\UpdateMenuDto;
use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Phase 4.4: menu administration. The key is the permission identity and
// never changes; everything else is navigation. Same request rules, Policies
// and Actions as the API.

/**
 * The menu form as the browser sends it (create when $menu is null).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function menuForm(?Menu $menu = null, array $overrides = []): array
{
    $fields = $menu === null
        ? ['key' => 'relatorios', 'label' => 'Relatórios', 'route_name' => 'reports.index', 'icon' => 'chart', 'parent_id' => '', 'order' => '7', 'is_active' => '1', 'description' => '']
        : [
            'label' => $menu->label,
            'route_name' => $menu->route_name,
            'icon' => $menu->icon ?? '',
            'parent_id' => $menu->parent_id ?? '',
            'order' => (string) $menu->order,
            'is_active' => $menu->is_active ? '1' : '0',
            'description' => $menu->description ?? '',
        ];

    return [...$fields, ...$overrides];
}

/**
 * The menu's profiles panel as the browser sends it: for each listed
 * profile, "1" on the given actions and "0" on the others.
 *
 * @param  array<string, array<int, string>>  $grants  profile id => actions
 * @return array{profiles: array<string, array<string, string>>}
 */
function menuProfilesForm(array $grants): array
{
    $profiles = [];

    foreach ($grants as $profileId => $actions) {
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $profiles[$profileId]["can_{$action}"] = in_array($action, $actions, true) ? '1' : '0';
        }
    }

    return ['profiles' => $profiles];
}

/**
 * Keys of the API navigation tree, nested as in the response.
 *
 * @param  array<int, array<string, mixed>>  $nodes
 * @return array<string, mixed>
 */
function navigationKeys(array $nodes): array
{
    return collect($nodes)->mapWithKeys(fn (array $node) => [$node['key'] => navigationKeys($node['children'] ?? [])])->all();
}

describe('Menu list', function () {
    it('lists the hierarchy with key, route, parent, order, status and type', function () {
        $response = $this->actingAs(seededUser('admin'))
            ->get('/admin/menus')
            ->assertOk()
            ->assertSeeInOrder(['Users', 'Profiles', 'Permissions', 'Menus', 'Audit Logs', 'Security Lab'])
            ->assertSee('<code>permissions</code>', false)
            ->assertSee('profiles.index')
            ->assertSee('Sistema');

        // Permissions is shown one level under Profiles, its parent.
        $rows = collect($response->viewData('menus'));
        $permissions = $rows->first(fn (array $row) => $row['menu']->key === 'permissions');

        expect($permissions['depth'])->toBe(1)
            ->and($permissions['menu']->parent->key)->toBe('profiles');
    });

    it('loads the hierarchy in one query', function () {
        DB::enableQueryLog();
        $this->actingAs(seededUser('admin'))->get('/admin/menus')->assertOk();
        $before = count(DB::getQueryLog());

        Menu::factory()->count(5)->withParent(Menu::where('key', 'menus')->sole())->create();
        DB::flushQueryLog();
        $this->actingAs(seededUser('admin'))->get('/admin/menus')->assertOk();

        expect(count(DB::getQueryLog()))->toBe($before);
    });

    it('answers 404 without menus.view', function () {
        $this->actingAs(userWithPermissions(['users' => ['view']]))
            ->get('/admin/menus')
            ->assertNotFound();
    });

    it('lets a menus-only profile into the admin area with just that section', function () {
        $user = userWithPermissions(['menus' => ['view']]);

        adminLogin($user)->assertRedirect(route('admin.home'));

        $this->get('/admin')
            ->assertOk()
            ->assertSee('href="'.route('admin.menus.index').'"', false)
            ->assertDontSee('href="'.route('admin.profiles.index').'"', false);
    });
});

describe('Create menu', function () {
    it('creates a custom menu and audits it once', function () {
        $this->actingAs(seededUser('admin'))
            ->post('/admin/menus', menuForm(overrides: ['is_system' => '1']))
            ->assertRedirect();

        $menu = Menu::where('key', 'relatorios')->sole();

        expect($menu)
            ->is_system->toBeFalse()
            ->parent_id->toBeNull()
            ->order->toBe(7)
            ->description->toBeNull()
            ->and(AuditLog::where('action', 'menu_created')->where('subject_id', $menu->id)->count())->toBe(1);
    });

    it('rejects invalid data with messages in Portuguese', function (array $input, array $errors) {
        $this->actingAs(seededUser('admin'))
            ->from('/admin/menus/create')
            ->post('/admin/menus', menuForm(overrides: $input))
            ->assertRedirect('/admin/menus/create')
            ->assertSessionHasErrors($errors);

        expect(AuditLog::where('action', 'menu_created')->exists())->toBeFalse();
    })->with([
        'key outside the format' => [['key' => 'Relatórios Gerais'], ['key' => 'Use letras minúsculas, números e hífens, como em relatorios-financeiros.']],
        'key in use' => [['key' => 'users'], ['key' => 'A chave já está em uso.']],
        'route in use' => [['route_name' => 'users.index'], ['route_name' => 'A rota já está em uso.']],
        'missing label' => [['label' => ''], ['label' => 'Informe o rótulo.']],
        'negative order' => [['order' => '-1'], ['order' => 'A ordem não pode ser negativa.']],
    ]);

    it('requires menus.create', function () {
        $this->actingAs(userWithPermissions(['menus' => ['view']]));

        $this->get('/admin/menus/create')->assertNotFound();
        $this->post('/admin/menus', menuForm())->assertNotFound();

        expect(Menu::where('key', 'relatorios')->exists())->toBeFalse();
    });

    it('gives the administrator the new area at once, and nobody else', function () {
        $this->actingAs(seededUser('admin'))->post('/admin/menus', menuForm());

        expect(seededUser('admin')->hasPermission('relatorios.delete'))->toBeTrue()
            ->and(userWithPermissions(['users' => ['view']])->hasPermission('relatorios.view'))->toBeFalse();
    });
});

describe('Update menu', function () {
    it('updates the navigation fields and audits their names only', function () {
        $menu = Menu::factory()->create(['label' => 'Antigo', 'order' => 2]);

        $this->actingAs(seededUser('admin'));

        $this->put("/admin/menus/{$menu->id}", menuForm($menu, ['label' => 'Novo', 'order' => '9', 'is_active' => '0']))
            ->assertRedirect(route('admin.menus.show', $menu));

        // Same values again: nothing recorded.
        $this->put("/admin/menus/{$menu->id}", menuForm($menu->fresh()));

        expect($menu->fresh())
            ->label->toBe('Novo')
            ->order->toBe(9)
            ->is_active->toBeFalse();

        expect(AuditLog::where('action', 'menu_updated')->sole()->meta)->toEqual(['fields' => ['label', 'order', 'is_active']]);
    });

    it('never changes the key, in either channel', function (string $key) {
        $menu = Menu::where('key', $key)->first() ?? Menu::factory()->create(['key' => $key]);

        $this->actingAs(seededUser('admin'))
            ->put("/admin/menus/{$menu->id}", menuForm($menu, ['key' => 'outra-chave']))
            ->assertSessionHasErrors(['key' => 'A chave de um menu não pode ser alterada.']);

        asToken(seededUser('admin'), 'PUT', "/api/v1/menus/{$menu->id}", ['key' => 'outra-chave'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');

        expect($menu->fresh()->key)->toBe($key);
    })->with(['system menu' => ['users'], 'custom menu' => ['relatorios']]);

    it('cannot change the key through the use case either', function () {
        // The DTO drops anything outside the navigation fields, whoever
        // builds it, so the request is not the only barrier.
        $menu = Menu::where('key', 'users')->sole();

        UpdateMenu::execute($menu, new UpdateMenuDto(['key' => 'outra-chave', 'label' => 'Contas']), AuditContext::system());

        expect($menu->fresh())->key->toBe('users')->label->toBe('Contas');
    });

    it('shows the key on the edit form as text, not as a field', function () {
        $menu = Menu::where('key', 'users')->sole();

        $this->actingAs(seededUser('admin'))
            ->get("/admin/menus/{$menu->id}/edit")
            ->assertOk()
            ->assertSee('<code>users</code>', false)
            ->assertDontSee('name="key"', false);
    });

    it('expects every field of the form, not a partial update', function () {
        $menu = Menu::factory()->create();
        $form = menuForm($menu);
        unset($form['label']);

        $this->actingAs(seededUser('admin'))
            ->put("/admin/menus/{$menu->id}", $form)
            ->assertSessionHasErrors(['label' => 'Informe o rótulo.']);
    });

    it('rejects an emptied label and lets optional fields be cleared', function () {
        $menu = Menu::factory()->create(['icon' => 'menu', 'description' => 'Texto']);

        $this->actingAs(seededUser('admin'));

        $this->put("/admin/menus/{$menu->id}", menuForm($menu, ['label' => '']))
            ->assertSessionHasErrors(['label' => 'Informe o rótulo.']);

        $this->put("/admin/menus/{$menu->id}", menuForm($menu, ['icon' => '', 'description' => '']))
            ->assertSessionHasNoErrors();

        expect($menu->fresh())->icon->toBeNull()->description->toBeNull();
    });

    it('requires menus.update', function () {
        $menu = Menu::factory()->create(['label' => 'Antigo']);

        $this->actingAs(userWithPermissions(['menus' => ['view']]));

        $this->get("/admin/menus/{$menu->id}/edit")->assertNotFound();
        $this->put("/admin/menus/{$menu->id}", menuForm($menu, ['label' => 'Novo']))->assertNotFound();

        expect($menu->fresh()->label)->toBe('Antigo');
    });
});

describe('Hierarchy', function () {
    beforeEach(function () {
        // a → b → c
        $this->a = Menu::factory()->create(['key' => 'a']);
        $this->b = Menu::factory()->withParent($this->a)->create(['key' => 'b']);
        $this->c = Menu::factory()->withParent($this->b)->create(['key' => 'c']);
    });

    it('refuses a menu as its own parent or under one of its submenus, in either channel', function (string $parent) {
        $target = $this->{$parent};

        $this->actingAs(seededUser('admin'))
            ->put("/admin/menus/{$this->a->id}", menuForm($this->a, ['parent_id' => $target->id]))
            ->assertSessionHasErrors(['parent_id' => 'Um menu não pode ficar abaixo de si mesmo nem de um dos seus submenus.']);

        asToken(seededUser('admin'), 'PUT', "/api/v1/menus/{$this->a->id}", ['parent_id' => $target->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');

        expect($this->a->fresh()->parent_id)->toBeNull();
    })->with(['itself' => ['a'], 'its child' => ['b'], 'its grandchild' => ['c']]);

    it('cannot store a menu as its own parent even bypassing the application', function () {
        expect(fn () => DB::table('menus')->where('id', $this->a->id)->update(['parent_id' => $this->a->id]))
            ->toThrow(QueryException::class);
    });

    it('moves a branch under another menu, or back to the root', function () {
        $other = Menu::factory()->create(['key' => 'other']);

        $this->actingAs(seededUser('admin'));

        $this->put("/admin/menus/{$this->b->id}", menuForm($this->b, ['parent_id' => $other->id]))->assertSessionHasNoErrors();
        expect($this->b->fresh()->parent_id)->toBe($other->id);

        $this->put("/admin/menus/{$this->b->id}", menuForm($this->b->fresh(), ['parent_id' => '']))->assertSessionHasNoErrors();
        expect($this->b->fresh()->parent_id)->toBeNull()
            ->and($this->c->fresh()->parent_id)->toBe($this->b->id);
    });

    it('does not offer the menu or its branch as a parent', function () {
        $parents = $this->actingAs(seededUser('admin'))
            ->get("/admin/menus/{$this->a->id}/edit")
            ->assertOk()
            ->viewData('parents');

        $offered = collect($parents)->map(fn (array $row) => $row['menu']->key);

        expect($offered)->not->toContain('a', 'b', 'c')
            ->and($offered)->toContain('users');
    });
});

describe('Delete menu', function () {
    it('deletes a custom menu without submenus, with its grants, and audits its key', function () {
        $menu = Menu::factory()->create(['key' => 'relatorios']);
        $profile = Profile::factory()->create();
        $profile->menus()->attach($menu->id, ['can_view' => true]);

        $this->actingAs(seededUser('admin'))
            ->get("/admin/menus/{$menu->id}")
            ->assertSee('1 perfil perde as permissões desta área.');

        $this->delete("/admin/menus/{$menu->id}")->assertRedirect(route('admin.menus.index'));

        expect(Menu::whereKey($menu->id)->exists())->toBeFalse()
            ->and($profile->menus()->count())->toBe(0)
            ->and(AuditLog::where('action', 'menu_deleted')->sole()->meta)->toEqual(['key' => 'relatorios', 'is_system' => false]);
    });

    it('refuses a menu with submenus, including for the administrator', function () {
        $target = tap(Menu::factory()->create(), fn (Menu $parent) => Menu::factory()->withParent($parent)->create());

        $this->actingAs(seededUser('admin'))
            ->delete("/admin/menus/{$target->id}")
            ->assertForbidden();

        expect(Menu::whereKey($target->id)->exists())->toBeTrue()
            ->and(AuditLog::where('action', 'menu_deleted')->exists())->toBeFalse();
    });

    it('lets only the administrator delete a system menu, until the seeder brings it back', function () {
        $menu = Menu::where('key', 'audit-logs')->sole();
        $reader = userWithPermissions(['audit-logs' => ['view'], 'menus' => ['view', 'delete']]);

        $this->actingAs($reader)->delete("/admin/menus/{$menu->id}")->assertForbidden();

        $this->actingAs(seededUser('admin'))
            ->get("/admin/menus/{$menu->id}")
            ->assertSee('fica só para o Administrador');

        $this->delete("/admin/menus/{$menu->id}")->assertRedirect(route('admin.menus.index'));

        // The area is the administrator's alone: the grants went with the menu.
        expect(Menu::where('key', 'audit-logs')->exists())->toBeFalse()
            ->and(User::find($reader->id)->hasPermission('audit-logs.view'))->toBeFalse()
            ->and(seededUser('admin')->hasPermission('audit-logs.view'))->toBeTrue()
            ->and(AuditLog::where('action', 'menu_deleted')->sole()->meta)->toEqual(['key' => 'audit-logs', 'is_system' => true]);

        $this->seed(MenuSeeder::class);

        expect(Menu::where('key', 'audit-logs')->sole()->is_system)->toBeTrue();
    });

    it('keeps a menu with submenus even when the Policy is bypassed', function () {
        // The Action checks again with the row locked: a submenu created
        // after the Policy ran is never cascaded away.
        $parent = Menu::factory()->create();
        $child = Menu::factory()->withParent($parent)->create();

        expect(fn () => DeleteMenu::execute($parent, AuditContext::system()))
            ->toThrow(ResourceInUseException::class);

        expect(Menu::whereKey($child->id)->exists())->toBeTrue();
    });

    it('requires menus.delete', function () {
        $menu = Menu::factory()->create();

        $this->actingAs(userWithPermissions(['menus' => ['view', 'update']]))
            ->delete("/admin/menus/{$menu->id}")
            ->assertNotFound();

        expect(Menu::whereKey($menu->id)->exists())->toBeTrue();
    });
});

describe('Navigation and permission identity', function () {
    it('removes an inactive menu from the navigation tree and brings it back', function () {
        $menu = Menu::where('key', 'audit-logs')->sole();

        $this->actingAs(seededUser('admin'))->put("/admin/menus/{$menu->id}", menuForm($menu, ['is_active' => '0']));
        expect(navigationKeys(asToken(seededUser('admin'), 'GET', '/api/v1/menus/tree')->json('data')))->not->toHaveKey('audit-logs');

        $this->actingAs(seededUser('admin'))->put("/admin/menus/{$menu->id}", menuForm($menu->fresh(), ['is_active' => '1']));
        expect(navigationKeys(asToken(seededUser('admin'), 'GET', '/api/v1/menus/tree')->json('data')))->toHaveKey('audit-logs');
    });

    it('hides a submenu from a profile that cannot view it', function () {
        $this->actingAs(seededUser('admin'));
        $this->post('/admin/menus', menuForm(overrides: ['key' => 'relatorios', 'route_name' => 'reports.index']));
        $parent = Menu::where('key', 'relatorios')->sole();
        $this->post('/admin/menus', menuForm(overrides: ['key' => 'relatorios-financeiros', 'route_name' => 'reports.finance', 'parent_id' => $parent->id]));

        $reader = userWithPermissions(['relatorios' => ['view']]);
        $tree = navigationKeys(asToken($reader, 'GET', '/api/v1/menus/tree')->json('data'));

        expect($tree)->toBe(['relatorios' => []]);
    });

    it('keeps the permission when label and route change', function () {
        $holder = userWithPermissions(['users' => ['view']]);
        $menu = Menu::where('key', 'users')->sole();

        $this->actingAs(seededUser('admin'))
            ->put("/admin/menus/{$menu->id}", menuForm($menu, ['label' => 'Contas', 'route_name' => 'accounts.index']))
            ->assertSessionHasNoErrors();

        expect($menu->fresh())->label->toBe('Contas')->key->toBe('users')
            ->and(User::find($holder->id)->hasPermission('users.view'))->toBeTrue();

        $this->actingAs(User::find($holder->id))->get('/admin/users')->assertOk();
    });
});

describe('Profiles of a menu', function () {
    beforeEach(function () {
        $this->menu = Menu::where('key', 'users')->sole();
        $this->edit = "/admin/menus/{$this->menu->id}/edit";
        $this->save = "/admin/menus/{$this->menu->id}/permissions";
    });

    it('lists every profile with its actions on the menu', function () {
        $support = profileWithPermissions(['users' => ['view']]);

        $content = $this->actingAs(seededUser('admin'))
            ->get($this->edit)
            ->assertOk()
            ->assertSee($support->name)
            ->assertSee('todas, implicitamente')
            ->assertSee('action="'.route('admin.menus.permissions.update', $this->menu).'"', false)
            ->getContent();

        expect($content)
            ->toMatch('/name="profiles\['.$support->id.'\]\[can_view\]" value="1" checked/')
            // The administrator manages system profiles too, but the
            // administrator profile holds everything implicitly: no field.
            ->toContain('profiles['.Profile::where('slug', 'viewer')->value('id').'][can_view]')
            ->not->toContain('profiles['.Profile::where('slug', Profile::ADMIN)->value('id').']');
    });

    it('links a profile to the menu without touching its other rows', function () {
        $support = profileWithPermissions(['profiles' => ['view']]);

        $this->actingAs(seededUser('admin'))
            ->put($this->save, menuProfilesForm([$support->id => ['view', 'create']]))
            ->assertRedirect(route('admin.menus.edit', $this->menu));

        expect($support->fresh()->permissionKeys()->all())->toEqualCanonicalizing(['profiles.view', 'users.view', 'users.create'])
            ->and(AuditLog::where('action', 'permission_changed')->sole()->subject_id)->toBe($support->id);
    });

    it('unlinks a profile when every action is cleared', function () {
        $support = profileWithPermissions(['users' => ['view', 'update'], 'profiles' => ['view']]);

        $this->actingAs(seededUser('admin'))->put($this->save, menuProfilesForm([$support->id => []]));

        expect(DB::table('menu_profiles')->where('profile_id', $support->id)->where('menu_id', $this->menu->id)->exists())->toBeFalse()
            ->and($support->fresh()->permissionKeys()->all())->toBe(['profiles.view']);
    });

    it('writes and audits only the profiles whose row changes', function () {
        $unchanged = profileWithPermissions(['users' => ['view']]);
        $changed = Profile::factory()->create();

        $this->actingAs(seededUser('admin'))->put($this->save, menuProfilesForm([
            $unchanged->id => ['view'],
            $changed->id => ['view'],
        ]));

        expect(AuditLog::where('action', 'permission_changed')->sole()->subject_id)->toBe($changed->id)
            ->and($changed->fresh()->permissionKeys()->all())->toBe(['users.view']);
    });

    it('is read-only without permissions.update', function () {
        $support = Profile::factory()->create();

        $this->actingAs(userWithPermissions(['menus' => ['view', 'update']]));

        $this->get($this->edit)
            ->assertOk()
            ->assertSee('não alterar estes vínculos')
            ->assertDontSee(route('admin.menus.permissions.update', $this->menu));

        $this->put($this->save, menuProfilesForm([$support->id => ['view']]))->assertNotFound();

        expect($support->menus()->count())->toBe(0);
    });

    it('applies the anti-escalation rules of the profile matrix', function () {
        $operator = userWithPermissions(['menus' => ['view', 'update'], 'permissions' => ['update'], 'users' => ['view']]);
        $own = $operator->profiles()->sole();
        $support = Profile::factory()->create();
        $other = Profile::factory()->create();

        $this->actingAs($operator);

        // A flag the operator does not hold, even next to an allowed change:
        // nothing is written.
        $this->put($this->save, menuProfilesForm([$support->id => ['view', 'delete'], $other->id => ['view']]))
            ->assertForbidden();
        // Their own profile, as on the profile page.
        $this->put($this->save, menuProfilesForm([$own->id => ['view', 'update']]))->assertForbidden();

        expect($support->menus()->count() + $other->menus()->count())->toBe(0)
            ->and(AuditLog::where('action', 'permission_changed')->exists())->toBeFalse();

        // What they hold, they may grant.
        $this->put($this->save, menuProfilesForm([$support->id => ['view']]))->assertRedirect();

        expect($support->fresh()->permissionKeys()->all())->toBe(['users.view']);
    });

    it('lets only the administrator link a system profile, and nobody the administrator profile', function () {
        $viewer = Profile::where('slug', 'viewer')->sole();
        $operator = userWithPermissions(['menus' => ['view', 'update'], 'permissions' => ['update'], 'users' => ['view']]);

        $this->actingAs($operator)
            ->put($this->save, menuProfilesForm([$viewer->id => ['view']]))
            ->assertForbidden();

        $this->actingAs(seededUser('admin'))
            ->put($this->save, menuProfilesForm([Profile::where('slug', Profile::ADMIN)->value('id') => ['view']]))
            ->assertForbidden();

        expect($viewer->menus()->count())->toBe(0);

        $this->put($this->save, menuProfilesForm([$viewer->id => ['view']]))->assertRedirect();

        expect($viewer->fresh()->permissionKeys()->all())->toBe(['users.view']);
    });

    it('rejects a profile that does not exist', function () {
        $this->actingAs(seededUser('admin'))
            ->put($this->save, menuProfilesForm(['01jzzzzzzzzzzzzzzzzzzzzzzz' => ['view']]))
            ->assertSessionHasErrors(['profiles' => 'A lista tem perfis que não existem.']);
    });
});
