<?php

use App\Domain\IAM\Actions\DeleteProfile;
use App\Domain\IAM\AuditContext;
use App\Domain\IAM\Exceptions\ResourceInUseException;
use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Phase 4.3: profiles and their permission matrix in the admin area. Same
// Policies, Actions, invariants and audit as the API; only the adapter differs.

/**
 * The matrix form as the browser sends it: every menu and action, "0" unless
 * listed in $grants (e.g. ['users.view']).
 *
 * @param  array<int, string>  $grants
 * @return array{permissions: array<string, array<string, string>>}
 */
function matrixForm(array $grants = []): array
{
    $permissions = [];

    foreach (Menu::all() as $menu) {
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $permissions[$menu->id]["can_{$action}"] = in_array($menu->permissionFor($action), $grants, true) ? '1' : '0';
        }
    }

    return ['permissions' => $permissions];
}

describe('Profile list', function () {
    it('lists profiles with identifier, type, users and granted permissions', function () {
        $custom = profileWithPermissions(['users' => ['view', 'update']]);
        $custom->users()->attach(User::factory()->create());

        $response = $this->actingAs(seededUser('admin'))
            ->get('/admin/profiles')
            ->assertOk()
            ->assertSee($custom->name)
            ->assertSee($custom->slug)
            ->assertSee('Sistema')
            ->assertSee('Personalizado')
            ->assertSee('todas (implícito)');

        $row = collect($response->viewData('profiles')->items())->firstWhere('id', $custom->id);

        expect($row->users_count)->toBe(1)
            ->and((int) $row->permissions_count)->toBe(2);
    });

    it('paginates and counts without one query per profile', function () {
        Profile::factory()->count(3)->create();

        DB::enableQueryLog();
        $this->actingAs(seededUser('admin'))->get('/admin/profiles')->assertOk();
        $queriesForFew = count(DB::getQueryLog());

        Profile::factory()->count(12)->create();
        DB::flushQueryLog();
        $response = $this->actingAs(seededUser('admin'))->get('/admin/profiles')->assertOk();

        expect(count(DB::getQueryLog()))->toBe($queriesForFew)
            ->and($response->viewData('profiles')->count())->toBe(15)
            ->and($response->viewData('profiles')->total())->toBe(18);
    });

    it('answers 404 without profiles.view', function () {
        $this->actingAs(userWithPermissions(['users' => ['view']]))
            ->get('/admin/profiles')
            ->assertNotFound();
    });

    it('shows the section only to users who may view profiles', function () {
        $this->actingAs(userWithPermissions(['profiles' => ['view']]))
            ->get('/admin')
            ->assertOk()
            ->assertSee('href="'.route('admin.profiles.index').'"', false)
            ->assertDontSee('href="'.route('admin.users.index').'"', false)
            ->assertDontSee('href="'.route('admin.menus.index').'"', false);
    });
});

describe('Create profile', function () {
    it('creates a custom profile without permissions and audits it once', function () {
        $this->actingAs(seededUser('admin'))
            ->post('/admin/profiles', [
                'name' => 'Auditoria',
                'slug' => 'auditoria',
                'description' => 'Consulta a trilha',
                // Not a field of the operation: ignored.
                'is_system' => '1',
            ])
            ->assertRedirect();

        $profile = Profile::where('slug', 'auditoria')->sole();

        expect($profile->is_system)->toBeFalse()
            ->and($profile->menus()->count())->toBe(0)
            ->and(AuditLog::where('action', 'profile_created')->where('subject_id', $profile->id)->count())->toBe(1);
    });

    it('rejects invalid data with messages in Portuguese', function (array $input, array $errors) {
        $this->actingAs(seededUser('admin'))
            ->from('/admin/profiles/create')
            ->post('/admin/profiles', [...['name' => 'Novo', 'slug' => 'novo', 'description' => ''], ...$input])
            ->assertRedirect('/admin/profiles/create')
            ->assertSessionHasErrors($errors);

        expect(AuditLog::where('action', 'profile_created')->exists())->toBeFalse();
    })->with([
        'empty name' => [['name' => ''], ['name' => 'Informe o nome.']],
        'slug with spaces' => [['slug' => 'com espaço'], ['slug' => 'O identificador só pode ter letras, números, hífens e sublinhados.']],
        'slug in use' => [['slug' => 'admin'], ['slug' => 'O identificador já está em uso.']],
    ]);

    it('requires profiles.create', function () {
        $this->actingAs(userWithPermissions(['profiles' => ['view']]));

        $this->get('/admin/profiles/create')->assertNotFound();
        $this->post('/admin/profiles', ['name' => 'X', 'slug' => 'x'])->assertNotFound();

        expect(Profile::where('slug', 'x')->exists())->toBeFalse();
    });
});

