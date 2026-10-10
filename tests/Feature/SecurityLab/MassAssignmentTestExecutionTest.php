<?php

use App\Domain\IAM\AuditContext;
use App\Domain\Security\Actions\RunIdorTest;
use App\Domain\Security\Actions\RunMassAssignmentTest;
use App\Domain\Security\Enums\SecurityTest;
use App\Domain\Security\Enums\SecurityTestExecutionStatus;
use App\Domain\Security\Enums\SecurityTestObservedOutcome;
use App\Domain\Security\Enums\SecurityTestScenario;
use App\Domain\Security\Enums\SecurityTestVerdict;
use App\DTOs\RunIdorTestDto;
use App\DTOs\RunMassAssignmentTestDto;
use App\Models\AuditLog;
use App\Models\Menu;
use App\Models\SecurityLabResource;
use App\Models\SecurityTestRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Phase 5.2: the Mass Assignment test. The actor owns the target, so access
// to the object is settled; what is observed is which properties a
// client-controlled payload gets to change, and that the document is back to
// its synthetic state when the run ends.

beforeEach(function () {
    $this->operator = userWithPermissions(['security-lab' => ['view', 'create']]);

    $this->alice = User::factory()->inactive()->create(['name' => 'Alice Persona']);
    $this->document = SecurityLabResource::factory()->create([
        'owner_user_id' => $this->alice->id,
        'name' => 'Documento A',
    ]);

    // By default, the payload the test is about: a permitted rename that
    // also carries the protected property.
    $this->attempt = fn (string $scenario, array $payload = ['name' => 'Renomeado', 'is_approved' => '1']) => [
        'target_resource_id' => $this->document->id,
        'scenario' => $scenario,
        'payload' => $payload,
    ];

    $this->row = fn () => (array) DB::table('security_lab_resources')->where('id', $this->document->id)->first();
});

describe('Mass Assignment scenarios', function () {
    it('changes the protected property in the vulnerable scenario', function () {
        $this->actingAs($this->operator)
            ->post('/admin/security/mass-assignment/run', ($this->attempt)('vulnerable'))
            ->assertRedirect(route('admin.security.mass-assignment.show'));

        $run = SecurityTestRun::sole();

        expect($run->execution_status)->toBe(SecurityTestExecutionStatus::Completed)
            ->and($run->observed_outcome)->toBe(SecurityTestObservedOutcome::Allowed)
            ->and($run->security_verdict)->toBe(SecurityTestVerdict::Exposed)
            ->and($run->result_context)->toEqual([
                'attempted' => ['name', 'is_approved'],
                'before' => ['name' => 'Documento A', 'is_approved' => false],
                'after' => ['name' => 'Renomeado', 'is_approved' => true],
                'protected_changed' => ['is_approved'],
            ]);
    });

    it('applies only the contract of the operation to the same payload in the protected scenario', function () {
        $this->actingAs($this->operator)
            ->post('/admin/security/mass-assignment/run', ($this->attempt)('protected'))
            ->assertRedirect(route('admin.security.mass-assignment.show'));

        $run = SecurityTestRun::sole();

        expect($run->execution_status)->toBe(SecurityTestExecutionStatus::Completed)
            ->and($run->observed_outcome)->toBe(SecurityTestObservedOutcome::Denied)
            ->and($run->security_verdict)->toBe(SecurityTestVerdict::Protected)
            ->and($run->result_context)->toEqual([
                'attempted' => ['name', 'is_approved'],
                'before' => ['name' => 'Documento A', 'is_approved' => false],
                'after' => ['name' => 'Renomeado', 'is_approved' => false],
                'protected_changed' => [],
            ]);
    });

    it('never classifies a payload with only the permitted property as an exposure', function (string $scenario) {
        $this->actingAs($this->operator)
            ->post('/admin/security/mass-assignment/run', ($this->attempt)($scenario, ['name' => 'Renomeado']));

        $run = SecurityTestRun::sole();

        // The mitigation does not block the legitimate change.
        expect($run->observed_outcome)->toBe(SecurityTestObservedOutcome::Allowed)
            ->and($run->security_verdict)->toBe(SecurityTestVerdict::Protected)
            ->and($run->result_context['attempted'])->toBe(['name'])
            ->and($run->result_context['after'])->toEqual(['name' => 'Renomeado', 'is_approved' => false]);
    })->with(['vulnerable', 'protected']);

    it('shows EXPOSED with the property that was changed', function () {
        $this->actingAs($this->operator)
            ->followingRedirects()
            ->post('/admin/security/mass-assignment/run', ($this->attempt)('vulnerable'))
            ->assertOk()
            ->assertSee('EXPOSED')
            ->assertSee('propriedade protegida alterada')
            ->assertSee('alteração indevida');
    });

    it('shows PROTECTED and no undue change after a protected run', function () {
        $this->actingAs($this->operator)
            ->followingRedirects()
            ->post('/admin/security/mass-assignment/run', ($this->attempt)('protected'))
            ->assertOk()
            ->assertSee('PROTECTED')
            ->assertSee('propriedade protegida ignorada')
            ->assertDontSee('alteração indevida');
    });

    it('presents a payload without the protected property as a legitimate change', function () {
        $this->actingAs($this->operator)
            ->followingRedirects()
            ->post('/admin/security/mass-assignment/run', ($this->attempt)('vulnerable', ['name' => 'Renomeado']))
            ->assertOk()
            ->assertSee('PROTECTED')
            ->assertSee('alteração legítima')
            ->assertDontSee('EXPOSED');
    });
});

