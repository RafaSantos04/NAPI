<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

final readonly class UpdateApplicationDto
{
    /**
     * @param  array{name?: string, slug?: string, description?: ?string}  $attributes
     */
    public function __construct(public array $attributes) {}

    public static function from(FormRequest $request): self
    {
        return new self(Arr::only($request->validated(), ['name', 'slug', 'description']));
    }
}
