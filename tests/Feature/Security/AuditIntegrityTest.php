<?php

use App\Domain\IAM\AuditContext;
use App\Events\ProfileAssigned;
use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\Profile;
use App\Models\User;

// FIND-002, FIND-012, FIND-013: one domain event produces exactly one audit
// entry, carrying the actor and request context of the event itself.
function auditRows(string $action): int
{
    return AuditLog::where('action', $action)->count();
}

describe('Audit integrity', function () {
    it('records a login exactly once', function () {
        $this->postJson('/api/v1/auth/login', ['email' => 'admin@napi.dev', 'password' => 'password'])
            ->assertOk();

        expect(auditRows('login'))->toBe(1);
    });

    it('records a logout exactly once', function () {
        asToken(seededUser('admin'), 'POST', '/api/v1/auth/logout')->assertOk();

        expect(auditRows('logout'))->toBe(1);
    });

    it('records a profile assignment exactly once, with the actor', function () {
        $admin = seededUser('admin');
        $target = User::factory()->create();

        asToken($admin, 'PUT', "/api/v1/users/{$target->id}/profiles", [
            'profile_ids' => [Profile::factory()->create()->id],
        ])->assertOk();

        expect(auditRows('profile_assigned'))->toBe(1);
        expect(AuditLog::where('action', 'profile_assigned')->first())
            ->user_id->toBe($admin->id)
            ->subject_type->toBe(User::class)
            ->subject_id->toBe($target->id);
    });

    it('records the assigning actor on the pivot', function () {
        $admin = seededUser('admin');
        $target = User::factory()->create();
        $profile = Profile::factory()->create();

        asToken($admin, 'PUT', "/api/v1/users/{$target->id}/profiles", ['profile_ids' => [$profile->id]])
            ->assertOk();

        expect($target->profiles()->first()->pivot->assigned_by)->toBe($admin->id);
    });

    it('records a permission change exactly once, with the actor', function () {
        $admin = seededUser('admin');
        $profile = Profile::factory()->create();
        $menu = Menu::factory()->create();

        asToken($admin, 'PUT', "/api/v1/profiles/{$profile->id}/menus", [
            'permissions' => [$menu->id => [
                'can_view' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false,
            ]],
        ])->assertOk();

        expect(auditRows('permission_changed'))->toBe(1);
        expect(AuditLog::where('action', 'permission_changed')->first()->user_id)->toBe($admin->id);
    });

    it('records user creation, update and deletion exactly once each', function () {
        $admin = seededUser('admin');

        $id = asToken($admin, 'POST', '/api/v1/users', [
            'name' => 'New', 'email' => 'new@napi.dev', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertCreated()->json('id');

        asToken($admin, 'PUT', "/api/v1/users/{$id}", ['email' => 'renamed@napi.dev'])->assertOk();
        asToken($admin, 'DELETE', "/api/v1/users/{$id}")->assertOk();

        expect(auditRows('user_created'))->toBe(1)
            ->and(auditRows('user_updated'))->toBe(1)
            ->and(auditRows('user_deleted'))->toBe(1);

        expect(AuditLog::where('action', 'user_updated')->first()->meta)->toBe(['fields' => ['email']]);
        expect(AuditLog::where('action', 'user_deleted')->first())
            ->user_id->toBe($admin->id)
            ->subject_type->toBe(User::class)
            ->subject_id->toBe($id);
    });

    // Phases 4.3 and 4.4: profile and menu CRUD go through the same Actions
    // as the admin area, so the API records the same entries.
    it('records profile creation, update and deletion exactly once each', function () {
        $admin = seededUser('admin');

        $id = asToken($admin, 'POST', '/api/v1/profiles', ['name' => 'Suporte', 'slug' => 'suporte'])
            ->assertCreated()->json('id');

        asToken($admin, 'PUT', "/api/v1/profiles/{$id}", ['description' => 'Atendimento'])->assertOk();
        asToken($admin, 'DELETE', "/api/v1/profiles/{$id}")->assertOk();

        expect(auditRows('profile_created'))->toBe(1)
            ->and(auditRows('profile_updated'))->toBe(1)
            ->and(auditRows('profile_deleted'))->toBe(1);

        expect(AuditLog::where('action', 'profile_updated')->first()->meta)->toBe(['fields' => ['description']]);
        expect(AuditLog::where('action', 'profile_deleted')->first())
            ->user_id->toBe($admin->id)
            ->subject_type->toBe(Profile::class)
            ->subject_id->toBe($id)
            ->meta->toEqual(['slug' => 'suporte', 'name' => 'Suporte', 'is_system' => false]);
    });

    it('records menu creation, update and deletion exactly once each', function () {
        $admin = seededUser('admin');

        $id = asToken($admin, 'POST', '/api/v1/menus', ['key' => 'relatorios', 'label' => 'Relatórios', 'route_name' => 'reports.index'])
            ->assertCreated()->json('id');

        asToken($admin, 'PUT', "/api/v1/menus/{$id}", ['route_name' => 'reports.list'])->assertOk();
        asToken($admin, 'DELETE', "/api/v1/menus/{$id}")->assertOk();

        expect(auditRows('menu_created'))->toBe(1)
            ->and(auditRows('menu_updated'))->toBe(1)
            ->and(auditRows('menu_deleted'))->toBe(1);

        expect(AuditLog::where('action', 'menu_updated')->first()->meta)->toBe(['fields' => ['route_name']]);
        expect(AuditLog::where('action', 'menu_deleted')->first())
            ->user_id->toBe($admin->id)
            ->subject_type->toBe(Menu::class)
            ->subject_id->toBe($id)
            ->meta->toEqual(['key' => 'relatorios', 'is_system' => false]);
    });

    it('records nothing for a rejected profile or menu change', function () {
        // System profiles and menus are the administrator's alone.
        $delegate = userWithPermissions(['profiles' => ['update'], 'menus' => ['delete']]);
        $viewer = Profile::where('slug', 'viewer')->sole();

        asToken($delegate, 'PUT', "/api/v1/profiles/{$viewer->id}", ['name' => 'Outro'])->assertForbidden();
        asToken($delegate, 'DELETE', '/api/v1/menus/'.Menu::where('key', 'audit-logs')->value('id'))->assertForbidden();

        expect(AuditLog::whereIn('action', ['profile_updated', 'menu_deleted'])->exists())->toBeFalse();
    });

    it('records token creation and revocation exactly once each', function () {
        $admin = seededUser('admin');

        asToken($admin, 'POST', '/api/v1/tokens', ['name' => 'ci', 'abilities' => ['read']])->assertCreated();
        $tokenId = $admin->tokens()->where('name', 'ci')->value('id');
        asToken($admin, 'DELETE', "/api/v1/tokens/{$tokenId}")->assertOk();

        expect(auditRows('token_created'))->toBe(1)
            ->and(auditRows('token_revoked'))->toBe(1);
        expect(AuditLog::where('action', 'token_created')->first()->meta)
            ->toMatchArray(['token_id' => $tokenId, 'name' => 'ci', 'abilities' => ['read']]);
    });

    it('takes the actor from the event, not from the authenticated session', function () {
        $actor = seededUser('admin');
        $target = User::factory()->create();

        event(new ProfileAssigned($target, [], new AuditContext($actor->id, '10.0.0.1', 'cli')));

        expect(AuditLog::where('action', 'profile_assigned')->first())
            ->user_id->toBe($actor->id)
            ->ip->toBe('10.0.0.1')
            ->user_agent->toBe('cli');
    });
});
