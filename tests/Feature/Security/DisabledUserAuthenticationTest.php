<?php

use App\Models\User;

// FIND-003: a deactivated account must lose API access immediately, even if a
// previously issued token is still technically valid.
describe('Disabled user authentication', function () {
    it('rejects a valid token once its owner is deactivated', function () {
        $user = seededUser('dev');
        $token = tokenFor($user);

        // Query-builder update skips model events: enforcement must not depend
        // on token cleanup having happened.
        User::whereKey($user->id)->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    });

    it('keeps accepting the token while the owner is active', function () {
        $this->withToken(tokenFor(seededUser('dev')))->getJson('/api/v1/auth/me')
            ->assertOk();
    });

    it('rejects the token of a soft-deleted user', function () {
        $user = seededUser('dev');
        $token = tokenFor($user);
        $user->delete();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    });

    it('revokes existing tokens when the user is deactivated', function () {
        $user = seededUser('dev');
        tokenFor($user);
        tokenFor($user);

        $user->update(['is_active' => false]);

        expect($user->tokens()->count())->toBe(0);
    });

    it('does not revoke tokens on unrelated updates', function () {
        $user = seededUser('dev');
        tokenFor($user);

        $user->update(['name' => 'Renamed']);

        expect($user->tokens()->count())->toBe(1);
    });

    it('does not issue a token to an inactive user', function () {
        User::factory()->inactive()->create(['email' => 'inactive@napi.dev']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@napi.dev',
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonMissingPath('token');
    });
});
