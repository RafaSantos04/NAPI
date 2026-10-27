<?php

namespace App\Http\Requests;

use App\Domain\Projects\Enums\EnvironmentClassification;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnvironmentIndexRequest extends FormRequest
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
            && ($this->user()?->can('viewAny', [Environment::class, $application, $project, $organization]) ?? false);
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
            'classification' => ['sometimes', Rule::enum(EnvironmentClassification::class)],
        ];
    }
}
