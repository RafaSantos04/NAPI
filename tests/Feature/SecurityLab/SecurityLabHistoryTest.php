<?php

use App\Domain\IAM\AuditContext;
use App\Domain\Security\Actions\RunIdorTest;
use App\Domain\Security\Enums\SecurityTestScenario;
use App\DTOs\RunIdorTestDto;
use App\Models\SecurityLabResource;
use App\Models\SecurityTestRun;
use App\Models\User;

// Phase 5.1: the history panel reads security_test_runs, the record of what
// each execution observed.

beforeEach(function () {
    $this->viewer = userWithPermissions(['security-lab' => ['view']]);
    $this->operator = userWithPermissions(['security-lab' => ['view', 'create']]);

    $this->alice = User::factory()->inactive()->create(['name' => 'Alice Persona']);
    $this->bobDocument = SecurityLabResource::factory()->create([
        'owner_user_id' => User::factory()->inactive()->create(['name' => 'Bob Persona'])->id,
        'name' => 'Documento B',
        'content' => 'Synthetic Security Lab Data — conteúdo de Bob',
    ]);

    // Runs go through the real use case, so the history shows what it wrote.
    $this->run = fn (SecurityTestScenario $scenario, ?SecurityLabResource $target = null) => RunIdorTest::execute(
        new RunIdorTestDto($this->alice->id, ($target ?? $this->bobDocument)->id, $scenario),
        new AuditContext($this->operator->id),
    );
});

describe('Security Lab history', function () {
    it('lists the runs with verdict, scenario, actor, target and operator', function () {
        ($this->run)(SecurityTestScenario::Vulnerable);
        ($this->run)(SecurityTestScenario::Protected);

        $this->actingAs($this->viewer)
            ->get('/admin/security/idor')
            ->assertOk()
            ->assertSeeInOrder(['Histórico', 'PROTECTED', 'Protegido', 'EXPOSED', 'Vulnerável'])
            ->assertSee('Alice Persona → Documento B')
            ->assertSee('dono: Bob Persona')
            ->assertSee('por '.$this->operator->name);
    });

    it('shows the newest run first', function () {
        $first = ($this->run)(SecurityTestScenario::Vulnerable);
        $second = ($this->run)(SecurityTestScenario::Protected);

        $runs = $this->actingAs($this->viewer)->get('/admin/security/idor')->viewData('runs');

        expect($runs->pluck('id')->all())->toBe([$second->id, $first->id]);
    });

    it('paginates instead of loading every run', function () {
        foreach (range(1, 12) as $_) {
            ($this->run)(SecurityTestScenario::Protected);
        }

        $this->actingAs($this->viewer);

        expect($this->get('/admin/security/idor')->viewData('runs')->count())->toBe(10)
            ->and($this->get('/admin/security/idor?page=2')->viewData('runs')->count())->toBe(2);
    });

    it('loads the people and the target of each run up front', function () {
        ($this->run)(SecurityTestScenario::Vulnerable);

        $run = $this->actingAs($this->viewer)->get('/admin/security/idor')->viewData('runs')->first();

        expect($run->relationLoaded('operator'))->toBeTrue()
            ->and($run->relationLoaded('actor'))->toBeTrue()
            ->and($run->relationLoaded('target'))->toBeTrue()
            ->and($run->target->relationLoaded('owner'))->toBeTrue();
    });

    it('never repeats the disclosed content in the history', function () {
        ($this->run)(SecurityTestScenario::Vulnerable);

        $this->actingAs($this->viewer)
            ->get('/admin/security/idor')
            ->assertSee('EXPOSED')
            ->assertDontSee('conteúdo de Bob');
    });

    it('keeps a run readable after its target is deleted', function () {
        ($this->run)(SecurityTestScenario::Vulnerable);
        $this->bobDocument->delete();

        expect(SecurityTestRun::sole()->target_resource_id)->toBeNull();

        $this->actingAs($this->viewer)
            ->get('/admin/security/idor')
            ->assertOk()
            ->assertSee('recurso removido');
    });

    it('escapes the names it renders', function () {
        $target = SecurityLabResource::factory()->create(['name' => '<script>alert("lab")</script>']);
        ($this->run)(SecurityTestScenario::Protected, $target);

        $this->actingAs($this->viewer)
            ->get('/admin/security/idor')
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("lab")', false);
    });
});
