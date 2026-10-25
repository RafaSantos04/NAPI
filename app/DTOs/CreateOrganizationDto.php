<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class CreateOrganizationDto
{
    public function __construct(
        public string $name,
        public string $slug,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            slug: $request->validated('slug'),
        );
    }
}
