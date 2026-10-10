<?php

use App\Domain\IAM\AuditContext;
use App\Domain\Security\Actions\RunIdorTest;
use App\Domain\Security\Enums\SecurityTest;
use App\Domain\Security\Enums\SecurityTestExecutionStatus;
use App\Domain\Security\Enums\SecurityTestObservedOutcome;
use App\Domain\Security\Enums\SecurityTestScenario;
use App\Domain\Security\Enums\SecurityTestVerdict;
use App\DTOs\RunIdorTestDto;
use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\SecurityLabResource;
use App\Models\SecurityTestRun;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

// Phase 5.1: the IDOR / BOLA test. Operator (who runs it), actor (whose
// authorization is simulated) and target (a synthetic document) are three
// different things, and the verdict is about security, not about success.

beforeEach(function () {
    $this->operator = userWithPermissions(['security-lab' => ['view', 'create']]);

    $this->alice = User::factory()->inactive()->create(['name' => 'Alice Persona']);
    $this->bob = User::factory()->inactive()->create(['name' => 'Bob Persona']);

    $this->aliceDocument = SecurityLabResource::factory()->create([
        'owner_user_id' => $this->alice->id,
        'name' => 'Documento A',
        'content' => 'Synthetic Security Lab Data — conteúdo de Alice',
    ]);
    $this->bobDocument = SecurityLabResource::factory()->create([
        'owner_user_id' => $this->bob->id,
        'name' => 'Documento B',
        'content' => 'Synthetic Security Lab Data — conteúdo de Bob',
    ]);

    // Alice asks for Bob's document: the cross-owner pair the test is about.
    $this->crossOwner = fn (string $scenario) => [
        'actor_user_id' => $this->alice->id,
        'target_resource_id' => $this->bobDocument->id,
        'scenario' => $scenario,
    ];
});

describe('IDOR scenarios', function () {
    it('exposes another owner\'s resource in the vulnerable scenario', function () {
        $this->actingAs($this->operator)
            ->post('/admin/security/idor/run', ($this->crossOwner)('vulnerable'))
            ->assertRedirect(route('admin.security.idor.show'));

        $run = SecurityTestRun::sole();

        expect($run->execution_status)->toBe(SecurityTestExecutionStatus::Completed)
            ->and($run->observed_outcome)->toBe(SecurityTestObservedOutcome::Allowed)
            ->and($run->security_verdict)->toBe(SecurityTestVerdict::Exposed);
    });

    it('denies the same actor and target in the protected scenario', function () {
        $this->actingAs($this->operator)
            ->post('/admin/security/idor/run', ($this->crossOwner)('protected'))
            ->assertRedirect(route('admin.security.idor.show'));

        $run = SecurityTestRun::sole();

        expect($run->execution_status)->toBe(SecurityTestExecutionStatus::Completed)
            ->and($run->observed_outcome)->toBe(SecurityTestObservedOutcome::Denied)
            ->and($run->security_verdict)->toBe(SecurityTestVerdict::Protected);
    });

    it('never classifies the owner reading their own resource as an exposure', function (string $scenario) {
        $this->actingAs($this->operator)->post('/admin/security/idor/run', [
            'actor_user_id' => $this->alice->id,
            'target_resource_id' => $this->aliceDocument->id,
            'scenario' => $scenario,
        ]);

        $run = SecurityTestRun::sole();

        expect($run->observed_outcome)->toBe(SecurityTestObservedOutcome::Allowed)
            ->and($run->security_verdict)->toBe(SecurityTestVerdict::Protected);
    })->with(['vulnerable', 'protected']);

    it('presents the owner case as legitimate access', function () {
        $this->actingAs($this->operator)
            ->followingRedirects()
            ->post('/admin/security/idor/run', [
                'actor_user_id' => $this->alice->id,
                'target_resource_id' => $this->aliceDocument->id,
                'scenario' => 'vulnerable',
            ])
            ->assertOk()
            ->assertSee('PROTECTED')
            ->assertSee('acesso legítimo do dono')
            ->assertDontSee('EXPOSED');
    });

    it('shows EXPOSED with the disclosed content after a vulnerable run', function () {
        $this->actingAs($this->operator)
            ->followingRedirects()
            ->post('/admin/security/idor/run', ($this->crossOwner)('vulnerable'))
            ->assertOk()
            ->assertSee('EXPOSED')
            ->assertSee('conteúdo de Bob');
    });

    it('shows PROTECTED and discloses nothing after a protected run', function () {
        $this->actingAs($this->operator)
            ->followingRedirects()
            ->post('/admin/security/idor/run', ($this->crossOwner)('protected'))
            ->assertOk()
            ->assertSee('PROTECTED')
            ->assertSee('SecurityLabResourcePolicy::view')
            ->assertDontSee('conteúdo de Bob');
    });
});

describe('Ownership policy of the synthetic resource', function () {
    it('lets only the owner view a resource, with no bypass for administrators', function () {
        $admin = seededUser('admin');

        expect(Gate::forUser($this->alice)->allows('view', $this->aliceDocument))->toBeTrue()
            ->and(Gate::forUser($this->alice)->allows('view', $this->bobDocument))->toBeFalse()
            ->and(Gate::forUser($admin)->allows('view', $this->bobDocument))->toBeFalse();
    });
});

