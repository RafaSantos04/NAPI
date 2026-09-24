<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assignProfiles', $this->route('user')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'profile_ids' => ['present', 'array'],
            'profile_ids.*' => ['exists:profiles,id'],
        ];
    }
}
