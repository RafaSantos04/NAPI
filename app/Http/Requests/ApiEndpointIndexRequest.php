<?php

namespace App\Http\Requests;

use App\Models\ApiEndpoint;
use App\Models\ApiSpecification;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiEndpointIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $specification = $this->route('apiSpecification');
        $organization = $this->route('organization');

        // The version is bound under its specification (scoped binding), so
        // seeing the specification is seeing its inventory.
        return $specification instanceof ApiSpecification
            && $organization instanceof Organization
            && ($this->user()?->can('view', [$specification, $organization]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['sometimes', 'string', Rule::in(ApiEndpoint::METHODS)],
            'search' => ['sometimes', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // `get` and `GET` are the same filter; the inventory stores uppercase.
        if (is_string($this->query('method'))) {
            $this->merge(['method' => strtoupper($this->query('method'))]);
        }
    }
}
