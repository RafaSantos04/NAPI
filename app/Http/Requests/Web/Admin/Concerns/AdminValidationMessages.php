<?php

namespace App\Http\Requests\Web\Admin\Concerns;

/**
 * Portuguese messages for the admin forms. The rules and authorize() come
 * from the API FormRequests these classes extend, so the two adapters never
 * validate or authorize differently (Phase 4.2).
 */
trait AdminValidationMessages
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Informe :attribute.',
            'string' => 'Informe :attribute.',
            'email' => 'Informe um e-mail válido.',
            'unique' => ':Attribute já está em uso.',
            'max' => ':Attribute deve ter no máximo :max caracteres.',
            'password.min' => 'A senha deve ter ao menos :min caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'profile_ids.array' => 'Seleção de perfis inválida.',
            'profile_ids.*.exists' => 'Perfil inválido.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'o nome',
            'email' => 'o e-mail',
            'password' => 'a senha',
        ];
    }
}
