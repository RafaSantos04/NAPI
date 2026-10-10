<?php

namespace App\Http\Requests\Web\Admin\Security;

use App\Domain\Security\Enums\SecurityTestScenario;
use App\Models\SecurityTestRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class RunIdorTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SecurityTestRun::class) ?? false;
    }

    /**
     * The actor must be a lab persona, i.e. the owner of some synthetic
     * resource. That keeps arbitrary real accounts from being simulated and
     * from being listed to operators who cannot see users.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actor_user_id' => ['required', 'ulid', Rule::exists('security_lab_resources', 'owner_user_id')],
            'target_resource_id' => ['required', 'ulid', Rule::exists('security_lab_resources', 'id')],
            'scenario' => ['required', Rule::enum(SecurityTestScenario::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Selecione :attribute.',
            'ulid' => 'Selecione :attribute.',
            'exists' => 'Selecione :attribute entre as opções do laboratório.',
            // Rule objects look their message up by class name.
            'scenario.'.Enum::class => 'Selecione um cenário válido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'actor_user_id' => 'o actor',
            'target_resource_id' => 'o recurso alvo',
            'scenario' => 'o cenário',
        ];
    }
}
