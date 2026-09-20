<?php

use App\Models\User;

describe('Authorization', function () {
    it('allows admin to view all users', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();
        $otherUser = User::where('email', 'dev@napi.dev')->first();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/users/{$otherUser->id}");

        $response->assertStatus(200);
    });

    it('blocks viewer from accessing user endpoints', function () {
        $viewer = User::where('email', 'viewer@napi.dev')->first();
        $otherUser = User::where('email', 'dev@napi.dev')->first();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/users/{$otherUser->id}");

        // Retorna 404 para não revelar que existe
        $response->assertStatus(404);
    });

    it('prevents user from viewing other users', function () {
        $userA = User::where('email', 'dev@napi.dev')->first();
        $userB = User::where('email', 'viewer@napi.dev')->first();

        $response = $this->actingAs($userA)
            ->getJson("/api/v1/users/{$userB->id}");

        $response->assertStatus(404);
    });

    it('allows user to view themselves', function () {
        $user = User::where('email', 'dev@napi.dev')->first();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/users/{$user->id}");

        $response->assertStatus(200);
    });

    it('blocks admin from deleting themselves', function () {
        $admin = User::where('email', 'admin@napi.dev')->first();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/users/{$admin->id}");

        $response->assertStatus(403);
    });

    it('prevents deleting a profile that still has users', function () {
        // Sem ProfileController/rotas nesta fase (isso é escopo da Fase 3),
        // então validamos a regra da ProfilePolicy diretamente, sem HTTP.
        $admin = User::where('email', 'admin@napi.dev')->first();
        $profile = $admin->profiles()->first();

        expect($admin->can('delete', $profile))->toBeFalse();
    });
});
