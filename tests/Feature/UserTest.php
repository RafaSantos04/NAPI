<?php

use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserDetails;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;

describe('User relations', function () {
    it('has one user details', function () {
        $user = User::factory()->create();
        expect($user->details)->toBeNull();

        $user->details()->create(['data_consent_at' => now()]);
        expect($user->refresh()->details)->toBeInstanceOf(UserDetails::class);
    });

    it('belongs to many profiles', function () {
        $user = User::factory()->create();
        $profiles = Profile::factory(2)->create();

        $user->profiles()->attach($profiles[0]->id);
        expect($user->profiles()->count())->toBe(1);
    });

    it('has profile by slug', function () {
        $user = User::factory()->create();
        $admin = Profile::factory()->create(['slug' => 'admin']);

        expect($user->hasProfile('admin'))->toBeFalse();
        $user->profiles()->attach($admin->id);

        // hasProfile() reads the cached `profiles` relation (see User model) to
        // avoid re-querying on every check within a request; since the relation
        // was already loaded above, it must be refreshed to see the new pivot row.
        $user->refresh();
        expect($user->hasProfile('admin'))->toBeTrue();
    });

    it('exposes the user who assigned a profile through the pivot', function () {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $profile = Profile::factory()->create();

        $user->profiles()->attach($profile->id, ['assigned_by' => $admin->id]);

        $pivot = $user->profiles()->first()->pivot;
        expect($pivot)->toBeInstanceOf(UserProfile::class);
        expect($pivot->assignedBy->id)->toBe($admin->id);
    });

    it('soft deletes', function () {
        $user = User::factory()->create();
        $user->delete();

        expect(User::count())->toBe(0);
        expect(User::withTrashed()->count())->toBe(1);
    });

    it('force deleting a user cascades to user_details and user_profiles', function () {
        $user = User::factory()->create();
        $profile = Profile::factory()->create();
        $user->details()->create(['data_consent_at' => now()]);
        $user->profiles()->attach($profile->id);

        $user->forceDelete();

        expect(UserDetails::count())->toBe(0);
        expect(UserProfile::count())->toBe(0);
    });

    it('force deleting a user nullifies its audit logs instead of deleting them', function () {
        $user = User::factory()->create();
        $log = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
        ]);

        $user->forceDelete();

        expect(AuditLog::count())->toBe(1);
        expect($log->refresh()->user_id)->toBeNull();
    });
});

describe('User validation', function () {
    it('email is unique', function () {
        User::factory()->create(['email' => 'test@example.com']);

        expect(function () {
            User::factory()->create(['email' => 'test@example.com']);
        })->toThrow(QueryException::class);
    });

    it('email is case-insensitive (citext)', function () {
        User::factory()->create(['email' => 'test@example.com']);

        expect(function () {
            User::factory()->create(['email' => 'TEST@EXAMPLE.COM']);
        })->toThrow(QueryException::class);
    });

    it('has fillable protection', function () {
        $user = User::factory()->create();

        // Model::shouldBeStrict() (see AppServiceProvider) turns mass-assigning
        // a non-fillable attribute into a hard failure instead of a silent no-op.
        expect(fn () => $user->update(['is_admin' => true]))
            ->toThrow(MassAssignmentException::class);
    });
});
