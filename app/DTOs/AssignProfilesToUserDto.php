<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

readonly class AssignProfilesToUserDto
{
    /**
     * @param  array<int, string>  $profile_ids
     */
    public function __construct(
        public array $profile_ids,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            profile_ids: $request->validated('profile_ids', []),
        );
    }
}
