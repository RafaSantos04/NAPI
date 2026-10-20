<?php

namespace App\Http\Requests;

use App\Models\Menu;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('menu')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => ['prohibited'],
            'label' => ['sometimes', 'string', 'max:100'],
            'route_name' => ['sometimes', 'string', 'max:100', Rule::unique('menus', 'route_name')->ignore($this->route('menu'))],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            // null moves the menu to the root.
            'parent_id' => ['sometimes', 'nullable', 'exists:menus,id', $this->outsideOwnBranch(...)],
            'order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The new parent may be neither the menu itself nor one of its submenus,
     * at any depth (FIND-019).
     */
    private function outsideOwnBranch(string $attribute, mixed $value, Closure $fail): void
    {
        $menu = $this->route('menu');

        if (is_string($value) && $menu instanceof Menu && $menu->isSelfOrAncestorOf($value)) {
            $fail($this->cycleMessage());
        }
    }

    protected function cycleMessage(): string
    {
        return 'A menu cannot be placed under itself or one of its submenus.';
    }
}