describe('Repeatability', function () {
    it('leaves the synthetic document as it was', function (string $scenario) {
        $before = ($this->row)();

        $this->actingAs($this->operator)->post('/admin/security/mass-assignment/run', ($this->attempt)($scenario));

        expect(SecurityTestRun::count())->toBe(1)
            ->and(($this->row)())->toBe($before);
    })->with(['vulnerable', 'protected']);

    it('gives the same answers whatever ran before', function () {
        $this->actingAs($this->operator);

        foreach (['vulnerable', 'protected', 'vulnerable', 'protected'] as $scenario) {
            $this->post('/admin/security/mass-assignment/run', ($this->attempt)($scenario));
        }

        $runs = SecurityTestRun::orderBy('id')->get();

        expect($runs->map->security_verdict->all())->toBe([
            SecurityTestVerdict::Exposed,
            SecurityTestVerdict::Protected,
            SecurityTestVerdict::Exposed,
            SecurityTestVerdict::Protected,
        ]);

        // Every run started from the same state, including the ones that
        // followed an exposure.
        foreach ($runs as $run) {
            expect($run->result_context['before'])->toEqual(['name' => 'Documento A', 'is_approved' => false]);
        }
    });

    it('does not unguard the models to make the vulnerable write work', function () {
        $this->actingAs($this->operator)->post('/admin/security/mass-assignment/run', ($this->attempt)('vulnerable'));

        // Model::unguard() is global: it would open every real model too.
        expect(Model::isUnguarded())->toBeFalse();
    });
});

describe('Operator, actor and session', function () {
    it('records the operator, and the owner of the target as the actor', function () {
        $this->actingAs($this->operator)->post('/admin/security/mass-assignment/run', ($this->attempt)('vulnerable'));

        $run = SecurityTestRun::sole();

        expect($run->test_key)->toBe(SecurityTest::MassAssignment)
            ->and($run->scenario)->toBe(SecurityTestScenario::Vulnerable)
            ->and($run->initiated_by_user_id)->toBe($this->operator->id)
            ->and($run->acting_as_user_id)->toBe($this->alice->id)
            ->and($run->target_resource_id)->toBe($this->document->id);
    });

    it('keeps the session with the operator after the run', function () {
        adminLogin($this->operator);

        $this->post('/admin/security/mass-assignment/run', ($this->attempt)('vulnerable'));
        app('auth')->forgetGuards();

        // A fresh guard reads the user back from the session.
        $this->get('/admin/security/mass-assignment')->assertOk()->assertSee($this->operator->email);
        $this->assertAuthenticatedAs($this->operator, 'web');
    });

    it('audits the run once, under the operator', function () {
        $this->actingAs($this->operator)->post('/admin/security/mass-assignment/run', ($this->attempt)('vulnerable'));

        $run = SecurityTestRun::sole();
        $audit = AuditLog::where('action', 'security_test_executed')->sole();

        expect($audit->user_id)->toBe($this->operator->id)
            ->and($audit->subject_type)->toBe(SecurityTestRun::class)
            ->and($audit->subject_id)->toBe($run->id)
            ->and($audit->meta)->toEqual([
                'test_key' => 'mass_assignment',
                'scenario' => 'vulnerable',
                'acting_as_user_id' => $this->alice->id,
                'verdict' => 'exposed',
            ]);
    });
});

