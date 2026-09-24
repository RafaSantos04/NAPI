<?php

use App\Events\ProfileAssigned;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Event;

describe('User Profile Sync', function () {
    it('assigns profiles to user', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();
        $user = User::factory()->create();
        $dev_profile = Profile::where('slug', 'dev')->first();

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/users/{$user->id}/profiles", [
                'profile_ids' => [$dev_profile->id],
            ]);

        $response->assertStatus(200);
        expect($user->profiles()->where('profile_id', $dev_profile->id)->exists())->toBeTrue();
    });

    it('blocks removing last admin', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();

        // Tenta remover admin de si mesmo (é o último)
        $response = $this->actingAs($admin)
            ->putJson("/api/v1/users/{$admin->id}/profiles", [
                'profile_ids' => [],
            ]);

        $response->assertStatus(500);
    });

    it('blocks user from assigning profiles to themselves', function () {
        $user = User::factory()->create();
        $profile = Profile::factory()->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/users/{$user->id}/profiles", [
                'profile_ids' => [$profile->id],
            ]);

        $response->assertStatus(404);
    });

    it('fires ProfileAssigned event', function () {
        Event::fake();

        $admin = User::where('email', 'admin@napi.dev')->first();
        $user = User::factory()->create();
        $profile = Profile::factory()->create();

        $this->actingAs($admin)
            ->putJson("/api/v1/users/{$user->id}/profiles", [
                'profile_ids' => [$profile->id],
            ]);

        Event::assertDispatched(ProfileAssigned::class);
    });
});
