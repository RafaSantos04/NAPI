<?php

namespace App\Http\Requests;

use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('profile')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $profile = $this->route('profile');
        $administratorProfile = $profile instanceof Profile && $profile->isAdministrator();

        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('profiles', 'name')->ignore($profile)],
            // The administrator profile is found by its identifier: changing
            // it would take every permission from every administrator.
            'slug' => $administratorProfile
                ? ['prohibited']
                : ['sometimes', 'string', 'max:100', 'alpha_dash', Rule::unique('profiles', 'slug')->ignore($profile)],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Only the administrator marks or unmarks a system profile, and
            // the administrator profile always is one.
            'is_system' => [Rule::prohibitedIf(fn () => $administratorProfile || ! $this->user()?->isAdministrator()), 'boolean'],
        ];
    }
}
