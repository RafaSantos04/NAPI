<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The only attributes a new profile can be created with. `is_system` is
 * explicit here, not left to the model's #[Fillable] list: the request only
 * lets the administrator send it (Phase 4.4 revision).
 */
readonly class CreateProfileDto
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description = null,
        public bool $isSystem = false,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            slug: $request->validated('slug'),
            description: $request->validated('description'),
            isSystem: (bool) $request->validated('is_system', false),
        );
    }
}
