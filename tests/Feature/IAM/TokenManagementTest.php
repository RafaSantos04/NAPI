<?php

use App\Models\User;

describe('Token Management', function () {
    it('creates a personal access token', function () {
        $user = User::where('email', 'admin@napi.dev')->first();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/tokens', [
                'name' => 'My App',
                'abilities' => ['read', 'write'],
                'expires_in_days' => 30,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['token']);
    });

    it('lists user tokens', function () {
        $user = User::where('email', 'admin@napi.dev')->first();
        $user->createToken('existing-token', ['read']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/tokens');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['id', 'name', 'abilities']]]);
    });

    it('revokes a token', function () {
        $user = User::where('email', 'admin@napi.dev')->first();
        $token = $user->createToken('test', ['read'])->accessToken;

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/tokens/{$token->id}");

        $response->assertStatus(200);
        expect($user->tokens()->count())->toBe(0);
    });
});
