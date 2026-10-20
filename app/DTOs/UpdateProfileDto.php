<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * Editable attributes of a profile, only the ones present in the request.
 * The constructor keeps FIELDS and drops anything else (`is_system`
 * included), whoever builds the DTO. A description sent empty arrives as
 * null and clears the field.
 */
readonly class UpdateProfileDto
{
    public const FIELDS = ['name', 'slug', 'description'];

    /**
     * @var array<string, mixed>
     */
    public array $attributes;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes)
    {
        $this->attributes = Arr::only($attributes, self::FIELDS);
    }

    public static function from(FormRequest $request): self
    {
        return new self($request->validated());
    }
}