describe('Update profile', function () {
    it('updates a custom profile and audits the changed fields only', function () {
        $profile = Profile::factory()->create(['name' => 'Suporte', 'slug' => 'suporte', 'description' => 'Antiga']);

        $this->actingAs(seededUser('admin'));

        $this->put("/admin/profiles/{$profile->id}", ['name' => 'Suporte N1', 'slug' => 'suporte', 'description' => ''])
            ->assertRedirect(route('admin.profiles.show', $profile));

        // Same values again: nothing changes, nothing is recorded.
        $this->put("/admin/profiles/{$profile->id}", ['name' => 'Suporte N1', 'slug' => 'suporte', 'description' => '']);

        expect($profile->fresh())
            ->name->toBe('Suporte N1')
            ->description->toBeNull();

        expect(AuditLog::where('action', 'profile_updated')->sole()->meta)->toEqual(['fields' => ['name', 'description']]);
    });

    it('rejects an emptied name', function () {
        $profile = Profile::factory()->create();

        $this->actingAs(seededUser('admin'))
            ->put("/admin/profiles/{$profile->id}", ['name' => '', 'slug' => $profile->slug, 'description' => ''])
            ->assertSessionHasErrors(['name' => 'Informe o nome.']);
    });

    it('keeps system profiles immutable, including for the administrator', function () {
        $viewer = Profile::where('slug', 'viewer')->sole();

        $this->actingAs(seededUser('admin'));

        $this->get("/admin/profiles/{$viewer->id}/edit")->assertForbidden();
        $this->put("/admin/profiles/{$viewer->id}", ['name' => 'Renomeado', 'slug' => 'viewer', 'description' => ''])->assertForbidden();
        $this->get("/admin/profiles/{$viewer->id}")
            ->assertOk()
            ->assertSee('Perfil de sistema')
            ->assertDontSee(route('admin.profiles.edit', $viewer));

        expect($viewer->fresh()->name)->toBe('Viewer');
    });
});

describe('Delete profile', function () {
    it('deletes a custom profile without users, with its matrix, and audits it', function () {
        $profile = profileWithPermissions(['users' => ['view']]);

        $this->actingAs(seededUser('admin'))
            ->delete("/admin/profiles/{$profile->id}")
            ->assertRedirect(route('admin.profiles.index'));

        expect(Profile::whereKey($profile->id)->exists())->toBeFalse()
            ->and(DB::table('menu_profiles')->where('profile_id', $profile->id)->exists())->toBeFalse()
            ->and(AuditLog::where('action', 'profile_deleted')->sole()->meta)
            ->toEqual(['slug' => $profile->slug, 'name' => $profile->name]);
    });

    it('refuses a system profile and a profile held by users', function (Closure $profile) {
        $target = $profile();

        $this->actingAs(seededUser('admin'))
            ->delete("/admin/profiles/{$target->id}")
            ->assertForbidden();

        expect(Profile::whereKey($target->id)->exists())->toBeTrue()
            ->and(AuditLog::where('action', 'profile_deleted')->exists())->toBeFalse();
    })->with([
        'system profile' => [fn () => Profile::where('slug', 'dev')->sole()],
        'profile with users' => [fn () => tap(Profile::factory()->create(), fn (Profile $p) => $p->users()->attach(User::factory()->create()))],
    ]);

    it('keeps a profile with users even when the Policy is bypassed', function () {
        // The Action checks again with the row locked: an assignment made
        // after the Policy ran is never cascaded away.
        $profile = Profile::factory()->create();
        $holder = User::factory()->create();
        $profile->users()->attach($holder);

        expect(fn () => DeleteProfile::execute($profile, AuditContext::system()))
            ->toThrow(ResourceInUseException::class);

        expect($holder->profiles()->whereKey($profile->id)->exists())->toBeTrue()
            ->and(AuditLog::where('action', 'profile_deleted')->exists())->toBeFalse();
    });

    it('answers the conflict as 409 in the API and as a flash message in the admin area', function () {
        $exception = ResourceInUseException::profileHasUsers();

        $api = $exception->render(Request::create('/api/v1/profiles/x', 'DELETE'));
        $web = $exception->render(Request::create('/admin/profiles/x', 'DELETE'));

        expect($api->getStatusCode())->toBe(409)
            ->and($web->isRedirect())->toBeTrue()
            ->and(session('error'))->toBe('O perfil está atribuído a usuários e não pode ser excluído.');
    });
});

