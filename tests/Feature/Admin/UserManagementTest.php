<?php

use App\Domain\IAM\Actions\DeactivateUser;
use App\Domain\IAM\AuditContext;
use App\Domain\IAM\Exceptions\LastActiveAdministratorException;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Phase 4.2: user administration in the Blade admin area. Same Policies,
// Actions, invariants and audit as the API; only the adapter differs.

describe('Admin area access', function () {
    it('does not open a session for a user without access to the admin area', function (string $slug) {
        $user = seededUser($slug);

        $this->from('/admin')
            ->post('/admin/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/admin')
            ->assertSessionHasErrors(['email' => 'Esta conta não tem acesso à área administrativa.']);

        $this->assertGuest('web');
        expect(AuditLog::where('user_id', $user->id)->where('action', 'login')->exists())->toBeFalse();
    })->with(['dev', 'viewer']);

    it('ends an existing session of a user without access', function () {
        $this->actingAs(seededUser('dev'))
            ->get('/admin')
            ->assertRedirect(route('admin.home'))
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    });

    it('ends the session of a user who loses access after logging in', function () {
        $user = userWithPermissions(['users' => ['view']]);
        adminLogin($user)->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($user, 'web');

        $user->profiles()->detach();
        app('auth')->forgetGuards();

        $this->get('/admin/users')->assertRedirect(route('admin.home'));
        $this->assertGuest('web');
    });

    it('lets in a custom profile that holds a usable permission', function () {
        $user = userWithPermissions(['users' => ['view']]);

        adminLogin($user)->assertRedirect(route('admin.home'));

        $this->get('/admin')
            ->assertOk()
            ->assertSee('href="'.route('admin.users.index').'"', false);
    });

    it('does not count permissions the admin area has no section for', function () {
        $user = userWithPermissions(['menus' => ['view', 'update']]);

        adminLogin($user)->assertSessionHasErrors('email');

        $this->assertGuest('web');
    });

    it('answers 404 for an admin page whose permission is missing', function () {
        $this->actingAs(userWithPermissions(['users' => ['view']]))
            ->get('/admin/users/create')
            ->assertNotFound();
    });

    it('hides actions the user has no permission for', function () {
        $this->actingAs(userWithPermissions(['users' => ['view']]))
            ->get('/admin/users')
            ->assertOk()
            ->assertDontSee(route('admin.users.create'))
            ->assertDontSee(route('admin.users.edit', seededUser('dev')));
    });
});

describe('User list', function () {
    it('lists users with status and profiles, without sensitive data', function () {
        $admin = seededUser('admin');
        UserDetails::factory()->create(['user_id' => $admin->id]);

        $response = $this->actingAs($admin)->get('/admin/users')->assertOk();

        $response->assertSee($admin->name)
            ->assertSee('admin@napi.dev')
            ->assertSee('dev@napi.dev')
            ->assertSee('Administrator')
            ->assertSee('Developer')
            ->assertSee('Ativo')
            ->assertDontSee($admin->password)
            ->assertDontSee($admin->details->cpf_hash)
            ->assertDontSee($admin->details->phone);

        expect($response->viewData('users')->first()->relationLoaded('profiles'))->toBeTrue();
    });

    it('paginates', function () {
        User::factory()->count(20)->create();

        $first = $this->actingAs(seededUser('admin'))->get('/admin/users')->assertOk();
        expect($first->viewData('users')->count())->toBe(15);
        $first->assertSee('Página 1 de 2');

        $second = $this->get('/admin/users?page=2')->assertOk();
        expect($second->viewData('users')->count())->toBe(8);
    });

    it('searches by name or e-mail and keeps the term across pages', function () {
        User::factory()->create(['name' => 'Ana Search', 'email' => 'ana@example.test']);
        User::factory()->create(['name' => 'Bruno Other', 'email' => 'bruno@example.test']);
        $this->actingAs(seededUser('admin'));

        $this->get('/admin/users?q=ana sea')->assertSee('ana@example.test')->assertDontSee('bruno@example.test');
        $this->get('/admin/users?q=BRUNO@EXAMPLE')->assertSee('Bruno Other')->assertDontSee('Ana Search');

        User::factory()->count(16)->create(['name' => 'Paged Search']);
        $this->get('/admin/users?q=paged')->assertSee('q=paged', false);
    });

    it('matches LIKE wildcards literally', function () {
        $this->actingAs(seededUser('admin'))
            ->get('/admin/users?q=%25')
            ->assertOk()
            ->assertSee('Nenhum usuário encontrado');
    });

    it('does not list users for dev, viewer or guests', function () {
        $this->get('/admin/users')->assertRedirect(route('admin.home'));

        $this->actingAs(seededUser('dev'))->get('/admin/users')
            ->assertRedirect(route('admin.home'))
            ->assertDontSee('admin@napi.dev');
    });
});

