<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

final readonly class UpdateEnvironmentDto
{
    /**
     * @param  array{name?: string, slug?: string, classification?: string, authorized_scope?: string, authorization_reference?: ?string}  $attributes
     */
    public function __construct(
        public array $attributes,
        public ?string $responsibleMembershipId,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            Arr::only($request->validated(), ['name', 'slug', 'classification', 'authorized_scope', 'authorization_reference']),
            $request->validated('responsible_membership_id'),
        );
    }
}
