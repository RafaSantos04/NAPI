<?php

namespace App\Http\Requests;

use App\Models\Menu;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SyncMenusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('syncMenus', [
            $this->route('profile'),
            (array) $this->input('permissions', []),
        ]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // `present` (not `required`) so a profile can have every permission
            // revoked; unknown menu ids are a 422, not a FK violation (FIND-011).
            'permissions' => ['present', 'array', 'max:200', $this->knownMenuIds(...)],
            'permissions.*' => ['array', 'required_array_keys:can_view,can_create,can_update,can_delete'],
            'permissions.*.can_view' => ['boolean'],
            'permissions.*.can_create' => ['boolean'],
            'permissions.*.can_update' => ['boolean'],
            'permissions.*.can_delete' => ['boolean'],
        ];
    }

    private function knownMenuIds(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        $ids = array_map('strval', array_keys($value));

        if (Menu::whereKey($ids)->count() !== count($ids)) {
            $fail('The :attribute field contains unknown menus.');
        }
    }
}
