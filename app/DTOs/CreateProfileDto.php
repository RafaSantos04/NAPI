<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The only attributes a new profile can be created with. `is_system` is not
 * one of them: system profiles come from the seeder, and the model's
 * #[Fillable] list is not what decides that (Phase 4.3).
 */
readonly class CreateProfileDto
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description = null,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            slug: $request->validated('slug'),
            description: $request->validated('description'),
        );
    }
}
