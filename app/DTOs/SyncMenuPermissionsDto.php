<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

readonly class SyncMenuPermissionsDto
{
    /**
     * @param  array<int|string, array{can_view: bool, can_create: bool, can_update: bool, can_delete: bool}>  $permissions
     */
    public function __construct(
        public array $permissions,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            permissions: $request->validated('permissions', []),
        );
    }
}