describe('Synthetic data only', function () {
    it('rejects a record of a real table as the target', function (Closure $realId) {
        $this->actingAs($this->operator)
            ->post('/admin/security/mass-assignment/run', [...($this->attempt)('vulnerable'), 'target_resource_id' => $realId()])
            ->assertSessionHasErrors(['target_resource_id' => 'Selecione o documento alvo entre as opções do laboratório.']);

        expect(SecurityTestRun::count())->toBe(0);
    })->with([
        'a user' => [fn () => seededUser('admin')->id],
        'a menu' => [fn () => Menu::where('key', 'users')->value('id')],
    ]);

    it('rejects a property that is not part of the experiment', function () {
        $bob = User::factory()->inactive()->create();

        // owner_user_id is mass assignable in the model too. The payload of
        // the lab cannot carry it, in either scenario.
        $this->actingAs($this->operator)
            ->post('/admin/security/mass-assignment/run', ($this->attempt)('vulnerable', [
                'name' => 'Renomeado',
                'owner_user_id' => $bob->id,
            ]))
            ->assertSessionHasErrors(['payload' => 'O payload só aceita as propriedades deste teste.']);

        expect(SecurityTestRun::count())->toBe(0)
            ->and($this->document->fresh()->owner_user_id)->toBe($this->alice->id);
    });

    it('offers the synthetic documents, with their owner as the actor', function () {
        $this->actingAs($this->operator)
            ->get('/admin/security/mass-assignment')
            ->assertOk()
            ->assertSee('Documento A')
            ->assertSee('actor: Alice Persona')
            ->assertDontSee('Admin User')
            ->assertDontSee('Dev User');
    });
});

describe('Validation', function () {
    it('requires target, scenario and the new name', function () {
        $this->actingAs($this->operator)
            ->post('/admin/security/mass-assignment/run', ['payload' => ['name' => '']])
            ->assertSessionHasErrors([
                'target_resource_id' => 'Selecione o documento alvo.',
                'scenario' => 'Selecione o cenário.',
                'payload.name' => 'Informe o novo nome.',
            ]);
    });

    it('rejects a scenario outside the closed set', function () {
        $this->actingAs($this->operator)
            ->post('/admin/security/mass-assignment/run', ($this->attempt)('bypass'))
            ->assertSessionHasErrors(['scenario' => 'Selecione um cenário válido.']);

        expect(SecurityTestRun::count())->toBe(0);
    });

    it('rejects a payload the experiment does not define', function (array $payload, string $field, string $message) {
        $this->actingAs($this->operator)
            ->post('/admin/security/mass-assignment/run', ($this->attempt)('vulnerable', $payload))
            ->assertSessionHasErrors([$field => $message]);

        expect(SecurityTestRun::count())->toBe(0);
    })->with([
        'name longer than the column' => [
            ['name' => str_repeat('a', 101)],
            'payload.name',
            'O novo nome deve ter no máximo 100 caracteres.',
        ],
        'protected property that is not an attempt to approve' => [
            ['name' => 'Renomeado', 'is_approved' => '0'],
            'payload.is_approved',
            'Marque a tentativa de aprovação ou deixe a propriedade de fora.',
        ],
    ]);
});

