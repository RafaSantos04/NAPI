<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\Enums\OrganizationMembershipStatus;
use App\Domain\Tenancy\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationMembershipUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $membership = $this->route('membership');
        $organization = $this->route('organization');

        return $membership instanceof OrganizationMembership
            && $organization instanceof Organization
            && ($this->user()?->can('update', [$membership, $organization]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required_without:status', Rule::enum(OrganizationRole::class)],
            'status' => ['required_without:role', Rule::enum(OrganizationMembershipStatus::class)],
        ];
    }
}
