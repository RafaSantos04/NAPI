<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Str;

// P2.5-T06 / ADR-0013, items 10 to 17. A privileged account (one that enters
// the admin area) never turns its password or a token into a new token on the
// API (FIND-035), and a derived token never outlives its parent (FIND-036).

describe('Privileged accounts on the API (FIND-035)', function () {
    it('refuses a token to a privileged account on the API login (403)', function () {
        $password = Str::password();
        $admin = adminUser(['password' => $password]);

        $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => $password])
            ->assertForbidden()
            ->assertJsonPath('message', 'Privileged accounts get their tokens in the admin area.')
            ->assertJsonMissingPath('token');

        expect($admin->tokens()->count())->toBe(0)
            ->and(AuditLog::where('action', 'login')->count())->toBe(0);
    });

    it('treats an account with any admin area section as privileged', function () {
        $password = Str::password();
        $delegate = User::factory()->create(['password' => $password]);
        profileWithPermissions(['menus' => ['view']], $delegate);

        $this->postJson('/api/v1/auth/login', ['email' => $delegate->email, 'password' => $password])
            ->assertForbidden();

        expect($delegate->tokens()->count())->toBe(0);
    });

    it('keeps the API login for an organization owner, who has no admin area section', function () {
        $password = Str::password();
        $owner = User::factory()->create(['password' => $password]);
        organizationFor($owner);

        $this->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => $password])
            ->assertOk()
            ->assertJsonStructure(['token']);
    });

    it('refuses POST /tokens to a privileged account, even from a full token (403)', function () {
        $admin = adminUser();

        asToken($admin, 'POST', '/api/v1/tokens', ['name' => 'derived', 'abilities' => ['read']])
            ->assertForbidden()
            ->assertJsonPath('message', 'Privileged accounts get their tokens in the admin area.');

        expect($admin->tokens()->where('name', 'derived')->exists())->toBeFalse();
    });
});

describe('Derived token lifetime (FIND-036)', function () {
    it('never lets a derived token outlive the token that minted it', function () {
        $this->freezeSecond();
        $dev = seededUser('dev');
        $parent = $dev->createToken('short', ['read', 'write'], now()->addHours(2));

        $this->withToken($parent->plainTextToken)
            ->postJson('/api/v1/tokens', ['name' => 'derived', 'abilities' => ['read'], 'expires_in_days' => 7])
            ->assertCreated()
            ->assertJsonPath('expires_at', now()->addHours(2)->toJSON());

        expect($dev->tokens()->where('name', 'derived')->sole()->expires_at->equalTo(now()->addHours(2)))->toBeTrue();
    });

    it('caps a token minted by a login token at the global expiration counted from the login', function () {
        $this->freezeSecond();
        $dev = seededUser('dev');
        // Login tokens carry no expires_at: Sanctum expires them 7 days after
        // creation (config/sanctum.php).
        $login = $dev->createToken('api-token', ['read', 'write', 'delete']);
        $this->travel(6)->days();

        $this->withToken($login->plainTextToken)
            ->postJson('/api/v1/tokens', ['name' => 'derived', 'abilities' => ['read'], 'expires_in_days' => 7])
            ->assertCreated();

        expect($dev->tokens()->where('name', 'derived')->sole()->expires_at->equalTo(now()->addDay()))->toBeTrue();
    });

    it('keeps the requested lifetime when it ends before the parent', function () {
        $this->freezeSecond();
        $dev = seededUser('dev');
        $parent = $dev->createToken('long', ['read', 'write'], now()->addDays(5));

        $this->withToken($parent->plainTextToken)
            ->postJson('/api/v1/tokens', ['name' => 'derived', 'abilities' => ['read'], 'expires_in_days' => 1])
            ->assertCreated();

        expect($dev->tokens()->where('name', 'derived')->sole()->expires_at->equalTo(now()->addDay()))->toBeTrue();
    });
});

describe('Expired token cleanup (ADR-0013, item 17)', function () {
    it('prunes tokens expired for over a day, every day', function () {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('0 0 * * *  php artisan sanctum:prune-expired --hours=24')
            ->assertSuccessful();
    });
});
