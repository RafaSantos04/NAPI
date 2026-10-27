<?php

namespace App\Http\Requests;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');
        $organization = $this->route('organization');

        return $project instanceof Project
            && $organization instanceof Organization
            && ($this->user()?->can('update', [$project, $organization]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('projects', 'slug')
                    ->where('organization_id', $project instanceof Project ? $project->organization_id : null)
                    ->ignore($project),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
