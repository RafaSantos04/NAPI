<?php

namespace App\Http\Requests;

use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;

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
            'description' => ['sometimes', 'string', 'max:500'],
        ];
    }
}
