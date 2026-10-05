<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Route;

describe('Admin shell', function () {
    it('renders the landing for guests with the login panel', function () {
        $this->get('/admin')
            ->assertOk()
            ->assertSee('Entrar')
            ->assertSee('action="'.route('admin.login').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee(route('admin.logout'));
    });

    it('shows the signed-in user and the logout action', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')
            ->assertOk()
            ->assertSee($user->email)
            ->assertSee('Sair')
            ->assertSee('action="'.route('admin.logout').'"', false)
            ->assertDontSee('name="password"', false);
    });
});

describe('Admin session login', function () {
    it('logs in with valid credentials and regenerates the session', function () {
        $user = User::factory()->create(['password' => 'secret-password']);
        $this->startSession();
        $before = session()->getId();

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect(route('admin.home'))
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user, 'web');
        expect(session()->getId())->not->toBe($before);
        expect(AuditLog::where('user_id', $user->id)->where('action', 'login')->exists())->toBeTrue();
    });

    it('does not issue an API token on web login', function () {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'secret-password']);

        expect($user->tokens()->count())->toBe(0);
    });

    it('rejects a wrong password with a generic message', function () {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->from('/admin')
            ->post('/admin/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/admin')
            ->assertSessionHasErrors(['email' => 'Credenciais inválidas.'])
            ->assertSessionHasInput('email', $user->email)
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest('web');
    });

    it('gives an unknown e-mail the same answer as a wrong password', function () {
        $this->from('/admin')
            ->post('/admin/login', ['email' => 'nobody@napi.dev', 'password' => 'whatever'])
            ->assertSessionHasErrors(['email' => 'Credenciais inválidas.']);

        $this->assertGuest('web');
    });

    it('does not let an inactive user in, nor reveal that it is inactive', function () {
        $user = User::factory()->create(['password' => 'secret-password', 'is_active' => false]);

        $this->from('/admin')
            ->post('/admin/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertSessionHasErrors(['email' => 'Credenciais inválidas.']);

        $this->assertGuest('web');
    });

    it('validates the input', function (array $payload, array $errors) {
        $this->from('/admin')
            ->post('/admin/login', $payload)
            ->assertRedirect('/admin')
            ->assertSessionHasErrors($errors);

        $this->assertGuest('web');
    })->with([
        'empty' => [[], ['email' => 'Informe o e-mail.', 'password' => 'Informe a senha.']],
        'invalid e-mail' => [['email' => 'not-an-email', 'password' => 'x'], ['email' => 'Informe um e-mail válido.']],
    ]);

    it('shows the error inside the reopened panel', function () {
        $this->from('/admin')
            ->followingRedirects()
            ->post('/admin/login', ['email' => 'nobody@napi.dev', 'password' => 'whatever'])
            ->assertOk()
            ->assertSee('Credenciais inválidas.')
            ->assertSee('id="login-panel" open', false);
    });

    it('locks out after too many failed attempts', function () {
        $user = User::factory()->create(['password' => 'secret-password']);

        foreach (range(1, 5) as $_) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    });

    it('requires a CSRF token', function () {
        $user = User::factory()->create(['password' => 'secret-password']);

        // CSRF verification is skipped in the testing environment, so the
        // request runs as a local one.
        $this->app['env'] = 'local';

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertStatus(419);

        $this->assertGuest('web');
    });
});

describe('Admin session logout', function () {
    it('logs out, invalidates the session and rotates the CSRF token', function () {
        $user = User::factory()->create(['password' => 'secret-password']);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'secret-password']);
        session()->put('marker', 'value');
        $token = session()->token();

        $this->post('/admin/logout')->assertRedirect(route('admin.home'));

        $this->assertGuest('web');
        expect(session()->has('marker'))->toBeFalse();
        expect(session()->token())->not->toBe($token);
        expect(AuditLog::where('user_id', $user->id)->where('action', 'logout')->exists())->toBeTrue();

        // A fresh guard finds no login in the session any more.
        app('auth')->forgetGuards();
        $this->get('/admin')->assertSee('name="password"', false);
    });

    it('keeps the user API tokens on web logout', function () {
        $user = User::factory()->create();
        $user->createToken('api');

        $this->actingAs($user)->post('/admin/logout');

        expect($user->tokens()->count())->toBe(1);
    });

    it('redirects guests away from logout', function () {
        $this->post('/admin/logout')->assertRedirect(route('admin.home'));
    });
});

describe('Admin access control', function () {
    beforeEach(function () {
        // Stand-in for the internal pages of the next sub-phases.
        Route::middleware(['web', 'auth'])->get('/admin/_protected', fn () => 'inside');
    });

    it('sends guests from protected admin routes back to the landing', function () {
        $this->get('/admin/_protected')->assertRedirect(route('admin.home'));
    });

    it('lets an authenticated user into protected admin routes', function () {
        $this->actingAs(User::factory()->create())
            ->get('/admin/_protected')
            ->assertOk()
            ->assertSee('inside');
    });

    it('ends the session of a user deactivated after logging in', function () {
        $user = User::factory()->create(['password' => 'secret-password']);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'secret-password']);
        $this->assertAuthenticatedAs($user, 'web');

        // Query-builder update skips model events; the fresh guard re-reads
        // the user from the session id.
        User::whereKey($user->id)->update(['is_active' => false]);
        app('auth')->forgetGuards();

        $this->get('/admin/_protected')->assertRedirect(route('admin.home'));
        $this->assertGuest('web');
    });

    it('sends an authenticated user away from the login endpoint', function () {
        $this->actingAs(User::factory()->create())
            ->post('/admin/login', ['email' => 'x@napi.dev', 'password' => 'x'])
            ->assertRedirect(route('admin.home'));
    });
});
