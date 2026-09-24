<?php

namespace App\Http\Requests;

use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;

class MenuStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Menu::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'route_name' => ['required', 'string', 'max:100', 'unique:menus'],
            'icon' => ['sometimes', 'string', 'max:50'],
            'parent_id' => ['sometimes', 'exists:menus,id'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'string', 'max:500'],
        ];
    }
}
