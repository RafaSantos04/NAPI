<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // qualquer um pode tentar login
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // No `exists:users`: whether an e-mail is registered is only ever
        // answered by VerifyLoginCredentials, with the same reply as a wrong
        // password (FIND-018).
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Invalid credentials.',
        ];
    }
}
