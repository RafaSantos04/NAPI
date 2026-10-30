<?php

// Issuing through the API is for non-privileged accounts (ADR-0013, item 12):
// the seeded dev user, who has no admin area section. Real bearer tokens, as
// in production: API routes never authenticate through the session.
describe('Token Management', function () {
    it('creates a personal access token', function () {
        $response = asToken(seededUser('dev'), 'POST', '/api/v1/tokens', [
            'name' => 'My App',
            'abilities' => ['read', 'write'],
            'expires_in_days' => 7,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['token', 'expires_at']);
    });

    it('lists user tokens', function () {
        $user = seededUser('dev');
        $user->createToken('existing-token', ['read']);

        $response = asToken($user, 'GET', '/api/v1/tokens');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['id', 'name', 'abilities']]]);
    });

    it('revokes a token', function () {
        $user = seededUser('dev');
        $token = $user->createToken('test', ['read'])->accessToken;

        $response = asToken($user, 'DELETE', "/api/v1/tokens/{$token->id}");

        $response->assertStatus(200);
        expect($user->tokens()->whereKey($token->id)->exists())->toBeFalse();
    });
});
