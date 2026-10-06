<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The only attributes a new account can be created with. Status and profiles
 * are not part of it: they change through their own flows, each with its own
 * authorization (Phase 4.2).
 */
readonly class CreateUserDto
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            name: $request->validated('name'),
            email: $request->validated('email'),
            password: $request->validated('password'),
        );
    }
}