describe('Operator, actor and session', function () {
    it('records the operator and the actor as different identities', function () {
        $this->actingAs($this->operator)->post('/admin/security/idor/run', ($this->crossOwner)('vulnerable'));

        $run = SecurityTestRun::sole();

        expect($run->test_key)->toBe(SecurityTest::Idor)
            ->and($run->scenario)->toBe(SecurityTestScenario::Vulnerable)
            ->and($run->initiated_by_user_id)->toBe($this->operator->id)
            ->and($run->acting_as_user_id)->toBe($this->alice->id)
            ->and($run->target_resource_id)->toBe($this->bobDocument->id)
            ->and($run->result_context)->toBe(['target_owner_id' => $this->bob->id]);
    });

    it('keeps the session with the operator after the run', function () {
        adminLogin($this->operator);

        $this->post('/admin/security/idor/run', ($this->crossOwner)('vulnerable'));
        app('auth')->forgetGuards();

        // A fresh guard reads the user back from the session.
        $this->get('/admin/security/idor')->assertOk()->assertSee($this->operator->email);
        $this->assertAuthenticatedAs($this->operator, 'web');
    });

    it('audits the run once, under the operator', function () {
        $this->actingAs($this->operator)->post('/admin/security/idor/run', ($this->crossOwner)('vulnerable'));

        $run = SecurityTestRun::sole();
        $audit = AuditLog::where('action', 'security_test_executed')->sole();

        expect($audit->user_id)->toBe($this->operator->id)
            ->and($audit->subject_type)->toBe(SecurityTestRun::class)
            ->and($audit->subject_id)->toBe($run->id)
            ->and($audit->meta)->toEqual([
                'test_key' => 'idor',
                'scenario' => 'vulnerable',
                'acting_as_user_id' => $this->alice->id,
                'verdict' => 'exposed',
            ]);
    });
});

describe('Synthetic data only', function () {
    it('rejects a real account as the actor', function () {
        $this->actingAs($this->operator)
            ->from('/admin/security/idor')
            ->post('/admin/security/idor/run', [
                'actor_user_id' => seededUser('dev')->id,
                'target_resource_id' => $this->bobDocument->id,
                'scenario' => 'vulnerable',
            ])
            ->assertRedirect('/admin/security/idor')
            ->assertSessionHasErrors(['actor_user_id' => 'Selecione o actor entre as opções do laboratório.']);

        expect(SecurityTestRun::count())->toBe(0);
    });

    it('rejects a record of a real table as the target', function (Closure $realId) {
        $this->actingAs($this->operator)
            ->post('/admin/security/idor/run', [
                'actor_user_id' => $this->alice->id,
                'target_resource_id' => $realId(),
                'scenario' => 'vulnerable',
            ])
            ->assertSessionHasErrors('target_resource_id');

        expect(SecurityTestRun::count())->toBe(0);
    })->with([
        'a user' => [fn () => seededUser('admin')->id],
        'a menu' => [fn () => Menu::where('key', 'users')->value('id')],
    ]);

    it('cannot store a run whose target is not a synthetic resource', function () {
        // The foreign key holds even if the application is bypassed.
        expect(fn () => DB::table('security_test_runs')->insert([
            'id' => (string) Str::ulid(),
            'test_key' => 'idor',
            'scenario' => 'vulnerable',
            'target_resource_id' => seededUser('admin')->id,
            'observed_outcome' => 'allowed',
            'security_verdict' => 'exposed',
            'execution_status' => 'completed',
        ]))->toThrow(QueryException::class);
    });

    it('lists only lab personas as actors', function () {
        $this->actingAs($this->operator)
            ->get('/admin/security/idor')
            ->assertOk()
            ->assertSee('Alice Persona')
            ->assertSee('Bob Persona')
            ->assertDontSee('Admin User')
            ->assertDontSee('Dev User');
    });
});

describe('Validation', function () {
    it('requires actor, target and scenario', function () {
        $this->actingAs($this->operator)
            ->post('/admin/security/idor/run', [])
            ->assertSessionHasErrors([
                'actor_user_id' => 'Selecione o actor.',
                'target_resource_id' => 'Selecione o recurso alvo.',
                'scenario' => 'Selecione o cenário.',
            ]);
    });

    it('rejects a scenario outside the closed set', function () {
        $this->actingAs($this->operator)
            ->post('/admin/security/idor/run', ($this->crossOwner)('bypass'))
            ->assertSessionHasErrors(['scenario' => 'Selecione um cenário válido.']);

        expect(SecurityTestRun::count())->toBe(0);
    });
});

describe('Operational errors', function () {
    it('records a technical failure as inconclusive, never as protected', function () {
        // The form validates the target, so the missing one goes straight to
        // the use case.
        $dto = new RunIdorTestDto($this->alice->id, (string) Str::ulid(), SecurityTestScenario::Protected);

        $run = RunIdorTest::execute($dto, new AuditContext($this->operator->id));

        expect($run->execution_status)->toBe(SecurityTestExecutionStatus::Error)
            ->and($run->observed_outcome)->toBeNull()
            ->and($run->security_verdict)->toBe(SecurityTestVerdict::Inconclusive)
            ->and($run->target_resource_id)->toBeNull()
            ->and($run->result_context)->toBe(['error' => 'ModelNotFoundException'])
            ->and(AuditLog::where('action', 'security_test_executed')->count())->toBe(1);
    });

    it('cannot store a failed run that claims a security result', function (array $attributes) {
        expect(fn () => DB::table('security_test_runs')->insert([
            'id' => (string) Str::ulid(),
            'test_key' => 'idor',
            'scenario' => 'protected',
            ...$attributes,
        ]))->toThrow(QueryException::class);
    })->with([
        'error with an outcome' => [['execution_status' => 'error', 'observed_outcome' => 'denied', 'security_verdict' => 'inconclusive']],
        'error called protected' => [['execution_status' => 'error', 'observed_outcome' => null, 'security_verdict' => 'protected']],
        'completed without an outcome' => [['execution_status' => 'completed', 'observed_outcome' => null, 'security_verdict' => 'protected']],
        'unknown verdict' => [['execution_status' => 'completed', 'observed_outcome' => 'allowed', 'security_verdict' => 'safe']],
    ]);
});
