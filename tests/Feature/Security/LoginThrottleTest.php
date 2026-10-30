<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;

// P2.5-T02 / ADR-0013: one password check for the admin form and the API
// login, with counters per identity + IP, per identity and per IP.
// Sleep is faked where a test fails many logins: every failure waits out the
// guard's 200 ms Timebox.

function attemptApiLogin(string $email, string $password, string $ip = '127.0.0.1'): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
}

function attemptAdminLogin(string $email, string $password, string $ip = '127.0.0.1'): TestResponse
{
    app('auth')->forgetGuards();

    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->from('/admin')
        ->post('/admin/login', ['email' => $email, 'password' => $password]);
}

describe('One login budget for admin and API', function () {
    it('blocks the API login after failures on the admin form (429)', function () {
        Sleep::fake();
        $this->freezeTime();
        $user = User::factory()->create(['password' => 'secret-password']);

        foreach (range(1, 5) as $_) {
            attemptAdminLogin($user->email, 'wrong-password');
        }

        attemptApiLogin($user->email, 'secret-password')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', 1800)
            ->assertExactJson(['message' => 'Too many login attempts. Try again later.']);
    });

    it('blocks the admin form after failures on the API login', function () {
        Sleep::fake();
        $this->freezeTime();
        $user = adminUser(['password' => 'secret-password']);

        foreach (range(1, 5) as $_) {
            attemptApiLogin($user->email, 'wrong-password');
        }

        attemptAdminLogin($user->email, 'secret-password')
            ->assertRedirect('/admin')
            ->assertSessionHasErrors(['email' => 'Muitas tentativas. Tente novamente em 1800 segundos.'])
            ->assertSessionHasInput('email', $user->email)
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest('web');
    });

    it('treats every spelling of an e-mail as the same identity', function () {
        Sleep::fake();
        User::factory()->create(['email' => 'target@napi.dev', 'password' => 'secret-password']);

        foreach (['TARGET@napi.dev', 'Target@Napi.Dev', ' target@napi.dev ', 'target@NAPI.DEV', 'tArGeT@napi.dev'] as $spelling) {
            attemptApiLogin($spelling, 'wrong-password')->assertUnprocessable();
        }

        attemptApiLogin('target@napi.dev', 'secret-password')->assertTooManyRequests();
    });
});

describe('Login counters', function () {
    it('keeps limiting an account when the attacker rotates IPs (429)', function () {
        Sleep::fake();
        $this->freezeTime();
        $user = User::factory()->create(['password' => 'secret-password']);

        foreach (range(1, 20) as $i) {
            attemptApiLogin($user->email, 'wrong-password', "203.0.113.{$i}")->assertUnprocessable();
        }

        attemptApiLogin($user->email, 'secret-password', '198.51.100.1')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', 3600);
    });

    it('keeps limiting an origin when the attacker rotates e-mails, even after a success (429)', function () {
        Sleep::fake();
        $first = User::factory()->create(['password' => 'secret-password']);
        $second = User::factory()->create(['password' => 'secret-password']);

        foreach (range(1, 99) as $i) {
            attemptApiLogin("nobody{$i}@napi.dev", 'wrong-password')->assertUnprocessable();
        }

        // The 100th attempt succeeds and must not give the origin its quota back.
        attemptApiLogin($first->email, 'secret-password')->assertOk();

        attemptApiLogin($second->email, 'secret-password')->assertTooManyRequests();
    });

    it('resets only the identity + IP counter on success', function () {
        Sleep::fake();
        $user = User::factory()->create(['password' => 'secret-password']);

        foreach (range(1, 4) as $_) {
            attemptApiLogin($user->email, 'wrong-password')->assertUnprocessable();
        }

        attemptApiLogin($user->email, 'secret-password')->assertOk();

        // The local burst starts over: four more failures from the same IP.
        foreach (range(1, 4) as $_) {
            attemptApiLogin($user->email, 'wrong-password')->assertUnprocessable();
        }

        // The identity counter kept all ten: eleven more from other IPs reach
        // its 20, and the next attempt is refused even with the right password.
        foreach (range(1, 11) as $i) {
            attemptApiLogin($user->email, 'wrong-password', "203.0.113.{$i}")->assertUnprocessable();
        }

        attemptApiLogin($user->email, 'secret-password', '198.51.100.1')->assertTooManyRequests();
    });

    it('decides on the value the atomic increment returns, never on a separate read', function () {
        $user = User::factory()->create(['password' => 'secret-password']);

        // A strict mock: any read such as tooManyAttempts() or attempts()
        // would fail the request. Simultaneous requests are only safe when
        // the decision comes from the increment itself (FIND-028).
        RateLimiter::shouldReceive('increment')->times(3)->andReturn(6, 1, 1);
        RateLimiter::shouldReceive('availableIn')->once()->andReturn(900);

        attemptApiLogin($user->email, 'secret-password')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', 900)
            ->assertJsonMissingPath('token');

        expect($user->tokens()->count())->toBe(0);
    });

    it('signals a refused attempt with the Lockout event', function () {
        Sleep::fake();
        $user = User::factory()->create(['password' => 'secret-password']);

        foreach (range(1, 5) as $_) {
            attemptApiLogin($user->email, 'wrong-password');
        }

        Event::fake([Lockout::class]);

        attemptApiLogin($user->email, 'secret-password');

        Event::assertDispatchedTimes(Lockout::class, 1);
    });
});

