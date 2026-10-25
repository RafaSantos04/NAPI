<?php

namespace App\DTOs;

use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Domain\Tenancy\Enums\OrganizationRole;
use Illuminate\Foundation\Http\FormRequest;

final readonly class UpdateOrganizationMembershipDto
{
    public function __construct(
        public ?OrganizationRole $role,
        public ?OrganizationMembershipStatus $status,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            role: $request->has('role')
                ? OrganizationRole::from($request->validated('role'))
                : null,
            status: $request->has('status')
                ? OrganizationMembershipStatus::from($request->validated('status'))
                : null,
        );
    }
}
