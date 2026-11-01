<?php

namespace App\Http\Requests;

use App\Models\ApiSpecification;
use App\Models\Organization;
use App\Rules\Slug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiSpecificationUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $specification = $this->route('apiSpecification');
        $organization = $this->route('organization');

        return $specification instanceof ApiSpecification
            && $organization instanceof Organization
            && ($this->user()?->can('update', [$specification, $organization]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $specification = $this->route('apiSpecification');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                new Slug,
                Rule::unique('api_specifications', 'slug')
                    ->where('application_id', $specification instanceof ApiSpecification ? $specification->application_id : null)
                    ->ignore($specification),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
