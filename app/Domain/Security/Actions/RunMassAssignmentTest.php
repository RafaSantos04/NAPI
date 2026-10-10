<?php

namespace App\Domain\Security\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Security\Enums\SecurityTest;
use App\Domain\Security\Enums\SecurityTestExecutionStatus;
use App\Domain\Security\Enums\SecurityTestObservedOutcome;
use App\Domain\Security\Enums\SecurityTestScenario;
use App\Domain\Security\Enums\SecurityTestVerdict;
use App\DTOs\RunMassAssignmentTestDto;
use App\Events\SecurityTestExecuted;
use App\Models\SecurityLabResource;
use App\Models\SecurityTestRun;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * Mass Assignment test: the owner of a synthetic document renames it, and
 * the payload may carry a property that operation has no business changing.
 *
 * The overly broad assignment lives only here. There is no route that
 * writes a client payload into a model; the lab runs the insecure write
 * in-process, against SecurityLabResource only, and records what it saw
 * (ADR-0010).
 *
 * The actor is the owner of the target: object-level access is settled, so
 * what is left to observe is the property-level one. The write is undone
 * once observed, and the only thing a run leaves behind is its record.
 */
class RunMassAssignmentTest
{
    /**
     * The contract of the operation under test, "the owner renames their
     * document": the one property it may change.
     *
     * @var list<string>
     */
    public const EDITABLE = ['name'];

    /**
     * Known to the client, not part of the operation: approving belongs to a
     * review step. The model accepts it in mass assignment all the same.
     *
     * @var list<string>
     */
    public const PROTECTED = ['is_approved'];

    public static function execute(RunMassAssignmentTestDto $dto, AuditContext $context): SecurityTestRun
    {
        // Last line of the feature flag: holds for any caller, not only the
        // routes behind the `security.lab` middleware.
        if (! config('security.lab.enabled')) {
            throw new LogicException('The Security Lab is disabled.');
        }

        $result = self::observe($dto);

        // The run and its audit entry are one fact, so they commit together
        // (same rule as the IAM Actions). The write above was already undone.
        return DB::transaction(function () use ($dto, $context, $result) {
            $run = SecurityTestRun::create([
                'test_key' => SecurityTest::MassAssignment,
                'scenario' => $dto->scenario,
                'initiated_by_user_id' => $context->actorId,
                ...$result,
            ]);

            event(new SecurityTestExecuted($run, $context));

            return $run;
        });
    }

    /**
     * Runs the scenario and classifies it by the state it left, not by the
     * payload. A technical failure is never read as "protected": it is an
     * error with an inconclusive verdict.
     *
     * @return array<string, mixed> Attributes of the run.
     */
    private static function observe(RunMassAssignmentTestDto $dto): array
    {
        $target = null;
        $actorId = null;

        try {
            $target = SecurityLabResource::findOrFail($dto->targetResourceId);
            // Read before the payload touches the model.
            $actorId = $target->owner_user_id;

            [$before, $after] = self::write($target, $dto);
        } catch (Throwable $e) {
            report($e);

            return [
                'acting_as_user_id' => $actorId,
                'target_resource_id' => $target?->id,
                'observed_outcome' => null,
                'security_verdict' => SecurityTestVerdict::Inconclusive,
                'execution_status' => SecurityTestExecutionStatus::Error,
                'result_context' => ['error' => class_basename($e)],
            ];
        }

        $protectedChanged = array_keys(array_filter(
            Arr::only($after, self::PROTECTED),
            fn (mixed $value, string $property) => $value !== $before[$property],
            ARRAY_FILTER_USE_BOTH,
        ));

        return [
            'acting_as_user_id' => $actorId,
            'target_resource_id' => $target->id,
            // Allowed: the record ended up exactly as the payload asked.
            'observed_outcome' => $after === [...$before, ...$dto->payload]
                ? SecurityTestObservedOutcome::Allowed
                : SecurityTestObservedOutcome::Denied,
            // Changing a permitted property is the operation working. Only a
            // protected property changed by client input is an exposure.
            'security_verdict' => $protectedChanged === [] ? SecurityTestVerdict::Protected : SecurityTestVerdict::Exposed,
            'execution_status' => SecurityTestExecutionStatus::Completed,
            // The resource is restored, so the run is the only place that
            // still knows what the write did.
            'result_context' => [
                'attempted' => array_keys($dto->payload),
                'before' => $before,
                'after' => $after,
                'protected_changed' => $protectedChanged,
            ],
        ];
    }

    /**
     * Writes the payload and returns the observed properties before and
     * after. The same update() in both scenarios; the difference is what is
     * handed to it.
     *
     * The transaction is always rolled back: every run starts from the same
     * synthetic state, whatever the previous ones did.
     *
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private static function write(SecurityLabResource $target, RunMassAssignmentTestDto $dto): array
    {
        $observed = [...self::EDITABLE, ...self::PROTECTED];
        $before = $target->only($observed);

        DB::beginTransaction();

        try {
            $target->update(match ($dto->scenario) {
                // Deliberately vulnerable: whatever the client sent goes to
                // the model, and $fillable lets the protected property in.
                SecurityTestScenario::Vulnerable => $dto->payload,
                SecurityTestScenario::Protected => Arr::only($dto->payload, self::EDITABLE),
            });

            // Read back from the database: what was persisted, not what the
            // model in memory believes.
            $after = $target->refresh()->only($observed);
        } finally {
            DB::rollBack();
        }

        return [$before, $after];
    }
}