describe('User detail', function () {
    it('shows the administrative data of a user', function () {
        $dev = seededUser('dev');
        $dev->createToken('api');

        $this->actingAs(seededUser('admin'))
            ->get("/admin/users/{$dev->id}")
            ->assertOk()
            ->assertSee('dev@napi.dev')
            ->assertSee('Developer')
            ->assertSee('Tokens de API')
            ->assertSee(route('admin.users.deactivate', $dev));
    });

    it('does not let dev inspect another user', function () {
        $this->actingAs(seededUser('dev'))
            ->get('/admin/users/'.seededUser('admin')->id)
            ->assertRedirect(route('admin.home'))
            ->assertDontSee('admin@napi.dev');
    });

    it('answers 404 for a soft-deleted user', function () {
        $user = User::factory()->create();
        $user->delete();

        $this->actingAs(seededUser('admin'))->get("/admin/users/{$user->id}")->assertNotFound();
    });
});

describe('Create user', function () {
    it('creates an active user without profiles, hashing the password', function () {
        $admin = seededUser('admin');

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Person',
            'email' => 'new@napi.dev',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $user = User::where('email', 'new@napi.dev')->firstOrFail();
        $response->assertRedirect(route('admin.users.show', $user))->assertSessionHas('status', 'Usuário criado.');

        expect($user->is_active)->toBeTrue()
            ->and($user->profiles()->count())->toBe(0)
            ->and($user->password)->not->toBe('a-strong-password')
            ->and(Hash::check('a-strong-password', $user->password))->toBeTrue();

        $audit = AuditLog::where('action', 'user_created')->where('subject_id', $user->id)->sole();
        expect($audit->user_id)->toBe($admin->id)
            ->and(json_encode($audit->toArray()))->not->toContain('a-strong-password');
    });

    it('ignores privileged fields in the payload', function () {
        $this->actingAs(seededUser('admin'))->post('/admin/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@napi.dev',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'is_active' => false,
            'is_admin' => true,
            'profile_ids' => [Profile::where('slug', 'admin')->value('id')],
        ]);

        $user = User::where('email', 'sneaky@napi.dev')->firstOrFail();
        expect($user->is_active)->toBeTrue()->and($user->isAdministrator())->toBeFalse();
    });

    it('rejects invalid data without echoing the password', function () {
        $this->actingAs(seededUser('admin'))
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'name' => '',
                'email' => 'admin@napi.dev',
                'password' => 'short',
                'password_confirmation' => 'other',
            ])
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors([
                'name' => 'Informe o nome.',
                'email' => 'O e-mail já está em uso.',
            ])
            ->assertSessionHasErrors('password')
            ->assertSessionMissing('_old_input.password');

        expect(User::count())->toBe(3);
    });

    it('requires users.create', function () {
        $this->actingAs(userWithPermissions(['users' => ['view', 'update']]))
            ->post('/admin/users', [
                'name' => 'X',
                'email' => 'x@napi.dev',
                'password' => 'a-strong-password',
                'password_confirmation' => 'a-strong-password',
            ])
            ->assertNotFound();

        expect(User::where('email', 'x@napi.dev')->exists())->toBeFalse();
    });
});

