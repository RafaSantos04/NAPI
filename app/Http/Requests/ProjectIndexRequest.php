<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProjectIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && ($this->user()?->can('viewAny', [Project::class, $organization]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:active,archived,all'],
            'search' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
