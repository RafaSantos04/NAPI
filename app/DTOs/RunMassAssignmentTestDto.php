<?php

namespace App\DTOs;

use App\Domain\Security\Enums\SecurityTestScenario;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Input of one Mass Assignment test run. The payload is the attribute bag
 * the simulated client sends, protected property included: it has to reach
 * the use case for the test to mean anything. There is no actor here; it is
 * the owner of the target.
 */
readonly class RunMassAssignmentTestDto
{
    /**
     * @param  array{name: string, is_approved?: true}  $payload
     */
    public function __construct(
        public string $targetResourceId,
        public SecurityTestScenario $scenario,
        public array $payload,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            targetResourceId: $request->validated('target_resource_id'),
            scenario: SecurityTestScenario::from($request->validated('scenario')),
            payload: [
                'name' => $request->validated('payload.name'),
                // Sent only when the operator asks for the attempt, and then
                // as the boolean the model would cast it to.
                ...($request->validated('payload.is_approved') ? ['is_approved' => true] : []),
            ],
        );
    }
}
