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
