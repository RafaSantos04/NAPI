<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class CreateProjectDto
{
    public function __construct(
        public string $name,
        public string $slug,
        public ?string $description,
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
