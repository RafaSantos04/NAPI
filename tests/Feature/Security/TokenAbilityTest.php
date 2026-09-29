<?php

use App\Models\User;

// FIND-001: token abilities must restrict what a token can do, independently
// of (and in addition to) what its owner is allowed to do.
describe('Token abilities', function () {
    it('rejects an invalid bearer token', function () {
        $this->withToken('napi_invalid')->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    });

    it('allows a read token on a read endpoint', function () {
        asToken(seededUser('admin'), 'GET', '/api/v1/users', abilities: ['read'])
            ->assertOk();
    });

    it('denies a read token on a destructive endpoint', function () {
        $dev = seededUser('dev');

        asToken(seededUser('admin'), 'DELETE', "/api/v1/users/{$dev->id}", abilities: ['read'])
            ->assertForbidden();

        expect(User::find($dev->id))->not->toBeNull();
    });

    it('denies a read token on mutating endpoints', function (string $method) {
        $dev = seededUser('dev');
        $uri = $method === 'POST' ? '/api/v1/users' : "/api/v1/users/{$dev->id}";

        asToken(seededUser('admin'), $method, $uri, ['name' => 'Changed'], ['read'])
            ->assertForbidden();

        expect($dev->fresh()->name)->not->toBe('Changed');
    })->with(['POST', 'PUT']);

    it('requires the delete ability for DELETE even with write', function () {
        $dev = seededUser('dev');

        asToken(seededUser('admin'), 'DELETE', "/api/v1/users/{$dev->id}", abilities: ['read', 'write'])
            ->assertForbidden();
    });

    it('allows a full token of an authorized user to delete', function () {
        $dev = seededUser('dev');

        asToken(seededUser('admin'), 'DELETE', "/api/v1/users/{$dev->id}")
            ->assertOk();
    });

    it('does not let a write token bypass IAM authorization', function () {
        asToken(seededUser('viewer'), 'POST', '/api/v1/users', [
            'name' => 'Intruder',
            'email' => 'intruder@napi.dev',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();

        expect(User::where('email', 'intruder@napi.dev')->exists())->toBeFalse();
    });

    it('issues a login token able to perform every operation of its owner', function () {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@napi.dev',
            'password' => 'password',
        ])->json('token');
        $dev = seededUser('dev');

        $this->withToken($token)->deleteJson("/api/v1/users/{$dev->id}")
            ->assertOk();
    });

    it('does not let a read token create tokens', function () {
        asToken(seededUser('admin'), 'POST', '/api/v1/tokens', [
            'name' => 'escalated',
            'abilities' => ['read'],
        ], ['read'])->assertForbidden();
    });

    it('does not let a token mint a token with broader abilities', function () {
        $admin = seededUser('admin');

        asToken($admin, 'POST', '/api/v1/tokens', [
            'name' => 'escalated',
            'abilities' => ['read', 'delete'],
        ], ['read', 'write'])->assertForbidden();

        expect($admin->tokens()->where('name', 'escalated')->exists())->toBeFalse();
    });

    it('lets a token mint a token with a subset of its abilities', function () {
        asToken(seededUser('admin'), 'POST', '/api/v1/tokens', [
            'name' => 'reader',
            'abilities' => ['read'],
        ], ['read', 'write'])->assertCreated();
    });

    it('caps token lifetime at the global Sanctum expiration', function () {
        asToken(seededUser('admin'), 'POST', '/api/v1/tokens', [
            'name' => 'long-lived',
            'abilities' => ['read'],
            'expires_in_days' => 30,
        ])->assertUnprocessable()->assertJsonValidationErrors('expires_in_days');
    });

    it('returns 404 when revoking a token that is not the caller\'s', function () {
        $foreign = seededUser('dev')->createToken('foreign', ['read'])->accessToken;

        asToken(seededUser('admin'), 'DELETE', "/api/v1/tokens/{$foreign->id}")
            ->assertNotFound();

        expect($foreign->fresh())->not->toBeNull();
    });
});
