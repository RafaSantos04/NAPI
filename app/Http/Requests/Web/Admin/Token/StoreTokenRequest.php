<?php

namespace App\Http\Requests\Web\Admin\Token;

use App\Http\Middleware\EnsureTokenAbility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Token issued in the admin area: short-lived, from a fixed list of
 * lifetimes well below the 7-day global expiration (ADR-0013, item 13).
 * The route already demands admin access and a recent password confirmation.
 */
class StoreTokenRequest extends FormRequest
{
    /** @var list<int> */
    public const LIFETIME_HOURS = [1, 8, 24];

    public const DEFAULT_LIFETIME_HOURS = 8;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['distinct', Rule::in(EnsureTokenAbility::ABILITIES)],
            'lifetime_hours' => ['required', 'integer', Rule::in(self::LIFETIME_HOURS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome.',
            'name.string' => 'Informe o nome.',
            'name.max' => 'O nome deve ter no máximo :max caracteres.',
            'abilities.required' => 'Escolha ao menos uma permissão.',
            'abilities.array' => 'Escolha ao menos uma permissão.',
            'abilities.min' => 'Escolha ao menos uma permissão.',
            'abilities.*.distinct' => 'Permissão repetida.',
            'abilities.*.in' => 'Permissão inválida.',
            'lifetime_hours.required' => 'Escolha a validade.',
            'lifetime_hours.integer' => 'Escolha a validade entre as opções.',
            'lifetime_hours.in' => 'Escolha a validade entre as opções.',
        ];
    }
}
