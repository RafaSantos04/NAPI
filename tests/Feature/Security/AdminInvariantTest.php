<?php

use App\Domain\IAM\Actions\DeleteUser;
use App\Domain\IAM\AuditContext;
use App\Domain\IAM\Exceptions\LastActiveAdministratorException;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;

// FIND-006: invariant "at least one active, non-deleted user holds the admin
// profile", enforced by the domain and reported as 409, never 500.
function makeAdmin(bool $active = true): User
{
    $user = User::factory()->create(['is_active' => $active]);
    $user->profiles()->attach(Profile::where('slug', 'admin')->firstOrFail()->id);

    return $user;
}

describe('Last active administrator invariant', function () {
    it('keeps the last active admin from losing the admin profile', function () {
        $admin = seededUser('admin');

        asToken($admin, 'PUT', "/api/v1/users/{$admin->id}/profiles", ['profile_ids' => []])
            ->assertStatus(409)
            ->assertJsonPath('message', 'The system must keep at least one active administrator.');

        expect($admin->fresh()->isAdministrator())->toBeTrue();
    });

    it('does not count inactive admins', function () {
        makeAdmin(active: false);
        $admin = seededUser('admin');

        asToken($admin, 'PUT', "/api/v1/users/{$admin->id}/profiles", ['profile_ids' => []])
            ->assertStatus(409);
    });

    it('does not count soft-deleted admins', function () {
        makeAdmin()->delete();
        $admin = seededUser('admin');

        asToken($admin, 'PUT', "/api/v1/users/{$admin->id}/profiles", ['profile_ids' => []])
            ->assertStatus(409);
    });

    it('allows the change when another active admin remains', function () {
        makeAdmin();
        $admin = seededUser('admin');

        asToken($admin, 'PUT', "/api/v1/users/{$admin->id}/profiles", ['profile_ids' => []])
            ->assertOk();

        expect($admin->fresh()->isAdministrator())->toBeFalse();
    });

    it('allows an admin to delete another admin while one remains', function () {
        $other = makeAdmin();

        asToken(seededUser('admin'), 'DELETE', "/api/v1/users/{$other->id}")->assertOk();

        expect(User::find($other->id))->toBeNull();
    });

    it('does not let a user deletion remove the last active admin', function () {
        // Only reachable if something other than the Policy lets the request
        // through, so the Action is exercised directly.
        $admin = seededUser('admin');

        expect(fn () => DeleteUser::execute($admin, AuditContext::system()))
            ->toThrow(LastActiveAdministratorException::class);

        expect(User::find($admin->id))->not->toBeNull();
    });

    it('leaves no audit trail for a rejected change', function () {
        $admin = seededUser('admin');

        asToken($admin, 'PUT', "/api/v1/users/{$admin->id}/profiles", ['profile_ids' => []])
            ->assertStatus(409);

        expect(AuditLog::where('action', 'profile_assigned')->count())->toBe(0);
    });
});
