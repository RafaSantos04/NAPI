<?php

use App\Models\User;
use Illuminate\Support\Str;

// The API login serves non-privileged accounts; a privileged one gets its
// token in the admin area (ADR-0013, item 11, PrivilegedTokenBoundaryTest).
describe('Authentication', function () {
    it('logs in with valid credentials', function () {
        $password = Str::password();
        $user = User::factory()->create(['password' => $password]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user', 'token']);
    });

    it('rejects invalid password', function () {
        $user = User::factory()->create(['password' => Str::password()]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => Str::password(),
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

    it('rate limits login after 5 failed attempts from the same origin', function () {
        $password = Str::password();
        $user = User::factory()->create(['password' => $password]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => Str::password(),
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(429);
    });

    it('returns 401 when accessing protected route without token', function () {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    });
});
