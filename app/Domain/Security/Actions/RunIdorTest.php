<?php

namespace App\Domain\Security\Actions;

use App\Domain\IAM\AuditContext;
use App\Domain\Security\Enums\SecurityTest;
use App\Domain\Security\Enums\SecurityTestExecutionStatus;
use App\Domain\Security\Enums\SecurityTestObservedOutcome;
use App\Domain\Security\Enums\SecurityTestScenario;
use App\Domain\Security\Enums\SecurityTestVerdict;
use App\DTOs\RunIdorTestDto;
use App\Events\SecurityTestExecuted;
use App\Models\SecurityLabResource;
use App\Models\SecurityTestRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Throwable;

/**
 * IDOR / BOLA test: an actor asks for a synthetic resource by its identifier.
 *
 * The vulnerable scenario lives only here. There is no route that serves a
 * resource without the ownership check; the lab runs the insecure read
 * in-process, against SecurityLabResource only, and records what it saw
 * (ADR-0010).
 *
 * The actor is a simulated identity: the Policy is evaluated for it with
 * Gate::forUser(), and the session keeps belonging to the operator.
 */
class RunIdorTest
{
    public static function execute(RunIdorTestDto $dto, AuditContext $context): SecurityTestRun
    {
        // Last line of the feature flag: holds for any caller, not only the
        // routes behind the `security.lab` middleware.
        if (! config('security.lab.enabled')) {
            throw new LogicException('The Security Lab is disabled.');
        }

        $result = self::observe($dto);

        // The run and its audit entry are one fact, so they commit together
        // (same rule as the IAM Actions). The read above stays outside.
        return DB::transaction(function () use ($dto, $context, $result) {
            $run = SecurityTestRun::create([
                'test_key' => SecurityTest::Idor,
                'scenario' => $dto->scenario,
                'initiated_by_user_id' => $context->actorId,
                ...$result,
            ]);

            event(new SecurityTestExecuted($run, $context));

            return $run;
        });
    }

    /**
     * Runs the scenario and classifies it. A technical failure is never
     * read as "protected": it is an error with an inconclusive verdict.
     *
     * @return array<string, mixed> Attributes of the run.
     */
    private static function observe(RunIdorTestDto $dto): array
    {
        $actor = null;
        $target = null;

        try {
            $actor = User::findOrFail($dto->actorUserId);
            $target = SecurityLabResource::findOrFail($dto->targetResourceId);

            $disclosed = self::read($actor, $target, $dto->scenario);
        } catch (Throwable $e) {
            report($e);

            return [
                'acting_as_user_id' => $actor?->id,
                'target_resource_id' => $target?->id,
                'observed_outcome' => null,
                'security_verdict' => SecurityTestVerdict::Inconclusive,
                'execution_status' => SecurityTestExecutionStatus::Error,
                'result_context' => ['error' => class_basename($e)],
            ];
        }

        // Reading your own resource is legitimate access, in either
        // scenario. Only access to someone else's resource is an exposure.
        $exposed = $disclosed && $target->owner_user_id !== $actor->id;

        return [
            'acting_as_user_id' => $actor->id,
            'target_resource_id' => $target->id,
            'observed_outcome' => $disclosed ? SecurityTestObservedOutcome::Allowed : SecurityTestObservedOutcome::Denied,
            'security_verdict' => $exposed ? SecurityTestVerdict::Exposed : SecurityTestVerdict::Protected,
            'execution_status' => SecurityTestExecutionStatus::Completed,
            // Kept because the target may be deleted later, and the run
            // must still say whose resource it was.
            'result_context' => ['target_owner_id' => $target->owner_user_id],
        ];
    }

    /**
     * Whether the resource, already found by its identifier, is handed to
     * the actor. Finding it is the same in both scenarios; the difference is
     * the authorization that follows.
     */
    private static function read(User $actor, SecurityLabResource $target, SecurityTestScenario $scenario): bool
    {
        return match ($scenario) {
            // Deliberately vulnerable: found means returned. Nobody asks
            // whether the actor owns it.
            SecurityTestScenario::Vulnerable => true,
            SecurityTestScenario::Protected => Gate::forUser($actor)->allows('view', $target),
        };
    }
}
