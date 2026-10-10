<?php

use App\Domain\IAM\AuditContext;
use App\Domain\Security\Actions\RunIdorTest;
use App\Domain\Security\Enums\SecurityTestScenario;
use App\DTOs\RunIdorTestDto;
use App\Models\AuditLog;
use App\Models\SecurityLabResource;
use App\Models\SecurityTestRun;
use App\Models\User;
use Database\Seeders\SecurityLabSeeder;

// Phase 5.1: who reaches the Security Lab. Feature flag, session, active
// account and functional permission each close the door on their own.

dataset('lab routes', [
    'landing' => ['GET', '/admin/security'],
    'idor page' => ['GET', '/admin/security/idor'],
    'idor run' => ['POST', '/admin/security/idor/run'],
]);

describe('Security Lab access', function () {
    it('sends guests to the admin landing', function (string $method, string $uri) {
        $this->call($method, $uri)->assertRedirect(route('admin.home'));
    })->with('lab routes');

    it('answers 404 to an admin area user without the lab permission', function (string $method, string $uri) {
        $this->actingAs(userWithPermissions(['users' => ['view']]))
            ->call($method, $uri)
            ->assertNotFound();
    })->with('lab routes');

    it('ends the session of a user with no admin access at all', function () {
        $this->actingAs(seededUser('dev'))
            ->get('/admin/security/idor')
            ->assertRedirect(route('admin.home'));

        $this->assertGuest('web');
    });

    it('shows the lab to a user who may view it', function () {
        $this->actingAs(userWithPermissions(['security-lab' => ['view']]));

        $this->get('/admin/security')
            ->assertOk()
            ->assertSee('href="'.route('admin.security.idor.show').'"', false);

        $this->get('/admin/security/idor')->assertOk()->assertSee('IDOR / BOLA');
    });

    it('does not let a view-only user run a test', function () {
        $target = SecurityLabResource::factory()->create();

        $this->actingAs(userWithPermissions(['security-lab' => ['view']]));

        $this->get('/admin/security/idor')
            ->assertOk()
            ->assertSee('não executar testes')
            ->assertDontSee(route('admin.security.idor.run'));

        $this->post('/admin/security/idor/run', [
            'actor_user_id' => $target->owner_user_id,
            'target_resource_id' => $target->id,
            'scenario' => 'vulnerable',
        ])->assertNotFound();

        expect(SecurityTestRun::count())->toBe(0);
    });

    it('gives the administrator the lab through the implicit permission', function () {
        SecurityLabResource::factory()->create();

        $this->actingAs(seededUser('admin'))
            ->get('/admin/security/idor')
            ->assertOk()
            ->assertSee('action="'.route('admin.security.idor.run').'"', false);
    });

    it('lets a lab-only profile into the admin area and shows only its section', function () {
        $user = userWithPermissions(['security-lab' => ['view']]);

        adminLogin($user)->assertRedirect(route('admin.home'));

        $this->get('/admin')
            ->assertOk()
            ->assertSee('href="'.route('admin.security.index').'"', false)
            ->assertDontSee('href="'.route('admin.users.index').'"', false);
    });

    it('ends the session of an operator deactivated after logging in', function () {
        $user = userWithPermissions(['security-lab' => ['view', 'create']]);
        adminLogin($user);

        User::whereKey($user->id)->update(['is_active' => false]);
        app('auth')->forgetGuards();

        $this->get('/admin/security/idor')->assertRedirect(route('admin.home'));
        $this->assertGuest('web');
    });
});

describe('Security Lab feature flag', function () {
    it('is disabled unless the environment enables it', function () {
        // Reads the file, not config(): the suite turns the lab on.
        expect((require config_path('security.php'))['lab']['enabled'])->toBeFalse();
    });

    it('answers 404 when disabled, even to the administrator', function (string $method, string $uri) {
        config(['security.lab.enabled' => false]);

        $this->actingAs(seededUser('admin'))->call($method, $uri)->assertNotFound();
    })->with('lab routes');

    it('hides the menu entry when disabled', function () {
        config(['security.lab.enabled' => false]);

        $this->actingAs(seededUser('admin'))
            ->get('/admin/users')
            ->assertOk()
            ->assertDontSee(route('admin.security.index'));
    });

    it('shows the menu entry when enabled', function () {
        $this->actingAs(seededUser('admin'))
            ->get('/admin/users')
            ->assertSee('href="'.route('admin.security.index').'"', false);
    });

    it('does not count the lab permission as admin access when disabled', function () {
        config(['security.lab.enabled' => false]);

        adminLogin(userWithPermissions(['security-lab' => ['view', 'create']]))
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    });

    it('refuses to run the use case when disabled, whoever calls it', function () {
        $target = SecurityLabResource::factory()->create();
        config(['security.lab.enabled' => false]);

        $dto = new RunIdorTestDto($target->owner_user_id, $target->id, SecurityTestScenario::Vulnerable);

        expect(fn () => RunIdorTest::execute($dto, AuditContext::system()))
            ->toThrow(LogicException::class, 'The Security Lab is disabled.');

        expect(SecurityTestRun::count())->toBe(0)
            ->and(AuditLog::where('action', 'security_test_executed')->exists())->toBeFalse();
    });

    it('seeds no synthetic persona while disabled', function () {
        config(['security.lab.enabled' => false]);

        $this->seed(SecurityLabSeeder::class);

        expect(User::where('email', 'like', '%@security-lab.invalid')->exists())->toBeFalse()
            ->and(SecurityLabResource::count())->toBe(0);
    });

    it('creates personas that cannot sign in', function () {
        $this->seed(SecurityLabSeeder::class);

        $alice = User::where('email', 'alice@security-lab.invalid')->sole();

        expect($alice->is_active)->toBeFalse()
            ->and($alice->profiles()->count())->toBe(0)
            ->and(SecurityLabResource::where('owner_user_id', $alice->id)->sole()->content)
            ->toContain('Synthetic Security Lab Data');

        // Seeding again adds nothing.
        $this->seed(SecurityLabSeeder::class);
        expect(SecurityLabResource::count())->toBe(2);
    });
});
