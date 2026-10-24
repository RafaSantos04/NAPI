<?php

namespace App\Http\Requests;

use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Profile::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'unique:profiles'],
            'slug' => ['required', 'string', 'max:100', 'unique:profiles', 'alpha_dash'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Only the administrator says which profiles are system ones,
            // i.e. which only the administrator manages (Phase 4.4 revision).
            'is_system' => [Rule::prohibitedIf(fn () => ! $this->user()?->isAdministrator()), 'boolean'],
        ];
    }
}
