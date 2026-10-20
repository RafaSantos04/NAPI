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
            'integer' => ':Attribute deve ser um número inteiro.',
            'boolean' => 'Valor inválido para :attribute.',
            'exists' => 'Selecione :attribute entre as opções.',
            'alpha_dash' => ':Attribute só pode ter letras, números, hífens e sublinhados.',
            'password.min' => 'A senha deve ter ao menos :min caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'profile_ids.array' => 'Seleção de perfis inválida.',
            'profile_ids.*.exists' => 'Perfil inválido.',
            'key.regex' => 'Use letras minúsculas, números e hífens, como em relatorios-financeiros.',
            'key.prohibited' => 'A chave de um menu não pode ser alterada.',
            'order.min' => 'A ordem não pode ser negativa.',
            'permissions.present' => 'Envie a matriz de permissões.',
            'permissions.array' => 'Matriz de permissões inválida.',
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
            'slug' => 'o identificador',
            'description' => 'a descrição',
            'key' => 'a chave',
            'label' => 'o rótulo',
            'route_name' => 'a rota',
            'icon' => 'o ícone',
            'parent_id' => 'o menu pai',
            'order' => 'a ordem',
            'is_active' => 'o status',
        ];
    }
}
