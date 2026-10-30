<?php

use App\Models\User;
use Illuminate\Support\Str;

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
        $password = Str::password();

        asToken(seededUser('viewer'), 'POST', '/api/v1/users', [
            'name' => 'Intruder',
            'email' => 'intruder@napi.dev',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertNotFound();

        expect(User::where('email', 'intruder@napi.dev')->exists())->toBeFalse();
    });

    // Token issuance through the API is for non-privileged accounts
    // (ADR-0013, item 12), so these tests mint as the seeded dev user.
    it('issues a login token able to perform every operation of its owner', function () {
        $password = Str::password();
        $user = User::factory()->create(['password' => $password]);
        $other = $user->createToken('other', ['read']);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->json('token');

        // read, write and delete, each on the caller's own account.
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
        $this->withToken($token)->postJson('/api/v1/tokens', ['name' => 'ci', 'abilities' => ['read']])->assertCreated();
        $this->withToken($token)->deleteJson("/api/v1/tokens/{$other->accessToken->id}")->assertOk();
    });

    it('does not let a read token create tokens', function () {
        asToken(seededUser('dev'), 'POST', '/api/v1/tokens', [
            'name' => 'escalated',
            'abilities' => ['read'],
        ], ['read'])->assertForbidden();
    });

    it('does not let a token mint a token with broader abilities', function () {
        $dev = seededUser('dev');

        asToken($dev, 'POST', '/api/v1/tokens', [
            'name' => 'escalated',
            'abilities' => ['read', 'delete'],
        ], ['read', 'write'])->assertForbidden();

        expect($dev->tokens()->where('name', 'escalated')->exists())->toBeFalse();
    });

    it('lets a token mint a token with a subset of its abilities', function () {
        asToken(seededUser('dev'), 'POST', '/api/v1/tokens', [
            'name' => 'reader',
            'abilities' => ['read'],
        ], ['read', 'write'])->assertCreated();
    });

    it('caps token lifetime at the global Sanctum expiration', function () {
        asToken(seededUser('dev'), 'POST', '/api/v1/tokens', [
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
