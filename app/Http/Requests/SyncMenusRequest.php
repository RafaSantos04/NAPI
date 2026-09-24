<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncMenusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('syncMenus', $this->route('profile')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['array', 'required_array_keys:can_view,can_create,can_update,can_delete'],
            'permissions.*.can_view' => ['boolean'],
            'permissions.*.can_create' => ['boolean'],
            'permissions.*.can_update' => ['boolean'],
            'permissions.*.can_delete' => ['boolean'],
        ];
    }
}
