<?php

namespace App\Http\Requests;

use App\Domain\Projects\Enums\EnvironmentClassification;
use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Models\Application;
use App\Models\Environment;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnvironmentUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $environment = $this->route('environment');
        $application = $this->route('application');
        $project = $this->route('project');
        $organization = $this->route('organization');

        return $environment instanceof Environment
            && $application instanceof Application
            && $project instanceof Project
            && $organization instanceof Organization
            && ($this->user()?->can('update', [$environment, $application, $project, $organization]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $environment = $this->route('environment');
        $isEnvironment = $environment instanceof Environment;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('environments', 'slug')
                    ->where('application_id', $isEnvironment ? $environment->application_id : null)
                    ->ignore($environment),
            ],
            'classification' => ['sometimes', 'required', Rule::enum(EnvironmentClassification::class)],
            'authorized_scope' => ['sometimes', 'required', 'string', 'max:5000'],
            'authorization_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'responsible_membership_id' => [
                'sometimes',
                'required',
                'ulid',
                Rule::exists('organization_memberships', 'id')
                    ->where('organization_id', $isEnvironment ? $environment->organization_id : null)
                    ->where('status', OrganizationMembershipStatus::Active->value),
            ],
        ];
    }
}
