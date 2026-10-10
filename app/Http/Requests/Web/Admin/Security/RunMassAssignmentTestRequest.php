<?php

namespace App\Http\Requests\Web\Admin\Security;

use App\Domain\Security\Enums\SecurityTestScenario;
use App\Models\SecurityTestRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class RunMassAssignmentTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SecurityTestRun::class) ?? false;
    }

    /**
     * Validates the shape of the experiment, not whether the client may set
     * each property: `is_approved` is a valid boolean and still not the
     * operation's to change, and deciding that is what the use case shows.
     *
     * The payload takes the two synthetic properties of the test and nothing
     * else, so no other attribute of the model can be reached through it.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_resource_id' => ['required', 'ulid', Rule::exists('security_lab_resources', 'id')],
            'scenario' => ['required', Rule::enum(SecurityTestScenario::class)],
            'payload' => ['required', 'array:name,is_approved'],
            'payload.name' => ['required', 'string', 'max:100'],
            'payload.is_approved' => ['sometimes', 'accepted'],
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
            'payload.required' => 'Informe o payload do teste.',
            'payload.array' => 'O payload só aceita as propriedades deste teste.',
            'payload.name.required' => 'Informe o novo nome.',
            'payload.name.string' => 'Informe o novo nome.',
            'payload.name.max' => 'O novo nome deve ter no máximo :max caracteres.',
            'payload.is_approved.accepted' => 'Marque a tentativa de aprovação ou deixe a propriedade de fora.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'target_resource_id' => 'o documento alvo',
            'scenario' => 'o cenário',
        ];
    }
}
