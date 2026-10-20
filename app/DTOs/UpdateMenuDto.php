<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * Navigation attributes of a menu, only the ones present in the request.
 * The constructor keeps FIELDS and drops anything else, `key` included, so
 * no caller of UpdateMenu can change the permission identifier, whatever it
 * builds the DTO from (ADR-0008).
 */
readonly class UpdateMenuDto
{
    public const FIELDS = ['label', 'route_name', 'icon', 'parent_id', 'order', 'is_active', 'description'];

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
