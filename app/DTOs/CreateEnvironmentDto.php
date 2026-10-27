<?php

namespace App\DTOs;

use App\Domain\Projects\Enums\EnvironmentClassification;
use Illuminate\Foundation\Http\FormRequest;

final readonly class CreateEnvironmentDto
{
    public function __construct(
        public string $name,
        public string $slug,
        public EnvironmentClassification $classification,
        public string $authorizedScope,
        public ?string $authorizationReference,
        // Null means the creator's own membership.
        public ?string $responsibleMembershipId,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            slug: $request->validated('slug'),
            classification: EnvironmentClassification::from($request->validated('classification')),
            authorizedScope: $request->validated('authorized_scope'),
            authorizationReference: $request->validated('authorization_reference'),
            responsibleMembershipId: $request->validated('responsible_membership_id'),
        );
    }
}