describe('Update user', function () {
    it('updates name and e-mail and audits the changed fields only', function () {
        $admin = seededUser('admin');
        $dev = seededUser('dev');

        $this->actingAs($admin)
            ->put("/admin/users/{$dev->id}", ['name' => 'Renamed Dev', 'email' => 'renamed@napi.dev'])
            ->assertRedirect(route('admin.users.show', $dev))
            ->assertSessionHas('status', 'Usuário atualizado.');

        expect($dev->fresh()->name)->toBe('Renamed Dev');

        $audit = AuditLog::where('action', 'user_updated')->where('subject_id', $dev->id)->sole();
        expect($audit->user_id)->toBe($admin->id)
            ->and($audit->meta)->toBe(['fields' => ['name', 'email']]);
    });

    it('records nothing for an update that changes nothing', function () {
        $dev = seededUser('dev');

        $this->actingAs(seededUser('admin'))
            ->put("/admin/users/{$dev->id}", ['name' => $dev->name, 'email' => $dev->email]);

        expect(AuditLog::where('action', 'user_updated')->exists())->toBeFalse();
    });

    it('does not let the edit form change status, password or profiles', function () {
        $dev = seededUser('dev');
        $password = $dev->password;

        $this->actingAs(seededUser('admin'))->put("/admin/users/{$dev->id}", [
            'name' => 'Dev',
            'email' => 'dev@napi.dev',
            'is_active' => false,
            'password' => 'changed-password',
            'profile_ids' => [Profile::where('slug', 'admin')->value('id')],
        ]);

        $dev->refresh();
        expect($dev->is_active)->toBeTrue()
            ->and($dev->password)->toBe($password)
            ->and($dev->isAdministrator())->toBeFalse();
    });

    it('rejects an emptied field', function () {
        $dev = seededUser('dev');

        $this->actingAs(seededUser('admin'))
            ->put("/admin/users/{$dev->id}", ['name' => '', 'email' => 'dev@napi.dev'])
            ->assertSessionHasErrors(['name' => 'Informe o nome.']);
    });

    it('requires users.update and keeps administrators out of reach of non-admins', function () {
        $admin = seededUser('admin');

        $this->actingAs(userWithPermissions(['users' => ['view']]))
            ->put("/admin/users/{$admin->id}", ['name' => 'X', 'email' => 'x@napi.dev'])
            ->assertNotFound();

        $this->actingAs(userWithPermissions(['users' => ['view', 'update']]))
            ->put("/admin/users/{$admin->id}", ['name' => 'X', 'email' => 'x@napi.dev'])
            ->assertForbidden();

        expect($admin->fresh()->name)->toBe('Admin User');
    });
});