describe('Login answers (FIND-018)', function () {
    it('gives the same answer and the same wait to an unknown e-mail, an inactive account and a wrong password (422)', function (string $email, string $password) {
        Sleep::fake();
        User::factory()->create(['email' => 'active@napi.dev', 'password' => 'secret-password']);
        User::factory()->inactive()->create(['email' => 'inactive@napi.dev', 'password' => 'secret-password']);

        attemptApiLogin($email, $password)
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Invalid credentials.',
                'errors' => ['email' => ['Invalid credentials.']],
            ]);

        // The guard's Timebox pads every failure to the same minimum time.
        Sleep::assertSleptTimes(1);
    })->with([
        'unknown e-mail' => ['nobody@napi.dev', 'secret-password'],
        'inactive account' => ['inactive@napi.dev', 'secret-password'],
        'wrong password' => ['active@napi.dev', 'wrong-password'],
    ]);
});

describe('Trusted proxies (FIND-030)', function () {
    it('ignores X-Forwarded-For when no proxy is trusted', function () {
        Sleep::fake();
        $user = User::factory()->create(['password' => 'secret-password']);

        foreach (range(1, 5) as $i) {
            $this->withHeader('X-Forwarded-For', "203.0.113.{$i}");
            attemptApiLogin($user->email, 'wrong-password');
        }

        $this->withHeader('X-Forwarded-For', '198.51.100.1');
        attemptApiLogin($user->email, 'secret-password')->assertTooManyRequests();
    });

    it('counts the forwarded client IP behind a trusted proxy', function () {
        Sleep::fake();
        config(['trustedproxy.proxies' => '127.0.0.1']);
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->withHeader('X-Forwarded-For', '203.0.113.7');
        foreach (range(1, 5) as $_) {
            attemptApiLogin($user->email, 'wrong-password');
        }

        $this->withHeader('X-Forwarded-For', '198.51.100.1');
        attemptApiLogin($user->email, 'secret-password')->assertOk();
    });
});

describe('Per-user limit on authenticated routes', function () {
    it('limits a user across all of their tokens, not each token or everyone (429)', function () {
        $this->freezeTime();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $first = tokenFor($user);
        $second = tokenFor($user);

        foreach (range(1, 120) as $_) {
            app('auth')->forgetGuards();
            $this->withToken($first)->getJson('/api/v1/auth/me')->assertOk();
        }

        app('auth')->forgetGuards();
        $this->withToken($second)->getJson('/api/v1/auth/me')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', 60);

        asToken($other, 'GET', '/api/v1/auth/me')->assertOk();
    });
});
