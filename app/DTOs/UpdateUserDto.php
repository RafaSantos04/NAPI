<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Editable profile data of an account. Fields left out of the request stay
 * null and are not touched. Password, status and profiles are not editable
 * here (Phase 4.2).
 */
readonly class UpdateUserDto
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            email: $request->validated('email'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_filter(
            ['name' => $this->name, 'email' => $this->email],
            fn (?string $value) => $value !== null,
        );
    }
}
