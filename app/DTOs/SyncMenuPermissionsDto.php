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
            // Booleans whatever the channel sent: true/false from the API,
            // "1"/"0" from the admin form. The audit meta records them as is.
            permissions: array_map(
                fn (array $flags) => array_map(fn (mixed $granted) => filter_var($granted, FILTER_VALIDATE_BOOLEAN), $flags),
                $request->validated('permissions', []),
            ),
        );
    }
}
