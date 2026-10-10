<?php

namespace App\DTOs;

use App\Domain\Security\Enums\SecurityTestScenario;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Input of one IDOR test run. Identifiers, not models: resolving them is
 * part of what the test demonstrates. The operator is not here; it travels
 * in the AuditContext, like the actor of every other use case.
 */
readonly class RunIdorTestDto
{
    public function __construct(
        public string $actorUserId,
        public string $targetResourceId,
        public SecurityTestScenario $scenario,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            actorUserId: $request->validated('actor_user_id'),
            targetResourceId: $request->validated('target_resource_id'),
            scenario: SecurityTestScenario::from($request->validated('scenario')),
        );
    }
}
