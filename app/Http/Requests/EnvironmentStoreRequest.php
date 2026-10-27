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

class EnvironmentStoreRequest extends FormRequest
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
            && ($this->user()?->can('create', [Environment::class, $application, $project, $organization]) ?? false);
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
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('environments', 'slug')->where('application_id', $application instanceof Application ? $application->id : null),
            ],
            'classification' => ['required', Rule::enum(EnvironmentClassification::class)],
            'authorized_scope' => ['required', 'string', 'max:5000'],
            'authorization_reference' => ['nullable', 'string', 'max:255'],
            // Optional: defaults to the creator's own membership.
            'responsible_membership_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('organization_memberships', 'id')
                    ->where('organization_id', $application instanceof Application ? $application->organization_id : null)
                    ->where('status', OrganizationMembershipStatus::Active->value),
            ],
        ];
    }
}