describe('Operational errors', function () {
    it('records a technical failure as inconclusive, never as protected', function () {
        // The form validates the target, so the missing one goes straight to
        // the use case.
        $dto = new RunMassAssignmentTestDto((string) Str::ulid(), SecurityTestScenario::Protected, ['name' => 'Renomeado']);

        $run = RunMassAssignmentTest::execute($dto, new AuditContext($this->operator->id));

        expect($run->execution_status)->toBe(SecurityTestExecutionStatus::Error)
            ->and($run->observed_outcome)->toBeNull()
            ->and($run->security_verdict)->toBe(SecurityTestVerdict::Inconclusive)
            ->and($run->target_resource_id)->toBeNull()
            ->and($run->acting_as_user_id)->toBeNull()
            ->and($run->result_context)->toBe(['error' => 'ModelNotFoundException'])
            ->and(AuditLog::where('action', 'security_test_executed')->count())->toBe(1);
    });

    it('stores the run and restores the document when the database refuses the write', function () {
        // Same owner, same name: the unique constraint refuses the rename.
        SecurityLabResource::factory()->create(['owner_user_id' => $this->alice->id, 'name' => 'Documento Z']);
        $before = ($this->row)();

        $this->actingAs($this->operator)
            ->followingRedirects()
            ->post('/admin/security/mass-assignment/run', ($this->attempt)('vulnerable', ['name' => 'Documento Z', 'is_approved' => '1']))
            ->assertOk()
            ->assertSee('INCONCLUSIVE')
            ->assertDontSee('EXPOSED');

        $run = SecurityTestRun::sole();

        expect($run->execution_status)->toBe(SecurityTestExecutionStatus::Error)
            ->and($run->security_verdict)->toBe(SecurityTestVerdict::Inconclusive)
            ->and($run->acting_as_user_id)->toBe($this->alice->id)
            ->and($run->target_resource_id)->toBe($this->document->id)
            ->and($run->result_context)->toBe(['error' => 'UniqueConstraintViolationException'])
            ->and(($this->row)())->toBe($before)
            ->and(AuditLog::where('action', 'security_test_executed')->count())->toBe(1);
    });
});

