<?php

use App\Models\User;

describe('Authentication', function () {
    it('logs in with valid credentials', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@napi.dev',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user', 'token']);
    });

    it('rejects invalid password', function () {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@napi.dev',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    });

    it('logs out and revokes tokens', function () {
        $user = User::where('email', 'admin@napi.dev')->first();

        $response = $this->actingAs($user)->postJson('/api/v1/auth/logout');

        $response->assertStatus(200);
        expect($user->tokens)->toHaveCount(0);
    });

    it('returns current user with /me', function () {
        $user = User::where('email', 'admin@napi.dev')->first();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $response->assertJson(['data' => ['id' => $user->id, 'email' => $user->email]]);
    });

    it('rate limits login after 6 attempts in 30 minutes', function () {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'admin@napi.dev',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@napi.dev',
            'password' => 'password',
        ]);

        $response->assertStatus(429);
    });

    it('returns 401 when accessing protected route without token', function () {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    });
});
