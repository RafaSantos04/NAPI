<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

// P2.5-T06 / ADR-0013, items 13 and 14: the signed-in user's own API tokens.
// For a privileged account this screen is the only way to get one, and
// creating it takes a password confirmed in the last five minutes.

function confirmAdminPassword(string $password): void
{
    test()->post('/admin/confirm-password', ['password' => $password]);
}

describe('Password confirmation before issuing a token', function () {
    it('asks for the password before the token form, then returns to it', function () {
        $password = Str::password();
        $admin = adminUser(['password' => $password]);
        $this->actingAs($admin);

        $this->get('/admin/tokens/create')->assertRedirect(route('admin.password.confirm'));
        $this->get('/admin/confirm-password')
            ->assertOk()
            ->assertSee('action="'.route('admin.password.confirm.store').'"', false)
            ->assertSee('autocomplete="current-password"', false);

        $this->post('/admin/confirm-password', ['password' => $password])
            ->assertRedirect(route('admin.tokens.create'))
            ->assertSessionHasNoErrors();

        $this->get('/admin/tokens/create')->assertOk()->assertSee('name="lifetime_hours"', false);
    });

    it('asks again once the confirmation is older than five minutes', function () {
        $this->freezeSecond();
        $password = Str::password();
        $admin = adminUser(['password' => $password]);
        $this->actingAs($admin);
        confirmAdminPassword($password);

        $this->travel(301)->seconds();

        $this->post('/admin/tokens', ['name' => 'ci', 'abilities' => ['read'], 'lifetime_hours' => 8])
            ->assertRedirect(route('admin.password.confirm'));

        expect($admin->tokens()->count())->toBe(0);
    });

    it('rejects a wrong password without confirming the session', function () {
        $admin = adminUser(['password' => Str::password()]);
        $this->actingAs($admin);

        $this->from('/admin/confirm-password')
            ->post('/admin/confirm-password', ['password' => Str::password()])
            ->assertRedirect('/admin/confirm-password')
            ->assertSessionHasErrors(['password' => 'Senha incorreta.']);

        $this->get('/admin/tokens/create')->assertRedirect(route('admin.password.confirm'));
    });

    it('spends the login budget shared with the API and the admin form', function () {
        Sleep::fake();
        $password = Str::password();
        $admin = adminUser(['password' => $password]);
        $this->actingAs($admin);

        foreach (range(1, 5) as $_) {
            confirmAdminPassword(Str::password());
        }

        $this->from('/admin/confirm-password')
            ->post('/admin/confirm-password', ['password' => $password])
            ->assertSessionHasErrors(['email' => 'Muitas tentativas. Tente novamente em 1800 segundos.']);

        app('auth')->forgetGuards();
        $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => $password])
            ->assertTooManyRequests();
    });
});

describe('Admin token screen', function () {
    it('issues a short-lived token, shows it once and never stores it in the session (201)', function () {
        $this->freezeSecond();
        $password = Str::password();
        $admin = adminUser(['password' => $password]);
        $this->actingAs($admin);
        confirmAdminPassword($password);

        $response = $this->post('/admin/tokens', ['name' => 'ci', 'abilities' => ['read', 'write'], 'lifetime_hours' => 8])
            ->assertCreated();

        $token = $admin->tokens()->sole();
        $plainText = Str::of($response->getContent())->match('/<code>([^<]+)<\/code>/')->toString();

        expect($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($token->abilities)->toBe(['read', 'write'])
            ->and($token->expires_at->equalTo(now()->addHours(8)))->toBeTrue()
            ->and(PersonalAccessToken::findToken($plainText)?->is($token))->toBeTrue()
            ->and(serialize(session()->all()))->not->toContain($plainText);

        $this->get('/admin/tokens')->assertOk()->assertSee('ci')->assertDontSee($plainText);

        expect(AuditLog::where('action', 'token_created')->sole())
            ->user_id->toBe($admin->id)
            ->meta->toMatchArray(['token_id' => $token->id, 'abilities' => ['read', 'write']]);
    });

    it('lets the admin-issued token work on the API within its abilities', function () {
        $password = Str::password();
        $admin = adminUser(['password' => $password]);
        $this->actingAs($admin);
        confirmAdminPassword($password);

        $plainText = Str::of($this->post('/admin/tokens', ['name' => 'reader', 'abilities' => ['read'], 'lifetime_hours' => 1])->getContent())
            ->match('/<code>([^<]+)<\/code>/')->toString();

        app('auth')->forgetGuards();
        $this->withToken($plainText)->getJson('/api/v1/users')->assertOk();
        $this->withToken($plainText)->postJson('/api/v1/users', [])->assertForbidden();
    });

    it('only accepts the offered lifetimes and abilities from the catalog', function (array $input, string $field) {
        $password = Str::password();
        $admin = adminUser(['password' => $password]);
        $this->actingAs($admin);
        confirmAdminPassword($password);

        $this->from('/admin/tokens/create')
            ->post('/admin/tokens', [...['name' => 'ci', 'abilities' => ['read'], 'lifetime_hours' => 8], ...$input])
            ->assertRedirect('/admin/tokens/create')
            ->assertSessionHasErrors($field);

        expect($admin->tokens()->count())->toBe(0);
    })->with([
        'a week, the global ceiling' => [['lifetime_hours' => 168], 'lifetime_hours'],
        'no lifetime' => [['lifetime_hours' => null], 'lifetime_hours'],
        'an ability outside the catalog' => [['abilities' => ['admin']], 'abilities.0'],
        'no ability' => [['abilities' => []], 'abilities'],
    ]);

    it('lists only the user\'s own tokens', function () {
        $admin = adminUser();
        $admin->createToken('mine', ['read']);
        User::factory()->create()->createToken('someone-else', ['read']);

        $this->actingAs($admin)->get('/admin/tokens')
            ->assertOk()
            ->assertSee('mine')
            ->assertDontSee('someone-else');
    });

    it('revokes the user\'s own token and audits it', function () {
        $admin = adminUser();
        $token = $admin->createToken('old-login', ['read', 'write', 'delete'])->accessToken;

        $this->actingAs($admin)->delete("/admin/tokens/{$token->id}")
            ->assertRedirect(route('admin.tokens.index'))
            ->assertSessionHas('status', 'Token revogado.');

        expect($token->fresh())->toBeNull()
            ->and(AuditLog::where('action', 'token_revoked')->sole()->user_id)->toBe($admin->id);
    });

    it('answers 404 for another user\'s token', function () {
        $foreign = User::factory()->create()->createToken('foreign', ['read'])->accessToken;

        $this->actingAs(adminUser())->delete("/admin/tokens/{$foreign->id}")
            ->assertNotFound();

        expect($foreign->fresh())->not->toBeNull();
    });
});