describe('History', function () {
    beforeEach(function () {
        $this->viewer = userWithPermissions(['security-lab' => ['view']]);

        // Runs go through the real use case, so the history shows what it wrote.
        $this->run = fn (SecurityTestScenario $scenario, string $name = 'Renomeado') => RunMassAssignmentTest::execute(
            new RunMassAssignmentTestDto($this->document->id, $scenario, ['name' => $name, 'is_approved' => true]),
            new AuditContext($this->operator->id),
        );
    });

    it('lists the runs with verdict, scenario, actor, target and operator', function () {
        ($this->run)(SecurityTestScenario::Vulnerable);
        ($this->run)(SecurityTestScenario::Protected);

        $this->actingAs($this->viewer)
            ->get('/admin/security/mass-assignment')
            ->assertOk()
            ->assertSeeInOrder(['Histórico', 'PROTECTED', 'Protegido', 'EXPOSED', 'Vulnerável'])
            ->assertSee('Alice Persona → Documento A')
            ->assertSee('por '.$this->operator->name);
    });

    it('keeps each test with its own history and its own result', function () {
        $massAssignment = ($this->run)(SecurityTestScenario::Vulnerable);
        $idor = RunIdorTest::execute(
            new RunIdorTestDto($this->alice->id, $this->document->id, SecurityTestScenario::Protected),
            new AuditContext($this->operator->id),
        );

        $this->actingAs($this->viewer);

        // The flashed run of one test is not a result for the page of the other.
        $page = $this->withSession(['security_run' => $idor->id])->get('/admin/security/mass-assignment')->assertOk();
        expect($page->viewData('runs')->pluck('id')->all())->toBe([$massAssignment->id])
            ->and($page->viewData('result'))->toBeNull();

        $page = $this->withSession(['security_run' => $massAssignment->id])->get('/admin/security/idor')->assertOk();
        expect($page->viewData('runs')->pluck('id')->all())->toBe([$idor->id])
            ->and($page->viewData('result'))->toBeNull();
    });

    it('paginates instead of loading every run', function () {
        foreach (range(1, 12) as $_) {
            ($this->run)(SecurityTestScenario::Protected);
        }

        $this->actingAs($this->viewer);

        expect($this->get('/admin/security/mass-assignment')->viewData('runs')->count())->toBe(10)
            ->and($this->get('/admin/security/mass-assignment?page=2')->viewData('runs')->count())->toBe(2);
    });

    it('opens a past run in the result panel when it is picked in the history', function () {
        $exposed = ($this->run)(SecurityTestScenario::Vulnerable);
        ($this->run)(SecurityTestScenario::Protected);

        // Reading the history is enough for this: nothing is executed.
        $page = $this->actingAs($this->viewer)
            ->get('/admin/security/mass-assignment?run='.$exposed->id)
            ->assertOk()
            ->assertSee('EXPOSED')
            ->assertSee('alteração indevida')
            ->assertSee('run='.$exposed->id.'#result-title', false);

        expect($page->viewData('result')->is($exposed))->toBeTrue()
            // Only the picked run is marked as the current one.
            ->and(substr_count($page->getContent(), 'aria-current="true"'))->toBe(1)
            ->and(SecurityTestRun::count())->toBe(2);
    });

    it('shows no result for a run it cannot open', function (string $query) {
        ($this->run)(SecurityTestScenario::Vulnerable);

        $page = $this->actingAs($this->viewer)->get('/admin/security/mass-assignment?'.$query)->assertOk();

        expect($page->viewData('result'))->toBeNull();
    })->with([
        'an unknown id' => ['run=01jzzzzzzzzzzzzzzzzzzzzzzz'],
        'something that is not an id' => ['run=not-an-id'],
        'a list' => ['run[]=01jzzzzzzzzzzzzzzzzzzzzzzz'],
    ]);

    it('does not open a run of another test through the URL', function () {
        $massAssignment = ($this->run)(SecurityTestScenario::Vulnerable);
        $idor = RunIdorTest::execute(
            new RunIdorTestDto($this->alice->id, $this->document->id, SecurityTestScenario::Protected),
            new AuditContext($this->operator->id),
        );

        $this->actingAs($this->viewer);

        expect($this->get('/admin/security/mass-assignment?run='.$idor->id)->assertOk()->viewData('result'))->toBeNull()
            ->and($this->get('/admin/security/idor?run='.$massAssignment->id)->assertOk()->viewData('result'))->toBeNull();
    });

    it('keeps the picked run while paging through the history', function () {
        $oldest = ($this->run)(SecurityTestScenario::Vulnerable);

        foreach (range(1, 11) as $_) {
            ($this->run)(SecurityTestScenario::Protected);
        }

        $page = $this->actingAs($this->viewer)
            ->get('/admin/security/mass-assignment?run='.$oldest->id.'&page=2')
            ->assertOk()
            // The link back to the first page carries the selection.
            ->assertSee('run='.$oldest->id.'&amp;page=1', false);

        expect($page->viewData('result')->is($oldest))->toBeTrue()
            ->and($page->viewData('runs')->count())->toBe(2);
    });

    it('shows the run just executed rather than the one picked before', function () {
        $picked = ($this->run)(SecurityTestScenario::Vulnerable);
        $executed = ($this->run)(SecurityTestScenario::Protected);

        $page = $this->actingAs($this->viewer)
            ->withSession(['security_run' => $executed->id])
            ->get('/admin/security/mass-assignment?run='.$picked->id);

        expect($page->viewData('result')->is($executed))->toBeTrue();
    });

    it('keeps a run readable after its target is deleted', function () {
        $run = ($this->run)(SecurityTestScenario::Vulnerable);
        $this->document->delete();

        // The state the run observed lives in the run, not in the document.
        $this->actingAs($this->viewer)
            ->withSession(['security_run' => $run->id])
            ->get('/admin/security/mass-assignment')
            ->assertOk()
            ->assertSee('recurso removido')
            ->assertSee('alteração indevida');
    });

    it('escapes the name sent in the payload', function () {
        $run = ($this->run)(SecurityTestScenario::Vulnerable, '<script>alert("lab")</script>');

        $this->actingAs($this->viewer)
            ->withSession(['security_run' => $run->id])
            ->get('/admin/security/mass-assignment')
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("lab")', false);
    });
});
