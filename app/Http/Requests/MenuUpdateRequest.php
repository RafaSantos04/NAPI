<?php

namespace App\Http\Requests;

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
            'label' => ['sometimes', 'string', 'max:100'],
            'route_name' => ['sometimes', 'string', 'max:100', Rule::unique('menus', 'route_name')->ignore($this->route('menu'))],
            'icon' => ['sometimes', 'string', 'max:50'],
            'parent_id' => ['sometimes', 'exists:menus,id'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'string', 'max:500'],
        ];
    }
}
