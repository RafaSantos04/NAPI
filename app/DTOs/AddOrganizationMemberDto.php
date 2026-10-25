<?php

namespace App\DTOs;

use App\Domain\Tenancy\Enums\OrganizationRole;
use Illuminate\Foundation\Http\FormRequest;

final readonly class AddOrganizationMemberDto
{
    public function __construct(
        public string $userId,
        public OrganizationRole $role,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            userId: $request->validated('user_id'),
            role: OrganizationRole::from($request->validated('role')),
        );
    }
}
