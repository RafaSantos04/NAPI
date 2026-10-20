<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One menu's row of the matrix for each profile on the form: profile id =>
 * the four flags. The other rows of each profile are not here; they stay as
 * they are.
 */
readonly class SyncMenuProfilesDto
{
    /**
     * @param  array<string, array<string, mixed>>  $profiles
     */
    public function __construct(
        public array $profiles,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self($request->validated('profiles', []));
    }
}
