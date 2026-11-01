<?php

namespace App\Http\Requests;

use App\Models\Application;
use App\Models\Organization;
use App\Models\Project;
use App\Rules\Slug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationStoreRequest extends FormRequest
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
            && ($this->user()?->can('create', [Application::class, $project, $organization]) ?? false);
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
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                new Slug,
                Rule::unique('applications', 'slug')->where('project_id', $project instanceof Project ? $project->id : null),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