describe('Deactivate user', function () {
    it('deactivates, revokes API tokens and audits', function () {
        $admin = seededUser('admin');
        $dev = seededUser('dev');
        $token = tokenFor($dev);

        $this->actingAs($admin)
            ->post("/admin/users/{$dev->id}/deactivate")
            ->assertRedirect(route('admin.users.show', $dev))
            ->assertSessionHas('status', 'Usuário desativado.');

        expect($dev->fresh()->is_active)->toBeFalse()
            ->and($dev->tokens()->count())->toBe(0);

        $audit = AuditLog::where('action', 'user_deactivated')->where('subject_id', $dev->id)->sole();
        expect($audit->user_id)->toBe($admin->id)
            ->and($audit->meta)->toEqual(['revoked_tokens' => 1, 'ended_sessions' => 0]);

        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    });

    it('makes an existing web session of the user unusable', function () {
        $target = userWithPermissions(['users' => ['view']]);
        adminLogin($target);
        $this->assertAuthenticatedAs($target, 'web');

        DeactivateUser::execute($target, AuditContext::system());
        app('auth')->forgetGuards();

        $this->get('/admin/users')->assertRedirect(route('admin.home'));
        $this->assertGuest('web');
    });

    it('removes the stored sessions of the user with the database driver', function () {
        config(['session.driver' => 'database']);
        $dev = seededUser('dev');
        DB::table('sessions')->insert([
            ['id' => 'dev-session', 'user_id' => $dev->id, 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'other-session', 'user_id' => seededUser('viewer')->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        DeactivateUser::execute($dev, AuditContext::system());

        expect(DB::table('sessions')->pluck('id')->all())->toBe(['other-session'])
            ->and(AuditLog::where('action', 'user_deactivated')->sole()->meta['ended_sessions'])->toBe(1);
    });

    it('does not let anyone deactivate themselves', function () {
        $admin = adminUser();

        $this->actingAs($admin)->get("/admin/users/{$admin->id}")
            ->assertOk()
            ->assertDontSee(route('admin.users.deactivate', $admin));

        $this->post("/admin/users/{$admin->id}/deactivate")->assertForbidden();

        expect($admin->fresh()->is_active)->toBeTrue();
    });

    it('keeps administrators out of reach of non-admins', function () {
        $admin = seededUser('admin');

        $this->actingAs(userWithPermissions(['users' => ['view', 'update']]))
            ->post("/admin/users/{$admin->id}/deactivate")
            ->assertForbidden();

        expect($admin->fresh()->is_active)->toBeTrue();
    });

    it('never deactivates the last active administrator', function () {
        // The Policy already stops the only reachable path (an admin acting
        // on themselves), so the domain guard is exercised directly.
        $admin = seededUser('admin');
        $admin->createToken('api');

        expect(fn () => DeactivateUser::execute($admin, AuditContext::system()))
            ->toThrow(LastActiveAdministratorException::class);

        expect($admin->fresh()->is_active)->toBeTrue()
            ->and($admin->tokens()->count())->toBe(1)
            ->and(AuditLog::where('action', 'user_deactivated')->exists())->toBeFalse();
    });

    it('lets an admin deactivate another admin while one remains', function () {
        $other = adminUser();

        $this->actingAs(seededUser('admin'))->post("/admin/users/{$other->id}/deactivate");

        expect($other->fresh()->is_active)->toBeFalse();
    });
});

describe('Activate user', function () {
    it('reactivates without bringing back tokens or sessions', function () {
        config(['session.driver' => 'database']);
        $admin = seededUser('admin');
        $dev = seededUser('dev');
        $token = tokenFor($dev);
        DB::table('sessions')->insert(['id' => 'old', 'user_id' => $dev->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        DeactivateUser::execute($dev, AuditContext::system());

        $this->actingAs($admin)
            ->post("/admin/users/{$dev->id}/activate")
            ->assertRedirect(route('admin.users.show', $dev))
            ->assertSessionHas('status', 'Usuário ativado.');

        expect($dev->fresh()->is_active)->toBeTrue()
            ->and($dev->tokens()->count())->toBe(0)
            ->and(DB::table('sessions')->where('user_id', $dev->id)->exists())->toBeFalse()
            ->and(AuditLog::where('action', 'user_activated')->where('subject_id', $dev->id)->sole()->user_id)->toBe($admin->id);

        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    });

    it('lets the reactivated user log in again', function () {
        $user = adminUser(['is_active' => false]);

        $this->actingAs(seededUser('admin'))->post("/admin/users/{$user->id}/activate");
        $this->post('/admin/logout');

        adminLogin($user)->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($user, 'web');
    });
});

describe('Profile assignment', function () {
    it('assigns profiles, recording the actor', function () {
        $admin = seededUser('admin');
        $user = User::factory()->create();
        $viewer = Profile::where('slug', 'viewer')->firstOrFail();

        $this->actingAs($admin)
            ->put("/admin/users/{$user->id}/profiles", ['profile_ids' => [$viewer->id]])
            ->assertRedirect(route('admin.users.show', $user))
            ->assertSessionHas('status', 'Perfis atualizados.');

        expect($user->profiles()->pluck('profiles.id')->all())->toBe([$viewer->id])
            ->and($user->profiles()->first()->pivot->assigned_by)->toBe($admin->id)
            ->and(AuditLog::where('action', 'profile_assigned')->where('subject_id', $user->id)->sole()->user_id)->toBe($admin->id);
    });

    it('treats a form with every box cleared as removing all profiles', function () {
        $dev = seededUser('dev');

        $this->actingAs(seededUser('admin'))->put("/admin/users/{$dev->id}/profiles", []);

        expect($dev->profiles()->count())->toBe(0);
    });

    it('blocks self-escalation and granting admin by a delegate', function () {
        $delegate = userWithPermissions(['users' => ['view', 'update']]);
        $adminProfile = Profile::where('slug', 'admin')->value('id');
        $target = User::factory()->create();

        $this->actingAs($delegate)
            ->put("/admin/users/{$delegate->id}/profiles", ['profile_ids' => [$adminProfile]])
            ->assertForbidden();

        $this->put("/admin/users/{$target->id}/profiles", ['profile_ids' => [$adminProfile]])
            ->assertForbidden();

        expect($delegate->fresh()->isAdministrator())->toBeFalse()
            ->and($target->fresh()->isAdministrator())->toBeFalse();
    });

    it('lets a delegate grant only what they hold', function () {
        $delegate = userWithPermissions(['users' => ['view', 'update']]);
        $within = profileWithPermissions(['users' => ['view']]);
        $beyond = profileWithPermissions(['profiles' => ['view']]);
        $target = User::factory()->create();

        $this->actingAs($delegate)
            ->put("/admin/users/{$target->id}/profiles", ['profile_ids' => [$beyond->id]])
            ->assertForbidden();

        $this->put("/admin/users/{$target->id}/profiles", ['profile_ids' => [$within->id]])
            ->assertRedirect(route('admin.users.show', $target));

        expect($target->profiles()->pluck('profiles.id')->all())->toBe([$within->id]);
    });

    it('turns the last-admin conflict into a flash message', function () {
        $admin = seededUser('admin');

        $this->actingAs($admin)
            ->from("/admin/users/{$admin->id}")
            ->put("/admin/users/{$admin->id}/profiles", ['profile_ids' => []])
            ->assertRedirect("/admin/users/{$admin->id}")
            ->assertSessionHas('error', 'O sistema precisa manter ao menos um administrador ativo.');

        expect($admin->fresh()->isAdministrator())->toBeTrue()
            ->and(AuditLog::where('action', 'profile_assigned')->exists())->toBeFalse();
    });

    it('shows the conflict on the page after the redirect', function () {
        $admin = seededUser('admin');

        $this->actingAs($admin)
            ->from("/admin/users/{$admin->id}")
            ->followingRedirects()
            ->put("/admin/users/{$admin->id}/profiles", ['profile_ids' => []])
            ->assertOk()
            ->assertSee('O sistema precisa manter ao menos um administrador ativo.')
            ->assertSee('role="alert"', false);
    });
});

describe('Same rules through the API and the admin area', function () {
    it('deactivates through the API with the same Action and audit', function () {
        $dev = seededUser('dev');
        tokenFor($dev);

        asToken(seededUser('admin'), 'POST', "/api/v1/users/{$dev->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        expect($dev->tokens()->count())->toBe(0)
            ->and(AuditLog::where('action', 'user_deactivated')->where('subject_id', $dev->id)->sole()->meta['revoked_tokens'])->toBe(1);

        asToken(seededUser('admin'), 'POST', "/api/v1/users/{$dev->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    });

    it('applies the same Policy to API status changes', function () {
        $admin = seededUser('admin');
        $delegate = userWithPermissions(['users' => ['view', 'update']]);

        asToken($admin, 'POST', "/api/v1/users/{$admin->id}/deactivate")->assertForbidden();
        asToken($delegate, 'POST', "/api/v1/users/{$admin->id}/deactivate")->assertForbidden();
        asToken(seededUser('dev'), 'POST', "/api/v1/users/{$admin->id}/deactivate")->assertNotFound();
        asToken($admin, 'POST', "/api/v1/users/{$admin->id}/deactivate", [], ['read'])->assertForbidden();

        expect($admin->fresh()->is_active)->toBeTrue();
    });

    it('protects the last administrator in both adapters from the same exception', function () {
        $admin = seededUser('admin');

        asToken($admin, 'PUT', "/api/v1/users/{$admin->id}/profiles", ['profile_ids' => []])
            ->assertStatus(409)
            ->assertJsonPath('message', 'The system must keep at least one active administrator.');

        app('auth')->forgetGuards();
        $this->actingAs($admin)
            ->put("/admin/users/{$admin->id}/profiles", ['profile_ids' => []])
            ->assertRedirect()
            ->assertSessionHas('error');

        expect($admin->fresh()->isAdministrator())->toBeTrue();
    });

    it('creates users through the API with the shared Action', function () {
        asToken(seededUser('admin'), 'POST', '/api/v1/users', [
            'name' => 'Api User',
            'email' => 'api-user@napi.dev',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'is_active' => false,
        ])->assertCreated();

        $user = User::where('email', 'api-user@napi.dev')->firstOrFail();
        expect($user->is_active)->toBeTrue()
            ->and(AuditLog::where('action', 'user_created')->where('subject_id', $user->id)->count())->toBe(1);
    });

});