describe('Permission matrix', function () {
    it('shows every area by key, with its four actions', function () {
        $profile = Profile::factory()->create();

        $this->actingAs(seededUser('admin'))
            ->get("/admin/profiles/{$profile->id}")
            ->assertOk()
            ->assertSeeInOrder(['Users', 'Profiles', 'Permissions', 'Menus', 'Audit Logs', 'Security Lab'])
            ->assertSee('name="permissions['.Menu::where('key', 'users')->value('id').'][can_delete]"', false)
            ->assertSee('action="'.route('admin.profiles.permissions.update', $profile).'"', false);
    });

    it('grants what is saved, and the holder can use it at once', function () {
        $profile = Profile::factory()->create();
        $holder = User::factory()->create();
        $profile->users()->attach($holder);

        $this->actingAs(seededUser('admin'))
            ->put("/admin/profiles/{$profile->id}/permissions", matrixForm(['users.view']))
            ->assertRedirect(route('admin.profiles.show', $profile));

        expect($holder->fresh()->hasPermission('users.view'))->toBeTrue()
            ->and($holder->fresh()->hasPermission('users.update'))->toBeFalse();

        // Same audit entry as the API: real booleans, not the form's "1"/"0".
        $usersMenu = Menu::where('key', 'users')->value('id');
        $flags = AuditLog::where('action', 'permission_changed')->sole()->meta['permissions'][$usersMenu];
        ksort($flags); // jsonb reorders keys

        expect($flags)->toBe(['can_create' => false, 'can_delete' => false, 'can_update' => false, 'can_view' => true]);

        $this->actingAs($holder->fresh())->get('/admin/users')->assertOk();
    });

    it('takes a removed permission away at once', function () {
        $profile = profileWithPermissions(['users' => ['view'], 'profiles' => ['view']]);
        $holder = User::factory()->create();
        $profile->users()->attach($holder);

        $this->actingAs(seededUser('admin'))
            ->put("/admin/profiles/{$profile->id}/permissions", matrixForm(['profiles.view']));

        $this->actingAs($holder->fresh())->get('/admin/users')->assertNotFound();
        $this->actingAs($holder->fresh())->get('/admin/profiles')->assertOk();
    });

    it('is read-only without permissions.update', function () {
        $profile = Profile::factory()->create();

        $this->actingAs(userWithPermissions(['profiles' => ['view']]));

        $this->get("/admin/profiles/{$profile->id}")
            ->assertOk()
            ->assertSee('não alterar esta matriz')
            ->assertDontSee(route('admin.profiles.permissions.update', $profile));

        $this->put("/admin/profiles/{$profile->id}/permissions", matrixForm(['users.view']))->assertNotFound();

        expect($profile->menus()->count())->toBe(0);
    });

    it('does not let a non-administrator grant what they do not hold, in either channel', function () {
        $operator = userWithPermissions(['profiles' => ['view'], 'permissions' => ['update'], 'users' => ['view']]);
        $target = Profile::factory()->create();

        $this->actingAs($operator)
            ->put("/admin/profiles/{$target->id}/permissions", matrixForm(['users.view', 'users.delete']))
            ->assertForbidden();

        asToken($operator, 'PUT', "/api/v1/profiles/{$target->id}/menus", matrixForm(['users.view', 'users.delete']))
            ->assertForbidden();

        // What they hold, they may grant.
        $this->actingAs($operator)
            ->put("/admin/profiles/{$target->id}/permissions", matrixForm(['users.view']))
            ->assertRedirect();

        expect($target->fresh()->permissionKeys()->all())->toBe(['users.view'])
            ->and(AuditLog::where('action', 'permission_changed')->count())->toBe(1);
    });

    it('marks the cells a non-administrator cannot grant', function () {
        $operator = userWithPermissions(['profiles' => ['view'], 'permissions' => ['update'], 'users' => ['view']]);
        $target = Profile::factory()->create();
        $usersMenu = Menu::where('key', 'users')->value('id');

        $content = $this->actingAs($operator)->get("/admin/profiles/{$target->id}")->assertOk()->getContent();

        expect($content)
            ->toMatch('/name="permissions\['.$usersMenu.'\]\[can_view\]" value="1"\s+aria-label/')
            ->toMatch('/name="permissions\['.$usersMenu.'\]\[can_delete\]" value="1"\s+disabled/');
    });

    it('does not let anyone but an administrator edit the matrix of a profile they hold', function () {
        $operator = userWithPermissions(['profiles' => ['view'], 'permissions' => ['update'], 'users' => ['view']]);
        $own = $operator->profiles()->sole();

        $this->actingAs($operator)
            ->get("/admin/profiles/{$own->id}")
            ->assertOk()
            ->assertDontSee(route('admin.profiles.permissions.update', $own));

        $this->actingAs($operator)
            ->put("/admin/profiles/{$own->id}/permissions", matrixForm(['users.view', 'profiles.view', 'permissions.update']))
            ->assertForbidden();
    });

    it('keeps the matrix of system profiles immutable, including for the administrator', function () {
        $viewer = Profile::where('slug', 'viewer')->sole();

        $this->actingAs(seededUser('admin'))
            ->put("/admin/profiles/{$viewer->id}/permissions", matrixForm(['users.view']))
            ->assertForbidden();

        expect($viewer->menus()->count())->toBe(0);
    });

    it('presents the administrator profile as holding every permission implicitly', function () {
        $admin = Profile::where('slug', Profile::ADMIN)->sole();

        $this->actingAs(seededUser('admin'))
            ->get("/admin/profiles/{$admin->id}")
            ->assertOk()
            ->assertSee('todas as permissões funcionais')
            ->assertDontSee(route('admin.profiles.permissions.update', $admin));
    });
});
