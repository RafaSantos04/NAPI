<?php

use App\Domain\IAM\AuditContext;
use App\Domain\Security\Actions\RunIdorTest;
use App\Domain\Security\Actions\RunMassAssignmentTest;
use App\Domain\Security\Enums\SecurityTestScenario;
use App\DTOs\RunIdorTestDto;
use App\DTOs\RunMassAssignmentTestDto;
use App\Models\AuditLog;
use App\Models\SecurityLabResource;
use App\Models\SecurityTestRun;
use App\Models\User;
use Database\Seeders\SecurityLabSeeder;

// Phases 5.1 and 5.2: who reaches the Security Lab. Feature flag, session,
// active account and functional permission each close the door on their own,
// for every test of the lab.

dataset('lab routes', [
    'landing' => ['GET', '/admin/security'],
    'idor page' => ['GET', '/admin/security/idor'],
    'idor run' => ['POST', '/admin/security/idor/run'],
    'mass assignment page' => ['GET', '/admin/security/mass-assignment'],
    'mass assignment run' => ['POST', '/admin/security/mass-assignment/run'],
]);

dataset('lab tests', [
    'idor' => ['admin.security.idor.show', 'admin.security.idor.run'],
    'mass assignment' => ['admin.security.mass-assignment.show', 'admin.security.mass-assignment.run'],
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
            ->assertSee('href="'.route('admin.security.idor.show').'"', false)
            ->assertSee('href="'.route('admin.security.mass-assignment.show').'"', false);

        $this->get('/admin/security/idor')->assertOk()->assertSee('IDOR / BOLA');
        $this->get('/admin/security/mass-assignment')->assertOk()->assertSee('Mass Assignment');
    });

    it('does not let a view-only user run a test', function (string $page, string $run) {
        SecurityLabResource::factory()->create();

        $this->actingAs(userWithPermissions(['security-lab' => ['view']]));

        $this->get(route($page))
            ->assertOk()
            ->assertSee('não executar testes')
            ->assertDontSee(route($run));

        // 404 from the permission layer: validation would have redirected.
        $this->post(route($run))->assertNotFound();

        expect(SecurityTestRun::count())->toBe(0);
    })->with('lab tests');

    it('gives the administrator the lab through the implicit permission', function (string $page, string $run) {
        SecurityLabResource::factory()->create();

        $this->actingAs(seededUser('admin'))
            ->get(route($page))
            ->assertOk()
            ->assertSee('action="'.route($run).'"', false);
    })->with('lab tests');

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

    it('refuses to run a use case when disabled, whoever calls it', function (Closure $execute) {
        $target = SecurityLabResource::factory()->create(['name' => 'Documento A']);
        config(['security.lab.enabled' => false]);

        expect(fn () => $execute($target))
            ->toThrow(LogicException::class, 'The Security Lab is disabled.');

        expect(SecurityTestRun::count())->toBe(0)
            ->and(AuditLog::where('action', 'security_test_executed')->exists())->toBeFalse()
            ->and($target->fresh()->only(['name', 'is_approved']))->toBe(['name' => 'Documento A', 'is_approved' => false]);
    })->with([
        'idor' => [fn (SecurityLabResource $target) => RunIdorTest::execute(
            new RunIdorTestDto($target->owner_user_id, $target->id, SecurityTestScenario::Vulnerable),
            AuditContext::system(),
        )],
        'mass assignment' => [fn (SecurityLabResource $target) => RunMassAssignmentTest::execute(
            new RunMassAssignmentTestDto($target->id, SecurityTestScenario::Vulnerable, ['name' => 'Renomeado', 'is_approved' => true]),
            AuditContext::system(),
        )],
    ]);

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
