<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Attributes of a new menu. `key` is set here and nowhere else: it is the
 * permission identifier and never changes afterwards (ADR-0008).
 * `is_system` is not accepted; system menus come from the seeder.
 */
readonly class CreateMenuDto
{
    public function __construct(
        public string $key,
        public string $label,
        public string $routeName,
        public ?string $icon = null,
        public ?string $parentId = null,
        public int $order = 0,
        public bool $isActive = true,
        public ?string $description = null,
    ) {}

    public static function from(FormRequest $request): self
    {
        return new self(
            key: $request->validated('key'),
            label: $request->validated('label'),
            routeName: $request->validated('route_name'),
            icon: $request->validated('icon'),
            parentId: $request->validated('parent_id'),
            order: (int) $request->validated('order', 0),
            isActive: (bool) $request->validated('is_active', true),
            description: $request->validated('description'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'route_name' => $this->routeName,
            'icon' => $this->icon,
            'parent_id' => $this->parentId,
            'order' => $this->order,
            'is_active' => $this->isActive,
            'description' => $this->description,
        ];
    }
}
