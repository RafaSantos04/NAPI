<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Models\Organization;
use App\Models\Project;
use App\Rules\Slug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->route('application');
        $project = $this->route('project');
        $organization = $this->route('organization');

        return $application instanceof Application
            && $project instanceof Project
            && $organization instanceof Organization
            && ($this->user()?->can('update', [$application, $project, $organization]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $application = $this->route('application');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                new Slug,
                Rule::unique('applications', 'slug')
                    ->where('project_id', $application instanceof Application ? $application->project_id : null)
                    ->ignore($application),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
